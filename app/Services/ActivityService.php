<?php


namespace App\Services;


use App\Constants\ActivityConstant;
use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\Activity\ActivityPackageResource;
use App\Http\Resources\Activity\ActivityResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\Order\OrderLogResource;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\ReportStatusResource;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\Order;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\User;
use App\Models\OrderPackage;
use App\Models\Delivery;
use App\Models\Complain;
use App\Models\Warehouse;
use App\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\ConfigInternationalShip;
use App\Constants\PackageConstant;

class ActivityService extends BaseService
{
    protected string $_resource = ActivityResource::class;

    /**
     * @param  int  $perPage
     * @return JsonResponse
     * @throws \Exception
     */
    public function pagination(int $perPage): JsonResponse
    {
        return parent::pagination($perPage);
    }

    /**
     * @param  Model  $subject
     * @param  string  $content
     * @param  string  $orderId
     */
    public function setOrderLog(Model $subject, string $content, string $orderId, $onlyViewAdmin = 0, $activity_type = 0)
    {
        $data = new ReportStatusResource(
            Order::query()->find($orderId)->status, OrderConstant::STATUSES, OrderConstant::STATUSES_COLOR
        );
        $this->setLog($subject, $content, ActivityConstant::ORDER_LOG, $orderId, json_encode($data), $onlyViewAdmin, $activity_type);
    }

    /**
     * @param  Model  $subject
     * @param  string  $content
     * @param  string  $logName
     * @param  string|null  $objectId
     * @param  string|null  $properties
     */
    public function setLog(Model $subject, string $content, string $logName, ?string $objectId = null, ?string $properties = null, $onlyViewAdmin = 0, $activity_type = 0)
    {
        /** @var Customer $user */
        $user = Auth::user() ?? User::query()->withoutGlobalScope(
                OrganizationScope::class
            )->first();

        // Nếu có tham số này thì nghĩa là do bên kho bắn sang
        $dongdong_id = isset($_POST['dongdong_id']) ? $_POST['dongdong_id']: null;
        if (!is_null($dongdong_id)) {
            $userModel = \App::make('App\Models\User');
            $userDD = $userModel->where('dongdong_id', $dongdong_id)
                                ->where('dongdong_id', '>', 0)
                                ->first();
            if (!is_null($userDD)) {
                $user = $userDD;
            }
        }

        $activityDB = Activity::query()->create(
            [
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'causer_type' => get_class($user),
                'causer_id' => $user->id,
                'log_name' => $logName,
                'content' => $content,
                'object_id' => $objectId,
                'organization_id' => $user->organization_id ?? getOrganization(),
                'properties' => $properties,
                'is_view_admin' => $onlyViewAdmin,
                'activity_type' => $activity_type
            ]
        );

        if ($content == PackageConstant::STATUS_PENDING) {
            $activityDB->created_at = date('Y-m-d H:i:s', (time() - 1));
            $activityDB->save();
        }

        $status_in_order_service = isset($_POST['status_in_order_service']) ? $_POST['status_in_order_service']: null;
        if (!is_null($status_in_order_service)) {
            $activityDB->created_at = date('Y-m-d H:i:s', (time() + 1));
            $activityDB->save();
        }
    }

    /**\
     * @param  string  $orderId
     * @return JsonResponse
     */
    public function getOrderLog(string $orderId, Request $request): JsonResponse
    {
        $params = $request->all();
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 10;
        $activities = Activity::query()->select(['created_at', 'properties', 'content', 'subject_type', 'subject_id', 'causer_type', 'causer_id'])
            ->where(['log_name' => ActivityConstant::ORDER_LOG, 'object_id' => $orderId])
            ->whereIn('subject_type', [Order::class, Delivery::class, Complain::class, User::class])
            ->with(['subject', 'causer']);
        if (!isAcceptViewLogFastCreateOrder()) {
            $activities->where('activity_type', '!=', 1);
        }
        $activities = $activities->paginate($perPage);
        return resSuccessWithinData(
            new PaginateJsonResource($activities, OrderLogResource::class)
        );
    }

    /**
     * @param  string  $packageId
     * @return JsonResponse
     */
    public function getPackageLog(string $packageId): JsonResponse
    {
        $activities = Activity::query()->where(
            ['log_name' => ActivityConstant::PACKAGE_LOG, 'subject_id' => $packageId]
        )->get();
        return resSuccessWithinData(new ListResource($activities, ActivityPackageResource::class));
    }

