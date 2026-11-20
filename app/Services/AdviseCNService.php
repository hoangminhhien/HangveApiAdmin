<?php


namespace App\Services;


use App\Constants\ConversationConstant;
use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\TransactionConstant;
use App\Constants\AdviseCNConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Helpers\RandomHelper;
use App\Http\Resources\AdviseCN\AdviseCNResource;
use App\Http\Resources\ListResource;
use App\Models\AdviseCN;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\User;
use App\Models\Role;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

use App\Constants\PackageConstant;

use GuzzleHttp\Client as Guzzle;

class AdviseCNService extends BaseService
{
    protected string $_resource = AdviseCNResource::class;
    protected array $_relations = [
        'user'
    ];

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $userOnline = \Auth::user();
        $data = $this->_model->newQuery()
                            ->orderBy('id', 'desc')
                            ->with($this->_relations);

        // Nếu không có quyền thì chỉ fetch theo chính tài khoản cúa nó thôi
        $viewAll = $userOnline->checkUserHasPer('customer.advise-cn-view-all');
        if (!$viewAll) {
            $data = $data->where('user_id', $userOnline->id);
        }

        $data = $data->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function getDetail(array $params = []){
        $query = $this->_model->newQuery();
        if (!empty($params['type']) && in_array($params['type'], [
                ConversationConstant::TYPE_ORDER,
                ConversationConstant::TYPE_PACKAGE,
                ConversationConstant::TYPE_COMPLAIN,
            ])
        ) {
            $query->where('type', $params['type']);
        } else {
            return false;
        }

        if (!empty($params['type_id'])) {
            $query->where('type_id', $params['type_id']);
        } else {
            return false;
        }

        if (!empty($params['customer_id'])) {
            $query->where('customer_id', $params['customer_id']);
        }
        return $query->first();
    }


