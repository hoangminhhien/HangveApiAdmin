<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Http\Resources\Customer\CustomerConfirmCloseResource;
use App\Models\Customer;
use App\Models\CustomerConfirmClose;
use Illuminate\Support\Facades\Auth;

class CustomerConfirmCloseService extends AbstractBaseService
{
    protected array $relations = ['customer', 'userCreate', 'userConfirm', 'noteReason'];
    protected string $_resource = CustomerConfirmCloseResource::class;
    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function setParams($request, $query)
    {
        $query->with(['customer' => function ($customer) {
            $customer->select('id', 'name', 'phone_number', 'code');
        }]);

        if (!empty($request['customer_code'])) {
            $query->whereHas('customer', function ($customer) use ($request) {
                $customer->where('code', $request['customer_code']);
            });
        }

        if (!empty($request['customer_phone'])) {
            $query->whereHas('customer', function ($customer) use ($request) {
                $customer->where('phone_number', $request['customer_phone']);
            });
        }

        if (!empty($request['status'])) {
            $query->where('status', $request['status']);
        }

        return $query->orderBy('id', 'DESC');
    }

    public function update(int $id, array $params): bool
    {
        $item = $this->detail($id);
        if (!empty($params['status'])) {
            if ($params['status'] == CustomerConfirmClose::STATUS_CONFIRMED) {
                Customer::query()
                    ->where('id', $item->customer_id ?? 0)
                    ->update(['status' => 0]);
                $this->logCloseAccount($item);
            }
        }
        $params['user_confirm_id'] = Auth::user()->id;
        $item->update($params);
        return true;
    }

    public function updateCloseMany(array $params): bool
    {
        $ids = $params['ids'] ?? [];
        $status = $params['status'] ?? '';
        $items = $this->newQuery()->whereIn('id', $ids)->get();
        $user_confirm_id = Auth::user()->id;
        foreach ($items as $item) {
            $item->status = $status;
            $item->user_confirm_id = $user_confirm_id;
            $item->save();
            if ($status == CustomerConfirmClose::STATUS_CONFIRMED) {
                Customer::query()
                    ->where('id', $item->customer_id ?? 0)
                    ->update(['status' => 0]);
                $this->logCloseAccount($item);
            }
        }
        return true;
    }

    protected function logCloseAccount(CustomerConfirmClose $item): void
    {
        $user = Auth::user();
        $reasonName = $item->noteReason->name ?? 'Không xác định';
        $content = sprintf('%s đóng tài khoản với lý do: %s', $user->name ?? 'Người dùng không xác định', $reasonName);
        $dataActivities = [[
            'subject_type'    => Customer::class,
            'subject_id'      => $item->customer_id,
            'causer_type'     => get_class($user),
            'causer_id'       => $user->id,
            'log_name'        => 'customer_log',
            'content'         => $content,
            'object_id'       => $item->customer_id,
            'properties'      => null,
            'organization_id' => $user->organization_id ?? getOrganization(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]];
        (new ActivityService())->setCustomerLogs($dataActivities);
    }
}