    public function getPackageDetailLog(string $packageId): JsonResponse
    {
        $activities = Activity::where('subject_id', $packageId)
            ->where('subject_type', OrderPackage::class)
            ->whereIn('log_name', [ActivityConstant::PACKAGE_NOTE, ActivityConstant::PACKAGE_STATUS, ActivityConstant::PACKAGE_WEIGHT, ActivityConstant::PACKAGE_INFO])
            ->with(['causer'])->get();
        $data['note'] = new ListResource($activities->filter(function ($item) {
            return $item->log_name == ActivityConstant::PACKAGE_NOTE ;
        }), ActivityPackageResource::class);
        $data['status'] = new ListResource($activities->filter(function ($item) {
            return $item->log_name == ActivityConstant::PACKAGE_STATUS ;
        }), ActivityPackageResource::class);
        $data['weight'] = new ListResource($activities->filter(function ($item) {
            return $item->log_name == ActivityConstant::PACKAGE_WEIGHT ;
        }), ActivityPackageResource::class);
        $data['info'] = new ListResource($activities->filter(function ($item) {
            return $item->log_name == ActivityConstant::PACKAGE_INFO ;
        }), ActivityPackageResource::class);
        return resSuccessWithinData($data);
    }

    /**
     * @param  string  $consignmentId
     * @return JsonResponse
     */
    public function getConsignmentLog(string $consignmentId): JsonResponse
    {
        $activities = Activity::query()->where(
            ['log_name' => ActivityConstant::CONSIGNMENT_LOG, 'object_id' => $consignmentId]
        )->get();
        return resSuccessWithinData(
            $activities->transform(
                function ($item) {
                    return new $this->_resource($item);
                }
            )
        );
    }

    public function getDeliveryLog(string $deliveryId): JsonResponse
    {
        $data['statuses'] = new ListResource(
            Activity::query()->where(
                ['log_name' => ActivityConstant::DELIVERY_STATUS, 'subject_id' => $deliveryId]
            )->get(), ActivityPackageResource::class
        );

        $data['packages'] = new ListResource(
            Activity::query()->where(
                ['log_name' => ActivityConstant::DELIVERY_PACKAGE, 'subject_id' => $deliveryId]
            )->get(), ActivityPackageResource::class
        );

        return resSuccessWithinData($data);
    }

    /**
     * @param  Model  $subject
     * @param  string  $content
     * @param  string  $orderId
     */
    public function setConsignmentLog(Model $subject, string $content, string $orderId)
    {
        $this->setLog($subject, $content, ActivityConstant::CONSIGNMENT_LOG, $orderId);
    }

    /**
     * @param  Model  $subject
     * @param  string  $content
     * @param  string  $orderId
     */
    public function setConsignment(Model $subject, string $content, string $orderId)
    {
        $this->setLog($subject, $content, ActivityConstant::CONSIGNMENT_LOG, $orderId);
    }

    /**
     * @param  Order  $order
     */
    public function activitiesOrder(Order $order)
    {
        $this->prepareActivityOrder(
            $order,
            'customer_delivery_id',
            'activity.order_customer_delivery',
            optional($order->customerDelivery)->custom_name
        );
        $this->prepareActivityOrder(
            $order,
            'warehouse_id',
            'activity.order_warehouse',
            optional($order->warehouse)->custom_name
        );
    }

    /**
     * @param  Order  $order
     * @param  string  $column
     * @param  string  $content
     * @param  string  $value
     */
    private function prepareActivityOrder(Order $order, string $column, string $content, string $value)
    {
        $activity = new ActivityService();
        if ($order->getOriginal($column) != $order->{$column}) {
            $activity->setOrderLog(
                $order,
                __($content, ['name' => getCurrentUser()->name, 'value' => $value]),
                $order->id
            );
        }
    }