    public function handlerStoreV2($params)
    {
        // Nếu là ủy thác nhập khẩu
        if ($params['type'] == AdviseCNConstant::TYPE_ENTRUSTMENT) {
            $params['total_amount']  = $params['item_unit_declare'] * $params['item_quantity'];
            if (isset($params['info_fee'])) {
                if (count($params['info_fee']) > 0) {
                    $params['t_tax_xnk']     = $params['info_fee']['t_tax_xnk'];
                    $params['t_tax_vat']     = $params['info_fee']['t_tax_vat'];
                    $params['t_tax_ttdb']    = $params['info_fee']['t_tax_ttdb'];
                    $params['t_tax_protect'] = $params['info_fee']['t_tax_protect'];
                    $params['t_tax_nature']  = $params['info_fee']['t_tax_nature'];

                    $params['taxTTDB']       = $params['info_fee']['taxTTDB'];
                    $params['taxNature']     = $params['info_fee']['taxNature'];
                    $params['taxProtect']    = $params['info_fee']['taxProtect'];
                    $params['taxVAT']        = $params['info_fee']['taxVAT'];
                    $params['taxXNK']        = $params['info_fee']['taxXNK'];

                    $params['feeVCQT']       = $params['info_fee']['feeVCQT'];
                    $params['feeService']    = $params['info_fee']['feeService'];
                    $params['feeOpen']       = $params['info_fee']['feeOpen'];
                    $params['total_amount']  = $params['info_fee']['fullMoney'];
                }
            }
        }
        // Nếu là tự đứng tên tờ khai
        if ($params['type'] == AdviseCNConstant::TYPE_SELF) {
            if (isset($params['info_fee'])) {
                if (count($params['info_fee']) > 0) {
                    $params['feeVCQT']       = $params['info_fee']['feeVCQT'];
                    $params['feeService']    = $params['info_fee']['feeService'];
                    $params['feeOpen']       = $params['info_fee']['feeOpen'];

                    $params['total_amount']  = $params['feeVCQT'] + $params['feeService'] + $params['feeOpen'];
                }
            }
        }

        // Tạo
        $adviseCN = AdviseCN::query()->create($params);

        if (is_null($adviseCN->hs_code)) {
            $update_data = [
                'advise_cn_id' => (int) $adviseCN->id
            ];
            $this->handlerUpdateHsCodeAndFee($update_data);
        }

        // Push noti cho người dc gán
        $permission = 'customer.advise-cn-assign-agent';

        $roleNames = Role::query()
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('name', $permission);
            })
            ->pluck('name')
            ->toArray();

        $userDBs = User::query()->role($roleNames)->select('id', 'name')->get();
        if (count($userDBs)) {
            $notificationService = \App::make('App\Services\NotificationService');
            foreach ($userDBs as $key => $userDB) {
                $data_advise_cn = [
                    'advise_cn_id'    => (int)$adviseCN->id,
                ];
                $data = [
                    'group'           => Notification::CUSTOMER,
                    'name'            => 1004,
                    'data'            => json_encode($data_advise_cn),
                    'time_create'     => time(),
                    'status'          => 1
                ];
                $notificationService->storeNoti($userDB->id, $data);
            }
        }
    }

    public function handlerChangeAgent($params)
    {
        $adviseCN = AdviseCN::query()->where('id', $params['advise_cn_id'])->first();
        $adviseCN->user_id = $params['user_id'];
        $adviseCN->save();

        // Đẩy thông báo
        $this->notiToUser($adviseCN->user_id, 1);
    }

    public function handlerChangeAgentArr($params)
    {
        $adviseCNs = AdviseCN::query()->whereIn('id', $params['arr_advise_cn_id'])->get();
        foreach ($adviseCNs as $key => $adviseCN) {
            $adviseCN->user_id = $params['user_id'];
            $adviseCN->save();
        }

        // Đẩy thông báo
        $this->notiToUser($params['user_id'], count($params['arr_advise_cn_id']));
    }

    public function notiToUser($user_id, $count = 1)
    {
        $notificationService = \App::make('App\Services\NotificationService');
        $data_advise_cn = [
            'count_advise_cn' => $count,
        ];
        $data = [
            'group'           => Notification::CUSTOMER,
            'name'            => 1005,
            'data'            => json_encode($data_advise_cn),
            'time_create'     => time(),
            'status'          => 1
        ];
        $notificationService->storeNoti($user_id, $data);

    }

    public function handlerChangeStatus($params)
    {
        $adviseCN = AdviseCN::query()->where('id', $params['advise_cn_id'])->first();
        $adviseCN->status = $params['status'];
        $adviseCN->save();
    }

    public function handlerUpdateHsCodeAndFee($params)
    {
        $adviseCN = AdviseCN::query()->where('id', $params['advise_cn_id'])->first();
        if ($adviseCN->hs_code) {
            return;
        }

        // Lấy HS_code
        $guzzle = new Guzzle;
        $url = env('BASE_URL_QUOTE') . 'open/hv/check-item-m290526m';
        $data_hs_code = [
            'name'     => $adviseCN->item_name,
            'color'    => $adviseCN->item_color,
            'size'     => $adviseCN->item_size,
            'material' => $adviseCN->item_material,
            'info'     => $adviseCN->item_info,
            'branch'   => $adviseCN->item_branch,
        ];
        $options = [
            'json'        => $data_hs_code,
            'verify'      => false,
            'http_errors' => true,
        ];
        $result_1 = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result_1 = json_decode($result_1, true);

        if ($result_1 && $result_1['code'] == 200) {
            $hs_code = $result_1['data'];
            if ($hs_code) {
                $this->pushTurn2($adviseCN, $hs_code);
            }
        }
    }

    public function pushTurn2($adviseCN, $hs_code)
    {
        $guzzle = new Guzzle;
        $url = env('BASE_URL_QUOTE') . 'open/hv/get-price-m290526m';
        $data_hs_code = [
            'hs_code'      => $hs_code,
            'quantity'     => (int) $adviseCN->item_quantity,
            'kg'           => $adviseCN->item_kg,
            'm3'           => $adviseCN->item_m3,
            'size'         => $adviseCN->item_size,
            'unit_declare' => $adviseCN->item_unit_declare,
            'province'     => $adviseCN->warehouse->name ?? '',
        ];
        $options = [
            'json'        => $data_hs_code,
            'verify'      => false,
            'http_errors' => true,
        ];
        $result_2 = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result_2 = json_decode($result_2, true);

        if ($result_2 && $result_2['code'] == 200) {
            $priceData = $result_2['data'];
            $data_update_hs_code = [
                'hs_code'       => $hs_code,
                't_tax_xnk'     => $priceData['t_tax_xnk'],
                't_tax_vat'     => $priceData['t_tax_vat'],
                't_tax_ttdb'    => $priceData['t_tax_ttdb'],
                't_tax_protect' => $priceData['t_tax_protect'],
                't_tax_nature'  => $priceData['t_tax_nature'],
                'taxTTDB'       => $priceData['taxTTDB'],
                'taxNature'     => $priceData['taxNature'],
                'taxProtect'    => $priceData['taxProtect'],
                'taxVAT'        => $priceData['taxVAT'],
                'taxXNK'        => $priceData['taxXNK'],
                'feeVCQT'       => $priceData['feeVCQT'],
                'feeService'    => $priceData['feeService'],
                'feeOpen'       => $priceData['feeOpen'],
                'total_amount'  => $priceData['fullMoney'],
            ];
            if ($adviseCN->type == AdviseCNConstant::TYPE_SELF) {
                $data_update_hs_code = [
                    'feeVCQT'       => $priceData['feeVCQT'],
                    'feeService'    => $priceData['feeService'],
                    'feeOpen'       => $priceData['feeOpen'],
                    'total_amount'  => $priceData['total_amount_seft']
                ];
            }
            $adviseCN->update($data_update_hs_code);
        }
    }
}
