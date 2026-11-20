<?php


namespace App\Services;

use App\Constants\OrderCostRealConstant;
use App\Models\Order;
use App\Models\OrderCostReal;
use App\Models\OrderCostRealLog;
use App\Scopes\FilterScope;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use App\Models\Fund;
use App\Http\Resources\OrderCostRealResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Image;
use App\Helpers\MediaHelper;

class OrderCostRealService extends BaseService
{
    protected string $_resource = OrderCostRealResource::class;
    protected array $_relations = ['order', 'staff'];

    /**
     * @param int $perPage
     * @return JsonResponse
     */
    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        $query = $this->_model->newQuery();
        $query->with('order', 'staff');

        if (!empty(\request()->order_code)) {
            $query->orderCode();
        }
        if (!empty(\request()->order_code_po)) {
            $query->orderCodePo();
        }
        if (!empty(\request()->status)) {
            $query->status();
        }

        // Per: Xem danh sách cập nhật tổng tiền thực tế theo tài khoản
        $userOnline = \Auth::user();
        $showOne = $userOnline->can('actual_amount.index_one');
        if ($showOne) {
            $query = $query->where('staff_order_id', $userOnline->id);
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function store(array $data)
    {
        $this->throwModel();
        // store data
        $data['status'] = OrderCostRealConstant::STATUS_WAITING;
        if(data_get($data, 'files')) {
            $data['files'] = json_encode($data['files'], JSON_UNESCAPED_UNICODE);
        }
        $item = $this->_model->create($data);
        // store log
        //$this->updateLog($item);
    }

    public function update($id, array $data)
    {
        $this->throwModel();
        $item = $this->_model->findOrFail($id);
        // update data
        unset($data['file']);
        if(data_get($data, 'files')) {
            $data['files'] = json_encode($data['files'], JSON_UNESCAPED_UNICODE);
        }
        $item->update($data);
        // store log
        $this->updateLog($item);
    }

    public function uploadFile(OrderCostReal $item)
    {
        try {
            $file = request()->file('file');

            if (empty($file)) {
                return $item;
            }

            $date     = date('Ymd');
            $path     = $this->createDirectory($date);
            $fileName = getUuid() . '.' . $file->getClientOriginalExtension();

            if(in_array($file->getClientOriginalExtension(), ["jpg", "jpeg", "jfif", "pjpeg", "pjp", "png"])){
                $img = Image::make($file->path());
                $img->resize(600, 600, function ($constraint) {
                    $constraint->aspectRatio();
                })->encode('jpg')->save($path.'/'.$fileName);
            }else{
                $file->move($path, $fileName);
            }

            if (env('FILESYSTEM_DRIVER') == 'oss') {
                $link  = $path . '/' . $fileName;
                $pathOSS = 'hangve/' . $date;
                Storage::disk('oss')->putFileAs($pathOSS, new File($link), $fileName);

                $pathName = '/' . $pathOSS . '/' . $fileName;
                $full_path =  env('OSS_URL') . $pathName;
                $data = ['name' => $fileName, 'path' => $pathName, "full_path" => $full_path];

                unlink($link);
            } else {
                $pathName = "/uploads/$date/$fileName";
                $data = ['name' => $fileName, 'path' => $pathName, 'full_path' => MediaHelper::getURL($pathName)];
            }

            $item->file = $data['full_path'];
            $item->save();
            //return resSuccessWithinData($data);
            return $data;
        } catch (\Exception $e) {
            \Log::error('Lỗi khi upload file: ' . $e);
            return resError();
        }
    }

    private function createDirectory(string $date): string
    {
        $path = public_path("storage/uploads/$date");
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    public function updateLog(OrderCostReal $item)
    {
        $user    = Auth::user();
        $dataLog = [
            'user_id'            => $user->id,
            'user_name'          => $user->name,
            'order_cost_real_id' => $item->id,
            'order_code'         => $item->order_code,
            'cost_old'           => $item->cost_old,
            'cost_new'           => $item->cost_new,
            'note'               => $item->note,
            'status'             => $item->status
        ];

        OrderCostRealLog::create($dataLog);
    }

    /**
     * @param array $ids
     * @return mixed
     * @throws \Exception
     */
    public function approve(array $ids)
    {
        $this->throwModel();

        $items = $this->_model->with('order.orderSuppliers')->find($ids);

        foreach ($items as $item) {
            if ($item->status == OrderCostRealConstant::STATUS_APPROVED) {
                continue;
            }
            // update status
            $item->status = OrderCostRealConstant::STATUS_APPROVED;
            $item->save();
            // update log
            $this->updateLog($item);
            // update cost real for order
            if (!empty($item->order)) {
                $order                  = $item->order;
                $shippingCostReal       = $order->china_shipping_cost_real;
                $exchangeRate           = $order->exchange_rate;
                $order->order_cost_real = round(($item->cost_new - $shippingCostReal) * $exchangeRate, 2);
                $order->save();

                // update cost real for supplier
                if (!empty($item->order->orderSuppliers)) {
                    $orderSuppliers = $item->order->orderSuppliers;
                    foreach ($orderSuppliers as $orderSupplier) {
                        $shippingCostRealSupplier       = $orderSupplier->china_shipping_cost_real;
                        $orderSupplier->order_cost_real = round((
                            $item->cost_new - $shippingCostRealSupplier),
                            2
                        );
                        $orderSupplier->save();
                    }
                }
            }
        }
    }

    public function getOldCost(string $orderCode)
    {
        $order = Order::query()->select(['id', 'order_cost_real', 'exchange_rate'])
            ->where('code', $orderCode)
            ->firstOrFail();

        return round(($order->order_cost_real / $order->exchange_rate), 2);
    }

    public function log()
    {
        $this->throwModel();

        $query = OrderCostRealLog::query();

        if (!empty(request()->user_name)) {
            $query->userName();
        }
        if (!empty(request()->order_code)) {
            $query->orderCode();
        }
        if (!empty(request()->date)) {
            $query->date();
        }
        $query->orderBy('id', 'desc');

        $perPage = request()->per_page ?? 15;

        return $query->paginate($perPage);
    }
}