    public function productActivity(
        array $modifies,
        string $orderId,
        string $objects,
        string $content,
        $model = null,
        $userName = null,
        $isViewAdmin = 0,
        $supplier_id = null
    )
    {
        if(!$userName) {
            $userName = getCurrentUser()->name;
        }
        $array = [
            'link' => 'link sản phẩm',
            'unit_price_cny' => 'Đơn giá (CNY)',
            'quantity' => 'Số lượng',
            'china_shipping_cost' => 'Ship nội địa TQ',
            'delivery_type' => 'Loại vận chuyển',
            'is_woodworking' => 'Đóng gỗ',
            'is_shock_proof' => 'Chống sốc',
            'is_inspection' => 'Kiểm đếm',
            'is_advanced_inspection' => 'Kiểm hàng nâng cao',
            'china_shipping' => 'Đơn có phí ship',
            'note_private' => 'Ghi chú nội bộ',
            'official_is_partner' => 'Chính ngạch - Đi bên đối tác'
        ];
        foreach ($modifies as $field => $modify) {
            if (!isset($array[$field])) {
                continue;
            }
            $value = $modify;
            if (Str::startsWith($field, 'is_')) {
                $value = $modify == 1 ? 'Có' : 'Không';
            }
            if (Str::startsWith($field, 'delivery_type')) {
                $value = OrderConstant::DELIVERIES_TEXT[$modify];
            }

            if ($field == 'china_shipping_cost' || $field == 'unit_price_cny') {
                $value .= '¥';
            }
            if(in_array($field, ['unit_price_cny', 'quantity'])){
                $contentLog = __(
                    'activity.order_products_update_with_begin',
                    [
                        'object' => $objects,
                        'name' => $userName,
                        'field' => $array[$field],
                        'value' => $value,
                        'begin' => $field == 'unit_price_cny' ? $model[$field].'¥' : $model[$field]
                    ]
                );
                (new ActivityService())->setOrderLog(
                    Order::query()->find($orderId),
                    $contentLog,
                    $orderId
                );
            }else{
                $contentLog = __(
                    'activity.'.$content,
                    [
                        'object' => $objects,
                        'name' => getCurrentUser()->name,
                        'field' => $array[$field],
                        'value' => $value
                    ]
                );
                if(isset($modifies['china_shipping'])) {
                    $supplier = Supplier::query()->where('id', $supplier_id)->first();
                    $contentLog = __(
                        'activity.'.$content,
                        [
                            'name' => getCurrentUser()->name,
                            'supplier' => $supplier->name
                        ]
                    );
                }

                $arr_is = [
                    'is_woodworking',
                    'is_shock_proof',
                    'is_inspection',
                    'is_advanced_inspection'
                ];

                if (in_array($field, $arr_is)) {
                    $valueNew = $modify == 1 ? 'thêm' : 'hủy bỏ';
                    $ncc = '';
                    if ($field == 'is_advanced_inspection') {
                        $supplier = Supplier::query()->where('id', $supplier_id)->first();
                        if ($modify == 1) {
                            $valueNew = 'hủy bỏ';
                            if ($supplier) {
                                $ncc = ' cho ncc [ ' . $supplier->name . ' ]';
                            }
                        } else {
                            if ($supplier) {
                                $valueNew = 'chọn';
                                $ncc = ' cho ncc [ ' . $supplier->name . ' ]';
                            } else {
                                $valueNew = 'thêm';
                            }
                        }
                    }
                    if ($field == 'is_inspection') {
                        $supplier = Supplier::query()->where('id', $supplier_id)->first();
                        if ($supplier) {
                            $ncc = ' cho ncc [ ' . $supplier->name . ' ]';
                        }
                    }
                    $contentLog = getCurrentUser()->name . ' đã ' . $valueNew . ' dịch vụ ' . $array[$field] . $ncc;
                }
                if($field == 'official_is_partner') {
                    $contentLog = __(
                        'activity.change_official_is_partner',
                        [
                            'name' => $userName,
                            'action' => $modify == 1 ? 'tích chọn' : 'bỏ tích',
                        ]
                    );
                }

                (new ActivityService())->setOrderLog(
                    Order::query()->find($orderId),
                    $contentLog,
                    $orderId,
                    $isViewAdmin
                );

            }


        }
    }

