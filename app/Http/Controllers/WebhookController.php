<?php

namespace App\Http\Controllers;

use App\Constants\DeliveryConstant;
use App\Constants\FilePrintConstant;
use App\Constants\LocateConstant;
use App\Constants\OrderConstant;
use App\Exports\PackPrintWarehouseExport;
use App\Http\Requests\Delivery\ModifiesRequest;
use App\Http\Resources\Customer\InfoDeliveryResource;
use App\Http\Resources\CustomerDelivery\CustomerDeliveryResource;
use App\Http\Resources\Delivery\DeliveryResource;
use App\Http\Resources\Delivery\PrintExWarehouseResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\OnlyValueKeyResource;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\TransporterResource;
use App\Jobs\SentNotificationOrderSlowQuoteJob;
use App\Jobs\SentSmsToCustomerJob;
use App\Jobs\SyncOrderDetailNoteToOrderDetailJob;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\Delivery;
use App\Models\Ecommerce;
use App\Models\FilePrint;
use App\Models\Order;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\OrderDetailNote;
use App\Models\OrderRefund;
use App\Models\Refund;
use App\Models\RefundFromXadal;
use App\Models\ReportCustomer;
use App\Models\SmsLog;
use App\Models\Staff;
use App\Models\Transporter;
use App\Models\TransporterDetail;
use App\Models\User;
use App\Models\OrderPackage;
use App\Pivots\OrderBuyderOrder;
use App\Services\CommonService;
use App\Services\CustomerDeliveryService;
use App\Services\CustomerService;
use App\Services\DeliveryService;
use App\Services\EcommerceService;
use App\Services\FcmTokenService;
use App\Services\FilePrintService;
use App\Services\FilterService;
use App\Services\OrderPackageService;
use App\Services\ReportService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Constants\PackageConstant;
use App\Constants\WarehouseConstant;
use App\Constants\CustomerConstant;
use App\Helpers\RandomHelper;

use GuzzleHttp\Client as Guzzle;

use App\Helpers\AccountingHelper;
use Illuminate\Support\Facades\Log;