    public function setChangeStaffCustomerLog($customer, $content, $staff_from_id, $staff_to_id, $user = null)
    {
        $from_staff = null;
        $to_staff = null;

        $userRepo = \App::make('App\Models\User');

        if ($staff_from_id) {
            $staffDB = $userRepo->where('id', $staff_from_id)->first();
            if ($staffDB) {
                $from_staff = $staffDB->name;
                $departmentDB = $staffDB->department;
                if ($departmentDB) {
                    $from_staff = $staffDB->name . ' - ' . $departmentDB->name;
                }
            }
        }

        if ($staff_to_id) {
            $staffDB = $userRepo->where('id', $staff_to_id)->first();
            if ($staffDB) {
                $to_staff = $staffDB->name;
                $departmentDB = $staffDB->department;
                if ($departmentDB) {
                    $to_staff = $staffDB->name . ' - ' . $departmentDB->name;
                }
            }
        }

        $data_change = [
            'from_staff' => $from_staff,
            'to_staff'   => $to_staff
        ];

        $activityModel = \App::make('App\Models\Activity');
        if(!$user) {
            $user = Auth::user() ?? User::query()->withoutGlobalScope(
                OrganizationScope::class
            )->first();
        }
        $data_log = [
            'subject_type' => get_class($customer),
            'subject_id' => $customer->id,
            'causer_type' => get_class($user),
            'causer_id' => $user->id,
            'log_name' => 'customer_staff_change_log',
            'content' => $content,
            'organization_id' => $customer->organization_id ?? getOrganization(),
            'properties' =>  json_encode($data_change),
        ];

        $activity = $activityModel->create($data_log);
    }

    // Đổi nhân viên bên đơn
    public function setChangeStaffOrderLog($order, $content, $staff_from_id, $staff_to_id, $user = null)
    {;
        $from_staff = null;
        $to_staff = null;

        $userRepo = \App::make('App\Models\User');

        if ($staff_from_id) {
            $staffDB = $userRepo->where('id', $staff_from_id)->first();
            if ($staffDB) {
                $from_staff = $staffDB->name;
                $departmentDB = $staffDB->department;
                if ($departmentDB) {
                    $from_staff = $staffDB->name . ' - ' . $departmentDB->name;
                }
            }
        }

        if ($staff_to_id) {
            $staffDB = $userRepo->where('id', $staff_to_id)->first();
            if ($staffDB) {
                $to_staff = $staffDB->name;
                $departmentDB = $staffDB->department;
                if ($departmentDB) {
                    $to_staff = $staffDB->name . ' - ' . $departmentDB->name;
                }
            }
        }
        if(!$user) {
            $user = Auth::user();
        }

        $contentLog = $user->name . ' thực hiện ' . $content . ' từ ' . $from_staff . ' thành ' . $to_staff;

        if ($from_staff == null) {
            $contentLog = $user->name . ' thực hiện ' . $content  . ' thành ' . $to_staff;
        }

        // Ghi log:
        $this->setOrderLog($order, $contentLog, $order->id);
    }

    /**
     * @param  Customer  $customer
     */
    public function activitiesCustomer(Customer $customer)
    {
        $columns = [
            'name', 'phone_number', 'email', 'customer_introduce_id', 'discount_by_kg', 'discount_by_m3', 'bod',
            'warehouse_id', 'service', 'label_id', 'source', 'warehouse_cn_id', 'password'
        ];

        // remove staff care, counselor in log customer info
        // save log change between staff care and staff counselor
        $currentStaffCare      = $customer->staff_care_id;
        $oldStaffCare          = $customer->getOriginal('staff_care_id');
        $currentStaffCounselor = $customer->staff_counselor_id;
        $oldStaffCounselor     = $customer->getOriginal('staff_counselor_id');
        $currentStaffGroup     = $customer->staff_group_id;
        $oldStaffGroup         = $customer->getOriginal('staff_group_id');

        //if ($oldStaffCare != $currentStaffCare || $oldStaffCounselor != $currentStaffCounselor) {
        if ($oldStaffGroup != $currentStaffGroup) {
            $dataStaff = [
                'staff_care'          => $currentStaffCare,
                'old_staff_care'      => $oldStaffCare,
                'staff_counselor'     => $currentStaffCounselor,
                'old_staff_counselor' => $oldStaffCounselor,
                'staff_group'         => $currentStaffGroup,
                'old_staff_group'     => $oldStaffGroup,
            ];
            $this->activitiesChangeStaffCustomer($customer, $dataStaff);
        }

        $this->prepareActivity($customer, $columns);
    }

    /**
     * @param  Model  $model
     * @param  array  $columns
     */
    private function prepareActivity(Model $model, array $columns)
    {
        $user = Auth::user() ?? User::query()->withoutGlobalScope(
                OrganizationScope::class
            )->first();

        $dataActivities = $this->getDataActivities($model, $columns, $user);

        $this->setCustomerLogs($dataActivities);
    }