use App\Models\LogWebhook;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class WebhookController extends Controller
{
    protected $packageService;
    public function __construct(OrderPackageService $packageService)
    {
        $this->packageService = $packageService;
    }

    public function writeLog($params)
    {
        $logWebhookModel = \App::make('App\Models\LogWebhook');
        unset($params['token2000a']);
        $data_log = [
            'direction'   => LogWebhook::LOGWEBHOOK_DIRECTION_IN,
            'log_name'    => isset($params['event_name']) ? $params['event_name'] : 'no-name',
            'description' => 'WebhookController@dongdongSubscribe',
            'body'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'from_ip'     => request()->ip()
        ];
        $logWebhookModel->create($data_log);
    }

    public function dongdongSubscribe(Request $request)
    {
        $params = $request->all();
        // unset($params['token2000a']);
        // \Log::info('IPHook : ' . request()->ip() . ' - ' . json_encode($params, JSON_UNESCAPED_UNICODE));
        $this->writeLog($params);

        try {
            \DB::beginTransaction();

            $result_data = [];

            if ($params['event_name'] == 'update_pack') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $this->updatePackV2($params['data']);
            }

            if ($params['event_name'] == 'pack_to_vn_done') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $this->packToVnDone($params['data']);
            }
            if ($params['event_name'] == 'create_user') {
                $this->createUser($params['data']);
            }
            if ($params['event_name'] == 'create_category') {
                $this->createCategory($params['data']);
            }
            if ($params['event_name'] == 'sync_category') {
                $this->syncCategoryPack($params['data']);
            }
            // Logic mới 25-06-2024
            // Logic mới 25-06-2024
            // Logic mới 25-06-2024
            // Logic mới 25-06-2024
            // Logic mới 25-06-2024
            // Logic mới 25-06-2024
            if ($params['event_name'] == 'update_images_receive_pack') {
                $this->updateImageReceive($params);
            }
            if ($params['event_name'] == 'update_no_customer_pack') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $this->updateNoCustomerPack($params['data']);
            }
            if ($params['event_name'] == 'update_location_warehouse_pack') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $this->updateLocationWarehousePack($params['data']);
            }
            if ($params['event_name'] == 'update_size_pack') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $result_data = $this->updateSizePack($params['data']);
            }
            if ($params['event_name'] == 'update_arr_status_pack') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $this->updateStatusPack($params['data']);
            }
            // Phần hợp nhất
            if ($params['event_name'] == 'update_merge_pack') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $result_data = $this->updateMergePack($params['data']);
            }
            if ($params['event_name'] == 'change_customer_code') {
                $result_data = $this->changeCustomerCode($params['data']);
            }
            // Phần lưu kiện thất lạc
            if ($params['event_name'] == 'update_lost_pack') {
                $_POST['dongdong_id']  = $params['dongdong_id'];
                $result_data = $this->updateLostPack($params['data']);
            }
            if ($params['event_name'] == 'waiting_confirm_pack') {
                $result_data = $this->waitingConfirmPack($params['data']);
            }
            if ($params['event_name'] == 'sync_note_pack') {
                $result_data = $this->syncNotePack($params['data']);
            }
            if ($params['event_name'] == 'store_noti_staff_is_inspection') {
                $result_data = $this->storeNotiStaffIsInspection($params['data']);
            }
            if ($params['event_name'] == 'store_noti_pack_waiting') {
                $result_data = $this->storeNotiPackWaiting($params['data']);
            }
            if ($params['event_name'] == 'store_noti_approve_unit_price_quote') {
                $result_data = $this->storeNotiApproveUnitPriceQuote($params['data']);
            }
            if ($params['event_name'] == 'search_customer_by_phone_n_code') {
                $result_data = $this->searchCustomerByPhoneNCode($params['data']);
            }
            if ($params['event_name'] == 'export_excel_receive_good') {
                \DB::commit();

                $request_p = new \Illuminate\Http\Request(
                    $params['data']
                );

                return app(PackageController::class)
                    ->exportMultiple($request_p);

            }
            if ($params['event_name'] == 'check_shared_bill_code_multi') {
                \DB::commit();
                $request_p = new \Illuminate\Http\Request(
                    $params['data']
                );
                return app(PackageController::class)
                    ->checkSharedBillCodeMulti($request_p);
            }
            if ($params['event_name'] == 'update_receive_goods') {
                \DB::commit();

                $request_p = new \Illuminate\Http\Request(
                    $params['data']
                );
                return app(PackageController::class)
                    ->updateReceiveGoods($request_p);
            }
            if ($params['event_name'] == 'print_word_receive_goods') {
                \DB::commit();
                return $this->printWordReceiveGoods($params['data']);
            }
            if ($params['event_name'] == 'report_print_receive_goods') {

                $request_p = new \Illuminate\Http\Request(
                    $params['data']
                );
                $result_data = app(PackageController::class)
                    ->reportPrint($request_p);
            }

            if ($params['event_name'] == 'pagination_delivery') {
                \DB::commit();
                return $this->paginationDelivery($params['data']);
            }
            if ($params['event_name'] == 'common_delivery') {
                \DB::commit();
                return $this->commonDelivery($params['data']);
            }
            if ($params['event_name'] == 'detail_delivery') {
                \DB::commit();
                return $this->detailDelivery($params['data']);
            }
            if ($params['event_name'] == 'update_print_delivery') {
                \DB::commit();
                $request_p = new \Illuminate\Http\Request(
                    $params['data']
                );
                return app(DeliveryController::class)
                    ->updatePrint($request_p);
            }

            if ($params['event_name'] == 'activity_delivery') {
                \DB::commit();
                return $this->activityDelivery($params['data']);
            }

            if ($params['event_name'] == 'update_note_delivery') {
                \DB::commit();
                $request_p = new \Illuminate\Http\Request(
                    $params['data']
                );
                $_POST['dongdong_id'] = $params['data']['user_dongdong_id'] ?? null;
                return app(DeliveryController::class)
                    ->updateNote($request_p,$params['data']['id']);
            }
            if ($params['event_name'] == 'order_debt_delivery') {
                \DB::commit();

                $packageIds =  $params['data']['package_ids'];

                return app(OrderController::class)
                    ->getDebt($packageIds);
            }
            if ($params['event_name'] == 'print_delivery') {
                \DB::commit();

                $delivery_id =  $params['data']['delivery_id'];
                $delivery = Delivery::query()->findOrFail($delivery_id);
                return app(DeliveryController::class)
                    ->printDelivery($delivery);
            }

            if ($params['event_name'] == 'print_ex_warehouse_delivery') {
                \DB::commit();

                $delivery_id =  $params['data']['delivery_id'];
                $delivery = Delivery::query()->findOrFail($delivery_id);
                return $this->printExWarehouseDelivery($delivery,$params['data']);
            }

            if ($params['event_name'] == 'export_excel_warehouse_delivery') {
                \DB::commit();

                $delivery_id =  $params['data']['delivery_id'];
                $delivery = Delivery::query()->findOrFail($delivery_id);

                return app(DeliveryController::class)
                    ->getXlsx($delivery);

            }

            if ($params['event_name'] == 'destroy_delivery') {
                \DB::commit();

                $id =  $params['data']['id'];

                return app(DeliveryController::class)
                    ->destroy($id);

            }
            if ($params['event_name'] == 'update_delivery') {
                \DB::commit();

                $delivery_id =  $params['data']['delivery_id'];
                $delivery = Delivery::query()->findOrFail($delivery_id);
                $request_p = new ModifiesRequest(
                    $params['data']
                );
                $_POST['dongdong_id'] = $params['data']['user_dongdong_id'] ?? null;
                return app(DeliveryController::class)
                    ->modifies($request_p,$delivery);

            }
            \DB::commit();
            return resSuccessWithinData($result_data);
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function syncOrderDetailNoteToOrderDetail(): JsonResponse
    {
        for ($i = 0; $i < 1900 ; $i ++) {
            $jobs = new SyncOrderDetailNoteToOrderDetailJob($i);
            dispatch($jobs)->delay(now()->addSeconds(2))->onQueue('sent-noti-admin');
        }
        return resSuccess();
    }

    public function testSmsPhone(Request $request){
        $array = [
            'phone_number' => $request->phone_number ?? '84979598500',
            'message' => 'Don hang ma aaa da duoc bao gia. Quy khach truy cap https://hangve.com thanh toan de chung toi co the dat hang cho ban som nhat. Xin cam on!'
        ];
        SmsService::send($array);
    }

    public function updatePack($data) // Bỏ hàm này
    {
        $packRepo = \App::make('App\Models\OrderPackage');;
        $pack = $packRepo->where('id', $data['id'])->first();

        if ($pack) {
            \Log::info('Kiện này ok update đi');
            // How to keep only specific array keys/values in array?
            // https://stackoverflow.com/questions/23158393/how-to-keep-only-specific-array-keys-values-in-array

            $onlyKeys = ['total_pack', 'category_pack_id', 'weight', 'transporter_id', 'length', 'width', 'height'];

            $data_fill = array_filter($data['params'], function($v) use ($onlyKeys) {
                return in_array($v, $onlyKeys);
            }, ARRAY_FILTER_USE_KEY);

            $data_fill['category_id'] = $data_fill['category_pack_id'];
            $data_fill['package_number'] = $data_fill['total_pack'];
            $data_fill['weight'] = (int)$data_fill['weight'];

            $pack->fill($data_fill)->save();
        }
    }

    public function updatePackV2($data)
    {
        $packRepo          = \App::make('App\Models\OrderPackage');
        $accountingService = \App::make('App\Services\AccountingService');
        $configService     = \App::make('App\Services\ConfigService');

        try {
            \DB::beginTransaction();

            $arr_data_sync = [];

            foreach ($data as $key => $item) {
                $packDB = $packRepo->where('id', $item['id'])->first();
                if ($packDB) {
                    $weight_real = $item['params']['weight'];
                    $kgRound = $item['params']['weight'] == 0 ? 0 : ruleForKg($item['params']['weight']);
                    $data_pack_update = [
                        'weight'         => $kgRound,
                        'weight_real'    => $weight_real,
                        'volumn'         => $item['params']['volumn'],
                        'length'         => $item['params']['length'],
                        'width'          => $item['params']['width'],
                        'height'         => $item['params']['height'],
                        'package_number' => $item['params']['package_number'],
                    ];

                    // Tính toán lại phí vận chuyển quốc tế
                    $international_shipping_cost = $accountingService->getInternationShippingCost(
                        getProvinceX(optional($packDB->warehouse)->province_id),
                        $data_pack_update['weight'],
                        ($data_pack_update['length'] * $data_pack_update['width'] * $data_pack_update['height']) / 1000000,
                        $packDB->customer_id,
                        $isWeight,
                        $packDB->id
                    );
                    $data_pack_update['international_shipping_cost'] = $international_shipping_cost;
                    $data_pack_update['type_international_shipping_cost'] = $isWeight ? 'weight' : 'volume';
                    $data_pack_update['type_quantitative'] = $isWeight ? 'weight' : 'volume';

                    // Bổ sung tính chiếu khấu
                    $customerDB = $packDB->customer;
                    if ($customerDB) {
                        $levelCaculator = $customerDB->level == 0 ? 1 : (int)$customerDB->level;
                    } else {
                        $levelCaculator = 0;
                    }
                    $delivery_discount_per = $configService->getResultFromValueByLevel($levelCaculator)->delivery;
                    $data_pack_update['discount_cost'] = $international_shipping_cost * $delivery_discount_per / 100;
                    $data_pack_update['discount_percent'] = $delivery_discount_per;

                    // Nếu có customer_id thì cập nhật cả khách và mã kho nhận
                    // Khi mà kiện là chờ xác nhận thì bên kho nó sẽ gửi thêm thông tin này sang
                    if (isset($item['params']['customer_id'])) {
                        $data_pack_update['customer_id'] = $item['params']['customer_id'];
                        $data_pack_update['warehouse_id'] = $item['params']['warehouse_id'];
                        $data_pack_update['order_type'] = 'App\Models\Consignment'; // Chuyển về kiện kí gửi
                    }

                    // Tính lại
                    $packDB->fill($data_pack_update)->save();

                    // Dữ liệu lại đồng bộ ngược sang DD
                    $temp_sync_dd = [
                        'id' => $packDB->id,
                        'params' => [
                            'weight'                           => $packDB->weight,
                            'china_shipping_cost'              => $packDB->china_shipping_cost,
                            'international_shipping_cost'      => $packDB->international_shipping_cost,
                            'type_international_shipping_cost' => $packDB->type_international_shipping_cost,
                        ]
                    ];
                    array_push($arr_data_sync, $temp_sync_dd);
                }
            }

            \DB::commit();

            if (count($arr_data_sync)) {
                $this->syncPackToDongDongFromWebhook($arr_data_sync);
            }
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function syncPackToDongDongFromWebhook($data_sync)
    {
        \Log::info('Đồng bộ ngược lại DD');
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
        $options = [
            'json' => [
                'event_name' => 'update_pack',
                'data'       => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }

    // Backup cái này xong thì xóa
    public function updatePackV2T04092023($data)
    {
        $packRepo = \App::make('App\Models\OrderPackage');
        foreach ($data as $key => $item) {
            $packDB = $packRepo->where('id', $item['id'])->first();
            if ($packDB) {
                $data_pack_update = [
                    'weight'         => $item['params']['weight'],
                    'volumn'         => $item['params']['volumn'],
                    'length'         => $item['params']['length'],
                    'width'          => $item['params']['width'],
                    'height'         => $item['params']['height'],
                    'package_number' => $item['params']['package_number'],
                ];

                // Nếu có customer_id thì cập nhật cả khách và mã kho nhận
                if (isset($item['params']['customer_id'])) {
                    $data_pack_update['customer_id'] = $item['params']['customer_id'];
                    $data_pack_update['warehouse_id'] = $item['params']['warehouse_id'];
                }
                $packDB->fill($data_pack_update)->save();
            }
        }
    }

    public function packToVnDone($data)
    {
        $packRepo = \App::make('App\Models\OrderPackage');
        $packs = $packRepo->whereIn('id', $data)->get();

        if (count($packs)) {
            foreach ($packs as $key => $pack) {
                $pack->status = PackageConstant::STATUS_WAREHOUSE_VN;
                $pack->save();
            }
        }
    }

    public function createUser($params)
    {
        $userModel = \App::make('App\Models\User');
        $userDB = $userModel->where('email', $params['email'])->first();

        try {
            \DB::beginTransaction();

            if ($userDB) {
                $userDB->dongdong_id = $params['dongdong_id'];
                $userDB->dongdong_warehouse = $params['dongdong_warehouse'];
                $userDB->save();
            } else {
                $orgRepo = \App::make('App\Models\Organization');
                $orgThuongDo = $orgRepo->where('code', 'TC_0001')->first();

                $data_user  = [
                    // 'code'            => RandomHelper::userCode(),
                    'email'           => $params['email'],
                    'name'            => $params['name'],
                    'organization_id' => $orgThuongDo->id,
                    'password'        => bcrypt('123456789ka99'),
                    'dongdong_id'     => $params['dongdong_id'],
                    'dongdong_warehouse' => $params['dongdong_warehouse'],
                ];
                $userDB = $userModel->create($data_user);
            }
            \DB::commit();
            return resSuccessWithinData('Success');
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function infoServer(Request $request)
    {
        // phpinfo();
    }

    public function updateImageReceive($params)
    {
        $packRepo = \App::make('App\Models\OrderPackage');
        $packImageRepo = \App::make('App\Models\OrderPackageImage');
        $packDB =  $packRepo->where('id', $params['pack_id'])->first();

        if (!is_null($packDB)) {
            foreach ($params['data'] as $image) {
                if($image != null) {
                    $packImageDB = $packImageRepo->where('order_package_id', $packDB->id)
                                            ->where('image', $image)
                                            ->where('source', PackageConstant::IMAGE_SOURE_DONGDONG)
                                            ->first();
                    if (is_null($packImageDB)) {
                        $data_image = [
                            'order_package_id' => $packDB->id,
                            'image'            => $image,
                            'source'           => PackageConstant::IMAGE_SOURE_DONGDONG
                        ];
                        $packImageDB = $packImageRepo->create($data_image);
                    }
                }
            }
        }
    }

    public function updateNoCustomerPack($data)
    {
        $warehouseModel = \App::make('App\Models\Warehouse');
        $packModel      = \App::make('App\Models\OrderPackage');
        $customerModel  = \App::make('App\Models\Customer');

        $accountingService = \App::make('App\Services\AccountingService');
        $configService     = \App::make('App\Services\ConfigService');


        $customerDB = $customerModel->where('id', $data['customer_id'])->first();
        if (is_null($customerDB)) {
            return;
        }
        $warehouseDB = $warehouseModel->where('id', $data['warehouse_id'])->first();
        if (is_null($warehouseDB)) {
            return;
        }

        $customerId = $customerDB->id;
        $customerService = app()->make(CustomerService::class);
        $customerService->saveCustomerLabel($customerId);
        $data['package_number'] = data_get($data, 'package_number', 1);

        // Loại kiện
        $data['order_type'] = 'App\Models\Order';
        if ($data['type'] == 'consignment') {
            $data['order_type'] = 'App\Models\Consignment';
        }
        if ($data['organization_id'] == 0) {
            $data['organization_id'] = 1;
        }
        $data['status']          = PackageConstant::STATUS_PENDING;
        $data['code']            = RandomHelper::getPackageCode();
        $data['bill_code']       = $data['pack_code'];

        // Trạng thái kiện
        if ($data['warehouse_dongdong'] == WarehouseConstant::WAREHOUSE_QUANGCHAU) {
            $statusNew = PackageConstant::STATUS_IN_GUANGZHOU;
        }
        if ($data['warehouse_dongdong'] == WarehouseConstant::WAREHOUSE_DONGHUNG) {
            $statusNew = PackageConstant::STATUS_IN_CHINA;
        }
        if ($data['warehouse_dongdong'] == WarehouseConstant::WAREHOUSE_BANGTUONG) {
            $statusNew = PackageConstant::STATUS_IN_PINGXIANG;
        }

        // Thêm phần tỉ giá mặc định
        $data['exchange_rate'] = $configService->getExchangeRate();

        // $data['status'] = $statusNew;
        $packDB = $packModel->create($data);
        if($customerDB) {
            $customerDB->last_order_at = date('Y-m-d H:i:s');
            $customerDB->save();
        }

        // Tính toán lại phí
        $data_result = $this->calFeeHook($packDB, $data);

        // Tính lại
        $packDB->fill($data_result)->save();

        // Nếu có trạng thái mới nhất thì lưu lại
        if ($packDB && isset($data['one_warehouse_status'])) {
            // \Log::info('vào vào 1');
            $status_new = $this->convertStatusOneToStatusHV($data['one_warehouse_status']);

            if (!is_null($status_new)) {
                if ($packDB->status != $status_new) {
                    // \Log::info('zô zô 1');
                    $packDB->status = $status_new;
                    $packDB->save();
                } else {
                    \Log::info('Không cập nhật 1');
                }
            }
        }


        $this->syncPackNoCustomerToDongDongFromWebhook($packDB);
    }

    public function syncPackNoCustomerToDongDongFromWebhook($packDB)
    {
        // \Log::info('Đồng bộ ngược lại DD V2');

        $data_sync = [
            'pack_code'                        => $packDB->bill_code,
            'hangve_id'                        => $packDB->id,
            'organization_id'                  => $packDB->organization_id,
            'weight'                           => $packDB->weight,
            'weight_real'                      => $packDB->weight_real,
            'international_shipping_cost'      => $packDB->international_shipping_cost,
            'type_international_shipping_cost' => $packDB->type_international_shipping_cost,
        ];
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
        $options = [
            'json' => [
                'event_name' => 'update_no_customer_pack',
                'data'       => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }

    public function updateLocationWarehousePack($data)
    {
        $packModel      = \App::make('App\Models\OrderPackage');

        $packDB = $packModel->where('id', $data['pack_id'])->first();

        $status_update = null;
        // Trạng thái kiện
        if ($data['warehouse_dongdong'] == WarehouseConstant::WAREHOUSE_QUANGCHAU) {
            $status_update = PackageConstant::STATUS_IN_GUANGZHOU;
        }
        if ($data['warehouse_dongdong'] == WarehouseConstant::WAREHOUSE_DONGHUNG) {
            $status_update = PackageConstant::STATUS_IN_CHINA;
        }

        if ($status_update) {
            $packDB->status = $status_update;
            $packDB->save();
        }
    }

    public function updateSizePack($data)
    {
        $packRepo          = \App::make('App\Models\OrderPackage');

        $warehouseModel    = \App::make('App\Models\Warehouse');
        $packModel         = \App::make('App\Models\OrderPackage');
        $customerModel     = \App::make('App\Models\Customer');
        $accountingService = \App::make('App\Services\AccountingService');
        $configService     = \App::make('App\Services\ConfigService');


        $packDB =  $packRepo->where('id', $data['pack_id'])->first();
        if (!is_null($packDB)) {
            // Tính toán lại phí
            $data_result = $this->calFeeHook($packDB, $data);

            // Tính lại
            $packDB->update($data_result);

            return $data_result;
        }
        return [];
    }


    public function updateMergePack($data)
    {
        $packRepo          = \App::make('App\Models\OrderPackage');

        $warehouseModel    = \App::make('App\Models\Warehouse');
        $packModel         = \App::make('App\Models\OrderPackage');
        $customerModel     = \App::make('App\Models\Customer');
        $accountingService = \App::make('App\Services\AccountingService');
        $configService     = \App::make('App\Services\ConfigService');


        $packDB =  $packRepo->where('id', $data['pack_id'])->first();
        if (!is_null($packDB)) {

            // Tính toán lại phí
            $data_result = $this->calFeeHook($packDB, $data);

            // Nếu có trạng thái mới nhất thì lưu lại
            if ($packDB && isset($data['one_warehouse_status'])) {
                // \Log::info('vào vào 2');
                $status_new = $this->convertStatusOneToStatusHV($data['one_warehouse_status']);
                if (!is_null($status_new)) {
                    if ($packDB->status != $status_new) {
                        // \Log::info('zô zô 2');
                        $data_result['status'] = $status_new;
                    } else {
                        // \Log::info('Không cập nhật 2');
                    }
                }
            }

            // Tính lại
            $packDB->update($data_result);

            // Loại key status đi
            unset($data_result['status']);

            return $data_result;
        }
        return [];
    }

    public function calFeeHook($packDB, $data)
    {
        $packRepo          = \App::make('App\Models\OrderPackage');
        $warehouseModel    = \App::make('App\Models\Warehouse');
        $packModel         = \App::make('App\Models\OrderPackage');
        $customerModel     = \App::make('App\Models\Customer');
        $accountingService = \App::make('App\Services\AccountingService');
        $configService     = \App::make('App\Services\ConfigService');

        $data['weight_real']     = $data['weight'];
        $data['weight']          = $data['weight'] == 0 ? 0 : ruleForKg($data['weight']);
        $volumn = ($data['length'] * $data['width'] * $data['height']) / 1000000;
        $volumn = round(($data['length'] * $data['width'] * $data['height']) / 1000000, 3);
        $data['m3'] = $volumn;

        // Xử lý lại phần thể tích theo logic mới
        $data['volume_enter'] = 0;
        if (isset($data['package_number']) && $data['package_number'] > 1) {
            $volumn = $volumn * $data['package_number'];
        }
        if (isset($data['volumn_enter']) && $data['volumn_enter'] > 0) {
            $volumn = $data['volumn_enter'];
            $data['volume_enter'] = $data['volumn_enter'];
        }
        $data['m3'] = $volumn;

        // $a = 1;

        // Tính toán lại phí vận chuyển quốc tế (Đã có logic 5kg)
        $international_shipping_cost = $accountingService->getInternationShippingCost(
            getProvinceX(optional($packDB->warehouse)->province_id),
            $data['weight'],
            $volumn,
            $packDB->customer_id,
            $isWeight,
            $packDB->id
        );
        $data['international_shipping_cost'] = $international_shipping_cost;
        $data['type_international_shipping_cost'] = $isWeight ? 'weight' : 'volume';
        $data['type_quantitative'] = $isWeight ? 'weight' : 'volume';

        // Lưu lại đơn giá
        $data['unit_price'] = 0;
        if ($data['type_quantitative'] == 'weight') {
            if ($data['weight'] > 0) {
                $data['unit_price'] = $data['international_shipping_cost'] / $data['weight'];
            }
        } else {
            if ($volumn > 0) {
                $data['unit_price'] = $data['international_shipping_cost'] / $volumn;
            }
        }

        // Bổ sung tính chiếu khấu
        $customerDB = $packDB->customer;
        if ($customerDB) {
            $levelCaculator = $customerDB->level == 0 ? 1 : (int)$customerDB->level;
        } else {
            $levelCaculator = 0;
        }
        $delivery_discount_per = $configService->getResultFromValueByLevel($levelCaculator)->delivery;
        $data['discount_cost'] = $international_shipping_cost * $delivery_discount_per / 100;
        $data['discount_cost'] = round($data['discount_cost']);
        $data['discount_percent'] = $delivery_discount_per;
        // Nếu khách hàng có ưu đãi thì không được hưởng chiếu khấu nữa
        if ($customerDB) {
            $warehouseDB = $packDB->warehouse;
            $offerDB = $customerDB->offer;
            if ($offerDB && $warehouseDB) {
                $levelOffer = $offerDB->level_rate;
                if ($levelOffer == 0) {
                    $levelOffer = $customerDB->level;
                }
                if ($levelOffer > 1) {
                    if (
                        $offerDB['trans_weight_hn_fee'] > 0 ||
                        $offerDB['trans_volumn_hn_fee'] > 0
                    ) {
                        if (
                            $warehouseDB->code == 'HN-TD' ||
                            $warehouseDB->code == 'HN-HD' ||
                            $warehouseDB->code == 'HN-TQB'
                        ) {
                            $data['discount_cost'] = 0;
                            $data['discount_percent'] = 0;
                        }
                    }

                    if (
                        $offerDB['trans_weight_hcm_fee'] > 0 ||
                        $offerDB['trans_volumn_hcm_fee'] > 0
                    ) {
                        if (
                            $warehouseDB->code == 'HCM'
                        ) {
                            $data['discount_cost'] = 0;
                            $data['discount_percent'] = 0;
                        }
                    }
                }
            }
        }

        // Tính phí đóng gỗ
        $isWoodworking = $packDB->is_woodworking ? true : false;
        $accounting = \App::make('App\Services\AccountingService');
        $weight = $data['weight'];
        // $volume = ($data['length'] * $data['width'] * $data['height']) / 1000000;
        // set vào để biết type gì
        $packDB->type_quantitative = $data['type_quantitative'];
        $data['woodworking_cost'] = $isWoodworking ? AccountingHelper::getCosts(
            $accounting->getWoodworkingCost($weight, $volumn, $packDB)
        ) : 0;


        // Tính phí chống sốc
        $isShockProof = $packDB->is_shock_proof ? true : false;
        $data['shock_proof_cost'] = $isShockProof ? AccountingHelper::getCosts(
            $accounting->getShockCost($weight, $packDB)
        ) : 0;

        // Nếu mã đơn bị trùng thì chia lại số tiền vào từng đơn (18-01-2026)
        // Nếu mã đơn bị trùng thì chia lại số tiền vào từng đơn
        // Nếu mã đơn bị trùng thì chia lại số tiền vào từng đơn
        $arr_status = [
            PackageConstant::STATUS_CANCEL,
            PackageConstant::STATUS_RECEIVED_GOODS,
        ];
        $packDBs = OrderPackage::query()->where('bill_code', $packDB->bill_code)
                            ->whereNotIn('status', $arr_status)
                            ->get();
        $count_pack = count($packDBs);
        if ($count_pack > 1) {
            $data['international_shipping_cost'] = (int) ($data['international_shipping_cost'] / $count_pack);
            $data['share_fee_ship']              = PackageConstant::SHARE_FEE_SHIP;
            // Nếu có đóng gỗ
            if ($isWoodworking) {
                $count_good = $packDBs->where('is_woodworking', 1)->count();
                $data['woodworking_cost'] = (int) ($data['woodworking_cost'] / $count_good);
            }
            // Nếu có chống sốc
            if ($isShockProof) {
                $count_good = $packDBs->where('is_shock_proof', 1)->count();
                $data['shock_proof_cost'] = (int) ($data['shock_proof_cost'] / $count_good);
            }
        }

        return $data;
    }

    public function updateStatusPack($data)
    {
        foreach ($data as $key => $item) {
            // $this->updateStatusPackAction($item);

            $job = new \App\Jobs\OneWarehouse\SyncStatusPack($item, $_POST['dongdong_id']);
            dispatch($job)->onQueue('one-status-line');
        }
    }

    public function updateStatusPackAction($item)
    {
        \Log::info('updateStatusPackAction: ' . $item['pack_partner_id']);
        $packRepo = \App::make('App\Models\OrderPackage');
        try {
            \DB::beginTransaction();
            $packDB = $packRepo->where('id', $item['pack_partner_id'])->first();
            if ($packDB) {
                $status_new = $this->convertStatusOneToStatusHV($item['one_warehouse_status']);

                if (!is_null($status_new)) {
                    if ($packDB->status != $status_new) {
                        \Log::info('zô zô');
                        if ($packDB->status != PackageConstant::STATUS_RECEIVED_GOODS) {
                            $packDB->status = $status_new;
                            $packDB->save();
                        }
                    } else {
                        \Log::info('Không cập nhật');
                    }
                }
            }
            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function convertStatusOneToStatusHV($status_one)
    {
        // Chốt
        // Kho - HV

        // Đang về kho TQ - Đang về kho TQ
        // Đang kho Quảng Châu - Đang ở kho QC
        // Đang kho Bằng Tường - Đến kho Bằng Tường

        // Đang vận chuyển - Đang về VN
        // Đang kho Trung chuyển - Đang kiểm hàng
        // Đã chuyển hàng - Đang chuyển hàng

        // Đến kho VN -> Quét bước cuối cùng VN

        $arr_convert_status = [
            PackageConstant::STATUS_ONE_COMING_TO_WAREHOUSE_TQ            => PackageConstant::STATUS_ON_THE_WAY_CHINA,
            PackageConstant::STATUS_ONE_IN_WAREHOUSE_QC                   => PackageConstant::STATUS_IN_GUANGZHOU,
            PackageConstant::STATUS_ONE_IN_WAREHOUSE_BT                   => PackageConstant::STATUS_IN_PINGXIANG,
            PackageConstant::STATUS_ONE_IN_WAREHOUSE_DH                   => PackageConstant::STATUS_IN_CHINA,

            PackageConstant::STATUS_ONE_PROCESSING_TRANSPORT              => PackageConstant::STATUS_ON_THE_WAY_VN,
            PackageConstant::STATUS_ONE_COMING_TO_WAREHOUSE_TRANSSHIPMENT => PackageConstant::STATUS_CHECKING_GOODS,
            PackageConstant::STATUS_ONE_DONE_TRANSPORT                    => PackageConstant::STATUS_LOADING_GOODS,

            PackageConstant::STATUS_ONE_IN_WAREHOUSE_VN                   => PackageConstant::STATUS_WAREHOUSE_VN,

            // 2 trạng thái phụ danh riêng cho bên HCM
            PackageConstant::STATUS_ONE_COMING_TO_HCM                     => PackageConstant::STATUS_ON_THE_WAY_HCM,
            PackageConstant::STATUS_ONE_CHECKING_IN_HCM                   => PackageConstant::STATUS_CHECKING_GOODS_HCM,
        ];

        if (array_key_exists($status_one, $arr_convert_status)) {
            return $arr_convert_status[$status_one];
        }
        return null;
    }

    public function testSms(Request $request): JsonResponse
    {
        try {
            $phone_number = $request->phone_number ?? 0;
            $params = [
                'phone_number' => $phone_number,
                'message' => 'Test gửi SMS ' . $phone_number
            ];
            $jobs = new SentSmsToCustomerJob($params);
            dispatch($jobs)->onQueue('sent-sms-customer');
            return resSuccess();
        } catch (\Exception $exception) {
            Log::info('Error SMS : '.$exception->getMessage());
            return resError($exception->getMessage());
        }

    }

    /**
     * @throws BindingResolutionException
     */
    public function testPushNotificationFcmForApp(Request $request): JsonResponse
    {
        try {
            $fcmTokenService = app()->make(FcmTokenService::class);
            $title = 'Thay đổi trạng thái đơn hàng';
            $body = 'Ông Tân test push notification';
            $dataSent = [
                'type' => 'test',
                'body' => 'Ông Tân test push notification'
            ];
            $fcmTokenService->sendMessageForApp([], $title, $body, $dataSent);
            Log::info('Fcm Token Success ');
            return resSuccess();
        } catch (\Exception $exception) {
            Log::info('Fcm Token Error ' . $exception->getMessage());
            return resError($exception->getMessage());
        }

    }

    public function forgetCache(Request $request): JsonResponse
    {
        $cache_name = $request->cache_name ?? '';
        if ($cache_name) {
            Cache::forget($cache_name);
            return resSuccess();
        }
        return resError();
    }

    public function setRefreshTokenZalo(Request $request): JsonResponse
    {
        $refresh_token = $request->refresh_token ?? '';
        $url_access_token = SmsService::URL_ACCESS_TOKEN_ZALO;
        $app_id = SmsService::APP_ID;
        $payload = 'refresh_token='.$refresh_token.'&app_id='.$app_id.'&grant_type=refresh_token';
        $response = $this->curl($payload, $url_access_token, 'POST');
        if ($response) {
            if (empty($response->error_name)) {
                Cache::put('access_token_oa', $response->access_token, now()->addHours(20));
                Cache::put('refresh_token_oa', $response->refresh_token, now()->addDays(85));
                return resSuccessWithinData($response);
            } else {
                return resErrorWithinData($response);
            }
        }
        return resError();
    }

    public function getTokenZalo(): JsonResponse
    {
        return resSuccessWithinData([
            'access_token_oa' => Cache::get('access_token_oa', ''),
            'refresh_token_oa' => Cache::get('refresh_token_oa', ''),
        ]);
    }

    public function setTokenZalo(Request $request): JsonResponse
    {
        if (!empty($request->access_token_oa) && !empty($request->refresh_token_oa)) {
            Cache::put('access_token_oa', $request->access_token_oa, now()->addHours(20));
            Cache::put('refresh_token_oa', $request->refresh_token_oa, now()->addDays(85));
        }
        return resSuccess();
    }

    public function ecommerce(EcommerceService $ecommerceService): JsonResponse
    {
        $ecommerce = OrderConstant::ECOMMERCE;
        $arrayInsert = [];
        foreach ($ecommerce as $key => $e) {
            if (!$ecommerceService->newQuery()->where('key', $key)->first()) {
                $arrayInsert[] = [
                    'name' => $e,
                    'key' => $key,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        if (!empty($arrayInsert)) {
            $ecommerceService->storeMany($arrayInsert);
        }
        if (!$ecommerceService->newQuery()->where('key', 'other')->first()) {
            $arrayInsert = [
                'name' => 'Khác',
                'key' => 'other',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $ecommerceService->store($arrayInsert);
        }
        return resSuccess();
    }

    public function testJobEcommerce(EcommerceService $ecommerceService, Request $request): JsonResponse
    {
        $params = $request->all();
        $jobs = new SentNotificationOrderSlowQuoteJob($params);
        dispatch($jobs)->onQueue('sent-noti-admin');
        return resSuccess();
    }

    private function curl($data, $url, $method)
    {
        try {
            $secret_key = SmsService::SECRET_KEY;
            $header = [
                'Content-Type: application/x-www-form-urlencoded',
                'secret_key: ' . $secret_key
            ];
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                $header
            );
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result, false);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function sentOtpApiClient(Request $request): array
    {
        $paramsZalo = $request->payload ?? [];
        if (empty($paramsZalo)) {
            return [
                'code' => 500,
                'message' => 'Thất bại!',
                'data' => []
            ];
        }
        $response = SmsService::sendZaLoOTP($paramsZalo);
        if ($response == 1) {
            Log::info('SMS ZaLo Success OTP phone ' . $paramsZalo['phone_number']);
            return [
                'code' => 200,
                'message' => 'Thành công!',
                'data' => []
            ];
        }
        Log::info('SMS ZaLo Error OTP phone ' . $paramsZalo['phone_number']);
        return [
            'code' => 500,
            'message' => 'Thất bại!',
            'data' => []
        ];
    }

    public function resetPasswordSendOtpPhone(Request $request): array
    {
        $paramsZalo = $request->payload ?? [];
        if (empty($paramsZalo)) {
            return [
                'code' => 500,
                'message' => 'Thất bại!',
                'data' => []
            ];
        }
        $response = SmsService::sendZaLoOTP($paramsZalo);
        if ($response == 1) {
            Log::info('SMS ZaLo Success ResetPassword phone ' . $paramsZalo['phone_number']);
            return [
                'code' => 200,
                'message' => 'Thành công!',
                'data' => []
            ];
        }
        Log::info('SMS ZaLo Error ResetPassword phone ' . $paramsZalo['phone_number']);
        return [
            'code' => 500,
            'message' => 'Thất bại!',
            'data' => []
        ];
    }

    public function thuongdoSubscribe(Request $request)
    {
        $validationRules = [
            'order_code' => 'required',
            'token' => 'required',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();
        \Log::info('thuongdoSubscribe : ' . request()->ip() . ' - ' . $params['order_code']);

        $token = $params['token'];

        if ($token != 'Mm28391acnqmxnqueouxnqjr2xjqjrxhr1888') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        \Log::info('SyncSupplier In Hook');
        for ($i=4055; $i < 4100; $i++) {
            $job = new \App\Jobs\SyncTD\SyncSupplier($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        }

        // Đồng bộ
        $job = new \App\Jobs\SyncTD\SyncOrderMrT(0, $params['order_code']);
        dispatch($job)->delay(now()->addSeconds(20))->onQueue('sent-noti-admin');

        return resSuccess();
    }

    public function thuongdoWithdrawal(Request $request)
    {
        $validationRules = [
            'token'      => 'required',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();
        \Log::info('thuongdoWithdrawal : ' . request()->ip());

        $token = $params['token'];

        if ($token != 'Mm28391acnqmxnqueouxnqjr2xjqjrxhr1888') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $customerWithdrawalModel = \App::make('App\Models\CustomerWithdrawal');
        $customerWithdrawalDBs = $customerWithdrawalModel->where('thuongdo_id', '>', 0)
                                                        ->where('status', CustomerConstant::KEY_WITHDRAWAL_STATUS_PENDING)
                                                        ->get();
        $arr_result = [];
        if (count($customerWithdrawalDBs) > 0) {
            foreach ($customerWithdrawalDBs as $key => $customerWithdrawalDB) {
                array_push($arr_result, (int) $customerWithdrawalDB->thuongdo_id);
            }
        }

        return resSuccessWithinData($arr_result);
    }

    public function thuongdoDeposit(Request $request)
    {
        $validationRules = [
            'token'      => 'required',
            'order_id'   => 'required|exists:orders,id'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();
        \Log::info('thuongdoDeposit : ' . $params['order_id']);

        $token = $params['token'];

        if ($token != 'Mm28391acnqmxnqueouxnqjr2xjqjrxhr1666') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $params['order_id'])->first();

        if ($orderDB->deposit_percent == 0) {
            $job = new \App\Jobs\SyncTD\SyncDepoistPercent($orderDB);
            dispatch($job)->delay(now()->addSeconds(3))->onQueue('sent-noti-admin');
            return resSuccessWithinData([
                "Thành công"
            ]);
        }

        return resSuccessWithinData([]);
    }

    public function changeCustomerCode($params)
    {
        $packRepo = \App::make('App\Models\OrderPackage');
        $packImageRepo = \App::make('App\Models\OrderPackageImage');
        $packRepo->where('id', $params['pack_id'])->update([
            'customer_id' => data_get($params, 'customer_id'),
            'warehouse_id' => data_get($params, 'warehouse_id'),
            'staff_group_id' => data_get($params, 'staff_group_id'),
        ]);
    }

    public function createCategory($params)
    {
        $categoryModel = \App::make('App\Models\Category');
        try {
            $categoryModel = \App::make('App\Models\Category');
            $categoryModel->firstOrCreate($params, [
                'name' => $params['name'],
                'thuongdo_name_china' => $params['name'],
                'thuongdo_name_en' => $params['name']
            ]);
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }

    }

    public function syncCategoryPack($params)
    {
        $categoryModel = \App::make('App\Models\Category');
        $packModel = \App::make('App\Models\OrderPackage');
        try {
            $category = $categoryModel->where('name', data_get($params, 'category_name'))->first();
            $package = $packModel->where('id', data_get($params, 'hangve_id'))->first();
            if($category && $package) {
                $package->category_id = $category->id;
                $package->save();
            }
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }

    }

    public function updateLostPack($data)
    {
        foreach ($data as $key => $item) {
            $this->updateLostPackAction($item);
        }
    }

    public function updateLostPackAction($item)
    {
        \Log::info('updateLostPackAction: ' . $item['pack_partner_id']);
        $packRepo = \App::make('App\Models\OrderPackage');
        try {
            $packDB = $packRepo->where('id', $item['pack_partner_id'])->first();
            if ($packDB) {
                if ($packDB->is_lost != 1) {
                    \Log::info('zô zô updateLostPackAction');
                    $packDB->is_lost = 1;
                    $packDB->save();
                } else {
                    \Log::info('Không cập nhật updateLostPackAction');
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function waitingConfirmPack($pack)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $roleModel = \App::make('App\Models\Role');
        $notificationService = \App::make('App\Services\NotificationService');
        $orderPackage = $orderPackageModel->where('id', data_get($pack, 'hangve_id'))->first();
        $staffOrderIds = [];
        if($orderPackage) {
            if($orderPackage->order_type == null) {
                $role = $roleModel::where('name', 'like', '%nhan-vien-dat-hang%')->first();
                $staffOrderIds = User::whereHas('roles', function($q) use($role) {
                    $q->where('id', $role->id);
                })->pluck('id')->toArray();
            }else {
                if($orderPackage->staff_order_id) {
                    array_push($staffOrderIds, $orderPackage->staff_order_id);
                }
            }
            $orderPackage->update(['is_confirm' => 1]);
        }
        if(count($staffOrderIds)) {
            foreach($staffOrderIds as $staffOrderId) {
                $data_order = [
                    'bill_code'  => $orderPackage->bill_code,
                ];
                $data = [
                    'group'           => 3,
                    'name'            => 3004,
                    'data'            => json_encode($data_order),
                    'time_create'     => time(),
                    'status'          => 1
                ];
                $notificationService->storeNoti($staffOrderId, $data);
            }
        }
    }

    public function syncNotePack($data)
    {
        try {
            $packId = data_get($data, 'hangve_id');
            $content = data_get($data, 'content');
            $userName = data_get($data, 'user_name');
            $model = \App::make('App\Models\OrderPackage');
            $orderPackage = $model->where('id', $packId)->first();
            if ($orderPackage) {
                if ($orderPackage->note_internal != $content) {
                    $orderPackage->notes()->create([
                        'content' => $content,
                        'type' => PackageConstant::TYPE_NOTE_INTERNAL,
                        'user_name' => $userName
                    ]);
                    $model->where('id', $packId)->update(['note_internal' => $content]);
                }
            }else {
                \Log::info('Không tìm thấy kiện để update ghi chú nội bộ');
            }
            return resSuccess();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function storeNotiStaffIsInspection($data)
    {
        try {
            $billCode = data_get($data, 'bill_code');
            $staffOrderId = data_get($data, 'staff_order_id');
            $packId = data_get($data, 'pack_id');
            $notificationService = \App::make('App\Services\NotificationService');
            $dataContent = [
                'bill_code'  => $billCode,
                'pack_id'    => $packId,
            ];
            $data = [
                'group'           => 3,
                'name'            => 3007,
                'data'            => json_encode($dataContent),
                'time_create'     => time(),
                'status'          => 1
            ];

            if ($staffOrderId) {
                $notificationService->storeNoti($staffOrderId, $data);
            }
            return resSuccess();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function storeNotiPackWaiting($data)
    {
        try {
            $billCode = data_get($data, 'bill_code');
            $staffOrderId = data_get($data, 'staff_order_id');
            $notificationService = \App::make('App\Services\NotificationService');
            $dataContent = [
                'bill_code'  => $billCode,
            ];
            $data = [
                'group'           => 3,
                'name'            => 3008,
                'data'            => json_encode($dataContent),
                'time_create'     => time(),
                'status'          => 1
            ];

            if ($staffOrderId) {
                $notificationService->storeNoti($staffOrderId, $data);
            }
            return resSuccess();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function refundFromXadal(Request $request)
    {
        try {
            $data = $request->all();
            $code = data_get($data, 'code');
            $moneyCN = data_get($data, 'moneyCN');
            $orderCode = data_get($data, 'order_code');
            // update các phần data đã update về DB
            // code sẽ tương ứng với buyder_no trong bảng order_buyders
            $orderBuyder = OrderBuyder::where('buyder_no', $code)->first();
            if(empty($orderBuyder)) {
                Log::info('Đơn này không phải của HV');
                return false;
            }
            $orderIds = OrderBuyderOrder::query()
                ->withoutGlobalScopes()
                ->where('order_buyder_id', $orderBuyder->id)
                ->pluck('order_id');
            // Tạm thời lấy 1 thằng đầu tiên. Vì case 1 mã đơn hàng nhiều mã đặt hàng bên xadal chưa xử lý được,
            // hoàn thủ công vẫn đang chỉ hoàn theo mã đơn hàng
            $orderId = 0;
            if(!empty($orderIds)) {
                $orderId = $orderIds[0];
            }
            if(!$orderCode) {
                $orderCode = $code;
            }
            Log::info('data tạo refund xadal ' . json_encode(
                    [
                        'order_code' => $orderCode,
                        'money' => $moneyCN,
                        'buyder_no' => $code,
                        'order_id' => $orderId,
                    ]
                ));
            return RefundFromXadal::query()->firstOrCreate([
                'order_code' => $orderCode,
                'money' => $moneyCN,
                'buyder_no' => $code,
                'order_id' => $orderId ?? 0,
            ]);
        } catch (\Exception $exception) {
            return resError($exception->getMessage());
        }
    }

    private function storeNotiApproveUnitPriceQuote($data)
    {
        try {
            // Gửi thông báo cho tất cả NV có quyền duyệt yêu cầu
            $permission = "pack.approve-unit-price-quote";
            $users = User::join('model_has_roles', function($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('role_has_permissions', 'model_has_roles.role_id', 'role_has_permissions.role_id')
            ->join('permissions', function($joinPer) use ($permission) {
                $joinPer->on('permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where('permissions.name', $permission);
                })
            ->select('users.*')
            ->groupBy('users.id')
            ->get();
            if(count($users) > 0) {
                $notificationService = \App::make('App\Services\NotificationService');
                $dataJson = [
                    'quote_code' => data_get($data, 'quote_code'),
                ];
                $data = [
                    'group'           => 3,
                    'name'            => 3012,
                    'data'            => json_encode($dataJson),
                    'time_create'     => time(),
                    'status'          => 1
                ];
                foreach($users as $user) {
                    $notificationService->storeNoti($user->id, $data);
                }
            }
            return resSuccess();
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function searchCustomerByPhoneNCode($data)
    {
        $phone = data_get($data, 'phone_number',null);
        $code = data_get($data, 'customer_code',null);
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel
            ->when($code, function ($query) use ($code) {
                $query->where('code', $code);
            })
            ->when($phone, function ($query) use ($phone) {
                $query->where('phone_number', $phone);
            })
            ->where('status',1)
            ->first();


        if($customer){

            $customer->balance_amount = data_get($customer->report, 'balance_amount', 0);
        }else{
            $customer = null;
        }
        $customerOffer = null;

        if($customer){
            $packages = OrderPackage::select("order_package.*")
                ->where('order_package.status', PackageConstant::STATUS_WAREHOUSE_VN)
                ->where('order_package.customer_id', $customer->id)
                ->with(['order', 'warehouse'])
                ->get();
            $customerOffer = CustomerOffer::where('customer_id', $customer->id)->first();
        }else{
            $packages = collect();
        }

        $data_pack = $packages->map(function ($item) {
            if ($item->order_type == Order::class) {
                $order = $item->order;
                // Tiền đã thanh toán
                $depositCost = data_get($order, 'deposit_cost', 0);
                if(data_get($order, 'delivery_pay_id') > 0) {
                    $depositCost += data_get($order, 'delivery_pay_debt_cost', 0);
                }
                $totalAmount = (data_get($order, 'thuongdo_id') && data_get($order, 'has_edit') == 0) ? data_get($order, 'thuongdo_tong_tien_don_hang') : $order->getTotalAmountNotShip();
                $item->unsetRelation('order');
                $item->order = [
                    'id' => data_get($order, 'id'),
                    'code' => data_get($order, 'code'),
                    'total' => $totalAmount, // New
                    'deposit_cost' => $depositCost,
                    'balance' => $totalAmount - $depositCost, // New
                ];
            } else {
                $item->unsetRelation('order');
                $item->order = null;
            }
            return $item;
        });

        $data_pack = [
            'order_package' => $data_pack->toArray(),
            'customer' => $customer,
            'offer_debt' => data_get($customerOffer, 'debt')
        ];


        if ($customer) {

            $companyName = [
                1 => 'Hàng Về',
                2 => 'Thương Đô',
            ];
            $result = [
                'customer' =>[ 'customer_has' => 1,
                    'id' => optional($customer)->id,
                    'name' => optional($customer)->name,
                    'phone_number' => optional($customer)->phone_number,
                    'code' => optional($customer)->code,
                    'company_name' => $companyName[$customer->source] ?? '',
                ],
                'data_pack' =>$data_pack

            ];
        }else{
            $result = [
                'customer' =>[  'customer_has' => 0,
                    'id' => "",
                    'name' => "",
                    'phone_number' => "",
                    'code' => "",
                    'company_name' => "",
                ],
                'data_pack' =>$data_pack

            ];
        }
        return $result;

    }

    private function checkSharedBillCodeMulti($data)
    {
        $orderPackIds = data_get($data, 'orderPackIds');
        $orderPackModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            PackageConstant::STATUS_RECEIVED_GOODS,
            PackageConstant::STATUS_CANCEL,
        ];

        $orderPackDBTemps = $orderPackModel->whereIn('id', $orderPackIds)
            ->whereNotNull('bill_code')
            ->get();
        $pack_codes = \Arr::pluck($orderPackDBTemps, 'bill_code');


        $orderPackDBs = $orderPackModel->whereIn('bill_code', $pack_codes)
            ->whereNotIn('status', $arr_status)
            ->get();
        $arr_bill_code = [];
        if (count($orderPackDBs) > 0) {
            $arr_bill_code = \Arr::pluck($orderPackDBs, 'bill_code');
        }

        $code_duplicate = "";
        $counts = array_count_values($arr_bill_code);
        foreach ($counts as $bill_code => $count) {
            if ($count > 1) {
                $code_duplicate .= $bill_code . ",";
            }
        }

        if ($code_duplicate == "") {
            return [
                'status'  => true,
                "message" => null
            ];
        }


        $code_duplicate = rtrim($code_duplicate, ",");
        return [
            'status'  => false,
            'message' => "Các kiện " . $code_duplicate . " đang thuộc nhiều đơn khác nhau. Vui lòng kiểm tra kĩ trước khi thực hiện thao tác"
        ];

    }
    private function printWordReceiveGoods($data)
    {
        // Bấm in cũng lưu lại lịch sử
        app(FilePrintService::class)
            ->store($data, FilePrintConstant::KEY_TYPE_PACK_RECEIVE_GOODS);

        $orderPackIds = data_get($data, 'orderPackIds', []);
        $user_dongdong_name = data_get($data, 'user_dongdong_name', '');
        $customerId = data_get(OrderPackage::whereIn('id', $orderPackIds)->first(), 'customer_id');
        $orderPacks = OrderPackage::whereIn('id', $orderPackIds)
            ->with([
                'order',
                'customerDelivery',
                'customerDelivery.district',
                'customerDelivery.province',
                'organization',
                'warehouse'
            ])
            ->get();
        $orderIds = [];
        $warehouseCode = '';
        foreach($orderPacks as $pack){
            if($pack->order_type == Order::class){
                array_push($orderIds, $pack->order_id);
            }
            $warehouseCode = $pack->warehouse->code ?? '';
        }

        $orders = Order::whereIn('id', $orderIds)->get();
        $customer = Customer::find($customerId);
        $warehouse = $customer ? $customer->warehouse : '';
        $data_p = [
            'print_receipt_code' => getPrintReceiptCode($warehouseCode),
            'packages' => $orderPacks->toArray(),
            'orders' => $orders->toArray(),
            'customer' => $customer,
            'userName' => $user_dongdong_name,
            'warehouse' => $warehouse
        ];

        $viewContent = View::make('print.dongdong_pack', $data_p)->render();

        return resSuccessWithinData($viewContent);
    }

    public function paginationDelivery($params)
    {
        $_paginateResource = PaginateJsonResource::class;
        $_resource = DeliveryResource::class;

        $model = Delivery::class;

        $query =  $model::query()->select('deliveries.*');
        $query->with([
            'notesStaff', 'deliveryOrderStatus', 'transporter', 'transporterDetail', 'customerDelivery',
            'customer.warehouse', 'customer.report', 'order:id,code',
            'customer' => function($q){
                return $q->select('id', 'name', 'phone_number', 'warehouse_id', 'source');
            },
            'orderPackages' => function($q){
                return $q->select('id', 'delivery_id', 'bill_code');
            },
        ])
            ->when(data_get($params, 'is_received') != null, function($q) use ($params){
                $q->where('deliveries.status', data_get($params, 'is_received') == 1 ? '=' : '!=', DeliveryConstant::KEY_STATUS_DONE);
            });


        // Lọc thêm theo cả sdt của khách
        $phone_number = isset($params['phone_number']) ? $params['phone_number'] : null;
        if ($phone_number) {
            $customerModel = \App::make('App\Models\Customer');
            $customerDB = $customerModel->where('phone_number', $phone_number)->first();

            $query->where(function ($q) use ($phone_number, $customerDB) {
                $q->where('deliveries.phone_number', $phone_number); // thêm bảng deliveries
                if ($customerDB) {
                    $q->orWhere('deliveries.customer_id', $customerDB->id); // thêm bảng deliveries
                }
            });
        }

        $receiver = isset($params['receiver']) ? $params['receiver'] : null;

        if ($receiver) {
            $query->where('receiver', 'like', '%' . $receiver . '%');
        }

        $bill_code = isset($params['bill_code']) ? $params['bill_code'] : null;

        if ($bill_code) {
            $query->join('order_package', 'deliveries.id', 'order_package.delivery_id')
                ->where(function ($q) use ($bill_code) {
                    $q->where('order_package.bill_code', $bill_code);
                });
        }

        $carrier_code = isset($params['carrier_code']) ? $params['carrier_code'] : null;

        if ($carrier_code) {
            $query->where('carrier_code', $carrier_code);
        }

        $date = isset($params['date']) ? $params['date'] : null;
        if ($date) {
            (new FilterService())->rangeDateFilter($query, $date, 'date');
        }

        $payment = isset($params['payment']) ? $params['payment'] : null;

        if ($payment) {
            $query->where('payment', $payment);
        }

        $order_code= isset($params['order_code']) ? $params['order_code'] : null;

        if ($order_code) {
            $query->join('delivery_orders', 'deliveries.id', '=', 'delivery_orders.delivery_id')
                ->join('orders', 'delivery_orders.order_id', '=', 'orders.id')
                ->where('orders.code', $order_code)
                ->select('deliveries.*')
                ->distinct();
        }

        $transporter_detail_id = isset($params['transporter_detail_id']) ? $params['transporter_detail_id'] : null;

        if ($transporter_detail_id) {
            $query->where('transporter_detail_id', $transporter_detail_id);
        }

        $transporter_id = isset($params['transporter_id']) ? $params['transporter_id'] : null;

        if ($transporter_id) {
            $query->where('transporter_id', $transporter_id);
        }

        $warehouse_id = isset($params['warehouse_id']) ? $params['warehouse_id'] : null;

        if ($warehouse_id) {
            $query->join('order_package', 'deliveries.id', 'order_package.delivery_id')
                ->where(function ($q) use ($warehouse_id) {
                    $q->where('order_package.warehouse_id', $warehouse_id);
                });
        }

        $status = isset($params['status']) ? $params['status'] : null;

        if ($status) {
            $query->where('deliveries.status', $status);
        }

        $query = $query->orderBy('created_at', 'desc');
        $query = $query->groupBy('deliveries.id');
        $perPage = data_get($params, 'per_page', 15);
        $page = data_get($params, 'page', 1);
        $data = $query->paginate($perPage, ['*'], 'page', $page);

        return resSuccessWithinData(new $_paginateResource($data, $_resource));
    }

    public function commonDelivery($params)
    {
        $customerId = isset($params['customer_id']) ? $params['customer_id'] : null;
        $data = [
            'reports' => [
                'all' => (new CommonService())->getDeliveryReportAll(),
                'time' => (new ReportService())->reports(Delivery::class),
            ],
            'payments' => new OnlyValueKeyResource(DeliveryConstant::PAYMENTS),
            'transporters' => new ListResource(Transporter::query()->where('country', LocateConstant::COUNTRY_VI)->get(), TransporterResource::class),
            'transporters_detail' => TransporterDetail::query()->select('id', 'name', 'phone_number')->get(),
            'status_package' => new OnlyValueKeyResource(PackageConstant::DELIVERIES),
            'status_payment' => new OnlyValueKeyResource(DeliveryConstant::STATUSES_PAYMENT),
            'statuses' => new OnlyValueKeyResource(DeliveryConstant::STATUSES),
            'warehouses' => (new CommonService())->getWarehouseByCountry(),
        ];
        if ($customerId) {
            $data['customer'] = new InfoDeliveryResource(Customer::query()->findOrFail($customerId));
            $data['customer_deliveries'] = new ListResource(
                (new CustomerDeliveryService())->getListByCustomerId($customerId),
                CustomerDeliveryResource::class
            );
        }
        return resSuccessWithinData($data);
    }

    public function detailDelivery($params)
    {
        $id = data_get($params, 'id');

        return app(DeliveryService::class)->detail($id);
    }
    public function activityDelivery($params)
    {
        $id = data_get($params, 'id');
        return app(ActivityController::class)->getDeliveryLog($id);
    }
    public function printExWarehouseDelivery(Delivery $delivery, $params): JsonResponse
    {
        $filePrintService = \App::make('App\Services\FilePrintService');

        $delivery->load(['customer:id,name,phone_number', 'packages', 'warehouse']);
        $orderPackageIds = $delivery->packages()->pluck('id')->toArray();
        $params['orderPackIds'] = $orderPackageIds;
        $filePrintService->store($params, FilePrintConstant::KEY_TYPE_PACK_DELIVERY);

        return resSuccessWithinData(new PrintExWarehouseResource($delivery));
    }




    public function setOrderCodeDD(Request $request)
    {
        try {
            if (!$request->order_code || !$request->pack_id) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Thiếu dữ liệu'
                ]);
            }

            $order = \App\Models\Order::query()
                ->where('code', $request->order_code)
                ->first();

            if ($order == null) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Mã đơn hàng không tồn tại'
                ]);
            }

            $customer = \App\Models\Customer::where('id', $order->customer_id)->first();

            if ($customer) {
                return response()->json([
                    'code' => 200,
                    'data' => [
                        'customer_has' => 1,
                        'customer_code' => $customer->code,
                        'customer_name' => $customer->name,
                        'customer_id' => $customer->id,
                        'company_id' => $customer->organization_id,
                        'staff_group_id' => $customer->staff_group_id,
                        'order' => $order,
                        'pack_id' => $request->pack_id,
                    ]
                ]);
            }

            return response()->json([
                'code' => 200,
                'data' => [
                    'customer_has' => 0,
                    'customer_code' => "",
                    'customer_name' => "",
                    'customer_id' => "",
                    'company_id' => 0,
                    'order' => $order,
                    'pack_id' => $request->pack_id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function storeWaitingConfirmHV(Request $request)
    {
        try {
            $packageController = app()->make(\App\Http\Controllers\PackageController::class);
            return $packageController->storeWaitingConfirm($request);

        } catch (\Exception $e) {
            \Log::error('Webhook HV error: ' . $e->getMessage());
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function searchCustomerConditionHV(Request $request)
    {
        if (!$request->customer_search) {
            return response()->json([
                'code' => 422,
                'message' => 'Thiếu dữ liệu'
            ]);
        }
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $request->customer_search)
            ->orWhere('phone_number', $request->customer_search)
            ->first();
        if($customer == null) {
            return resError("Khách hàng không tồn tại");
        }

        if ($customer) {
            $result = [
                'customer_has' => 1,
                'customer_code' => $customer->code,
                'customer_name' => $customer->name,
                'customer_id' => $customer->id,
                'company_id' => $customer->organization_id,
                'staff_group_id' => $customer->staff_group_id
            ];
            return resSuccessWithinData($result);
        }

        $result = [
            'customer_has' => 0,
            'customer_code' => "",
            'customer_name' => "",
            'customer_id' => "",
            'company_id' => 0
        ];
        return resSuccessWithinData($result);
    }
    public function printReceiveGoods(Request $request)
    {
        try {
            $params = $request->all();
            $filePrintService = \App::make('App\Services\FilePrintService');
            return $filePrintService->getData($params);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function printFile($id)
    {
        $orderPackService = \App::make('App\Services\OrderPackageService');
        $file = FilePrint::find($id);
        $dataJson = json_decode($file->data_json);
        $type = $file->type;
        $data = $orderPackService->getDataPrintPack($dataJson);
        $data['type'] = $type;
        $view_content = View::make('print.warehouse_pack', $data)->render();
        return resSuccessWithinData($view_content);
    }
    public function getPackInFilePrintHV(Request $request)
    {
        try {
            $statisticController = app()->make(\App\Http\Controllers\StatisticController::class);
            return $statisticController->getPackInFilePrint($request);

        } catch (\Exception $e) {
            \Log::error('Webhook HV error: ' . $e->getMessage());
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function checkPackInFilePrintHV(Request $request)
    {
        $validationRules = [
            'file_print_id'    => 'required|exists:file_prints,id',
            'order_package_id' => 'required'
        ];
        $validationMessages = [
        ];

        $params = $request->all();

        $orderPackModel = \App::make('App\Models\OrderPackage');
        $filePrintModel = \App::make('App\Models\FilePrint');
        $filePrintCheckModel = \App::make('App\Models\FilePrintCheck');

        $filePrintDB = $filePrintModel->where('id', $params['file_print_id'])->first();
        $dataFilePrint = json_decode($filePrintDB->data_json, true);

        // Nếu không có kiện trong File thì cho té
        if (!in_array($params['order_package_id'], $dataFilePrint['orderPackIds'])) {
            $result = [
                'unit' => 'Kiện không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check xem nó đã dc được check chưa
        $filePrintCheckDB = $filePrintCheckModel->where('file_print_id', $params['file_print_id'])
            ->where('order_package_id', $params['order_package_id'])
            ->first();
        if (!is_null($filePrintCheckDB)) {
            $result = [
                'unit' => 'Kiện đã được quét'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        // Cập nhật
        try {
            \DB::beginTransaction();
            $data_check = [
                'file_print_id'    => $params['file_print_id'],
                'order_package_id' => $params['order_package_id'],
                'dongdong_id'          => $params['dongdong_id'],
            ];
            $filePrintCheckModel->create($data_check);
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            \DB::rollback();
            \Log::error('Webhook HV error: ' . $e->getMessage());
            return resError($e->getMessage());
        }
    }
}