    public function getDataActivities(Model $model, $columns, $user)
    {
        $dataActivities = [];
        foreach ($columns as $column) {
            if ($model->getOriginal($column) != $model->{$column}) {
                list($content, $properties) = $this->getContentActivityByColumn($column, $model->{$column}, $model,
                    $model->getOriginal($column));
                $logName                    = $this->getLogNameByColumn($column);
                $objectId                   = $this->getObjectIdByColumn($column, $model);
                $dataActivities[]           = [
                    // 'id'              => getUuid(),
                    'subject_type'    => get_class($model),
                    'subject_id'      => $model->id,
                    'causer_type'     => get_class($user),
                    'causer_id'       => $user->id,
                    'log_name'        => $logName,
                    'content'         => $content,
                    'object_id'       => $objectId,
                    'properties'      => $properties ? $properties : null,
                    'organization_id' => $user->organization_id ?? getOrganization(),
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s')
                ];
            }
        }

        return $dataActivities;
    }

    public function getContentActivityByColumn($column, $value, $model, $oldValue)
    {
        $properties = '';

        $array = [
            'name'                  => 'tên',
            'phone_number'          => 'số điện thoại',
            'email'                 => 'email',
            'customer_introduce_id' => 'người giới thiệu',
            'discount_by_kg'        => 'ck cho người giới thiệu (đ/kg)',
            'discount_by_m3'        => 'ck cho người giới thiệu (đ/m3)',
            'bod'                   => 'ngày sinh',
            'warehouse_id'          => 'kho hàng nhận',
            'service'               => 'dịch vụ',
            'staff_care_id'         => 'nhân viên CSKH',
            'staff_counselor_id'    => 'nhân viên tư vấn',
            'deposit_rate'          => 'đặt cọc',
            'offer_rate'            => 'đặt hàng',
            'trans_volumn_hcm_fee'  => 'phí vận chuyển HCM (/kg)',
            'trans_volumn_hn_fee'   => 'phí vận chuyển HN (/kg)',
            'trans_volumn_hp_fee'   => 'phí vận chuyển HP (/kg)',
            'trans_weight_hcm_fee'  => 'phí vận chuyển HCM (/m3)',
            'trans_weight_hn_fee'   => 'phí vận chuyển HN (/m3)',
            'trans_weight_hp_fee'   => 'phí vận chuyển HP (/m3)',
            'label_id'              => 'nhãn',
            'staff_group_id'        => 'nhân viên quản lý',
            'source'                => 'nguồn tạo tài khoản',
            'warehouse_cn_id'       => 'kho nhận hàng TQ',
            'password'              => 'mật khẩu'
        ];

        $pValueOld = '';
        $pValue    = '';

        switch ($column) {
            case 'warehouse_id':
                $value = optional($model->warehouse)->address;
                break;
            case 'warehouse_cn_id':
                $value = optional($model->warehouseCN)->name;
                break;
            case 'password':
                $value = 'mật khẩu mới';
                break;
            case 'source':
                $list = CustomerConstant::CUSTOMER_SOURCES;
                $value = isset($list[$value]) ? $list[$value] : $value;
                break;
            case 'service':
                $value = !empty(CustomerConstant::CUSTOMER_SERVICE[$value]) ? CustomerConstant::CUSTOMER_SERVICE[$value] : '';
                break;
            case 'customer_introduce_id':
                $customerIntroduce = Customer::query()->where('code', $model->customer_introduce_id)->first();
                $value             = optional($customerIntroduce)->name;
                break;
            case 'staff_care_id':
                $pValueOld = optional(Staff::find($oldValue))->name . ' - Chăm sóc';
                $value     = optional($model->staffCare)->name;
                $pValue    = $value ? $value . ' -  Chăm sóc' : '';
                break;
            case 'staff_counselor_id':
                $pValueOld = optional(Staff::find($oldValue))->name . ' - Tư vấn';
                $value     = optional($model->staffCounselor)->name;
                $pValue    = $value ? $value . ' -  Tư vấn' : '';
                break;
            case 'label_id':
                $value     = optional($model->label)->name;
                break;
            case 'staff_group_id':
                $pValueOld = optional(Staff::find($oldValue))->name . ' - Quản lý';
                $value     = optional($model->staffGroup)->name;
                $pValue    = $value ? $value . ' -  Quản lý' : '';
        }

        $transWeight = ['trans_weight_hcm_fee', 'trans_weight_hn_fee', 'trans_weight_hp_fee', 'discount_by_kg'];
        $transVolumn = ['trans_volumn_hcm_fee', 'trans_volumn_hn_fee', 'trans_volumn_hp_fee', 'discount_by_m3'];
        $orderRates  = ['offer_rate', 'deposit_rate'];
        if (in_array($column, $transWeight)) {
            $value .= ' (đ/kg)';
        } else if (in_array($column, $transVolumn)) {
            $value .= ' (đ/m3)';
        } elseif (in_array($column, $orderRates)) {
            $value .= ' %';
        }
        $user = \Auth::user() ?? User::query()->withoutGlobalScope(
            OrganizationScope::class
        )->first();
        $content = __('activity.customer_input',
            ['name' => $user->name, 'field' => $array[$column], 'value' => $value]
        );

        $labelColumns = ['label_id'];
        if (in_array($column, $labelColumns)) {
            $content = $value;
        }

        $saleColumns  = ['staff_care_id', 'staff_counselor_id'];
        if (in_array($column, $saleColumns)) {
            $properties = ['from_sale' => $pValueOld, 'to_sale' => $pValue];
        }
        if ($column == "warehouse_id") {
            $warehouseOld = Warehouse::find($model->getOriginal('warehouse_id'));
            $content = __('activity.customer_input_warehouse',
                ['name' => $user->name, 'field' => $array[$column], 'from' => data_get($warehouseOld, 'address'), 'to' => $value]
            );
        }

        $properties = $properties ? json_encode($properties) : '';

        return [$content, $properties];
    }

    public function getLogNameByColumn($column)
    {
        $saleColumns  = ['staff_care_id', 'staff_counselor_id', 'staff_group_id'];
        $labelColumns = ['label_id'];

        $logName = ActivityConstant::CUSTOMER_LOG;

        if (in_array($column, $saleColumns)) {
            $logName = ActivityConstant::CUSTOMER_SALE_LOG;
        } elseif (in_array($column, $labelColumns)) {
            $logName = ActivityConstant::CUSTOMER_LABEL_LOG;
        }

        return $logName;
    }

    public function getObjectIdByColumn($column, $model) {
        $objectId = $model->id;

        $customerOfferColumns = [
            'deposit_rate', 'offer_rate', 'trans_volumn_hcm_fee', 'trans_volumn_hn_fee', 'trans_volumn_hp_fee',
            'trans_weight_hcm_fee', 'trans_weight_hn_fee', 'trans_weight_hp_fee'
        ];

        if (in_array($column, $customerOfferColumns)) {
            $objectId = $model->customer_id;
        }

        return $objectId;
    }

    public function setCustomerLogs($dataActivities)
    {
        if (count($dataActivities)) {
            Activity::query()->insert($dataActivities);
        }
    }

    /**\
     * @param  Request $request
     * @return JsonResponse
     */
    public function getCustomerLog(Request $request): JsonResponse
    {
        $params = $request->only('customer_id', 'page', 'per_page', 'type');

        $customerId = !empty($params['customer_id']) ? $params['customer_id'] : '';
        if (!$customerId) {
            return resSuccessWithinData([]);
        }

        $type    = !empty($params['type']) ? $params['type'] : ActivityConstant::CUSTOMER_LOG;
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 10;

        $activities = Activity::query()->where(
            ['log_name' => $type, 'object_id' => $customerId]
        )->paginate($perPage);

        return resSuccessWithinData(
            new PaginateJsonResource($activities, ActivityResource::class)
        );
    }

    /**
     * @param CustomerOffer $customerOffer
     */
    public function activitiesCustomerOffer(CustomerOffer $customerOffer)
    {
        $columns = [
            'deposit_rate', 'offer_rate', 'trans_volumn_hcm_fee', 'trans_volumn_hn_fee', 'trans_volumn_hp_fee',
            'trans_weight_hcm_fee', 'trans_weight_hn_fee', 'trans_weight_hp_fee'
        ];

        $this->prepareActivity($customerOffer, $columns);
    }

    /**
     * @param Customer $customer
     * @param array $staff_care_id
     */
    public function activitiesChangeStaffCustomer(Customer $customer, $dataStaff)
    {
        list($content, $properties) = $this->getContentChangeStaffCustomer($customer, $dataStaff);

        $user = Auth::user() ?? User::query()->withoutGlobalScope(
                OrganizationScope::class
            )->first();

        $dataActivitie = [
            // 'id'              => getUuid(),
            'subject_type'    => get_class($customer),
            'subject_id'      => $customer->id,
            'causer_type'     => get_class($user),
            'causer_id'       => $user->id,
            'log_name'        => ActivityConstant::CUSTOMER_SALE_LOG,
            'content'         => $content,
            'object_id'       => $customer->id,
            'properties'      => json_encode($properties),
            'organization_id' => $user->organization_id ?? getOrganization(),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s')
        ];

        $this->setCustomerLogs($dataActivitie);
    }

    public function getContentChangeStaffCustomer(Customer $customer, $dataStaff)
    {
        $oldStaffCare      = $dataStaff['old_staff_care'];
        $staffCare         = $dataStaff['staff_care'];
        $oldStaffCounselor = $dataStaff['old_staff_counselor'];
        $staffCounselor    = $dataStaff['staff_counselor'];
        $oldStaffGroup     = $dataStaff['old_staff_group'];
        $staffGroup        = $dataStaff['staff_group'];

//        $toName = $staffCare ? optional($customer->staffCare)->name :
//            ($staffCounselor ? optional($customer->staffCounselor)->name : '');
//        $toSale = $toName ? $toName . ($staffCare ? ' - Chăm sóc' : ' - Tư vấn') : '';
        $toName = $staffGroup ? optional($customer->staffGroup)->name : '';
        $toSale = $toName ? $toName . ' - Quản lý' : '';

        //if (!$oldStaffCare && !$oldStaffCounselor) { // with first time update staff for customer
        if (!$oldStaffGroup) { // with first time update staff for customer
            $content    = 'Cập nhật nhân viên quản lý';
            $properties = ['from_sale' => '', 'to_sale' => $toSale];

            return [$content, $properties];
        }

        $fromSale   = $this->getFromSaleWhenChangeStaffCareCounselor($oldStaffCare, $oldStaffCounselor, $oldStaffGroup);

        $content    = 'Chuyển từ ' . $fromSale . ' thành ' . $toSale;
        $properties = ['from_sale' => $fromSale, 'to_sale' => $toSale];

        return [$content, $properties];
    }

    public function getFromSaleWhenChangeStaffCareCounselor($oldStaffCare, $oldStaffCounselor, $oldStaffGroup)
    {
        //$prefix = $oldStaffCare ? 'Chăm sóc' : ($oldStaffCounselor ? 'Tư vấn' : '');
        $prefix = $oldStaffGroup ? 'Quản lý' : '';

//        if ($oldStaffCare) {
//            $fromName = optional(Staff::find($oldStaffCare))->name;
//        } else {
//            $fromName = optional(Staff::find($oldStaffCounselor))->name;
//        }

        $fromName = $oldStaffGroup ? optional(Staff::find($oldStaffGroup))->name : '';

        $fromSale = $fromName ? $fromName . ' - ' . $prefix: '';

        return $fromSale;
    }

    // config log
    public function setConfigLog($userOnline, $content, $typeLog = ActivityConstant::CONFIG_LOG)
    {
        $activityModel = \App::make('App\Models\Activity');
        $data_log = [
            'subject_type' => get_class($userOnline),
            'subject_id' => $userOnline->id,
            'log_name' => $typeLog,
            'content' => $content,
            'organization_id' => $userOnline->organization_id ?? getOrganization(),
            'causer_type' => get_class($userOnline),
            'causer_id' => $userOnline->id,
        ];

        $activity = $activityModel->create($data_log);

    }

    public function getConfigLog(Request $request): JsonResponse
    {
        $typeLog = $request->input('official') == ConfigInternationalShip::OFFICIAL_2 ? ActivityConstant::CONFIG_LOG_OFFICIAL : ActivityConstant::CONFIG_LOG;
        $activities = Activity::query()->where(
            ['log_name' => $typeLog]
        )->where('created_at', '>', '2023-11-21 00:00:00')
        ->get();
        return resSuccessWithinData(
            $activities->transform(
                function ($item) {
                    return new $this->_resource($item);
                }
            )
        );
    }
}
