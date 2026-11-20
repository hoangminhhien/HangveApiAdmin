<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FundService;

use GuzzleHttp\Client as Guzzle;

use App\Helpers\RandomHelper;
use App\Services\ReportService;
use App\Constants\ReportConstant;

use App\Constants\WarehouseConstant;
use App\Constants\PackageConstant;
use App\Constants\TransactionConstant;

use App\Helpers\AccountingHelper;

use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

use App\Constants\DeliveryConstant;
use App\Models\OrderPackage;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Delivery;

// Phần đồng bộ khách hàng
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\CustomerOffer;
use App\Constants\CustomerConstant;

// Phần đồng bộ quỹ
use App\Constants\FundTransactionConstant;

// Đồng bộ hoàn tiền
use App\Constants\RefundConstant;

// Đồng bộ Ghi chú
use App\Models\OrderDetailNote;

// Đồng bộ cập nhật tổng tiền thực tế
use App\Constants\OrderCostRealConstant;

// Đồng bộ ảnh
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

// Ghi chú v2
use App\Models\OrderDetailHistory;
use App\Models\OrderSupplierHistory;

class PartnerController extends Controller
{
    public function __construct(FundService $service)
    {
        $this->_service = $service;
    }

    public function makeAccountSync(Request $request)
    {
        $userRepo = \App::make('App\Models\User');
        $user = $userRepo->where('email', 'partner2023@gmail.com')->first();

        if (is_null($user)) {
            $data_user = [
                'name' => 'Partner 2023',
                'email' => 'partner2023@gmail.com',
                'password' => bcrypt('92k2832k1ssmj242ATkm')
            ];
            $user = $userRepo->create($data_user);
        }
        dd($user);
    }

    // Đồng bộ danh mục sản phẩm
    public function syncCategory(Request $request)
    {
        $categoryRepo = \App::make('App\Models\Category');
        $categories = $categoryRepo->get();

        $list_categories = [];
        if (count($categories)) {
            foreach ($categories as $key => $category) {
                $temp = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'organization_id' => $category->organization_id,
                ];
                array_push($list_categories, $temp);
            }
        }


        $data_result = [
            'list_categories'  => $list_categories,
        ];
        return resSuccessWithinData($data_result);
    }

    // Đồng bộ kho
    public function syncWarehouse(Request $request)
    {
        $validationRules = [
            'code' => 'required|exists:organizations,code',
        ];
        $validationMessages = [
            'code.exists' => "Mã công ty không tồn tại"
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = request()->all();
        // Tìm công ty
        $organizationRepo = \App::make('App\Models\Organization');
        $organization = $organizationRepo->where('code', $params['code'])->first();

        // Tìm các kho ở VN
        $warehouseRepo = \App::make('App\Models\Warehouse');
        $warehouses = $warehouseRepo->where('country', 'vi')
                        ->where('organization_id', $organization->id)
                        ->where('status', 1)
                        ->get();

        $lists = [];
        if (count($warehouses)) {
            foreach ($warehouses as $key => $item) {
                $temp = [
                    'id'      => $item->id,
                    'name'    => $item->name,
                    'code'    => $item->code,
                    'address' => $item->address,
                ];
                array_push($lists, $temp);
            }
        }

        $data_result = [
            'lists'  => $lists,
        ];
        return resSuccessWithinData($data_result);
    }

    // Đồng bộ hãng vận chyển
    public function syncTransporter(Request $request)
    {
        $validationRules = [
            'code' => 'required|exists:organizations,code',
        ];
        $validationMessages = [
            'code.exists' => "Mã công ty không tồn tại"
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = request()->all();
        // Tìm công ty
        $organizationRepo = \App::make('App\Models\Organization');
        $organization = $organizationRepo->where('code', $params['code'])->first();

        // Tìm hãng vận chyển ở VN
        $warehouseRepo = \App::make('App\Models\Transporter');
        $warehouses = $warehouseRepo->where('country', 'cn')->get();

        $lists = [];
        if (count($warehouses)) {
            foreach ($warehouses as $key => $item) {
                $temp = [
                    'id'                    => $item->id,
                    'name'                  => $item->name,
                    'short_name'            => $item->short_name,
                    'is_delivery_type'      => $item->is_delivery_type,
                    'is_get_delivery_price' => $item->is_get_delivery_price,
                    'order'                 => $item->order,
                    'has_children'          => $item->has_children,
                    'country'               => $item->country,
                ];
                array_push($lists, $temp);
            }
        }

        $data_result = [
            'lists'  => $lists,
        ];
        return resSuccessWithinData($data_result);
    }

    public function searchPackage(Request $request)
    {
        try {
            \DB::beginTransaction();

            $validationRules = [
                'pack_code' => 'required',
                'warehouse' => 'required|in:quangchau-001,donghung-002,vietnam-003',
                'dongdong_id' => 'required|numeric|min:1|exists:users,dongdong_id', // Ai thao tác cái này
            ];
            $validationMessages = [
                'dongdong_id.min' => 'Tài khoản đang đăng nhập này không thể lấy dữ liệu',
                'dongdong_id.exists' => 'Tài khoản đang đăng nhập này không thể lấy dữ liệu.'
            ];

            $validated = $request->validate($validationRules, $validationMessages);

            $params = request()->all();
            $orderPackRepo = \App::make('App\Models\OrderPackage');
            $orderPackage = $orderPackRepo->where('bill_code', (string)$params['pack_code'])->first();

            $_POST['dongdong_id']  = $params['dongdong_id'];

            if (is_null($orderPackage)) {
                // Nếu chưa có thì tạo mới
                $data_pack_new = [
                    'name'           => "",
                    'bill_code'      => $params['pack_code'],
                    'exchange_rate'  => getExchangeRate(),
                    'organization_id'=> getOrganization(),
                    'weight'         => 0,
                    'package_number' => 1,
                    'length'         => 0,
                    'width'          => 0,
                    'height'         => 0,
                    'is_inspection'  => 0,
                    'is_woodworking' => 0,
                    'is_shock_proof' => 0,
                    'is_insurance'   => 0,
                    'status'         => PackageConstant::STATUS_PENDING
                ];

                // Kho mặc định
                $warehouseRepo = \App::make('App\Models\Warehouse');
                $warehouse = $warehouseRepo->where('country', 'vi')->where('code', WarehouseConstant::DEFAULT)->first();

                if ($warehouse) {
                    $data_pack_new['warehouse_id'] = $warehouse->id;
                }

                $orderPackage = $orderPackRepo->create($data_pack_new);
            }

            // Check nó quá đã về VN thì cũng ko cho dữ liệu
            $arr_package_status = \App\Constants\PackageConstant::STATUSES;
            $key_status = array_search ($orderPackage->status, array_keys($arr_package_status));
            if ($key_status >= 6) {
                $result = [
                    'error' => 'Kiện đã về VN ..'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            // Cập nhật trạng thái kiện tùy thuộc vào vào kho của người quét
            $this->updateStatusPackFromWarehouseOfStaff($orderPackage, $params);

            $size = "";

            if ($orderPackage->length) {
                $size .= $orderPackage->length;
            }
            if ($orderPackage->width) {
                $size .= "x" . $orderPackage->width;
            }
            if ($orderPackage->height) {
                $size .= "x" . $orderPackage->height;
            }

            $volumnDB = ($orderPackage->length * $orderPackage->width * $orderPackage->height)/1000000;

            $warehouseDB = $orderPackage->warehouse_id;

            if (is_null($warehouseDB)) {
                $warehouseRepo = \App::make('App\Models\Warehouse');
                $warehouse = $warehouseRepo->where('country', 'vi')->where('code', WarehouseConstant::DEFAULT)->first();

                if ($warehouse) {
                    $warehouseDB = $warehouse->id;
                }
            }

            // Tính giá sản pẩm
            // $priceDB = 0;
            // $orderDetails = $orderPackage->orderDetails;
            // $orderDB = $orderPackage->order;
            // if ($orderDetails) {
            //     foreach ($orderDetails as $key => $item) {
            //         $priceDB += $item->unit_price_cny * $item->quantity * $orderDB->exchange_rate;
            //     }
            // }

            // Loại kiện
            $typeDB = 'unconfirmed';
            if ($orderPackage->order_type == 'App\Models\Order') {
                $typeDB = 'order';
            }
            if ($orderPackage->order_type == 'App\Models\Consignment') {
                $typeDB = 'consignment';
            }

            $data_package = [];
            $data_package['code']             = $orderPackage->bill_code;
            $data_package['organization_id']  = (int)$orderPackage->organization_id;
            $data_package['order_code']       = $orderPackage->order_code;
            $data_package['type']             = $typeDB;
            $data_package['name']             = $orderPackage->product_name;
            $data_package['weight']           = $orderPackage->weight;

            $data_package['volumn']           = $volumnDB;
            $data_package['size']             = $size;
            $data_package['length']           = $orderPackage->length;
            $data_package['width']            = $orderPackage->width;
            $data_package['height']           = $orderPackage->height;

            $data_package['hangve_id']        = $orderPackage->id;
            $data_package['category_pack_id'] = $orderPackage->category_id;
            $data_package['package_number']   = $orderPackage->package_number;
            $data_package['is_inspection']    = $orderPackage->is_inspection;
            $data_package['is_woodworking']   = $orderPackage->is_woodworking;
            $data_package['is_shock_proof']   = $orderPackage->is_shock_proof;
            $data_package['is_insurance']     = $orderPackage->is_insurance;
            // $data_package['price']            = round($priceDB);
            $data_package['carriers']         = optional($orderPackage->transporterRelation)->name ?? "";

            $data_package['note']             = $orderPackage->note;
            $data_package['warehouse_id']     = $warehouseDB;
            $data_package['transporter_id']   = $orderPackage->transporter_id;
            $data_package['customer_id']      = $orderPackage->customer_id;
            $data_package['customer_code']    = null;
            $data_package['customer_name']    = null;
            $data_package['date_create']      = $orderPackage->created_at->format('Y-m-d H:i:s');


            if ($orderPackage['customer_id']) {
                $customerOfPack = $orderPackage->customer;
                $data_package['customer_code'] = $customerOfPack->code;
                $data_package['customer_name'] = $customerOfPack->name;
            }

            $data_result = [
                'data_package'  => $data_package,
            ];

            \DB::commit();

            return resSuccessWithinData($data_result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateStatusPackFromWarehouseOfStaff($orderPackage, $params)
    {
        $status_update = null;
        if ($params['warehouse'] == WarehouseConstant::WAREHOUSE_QUANGCHAU) {
            $status_update = PackageConstant::STATUS_IN_GUANGZHOU;
        }
        if ($params['warehouse'] == WarehouseConstant::WAREHOUSE_DONGHUNG) {
            $status_update = PackageConstant::STATUS_IN_CHINA;
        }

        if ($status_update) {
            $orderPackage->status = $status_update;
            $orderPackage->save();
        }
    }

    // Lưu khách hàng vào kiện chờ xác nhận
    public function saveCustomerPackUnconfirmed(Request $request)
    {
        $validationRules = [
            'pack_code'     => 'required',
            'customer_code' => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->all();

        // Check xem đây phải là kiện chờ xác nhận không
        $orderPackRepo = \App::make('App\Models\OrderPackage');
        $orderPackageDB = $orderPackRepo->where('bill_code', $params['pack_code'])->where('order_type', null)->first();

        if (is_null($orderPackageDB)) {
            return resError('Kiện chờ xác nhận này không tồn tại trên hệ thống');
        }

        // Check xem khách hàng này có tồn tại trên hệ thống không
        $customerRepo = \App::make('App\Models\Customer');
        $customerDB = $customerRepo->where('code', $params['customer_code'])->first();

        if (is_null($customerDB)) {
            return resError('Khách hàng không tồn tại');
        }

        try {
            \DB::beginTransaction();

            // B1: Gán khách hàng vào kiện
            $orderPackageDB->customer_id = $customerDB->id;
            $orderPackageDB->order_type = 'App\Models\Consignment'; // Chuyển về kiện kí gửi
            $orderPackageDB->save();

            \DB::commit();

            // Update các thông tin sau sang bên DD
            $data_package = [
                'customer_id'   => $customerDB->id,
                'customer_code' => $customerDB->code,
                'customer_name' => $customerDB->name,
                'type'          => 'consignment',
            ];

            return resSuccessWithinData($data_package);

        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function searchCustomer(Request $request)
    {
        $validationRules = [
            'customer_code' => 'required',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = request()->all();
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $params['customer_code'])->with(['deliveryDefault', 'deliveryDefault.province', 'deliveryDefault.province', 'deliveryDefault.district', 'deliveryDefault.ward'])->first();

        if (is_null($customer)) {
            $result = ['error' => 'Khách hàng không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $data_customer = [
            'customer_id'   => $customer->id,
            'customer_code' => $customer->code,
            'customer_name' => $customer->name,
            'warehouse_id'  => $customer->warehouse_id,
            'warehouse_id'  => is_null($customer->warehouse_id) ? 1 : $customer->warehouse_id,
            'customer_phone'  => $customer->phone_number,
            'customer_delivery' => data_get($customer->deliveryDefault, 'address').' - '.
                                    (data_get($customer->deliveryDefault, 'district') ? data_get($customer->deliveryDefault, 'district.name') : '').' - '.
                                    (data_get($customer->deliveryDefault, 'ward') ? data_get($customer->deliveryDefault, 'ward.name') : '').' - '.
                                    (data_get($customer->deliveryDefault, 'province') ? data_get($customer->deliveryDefault, 'province.name') : ''),
            'staff_group_id'  => $customer->staff_group_id,
            
        ];

        $data_result = [
            'data_customer'  => $data_customer,
        ];

        return resSuccessWithinData($data_result);
    }

    public function exportManifest(Request $request)
    {
        $validationRules = [
            'list_pack' => 'required|array',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = request()->all();
        $packModel = \App::make('App\Models\OrderPackage');
        $packDB = $packModel->whereIn('code_warehouse', $params['list_pack'])->with('categoryRelation', 'products', 'customer', 'customer.deliveryDefault')->get();
        $data_1 = [];
        $data_2 = [];

        if (count($packDB)) {
            foreach ($packDB as $key => $pack) {
                $categoryDB = $pack->categoryRelation;

                $products = $pack->products;

                $size = ($pack->length != 0 ? $pack->length : '');
                $size .= '*' . ($pack->width != 0 ? $pack->width : '');
                $size .= '*' . ($pack->height != 0 ? $pack->height : '');

                $links = '';
                if (count($products)) {
                    foreach ($products as $key_product => $product) {
                        $links .= $product->link . ',';
                    }
                }
                $links = substr_replace($links ,"", -1);

                $temp1 = [
                    'code'     => $pack->code_warehouse,
                    'cate_cn'  => $categoryDB ? $categoryDB->thuongdo_name_china : null,
                    'cate_en'  => $categoryDB ? $categoryDB->thuongdo_name_en : null,
                    'quantity' => $pack->quantity,
                    'kg'       => $pack->weight,
                    'kg_real'  => $pack->weight_real,
                    'm3'       => $pack->volume,
                    'size'     => $size,
                    'customer_name' => data_get($pack->customer, 'name'),
                    'customer_phone' => data_get($pack->customer, 'phone_number'),
                    'customer_address' => $pack->customer ? data_get($pack->customer->deliveryDefault, 'address') : null,
                    'unit_price_cny' => count($products) ? data_get($products[0], 'unit_price_cny') : 0,
                    'total_amount' =>  count($products) ? data_get($products[0], 'pivot.quantity') * data_get($products[0], 'unit_price_cny') : 0
                ];
                $temp2 = [
                    'code'       => $pack->code_warehouse,
                    'cate_cn'    => $categoryDB ? $categoryDB->thuongdo_name_china : null,
                    'cate_en'    => $categoryDB ? $categoryDB->thuongdo_name_en : null,
                    'quantity'   => $pack->quantity,
                    'kg'         => $pack->weight,
                    'kg_real'    => $pack->weight_real,
                    'order_cost' => $pack->order_cost,
                    'links'      => $links,
                ];

                array_push($data_1, $temp1);
                array_push($data_2, $temp2);
            }
        }

        $data_result = [
            'data_1'  => $data_1,
            'data_2'  => $data_2,
        ];

        return resSuccessWithinData($data_result);
    }

    public function exportManifestScreenBag(Request $request)
    {
        $validationRules = [
            'list_pack' => 'required|array',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        try {
            $params = request()->all();
            $orderPackages = OrderPackage::select('id', 'amount', 'exchange_rate', 'code_warehouse', 'weight')->whereIn('code_warehouse', $params['list_pack'])->with([
                'orderDetailsPackage', 
                'customer' => function($q) {
                    return $q->select('id', 'name');
                }, 'customer.deliveryDefault' => function($q) {
                    return $q->select('id', 'name', 'is_default', 'ward_id', 'district_id', 'province_id');
                }, 'customer.deliveryDefault.district', 'customer.deliveryDefault.province',
                'warehouseCn' => function($q) {
                    return $q->select('id', 'name', 'address');
                }
                ])->get();
            $data = [];
            foreach ($orderPackages as $key => $pack) {
                $category = $pack->categoryRelation;
                $customerDelivery = data_get($pack->customer, 'deliveryDefault');
                $warehouseCn = data_get($pack, 'warehouseCn');
                array_push($data, [
                    'unit_price' => data_get($pack, 'amount'),
                    'unit_price_cny' => data_get($pack, 'exchange_rate') != 0 ? round(data_get($pack, 'amount') / data_get($pack, 'exchange_rate'), 2) : data_get($pack, 'amount'),
                    'code' => data_get($pack, 'code_warehouse'),
                    'package_number' => $pack->orderDetailsPackage()->sum('quantity'),
                    'weight' => data_get($pack, 'weight'),
                    'address_cn' => data_get($warehouseCn, 'name'),
                    'address_full_cn' => data_get($warehouseCn, 'address'),
                    'customer_name' => data_get($pack->customer, 'name'),
                    'customer_address' => data_get($customerDelivery, 'address'),
                    'customer_district' => $customerDelivery ? data_get($customerDelivery->district, 'name') : null,
                    'customer_province' => $customerDelivery ? data_get($customerDelivery->province, 'name') : null,
                    'customer_receiver' => data_get($customerDelivery, 'receiver'),
                    'customer_phone_number' => data_get($customerDelivery, 'phone_number'),
                ]);
            }

            return resSuccessWithinData($data);
        } catch (\Exception $exception) {
            \Log::info($exception);
            return resSuccessWithinData([]);
        }
        
    }

    public function testSendHook(Request $request)
    {
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        // $url = 'http://172.31.0.5/api/auth/demo-huuhu';

        $url = 'http://02:42:ac:1f:00:05/api/webhooks/hangve-x329kxm2k2ixk39x92x1911947165';
        $url = 'http://host.docker.internal:8000/api/webhooks/hangve-x329kxm2k2ixk39x92x1911947165';
        $options = [
            'json' => [
                'event_name'    => 'update_pack1',
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        dd($result);
    }

















































    // ĐỒNG BỘ
    // ĐỒNG BỘ
    // ĐỒNG BỘ
    // ĐỒNG BỘ
    // ĐỒNG BỘ

    public function syncCityLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/city';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1200,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
        // dd($result);

        if ($result && count($result)) {
            $count = 0;
            $count_2 = 0;
            $provinceModel = \App::make('App\Models\Province');
            $districtModel = \App::make('App\Models\District');
            foreach ($result as $key => $item) {
                $next = 0;
                if ($item['parent'] == null) {
                    $name = trim($item['name']);
                    if ($item['id'] == 2) {
                        $name = 'Hồ Chí Minh';
                    }
                    if ($item['id'] == 13) {
                        $name = 'Bình Ðịnh';
                    }
                    if ($item['id'] == 16) {
                        $name = 'Bình Thuận';
                    }
                    if ($item['id'] == 36) {
                        $name = 'Lâm Ðồng';
                    }
                    if ($item['id'] == 40) {
                        $name = 'Nam Ðịnh';
                    }
                    if ($item['id'] == 56) {
                        $name = 'Thừa Thiên - Huế';
                    }
                    $province = $provinceModel->where('name', $name)->first();
                    if ($province) {
                        $count += 1;
                        $province->thuongdo_id = (int)$item['id'];
                        $province->save();

                    } else {
                        dd($item);
                    }
                }
                if ($item['parent'] != null) {
                    $name = trim($item['name']);
                    if ($item['id'] == 96) {
                        $name = 'THÀNH PHỐ THỦ ĐỨC';
                    }
                    if ($item['id'] == 103) {
                        $next = 1;
                    }
                    if ($item['id'] == 145) {
                        $name = 'QUẬN SƠN TRÀ';
                    }
                    if ($item['id'] == 188) {
                        $name = 'THỊ XÃ GIÁ RAI';
                    }
                    if ($item['id'] == 195) {
                        $name = 'THỊ XÃ TỪ SƠN';
                    }
                    if ($item['id'] == 211) {
                        $next = 1;
                    }
                    if ($item['id'] == 223) {
                        $name = 'THÀNH PHỐ THUẬN AN';
                    }
                    if ($item['id'] == 224) {
                        $next = 1;
                    }
                    if ($item['id'] == 231) {
                        $name = 'THÀNH PHỐ ĐỒNG XOÀI';
                    }
                    if ($item['id'] == 251) {
                        $next = 1;
                    }
                    if ($item['id'] == 261) {
                        $name = 'THỊ XÃ CAO BẰNG';
                    }
                    if ($item['id'] == 273) {
                        $name = 'HUYỆN TRÙNG KHÁNH';
                    }
                    if ($item['id'] == 275) {
                        $name = 'THỊ XÃ BUÔN HỒ';
                    }
                    if ($item['id'] == 281) {
                        $next = 1;
                    }
                    if ($item['id'] == 282) {
                        $name = "HUYỆN M'ĐRẮK";
                    }
                    if ($item['id'] == 289) {
                        $name = 'THÀNH PHỐ GIA NGHĨA';
                    }
                    if ($item['id'] == 293) {
                        $name = 'HUYỆN ĐẮK RLẤP';
                    }
                    if ($item['id'] == 297) {
                        $name = 'THÀNH PHỐ ĐIỆN BIÊN PHỦ';
                    }
                    if ($item['id'] == 298) {
                        $name = 'THỊ XÃ MƯỜNG LAY';
                    }
                    if ($item['id'] == 308) {
                        $name = 'THÀNH PHỐ LONG KHÁNH';
                    }
                    if ($item['id'] == 342) {
                        $name = 'HUYỆN KBANG';
                    }
                    if ($item['id'] == 358) {
                        $name = 'THỊ XÃ DUY TIÊN';
                    }
                    if ($item['id'] == 364) {
                        $name = 'THỊ XÃ HỒNG LĨNH';
                    }
                    if ($item['id'] == 377) {
                        $name = 'THÀNH PHỐ CHÍ LINH';
                    }
                    if ($item['id'] == 379) {
                        $name = 'THỊ XÃ KINH MÔN';
                    }
                    if ($item['id'] == 390) {
                        $name = 'THÀNH PHỐ NGÃ BẢY';
                    }
                    if ($item['id'] == 411) {
                        $name = 'THỊ XÃ MỸ HÀO';
                    }
                    if ($item['id'] == 426) {
                        $name = 'THÀNH PHỐ HÀ TIÊN';
                    }
                    if ($item['id'] == 433) {
                        $name = 'HUYỆN PHÚ QUỐC';
                    }
                    if ($item['id'] == 452) {
                        $name = 'THÀNH PHỐ BẢO LỘC';
                    }
                    if ($item['id'] == 491) {
                        $name = 'THỊ XÃ SA PA';
                    }
                    if ($item['id'] == 492) {
                        $name = 'HUYỆN SI MA CAI';
                    }
                    if ($item['id'] == 541) {
                        $name = 'THÀNH PHỐ TAM ĐIỆP';
                    }
                    if ($item['id'] == 548) {
                        $name = 'THÀNH PHỐ PHAN RANG - THÁP CHÀM';
                    }
                    if ($item['id'] == 568) {
                        $name = 'THÀNH PHỐ ĐỒNG HỚI';
                    }
                    if ($item['id'] == 578) {
                        $name = 'THỊ XÃ ĐIỆN BÀN';
                    }
                    if ($item['id'] == 597) {
                        $name = 'THỊ XÃ ĐỨC PHỔ';
                    }
                    if ($item['id'] == 600) {
                        $name = 'HUYỆN LÝ SƠN';
                    }
                    if ($item['id'] == 613) {
                        $name = 'THỊ XÃ ĐÔNG TRIỀU';
                    }
                    if ($item['id'] == 614) {
                        $name = 'HUYỆN VÂN ĐỒN';
                    }
                    if ($item['id'] == 617) {
                        $name = 'HUYỆN CÔ TÔ';
                    }
                    if ($item['id'] == 660) {
                        $name = 'THỊ XÃ HÒA THÀNH';
                    }
                    if ($item['id'] == 663) {
                        $name = 'THỊ XÃ TRẢNG BÀNG';
                    }
                    if ($item['id'] == 673) {
                        $name = 'THÀNH PHỐ SÔNG CÔNG';
                    }
                    if ($item['id'] == 674) {
                        $name = 'THỊ XÃ PHỔ YÊN';
                    }
                    if ($item['id'] == 675) {
                        $name = ' HUYỆN ĐẠI TỪ';
                    }
                    if ($item['id'] == 683) {
                        $name = 'THÀNH PHỐ SẦM SƠN';
                    }
                    if ($item['id'] == 704) {
                        $next = 1; // Huyện Tĩnh Gia
                    }
                    if ($item['id'] == 715) {
                        $name = 'THỊ XÃ HƯƠNG THỦY';
                    }
                    if ($item['id'] == 716) {
                        $name = 'THỊ XÃ HƯƠNG TRÀ';
                    }
                    if ($item['id'] == 745) {
                        $name = 'THÀNH PHỐ PHÚC YÊN';
                    }
                    if ($item['id'] == 790) {
                        $name = 'HUYỆN KIÊN HẢI';
                    }
                    if ($item['id'] == 793) {
                        $next = 1; // Huyện Triệu Hóa
                    }
                    $district = $districtModel->where('name', $name)->first();
                    if ($district || $next == 1) {
                        $count_2 += 1;
                        if ($district) {
                            $district->thuongdo_id = (int)$item['id'];
                            $district->save();
                        }
                    } else {
                        dd($item);
                    }
                }
            }

            \Log::info('Đồng bộ được ' . $count . ' tỉnh thành');
            \Log::info('Đồng bộ được ' . $count_2 . ' tỉnh thành');
        }
    }

    public function syncWarehouseLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/warehouse';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1200,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        // dd($result);

        if ($result && count($result)) {
            $provinceModel = \App::make('App\Models\Province');
            $districtModel = \App::make('App\Models\District');
            $organizationModel = \App::make('App\Models\Organization');
            $warehouseModel = \App::make('App\Models\Warehouse');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();


            foreach ($result as $key => $item) {
                // dd($item);
                if ($item['city_id']) {
                    $data_warehouse = [
                        'name'            => $item['name'],
                        'code'            => $item['name_other'],
                        'address'         => $item['address'],
                        'address_other'   => $item['address_other'],
                        'country'         => 'vi',
                        'organization_id' => $organizationTD->id,
                        'thuongdo_id'     => (int)$item['id'],
                    ];
                    $warehouseDB = $warehouseModel->withTrashed()->where('thuongdo_id', $data_warehouse['thuongdo_id'])->first();
                    if (is_null($warehouseDB)) {
                        // Thêm phần tỉnh thành quận huyện
                        $provinceDB = $provinceModel->where('thuongdo_id', $item['city_id'])->first();
                        if (!is_null($provinceDB)) {
                            $data_warehouse['province_id'] = $provinceDB->id;
                        }
                        $districtDB = $districtModel->where('thuongdo_id', $item['district_id'])->first();
                        if (!is_null($districtDB)) {
                            $data_warehouse['district_id'] = $districtDB->id;
                        }

                        $warehouseNew = $warehouseModel->create($data_warehouse);
                    } else {
                        $warehouseDB->address_other = $item['address_other'];
                        $warehouseDB->save();
                    }
                }
            }
        }
    }

    public function syncDepartmentLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/department';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        // dd($result);

        if ($result && count($result)) {
            $count = 0;
            $departmentModel = \App::make('App\Models\Department');
            $organizationModel = \App::make('App\Models\Organization');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            foreach ($result as $key => $item) {
                $data_department = [
                    'name'            => $item['name'],
                    'organization_id' => (int)$organizationTD->id,
                    'thuongdo_id'     => (int)$item['id'],
                ];

                $departmentDB = $departmentModel->where('thuongdo_id', $data_department['thuongdo_id'])->first();
                if (is_null($departmentDB)) {
                    if ($data_department['thuongdo_id'] != 8) {
                        $departmentNew = $departmentModel->create($data_department);
                        $count += 1;
                    }
                }
            }

            \Log::info('Đồng bộ được ' . $count . ' phòng ban');
        }
    }

    public function syncEmployeeLive(Request $request)
    {
        // $userModel = \App::make('App\Models\User');
        // $cusAll = $userModel->where('thuongdo_id', '>', 0)->delete();
        // dd('1321');

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/employee';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        // dd($result);

        // phongban_id, chi_nhanh_id, group_id, tinhtrang_lamviec

        // tinhtrang_lamviec: options
        // 1: Đang thử việc
        // 2: Đang làm việc
        // 3: Đã nghỉ việc

        if ($result && count($result)) {
            $count = 0;
            $userModel = \App::make('App\Models\User');
            $organizationModel = \App::make('App\Models\Organization');
            $departmentModel = \App::make('App\Models\Department');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            $passDefault = \Hash::make('hangve2024');

            // Pass cũ là: 123456a

            foreach ($result as $key => $item) {
                $data_user = [
                    'name'            => $item['fullname'],
                    'email'           => $item['email'],
                    'username'        => $item['username'],
                    'phone_number'    => $item['phone'] == null ? NULL : $item['phone'],
                    'organization_id' => $organizationTD->id,
                    'thuongdo_id'     => (int)$item['id'],
                    'thuongdo_uuid'   => (int)$item['uid'],
                    'status'          => (int)$item['status'],
                    'password'        => $passDefault,
                    // 'code'            => RandomHelper::userCode(),
                ];

                $userDB = $userModel->where('username', $data_user['username'])->first();
                if (is_null($userDB)) {
                    $departmentDB = $departmentModel->where('thuongdo_id', $item['phongban_id'])->first();
                    if (!is_null($departmentDB)) {
                        $data_user['department_id'] = $departmentDB->id;
                    }
                    $userNew = $userModel->create($data_user);
                    // (new ReportService())->incrementQuantity(ReportConstant::QUANTITY_USER);
                    $count += 1;
                }
            }

            dd($count);
        }
    }

    public function syncCustomerLive(Request $request)
    {
        // 03-01-24: 1900 (Chưa chạy lại)
        // 16-02-2024: dừng ở 2089

        // 18-03-2024: Chạy lại ở con V2: 2111

        // 20-04-2024: Chạy lại ở con V2: 2128

        // 08-06-2024: Chạy lại ở con V2: 2148

        // 29-06-2024: Chạy lại ở con V2: 2155

        //18-06-2024: Chạy lại ở con V2: 2164

        //26-06-2024: Chạy lại ở con V2: 2167

        //30-07-2024: Chạy lại ở con V2: 2169

        //01-08-2024: Chạy lại ở con V2: 2170

        //12-09-2024: Chạy lại ở con V2:

        // $job = new \App\Jobs\SyncTD\SyncCustomer(1);
        // dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');

        // return;


        // for ($i=0; $i < 2250; $i++) {
        //     $job = new \App\Jobs\SyncTD\SyncCustomer($i);
        //     dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        // }
        // return;

        // $customerModel = \App::make('App\Models\Customer');
        // $cusAll = $customerModel->where('thuongdo_id', '>', 0)->delete();
        // dd('1321');


        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/customer';

        $pageCron = (int)$request->page;

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'phone',
                    'contents' => '096564140',
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $count = 0;
            $kCodeLast = 0;
            $customerModel = \App::make('App\Models\Customer');
            $userModel = \App::make('App\Models\User');
            $provinceModel = \App::make('App\Models\Province');
            $districtModel = \App::make('App\Models\District');
            $warehouseModel = \App::make('App\Models\Warehouse');
            $roleModel = \App::make('App\Models\Role');
            $organizationModel = \App::make('App\Models\Organization');

            // Số dư đầu kì
            $reportCustomerModel = \App::make('App\Models\ReportCustomer');
            $transactionModel = \App::make('App\Models\Transaction');
            $customerOfferModel = \App::make('App\Models\CustomerOffer');

            // Ghi chú
            $customerNoteModel = \App::make('App\Models\CustomerNote');

            // Ngân hàng
            $customerBankModel = \App::make('App\Models\CustomerBank');
            $bankModel = \App::make('App\Models\Bank');

            // Rút tiền
            $customerWithdrawalModel = \App::make('App\Models\CustomerWithdrawal');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            $passDefault = \Hash::make('hangve123456');

            foreach ($result as $key => $item) {

                // if ($item['kcode'] != '069175') {
                //     continue;
                // } else {
                //     dd($item);
                // }

                if ($item['username'] == '') {
                    \Log::info('Check lai de');
                    continue;
                }

                // loai_vc, kenh_id, loai_hinh_id, type_customer, type, tu_van_count_id, loai_khach_hang
                $gioi_tinh = '';
                if ($item['gioi_tinh'] == 1) {
                    $gioi_tinh = 'male';
                }
                if ($item['gioi_tinh'] == 2) {
                    $gioi_tinh = 'female';
                }


                $bod = null;
                if ($item['ngay_sinh'] != '' && $item['ngay_sinh'] > 0) {
                    $bod = date("Y-m-d", $item['ngay_sinh']);
                }

                $name = $item['fullname'];
                if ($item['fullname'] == '') {
                    $name = $item['username'];
                }

                $phone = $item['phone'];
                if ($phone == null) {
                    // $phone = 11111;
                }

                $level = $item['cap_do'];
                if ($level == null) {
                    $level = 0;
                }

                // Loại VC:
                // 1 => Đặt hàng
                // 7 => Ký gửi

                $service = 99; // Trong HV 1 là kí gửi, 0 là đặt hàng
                if ($item['loai_vc'] == 1) {
                    $service = 0;
                }
                if ($item['loai_vc'] == 7) {
                    $service = 1;
                }


                // kenh_id:
                // 1 => Google ads
                // 2 => Zalo ads
                // 3 => Facebook ads
                // 4 => OGS
                // 5 => OGS Google
                // 6 => Facebook Form
                // 7 => Google GDN
                // 8 => Email marketing
                // 9 => Tu tim kiem
                // 10=> Thong Tien
                // 11 => Tiktok

                $arr_kenh = [
                    1  => 'Google ads',
                    2  => 'Zalo ads',
                    3  => 'Facebook ads',
                    4  => 'OGS',
                    5  => 'OGS Google',
                    6  => 'Facebook Form',
                    7  => 'Google GDN',
                    8  => 'Email marketing',
                    9  => 'Tu tim kiem',
                    10 => 'Thong Tien',
                    11 => 'Tiktok',
                ];
                $via = null;

                if ($item['kenh_id'] != '') {
                    $arr_temp = [
                        1,2,3,4,5,6,7,8,9,10,11
                    ];
                    if (in_array($item['kenh_id'], $arr_temp)) {
                        $via = $arr_kenh[$item['kenh_id']];
                    }
                }


                // Loai_hinh_id:
                // 1 => Tiêu dùng
                // 2 => Kinh doanh

                $business_type = null;

                if ($item['loai_hinh_id'] == 1) {
                    $business_type = 'personal';
                }
                if ($item['loai_hinh_id'] == 2) {
                    $business_type = 'business';
                }

                // Loại khách hàng :===> bỏ


                // Chia nhân viên:

                // nhan_vien_kinh_doanh_id, nhan_vien_dat_hang_id, nhan_vien_tu_van_id
                // if ($item['nhan_vien_dat_hang_id']) {
                //     dump($item['nhan_vien_dat_hang_id']);
                // }

                $status = $item['status'];

                $data_customer = [
                    'code'            => $item['kcode'],
                    'name'            => $name,
                    'email'           => $item['mail'],
                    'username'        => $item['username'],
                    'password'        => $passDefault,
                    'phone_number'    => $phone,
                    'address'         => $item['address'],
                    'bod'             => $bod,
                    'gender'          => $gioi_tinh,
                    'facebook_link'   => $item['facebook'],
                    'level'           => $level,
                    'service'         => $service,
                    'via'             => $via,
                    'status'          => $status,
                    'last_order_at'   => date('Y-m-d H:i:s', $item['pay_time_last']),
                    'created_at'      => date('Y-m-d H:i:s', $item['created']),
                    'business_type'   => $business_type,
                    'organization_id' => $organizationTD->id,
                    'thuongdo_id'     => (int)$item['id'],
                    'thuongdo_uuid'   => (int)$item['uid'],
                    'thuongdo_page'   => $pageCron,
                ];

                if ($item['kcode'] == null || $item['kcode'] == '') {
                    \Log::info('Phải dùng ID nè creater');
                    $data_customer['code'] = $item['id'];
                }

                // Thêm số tiền đã đặt hàng
                $data_customer['init_order_complete'] = $item['total_order_complete'];

                $customerDB = $customerModel->where('username', $data_customer['username'])->first();
                if (is_null($customerDB)) {
                    // Gán lại pass
                    $data_customer['password'] = $phone == null ? $passDefault : \Hash::make($phone);
                    // Gán lại Kcode:
                    $kCodeLast = $item['kcode'];
                    // Thêm phần tỉnh thành quận huyện
                    $provinceDB = $provinceModel->where('thuongdo_id', $item['tinh_id'])->first();
                    if (!is_null($provinceDB)) {
                        $data_customer['province_id'] = $provinceDB->id;
                    }
                    $districtDB = $districtModel->where('thuongdo_id', $item['quan_id'])->first();
                    if (!is_null($districtDB)) {
                        $data_customer['district_id'] = $districtDB->id;
                    }

                    // Thêm phần kho nhận hàng
                    $warehouseDB = $warehouseModel->where('thuongdo_id', $item['chi_nhanh_id'])->first();
                    if (!is_null($warehouseDB)) {
                        $data_customer['warehouse_id'] = $warehouseDB->id;
                    }

                    // Thêm phần chia nhân vien
                    $staffCareDB = $userModel->where('thuongdo_uuid', $item['nhanvien_cskh'])->first();
                    if (!is_null($staffCareDB)) {
                        $data_customer['staff_care_id'] = $staffCareDB->id;
                        $data_customer['staff_group_id'] = $staffCareDB->id;
                    }
                    $staffCounselorDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_tu_van_id'])->first();
                    if (!is_null($staffCounselorDB)) {
                        $data_customer['staff_counselor_id'] = $staffCounselorDB->id;
                        $data_customer['staff_group_id'] = $staffCounselorDB->id;
                    }
                    $staffOrderDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_dat_hang_id'])->first();
                    if (!is_null($staffOrderDB)) {
                        $data_customer['staff_order_id'] = $staffOrderDB->id;
                    }
                    $staffSaleDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_kinh_doanh_id'])->first();
                    if (!is_null($staffSaleDB)) {
                        $data_customer['staff_sale_id'] = $staffSaleDB->id;
                    }
                    // dd($data_customer);

                    // $customerDB = $customerModel->create($data_customer);
                    $customerDB = Customer::withoutEvents(function () use ($customerModel, $data_customer) {
                        $customerDB = $customerModel->create($data_customer);
                        return $customerDB;
                    });
                    $count += 1;
                } else {
                    // Gán lại Kcode nếu null
                    if ($customerDB->code == null || $customerDB->code == '') {
                        \Log::info('Khách null mã');
                        \Log::info($item['kcode']);
                        $customerDB->code = $item['kcode'];

                        if ($item['kcode'] == null || $item['kcode'] == '') {
                            \Log::info('Phải dùng ID nè');
                            $customerDB->code = $item['id'];
                        }
                    }

                    // Thêm phần ngân hàng của khách hàng
                    foreach ($item['banks'] as $key => $bank) {
                        $bank_id = 0;
                        $bankName = $bank['bank_name'];

                        // Map
                        $arr_vcb = [
                            'Vietcombank'
                        ];
                        if (in_array($bankName, $arr_vcb)) {
                            $bankName = 'VCB';
                        }


                        $bankDB = $bankModel->where('short_name', $bankName)->first();
                        if (!is_null($bankDB)) {
                            $bank_id = (int) $bankDB->id;
                        }
                        $data_customer_bank = [
                            'customer_id'    => (int) $customerDB->id,
                            'bank_id'        => $bank_id,
                            'bank_name'      => $bank['bank_name'],
                            'account_holder' => $bank['bank_fullname'],
                            'account_number' => $bank['bank_account'],
                            'branch'         => $bank['bank_branch'],
                            'thuongdo_id'    => (int) $bank['id'],
                        ];
                        $customerBankDB = $customerBankModel->where('thuongdo_id', $bank['id'])->first();
                        if (is_null($customerBankDB)) {
                            $customerBankDB = $customerBankModel->create($data_customer_bank);
                        }
                    }

                    // Rút tiền
                    foreach ($item['withdrawns'] as $key => $with) {
                        // Chờ xử lý 0, Hoàn thành 1
                        $status = CustomerConstant::KEY_WITHDRAWAL_STATUS_PENDING;
                        if ($with['status'] == '1') {
                            $status = CustomerConstant::KEY_WITHDRAWAL_STATUS_DONE;
                        }
                        $customerBankDB = $customerBankModel->where('thuongdo_id', $with['thongtin_rt_id'])->first();
                        $data_customer_with = [
                            'organization_id'  => 1,
                            'customer_id'      => (int) $customerDB->id,
                            'code'             => $with['rid'],

                            'customer_bank_id' => $customerBankDB ? (int) $customerBankDB->id : 0,
                            'bank_id'          => $customerBankDB ? $customerBankDB->bank_id : 0,
                            'bank_name'        => $customerBankDB ? $customerBankDB->bank_name : "",
                            'account_holder'   => $customerBankDB ? $customerBankDB->account_holder : "",
                            'account_number'   => $customerBankDB ? $customerBankDB->account_number : "",
                            'bank'             => $customerBankDB ? $customerBankDB->bank_name : "",
                            'branch'           => $customerBankDB ? $customerBankDB->branch : "",

                            'amount'           => $with['total'],
                            'status'           => $status,
                            'created_at'       => date('Y-m-d H:i:s', $with['created']),

                            'thuongdo_id'      => (int) $with['rid'],
                        ];
                        $customerWithDB = $customerWithdrawalModel->where('thuongdo_id', $with['rid'])->first();
                        if (is_null($customerWithDB)) {
                            $customerWithDB = $customerWithdrawalModel->create($data_customer_with);
                        } else {
                            $customerWithDB->status = $status;
                            $customerWithDB->save();
                        }
                    }


                    // Thêm ghi chú
                    foreach ($item['notes'] as $key => $note) {
                        $customerNoteDB = $customerNoteModel->where('thuongdo_id', $note['id'])->first();
                        if (is_null($customerNoteDB)) {
                            $user_id = 0;
                            $userDB = $userModel->where('thuongdo_uuid', $note['uid_created'])->first();
                            if (!is_null($userDB)) {
                                $user_id = (int) $userDB->id;
                            }
                            $data_customer_note = [
                                'customer_id' => (int) $customerDB->id,
                                'user_id'     => $user_id,
                                'content'     => $note['note'],
                                'thuongdo_id' => $note['id']
                            ];
                            $customerNoteDB = $customerNoteModel->create($data_customer_note);
                        }
                    }

                    // Cập nhật số tiền đã đặt hàng
                    if ($customerDB->init_order_complete != $item['total_order_complete']) {
                        $customerDB->init_order_complete = $item['total_order_complete'];
                    }

                    // Cập nhật cả cấp độ lại
                    if ($customerDB->level != $level) {
                        $customerDB->level = $level;
                    }

                    // Gán lại status
                    $customerDB->status = $item['status'];

                    // Thời gian đặt hàng cuối
                    $customerDB->last_order_at = date('Y-m-d H:i:s', $item['pay_time_last']);

                    // Cập nhật thời gian tạo tài khoản
                    $customerDB->created_at = date('Y-m-d H:i:s', $item['created']);

                    // Thêm phần chia nhân vien
                    $staffCounselorDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_tu_van_id'])->first();
                    if (!is_null($staffCounselorDB)) {
                        $customerDB->staff_group_id = $staffCounselorDB->id;
                    }
                    $staffCareDB = $userModel->where('thuongdo_uuid', $item['nhanvien_cskh'])->first();
                    if (!is_null($staffCareDB)) {
                        $customerDB->staff_group_id = $staffCareDB->id;
                    }
                    $customerDB->thuongdo_page = $pageCron;
                    // $customerDB->password = \Hash::make($customerDB->phone_number);
                    // $customerDB->save();
                    $customerDB = Customer::withoutEvents(function () use ($customerDB) {
                        $customerDB->save();
                        return $customerDB;
                    });
                }

                // Tạo số dư đầu kì
                if (!is_null($customerDB)) {
                    $transactionDB = null;
                    if ($item['so_du'] != 0) {
                        // Kiểm tra xem có giao dịch số dư đầu kì chưa
                        $transactionDB = $transactionModel->where('customer_id', $customerDB->id)
                                                          ->where('content', 'Khởi tạo số dư đầu kì v88.15.02.2024')
                                                          ->first();
                        if (is_null($transactionDB)) {
                            $data_transaction = [
                                'customer_id'     => $customerDB->id,
                                'organization_id' => $organizationTD->id,
                                'amount'          => $item['so_du'],
                                'balance'         => $item['so_du'],
                                'status'          => TransactionConstant::STATUS_DEPOSIT,
                                'time'            => now(),
                                'content'         => 'Khởi tạo số dư đầu kì v88.15.02.2024',
                                'thuongdo_id'     => (int)$item['id'],

                            ];
                            // $transactionDB = $transactionModel->create($data_transaction);
                            $transactionDB = Transaction::withoutEvents(function () use ($transactionModel, $data_transaction) {
                                $transactionDB = $transactionModel->create($data_transaction);
                                return $transactionDB;
                            });
                        } else {
                            if ($transactionDB->amount != $item['so_du']) {
                                $transactionDB->amount  = $item['so_du'];
                                $transactionDB->balance = $item['so_du'];
                                $transactionDB->save();
                            }
                        }
                    }

                    $reportCustomerDB = $reportCustomerModel->where('customer_id', $customerDB->id)->first();
                    if (is_null($reportCustomerDB)) {
                        $data_report_customer = [
                            'customer_id' => $customerDB->id,
                            'thuongdo_id' => (int)$item['id'],
                            'init_total_deposit' => $item['total_deposit'],
                        ];

                        if (!is_null($transactionDB)) {
                            $data_report_customer['balance_amount'] = $item['so_du'];
                        }
                        $reportCustomerModel->create($data_report_customer);
                    } else {
                        // Nếu có số dư update thì cập nhật
                        if (!is_null($transactionDB)) {
                            if ($reportCustomerDB->balance_amount != $item['so_du']) {
                                $reportCustomerDB->balance_amount = $item['so_du'];
                                $reportCustomerDB->save();
                            }
                        }

                        // Cập nhật tổng tiền nạp
                        if ($reportCustomerDB->init_total_deposit != $item['total_deposit']) {
                            $reportCustomerDB->init_total_deposit = $item['total_deposit'];
                            $reportCustomerDB->save();
                        }
                    }

                    // Xử lý phần ưu đãi deposit_rate, phí hcm, hn
                    $data_customer_offer = [
                        'customer_id'          => $customerDB->id,
                        'thuongdo_id'          => (int)$item['id'],
                        'offer_rate'           => $item['dat_hang'] == null ? 0 : $item['dat_hang'],
                        'deposit_rate'         => $item['dat_coc'] == null ? 0 : $item['dat_coc'],
                        'trans_weight_hn_fee'  => $item['phi_van_chuyen'] == null ? 0 : $item['phi_van_chuyen'],
                        'trans_volumn_hn_fee'  => $item['phi_van_chuyen_m3'] == null ? 0 : $item['phi_van_chuyen_m3'],
                        'trans_weight_hcm_fee' => $item['hcm_phi_van_chuyen_kg'] == null ? 0 : $item['hcm_phi_van_chuyen_kg'],
                        'trans_volumn_hcm_fee' => $item['hcm_phi_van_chuyen_m3'] == null ? 0 : $item['hcm_phi_van_chuyen_m3'],
                        'trans_weight_hp_fee'  => $item['hp_phi_van_chuyen_kg'] == null ? 0 : $item['hp_phi_van_chuyen_kg'],
                        'trans_volumn_hp_fee'  => $item['hp_phi_van_chuyen_m3'] == null ? 0 : $item['hp_phi_van_chuyen_m3'],
                    ];

                    if (
                        $data_customer_offer['offer_rate'] > 0 ||
                        $data_customer_offer['deposit_rate'] > 0 ||
                        $data_customer_offer['trans_weight_hn_fee'] > 0 ||
                        $data_customer_offer['trans_volumn_hn_fee'] > 0 ||
                        $data_customer_offer['trans_weight_hcm_fee'] > 0 ||
                        $data_customer_offer['trans_volumn_hcm_fee'] > 0 ||
                        $data_customer_offer['trans_weight_hp_fee'] > 0 ||
                        $data_customer_offer['trans_volumn_hp_fee'] > 0
                    ) {
                        $customerOfferDB = $customerOfferModel->where('customer_id', $customerDB->id)->first();
                        if (is_null($customerOfferDB)) {
                            $customerOfferDB = $customerOfferModel->create($data_customer_offer);
                        } else {
                            unset($data_customer_offer['customer_id']);
                            unset($data_customer_offer['thuongdo_id']);
                            // $customerOfferDB->update($data_customer_offer);
                            $customerOfferDB = CustomerOffer::withoutEvents(function () use ($customerOfferDB, $data_customer_offer) {
                                $customerOfferDB->update($data_customer_offer);
                                return $customerOfferDB;
                            });
                        }
                    }
                }
            }


            // Set lại sổ tổng người
            // if ($kCodeLast) {
            //     $reportQuantityModel = \App::make('App\Models\ReportQuantity');
            //     $reportQuantityUser = $reportQuantityModel->where('key', 'customer')->first();

            //     if ($reportQuantityUser) {
            //         if ($kCodeLast > $reportQuantityUser->quantity) {
            //             $reportQuantityUser->quantity = $kCodeLast;
            //             $reportQuantityUser->save();
            //         }
            //     }
            // }
        }
    }

    public function syncCustomerDeliveryLive(Request $request)
    {
        // 15-02-2024: 1212

        // 19-03-2024: 1224

        // 20-04-2024: 1233

        // 08-06-2024: 1245

        // 29-06-2024: 1249

        // 18-07-2024: 1255

        // 26-07-2024: 1258

        // 30-07-2024: 1258

        // 01-08-2024: 1258

        // 12-09-2024:

        // $job = new \App\Jobs\SyncTD\SyncCustomerDelivery(1);
        // dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');

        // return;

        for ($i=0; $i < 1350; $i++) {
            $job = new \App\Jobs\SyncTD\SyncCustomerDelivery($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        }
        return;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/user-delivery-address';

        $pageCron = 0;

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $customerModel = \App::make('App\Models\Customer');
            $customerDeliveryModel = \App::make('App\Models\CustomerDelivery');
            $provinceModel = \App::make('App\Models\Province');
            $districtModel = \App::make('App\Models\District');

            foreach ($result as $key => $item) {

                $default_check = $item['default_check'];
                if ($item['default_check'] == '') {
                    $default_check = 0;
                }


                if ($item['fullname'] == '') {
                    // continue;
                }

                $address = $item['data'];
                if ($item['data'] == '') {
                    $address = NULL;
                }

                $phone_number = $item['phone'];
                if ($item['phone'] == '') {
                    $phone_number = 0;
                }

                // dd($item);
                $data_customer_delivey = [
                    'receiver'     => $item['fullname'],
                    'address'      => $address,
                    'phone_number' => $phone_number,
                    'is_default'   => $default_check,
                    'thuongdo_id'  => $item['did'],
                    'thuongdo_page'  => $pageCron,
                    'status'       => 1,
                ];

                $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $data_customer_delivey['thuongdo_id'])->first();

                if (is_null($customerDeliveryDB)) {
                    // Thêm phần tỉnh thành quận huyện
                    $provinceDB = $provinceModel->where('thuongdo_id', $item['tinh_id'])->first();
                    if (!is_null($provinceDB)) {
                        $data_customer_delivey['province_id'] = $provinceDB->id;
                    }
                    $districtDB = $districtModel->where('thuongdo_id', $item['quan_huyen_id'])->first();
                    if (!is_null($districtDB)) {
                        $data_customer_delivey['district_id'] = $districtDB->id;
                    }

                    // Thêm customer_id
                    $customerDB = $customerModel->where('thuongdo_uuid', $item['uid'])->first();
                    if (!is_null($customerDB)) {
                        $data_customer_delivey['customer_id'] = $customerDB->id;
                    } else {
                        \Log::info('Không tim khách');
                    }

                    // Check tìm dc customer_id mới tạo địa chỉ cho khách
                    if (isset($data_customer_delivey['customer_id'])) {
                        $customerDeliverysNew = $customerDeliveryModel->create($data_customer_delivey);
                    }
                } else {
                    $customerDeliveryDB->thuongdo_page = $pageCron;
                    $customerDeliveryDB->save();
                }
            }
        }
    }

    public function syncSupplierLive(Request $request)
    {
        $pageCron = (int)$request->page;
        // History: 3400 - 3718
        // 15-02-2024: 3767

        // Chạy mới lại:
        // 19-03-2024: 3819

        // Chạy mới lại:
        // 20-04-2024: 3864

        // 08-06-2024: 3924

        // 29-06-2024: 3945

        // 18-07-2024: 3967

        // 26-07-2024: 3976

        // 30-07-2024: 3981

        // 01-08-2024: 3985

        // 12-09-2024:

        // $pageCron = 1;
        // $job = new \App\Jobs\SyncTD\SyncSupplier($pageCron);
        // dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');

        // return;

        for ($i=4050; $i < 4250; $i++) {
            $job = new \App\Jobs\SyncTD\SyncSupplier($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        }
        return;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/supplier';

        $pageCron = 1;

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $supplierModel = \App::make('App\Models\Supplier');
            foreach ($result as $key => $item) {
                $code = $item['ncc_code'];
                if (is_null($code)) {
                    $code = 'CC_' . \Str::random(12);
                }

                $name = $item['name'] == null ? 'null2023' : $item['name'];
                $data_supplier = [
                    'thuongdo_id'         => $item['id'],
                    'thuongdo_shop_id'    => $item['shop_id'],
                    'thuongdo_name_en'    => $item['name_en'],
                    'thuongdo_address_en' => $item['address_en'],
                    'thuongdo_page'       => $pageCron,
                    'name'                => $name,
                    'address'             => $item['shop_address'],
                    'website'             => $item['website'],
                    'logo'                => $item['logo'],
                    'code'                => $code,
                    'organization_id'     => 1
                ];

                $supplierDB = $supplierModel->where('thuongdo_id', $data_supplier['thuongdo_id'])->first();
                if (is_null($supplierDB)) {
                    $supplierNew = $supplierModel->create($data_supplier);
                } else {
                    $supplierDB->thuongdo_page = $pageCron;
                    $supplierDB->save();
                }
            }
        }
    }

    public function syncOrderLive(Request $request)
    {
        // $columns = \Schema::getColumnListing('transactions'); // users table
        // $string = "";
        // foreach ($columns as $key => $item) {
        //     $string .= '"' . $item . '", ';
        // }
        // dd($string);
        $pageCron = (int)$request->page;

        // Vòng 1:

        // History: 1
        // History: 94
        // History: 1303
        // History: 2141
        // History: 2149
        // History: 3104
        // History: 3134
        // History: 4331
        // History: 4358
        // History: 4367
        // History: 4461
        // History: 4493
        // History: 4597
        // History: 4623
        // History: 4649
        // History: 4663
        // History: 4696
        // History: 4923


        // Vòng 2:

        $pageCron = 1;
        $pageCron = 5057;
        $pageCron = 5062;
        $pageCron = 5129;
        $pageCron = 5215;
        $pageCron = 5238;
        $pageCron = 5244;
        $pageCron = 5245;
        $pageCron = 5257;
        $pageCron = 5282;
        $pageCron = 5291;
        $pageCron = 5294;
        $pageCron = 5314;
        $pageCron = 5324;
        $pageCron = 5377;
        $pageCron = 5411;
        $pageCron = 6668;
        $pageCron = 6831;
        $pageCron = 6833;
        $pageCron = 6888;
        $pageCron = 6937;
        $pageCron = 6946;
        $pageCron = 8774;

        $pageCron = (int)$request->page;
        $pageCron = 5128;


        // Vòng 2024:
        $pageCron = 1;
        $pageCron = 1000;

        // Vòng 2024-V2: Kết thúc ở: 7375
        $pageCron = 1;
        $pageCron = 4749;
        $pageCron = 5521;
        $pageCron = 5861;

        // Vòng 2024-V2: ngày 20-04-2024 Kết thúc ở 7627
        $pageCron = 1;
        $pageCron = 7386;
        $pageCron = 7396;
        $pageCron = 7411;
        $pageCron = 7425;
        $pageCron = 7454;
        $pageCron = 7616;
        $pageCron = 7627;

        // Vòng 2024-V3: ngày 28-04-2024 Kết thúc ở 7677
        $pageCron = 1;


        // Vòng 2024-V4: ngày 02-05-2024 Kết thúc ở
        $pageCron = 1;
        $pageCron = 7676;


        // Vòng 2024-V4: ngày 08-06-2024 kết thúc ở 8019
        $pageCron = 1;
        $pageCron = 8046;
        $pageCron = 8165;

        // Vòng 2024-V4: ngày 18-07-2024 kết thúc ở 2989
        $pageCron = 1;
        $pageCron = (int)$request->page;

        // 12-09-2024:

        // for ($i=300; $i > 0; $i--) {
        //     $job = new \App\Jobs\SyncTD\SyncOrder($i);
        //     dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        // }
        // $job = new \App\Jobs\SyncTD\SyncOrder(0);
        // dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        // return;

        // for ($i=0; $i < 9000; $i++) {
        //     $job = new \App\Jobs\SyncTD\SyncOrder($i);
        //     dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        // }
        // return;

        // $job = new \App\Jobs\SyncTD\SyncOrder($pageCron);
        // dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');
        // return;

        // $pageCron = 59;
        $pageCron = (int)$request->page;
        $orderCode = $request->order_code;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/orders-fulfillment';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'order_code',
                    'contents' => $orderCode
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        // dd($result);

        $showLog = false;

        if ($result && count($result)) {
            $organizationModel = \App::make('App\Models\Organization');
            $orderModel = \App::make('App\Models\Order');
            $customerModel = \App::make('App\Models\Customer');
            $customerDeliveryModel = \App::make('App\Models\CustomerDelivery');
            $userModel = \App::make('App\Models\User');
            $warehouseModel = \App::make('App\Models\Warehouse');
            $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
            $orderPackageStatusTimeModel = \App::make('App\Models\OrderPackageStatusTime');
            $orderDetailPackageModel = \App::make('App\Models\OrderDetailPackage');
            $orderSupplierModel = \App::make('App\Models\OrderSupplier');
            $orderDetailModel = \App::make('App\Models\OrderDetail');
            $supplierModel = \App::make('App\Models\Supplier');
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $categoryModel = \App::make('App\Models\Category');
            $activitiModel = \App::make('App\Models\Activity');

            $deliveryModel = \App::make('App\Models\Delivery');
            $transporterModel = \App::make('App\Models\Transporter');
            $deliveryOrderModel = \App::make('App\Models\DeliveryOrder');

            $complainTypeModel = \App::make('App\Models\ComplainType');
            $solutionModel = \App::make('App\Models\Solution');
            $complainModel = \App::make('App\Models\Complain');
            $complainDetailModel = \App::make('App\Models\ComplainDetail');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            // Phần khiếu nại
            $complainTypeFullDB = $complainTypeModel->where('thuongdo_id', '>', 0)->get();
            $complainTypeFullDB = \Arr::pluck($complainTypeFullDB, 'id', 'thuongdo_id');

            $solutionFullDB = $solutionModel->where('thuongdo_id', '>', 0)->get();
            $solutionFullDB = \Arr::pluck($solutionFullDB, 'id', 'thuongdo_id');

            // Phần hoàn tiền
            $refundModel = \App::make('App\Models\Refund');

            // Ghi chú cho khách hàng
            $orderDetailNoteModel = \App::make('App\Models\OrderDetailNote');
            $orderNoteModel = \App::make('App\Models\OrderNote');

            // Cập nhật tổng tiền thực tế
            $orderCostRealModel = \App::make('App\Models\OrderCostReal');

            foreach ($result as $key => $item) {
                if ($showLog) {
                    \Log::info('ID:' . $key . ':code:' . $item['orders']['order_code']);
                }
                // dump($item);

                $order = $item['orders'];
                $orders_supplier = $item['orders_supplier'];

                // if (count($orders_supplier) > 1) {
                //     dd($item);
                // }

                if (count($orders_supplier) <= 1) {
                    // continue;
                } else {
                    // dump(count($orders_supplier));
                    // dump($item);
                }

                if ($order['order_code'] != '0728433042') {
                   // continue;
                } else {
                    // dump(1);
                    // dump($item);
                    // dd($item);
                }

                if (count($item['refunds']) == 0) {
                   // continue;
                } else {
                    // dump(1);
                    // dump($item);
                    // dd($item);
                }

                // if (count($item['delivery']) > 1) {
                //     dd($item);
                // }

                $truso = $order['truso_id'];

                if (!in_array($truso, [null,1,2,3,7,10,11])) {
                    \Log::info('truso_id:' . $order['truso_id']);
                }
                if ($order['order_status'] == 3) {
                    // dd($item);
                }

                // Time
                $date_purchased = $order['dat_coc_time'] ? date('Y-m-d H:i:s', $order['dat_coc_time']) : null;
                $date_ordered   = $order['dat_hang_time'] ? date('Y-m-d H:i:s', $order['dat_hang_time']) : null;
                $date_done      = $order['hoan_thanh_time'] ? date('Y-m-d H:i:s', $order['hoan_thanh_time']) : null;

                $pham_tram_dat_coc    = $order['pham_tram_dat_coc'] == null ? 0 : $order['pham_tram_dat_coc'];
                $phan_tram_chiet_khau = $order['phan_tram_chiet_khau'] == null ? 0 : $order['phan_tram_chiet_khau'];
                $phi_chiet_khau       = $order['phi_chiet_khau'] == null ? 0 : $order['phi_chiet_khau'];
                $phi_giaodich         = $order['phi_giaodich'] == null ? 0 : $order['phi_giaodich'];
                $phi_dich_vu          = $order['phi_dich_vu'] == null ? 0 : $order['phi_dich_vu'];
                $total_phi_kiem_hang  = $order['total_phi_kiem_hang'] == null ? 0 : $order['total_phi_kiem_hang'];

                $dong_go   = $order['dong_go'] == null ? 0 : $order['dong_go'];
                $kiem_diem = $order['kiem_diem'] == null ? 0 : $order['kiem_diem'];
                $chong_soc = $order['chong_soc'] == null ? 0 : $order['chong_soc'];

                $order_cost = $order['total_dat_hang'] == null ? 0 : $order['total_dat_hang'];
                $order_total_tam_ungTD = $order['tong_tam_ung'] == null ? 0 : $order['tong_tam_ung'];
                $order_total_don_hangTD = $order['total'] == null ? 0 : $order['total'];

                $data_order = [
                    'thuongdo_page'    => $pageCron,
                    'organization_id'  => $organizationTD->id,
                    // 'thuongdo_id'      => $order['id'],
                    'thuongdo_id'      => null,
                    'thuongdo_id_old'      => $order['id'],
                    'thuongdo_created' => $order['created'],

                    'code'             => $order['order_code'],
                    'code_po'          => null,
                    'code_parent'      => $order['order_code_parent'],
                    'order_cost'       => $order_cost,
                    'exchange_rate'    => $order['ti_gia'],

                    'inspection_cost'  => $total_phi_kiem_hang, // Chuẩn rồi

                    'is_woodworking'   => $dong_go,
                    'is_inspection'    => $kiem_diem,
                    'is_shock_proof'   => $chong_soc,

                    'is_deposit'       => $date_purchased == null ? 0 : 1, //  Đặt cọc
                    'deposit_percent'  => $pham_tram_dat_coc, //  % Đặt cọc
                    'deposit_cost'     => $pham_tram_dat_coc * $order_cost / 100, // Dựa theo công thức

                    'deposit_cost'     => $order_total_tam_ungTD,

                    'discount_percent' => $phan_tram_chiet_khau, // % Chiết Khấu
                    'discount_cost'    => $phi_chiet_khau, // Phí Chiết Khấu

                    'order_fee'        => $phi_giaodich, // Phí Đặt hàng
                    'order_percent'    => $phi_dich_vu, // % đặt hàng

                    'date_purchased'   => $date_purchased, // Thời gian thanh toán (đặt cọc)
                    'date_ordered'     => $date_ordered, // Thời gian đặt hàng
                    'date_done'        => $date_done, // Thời gian hoàn thành

                    'thuongdo_tong_tien_tam_ung' => $order_total_tam_ungTD,
                    'thuongdo_tong_tien_don_hang' => $order_total_don_hangTD,

                    'status'           => $this->getStatusOder($order['order_status']),
                ];

                $arr_website = [
                    null => null,
                    '1'  => 'taobao.com',
                    '2'  => 'tmall.com',
                    '3'  => '1688.com',
                ];

                $data_order['ecommerce'] = $arr_website[$order['website_id']];
                $data_order['is_website'] = $data_order['ecommerce'] == null ? 0 : 1;

                $type_delivery = [
                    null => "no",
                    '1'  => 'normal',
                    '2'  => 'fast',
                ];
                if (count($orders_supplier)) {
                    $data_order['delivery_type'] = $type_delivery[$orders_supplier[key($orders_supplier)]['loai_van_chuyen']];
                } else {
                    if ($showLog) {
                        \Log::info('Không có orders_supplier');
                    }
                }

                $customerDB = $customerModel->where('thuongdo_uuid', $order['uid'])->first();

                if (is_null($customerDB)) {
                    if ($showLog) {
                        \Log::info('Không có khách hàng, bỏ qua');
                        continue;
                    }
                }

                $orderDB = $orderModel->where('thuongdo_id', $data_order['thuongdo_id'])->first();

                // if ($orderDB) {
                //     if ($showLog) {
                //         \Log::info('Đơn này đã đồng bộ, bỏ qua');
                //         continue;
                //     }
                // }

                if (is_null($orderDB)) {
                    // Thêm customer_id
                    // $customerDB = $customerModel->where('thuongdo_uuid', $order['uid'])->first();
                    if (!is_null($customerDB)) {
                        $data_order['customer_id'] = $customerDB->id;
                        $data_order['staff_group_id'] = $customerDB->staff_group_id;
                    }

                    // Thêm phần kho
                    $warehouseDB = $warehouseModel->where('thuongdo_id', $order['truso_id'])->first();
                    if (!is_null($warehouseDB)) {
                        $data_order['warehouse_id'] = $warehouseDB->id;
                    }

                    // Thêm phần địa chỉ nhận hàng
                    $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $order['diachi_id'])->first();
                    if (!is_null($customerDeliveryDB)) {
                        $data_order['customer_delivery_id'] = $customerDeliveryDB->id;
                    }

                    // Thêm các nhân viên tư vân
                    // uid_xu_ly => Hoa bảo là nhân viên đặt hàng
                    // nhan_vien_cs_uid
                    // nhan_vien_kinh_doanh_uid
                    // nhan_vien_tu_van_uid
                    // uid_bao_gia

                    $staffCareDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_cs_uid'])->first();
                    if (!is_null($staffCareDB)) {
                        $data_order['staff_care_id'] = $staffCareDB->id;
                    }

                    $staffCounselorDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_tu_van_uid'])->first();
                    if (!is_null($staffCounselorDB)) {
                        $data_order['staff_counselor_id'] = $staffCounselorDB->id;
                    }

                    $staffQuotationDB = $userModel->where('thuongdo_uuid', $order['uid_bao_gia'])->first();
                    if (!is_null($staffQuotationDB)) {
                        $data_order['staff_quotation_id'] = $staffQuotationDB->id;
                    }

                    $staffOrderDB = $userModel->where('thuongdo_uuid', $order['uid_xu_ly'])->first();
                    if (!is_null($staffOrderDB)) {
                        $data_order['staff_order_id'] = $staffOrderDB->id;
                    }

                    // Phí vận chuyển nội địa china_shipping_cost
                    $china_shipping_cost_sum = 0;

                    foreach ($orders_supplier as $key_11 => $item_11) {
                        $china_shipping_cost_sum += ($item_11['phi_ship'] == null) ? 0 : $item_11['phi_ship'];
                    }

                    $data_order['china_shipping_cost'] = $china_shipping_cost_sum;

                    // Thời gian tạo
                    $data_order['created_at'] =  date('Y-m-d H:i:s', $order['created']);

                    if (isset($data_order['customer_id'])) {
                        // $orderDB = $orderModel->create($data_order);
                        $orderDB = Order::withoutEvents(function () use ($orderModel, $data_order) {
                            $orderDB = $orderModel->create($data_order);
                            return $orderDB;
                        });
                    } else {
                        if ($showLog) {
                            \Log::info('Không tìm được khách hàng: ' . $order['uid'] . ' nên không tạo đơn');
                        }
                    }
                } else {
                    // Thêm phần địa chỉ nhận hàng
                    $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $order['diachi_id'])->first();
                    if (!is_null($customerDeliveryDB)) {
                        if ($orderDB->customer_delivery_id == 0) {
                            $orderDB->customer_delivery_id = $customerDeliveryDB->id;
                        }
                    }

                    // Nếu trạng thái đơn là đã đặt hàng hoặc hoàn thành
                    // thì cập nhật order_cost_real
                    if (
                        $orderDB->status == OrderConstant::KEY_STATUS_ORDERED ||
                        $orderDB->status == OrderConstant::KEY_STATUS_DONE
                    ) {
                        $orderDB->order_cost_real = $orderDB->order_cost;
                    }

                    // Cập nhật lại tổng tiền tạm ứng
                    $orderDB->deposit_cost = $order_total_tam_ungTD;

                    // Cập nhật phí kiểm đếm
                    $orderDB->inspection_cost = $total_phi_kiem_hang;

                    // Cập nhật các cái check
                    $orderDB->is_woodworking = $dong_go;
                    $orderDB->is_inspection  = $kiem_diem;
                    $orderDB->is_shock_proof = $chong_soc;

                    // Cập nhật lại phi_chiet_khau
                    $orderDB->discount_cost = $phi_chiet_khau;

                    // Thêm ai là người quản lý đơn hàng
                    $orderDB->staff_group_id = $customerDB->staff_group_id;

                    $orderDB->created_at = date('Y-m-d H:i:s', $order['created']);
                    $orderDB->thuongdo_tong_tien_tam_ung = $order_total_tam_ungTD;
                    $orderDB->thuongdo_tong_tien_don_hang = $order_total_don_hangTD;
                    $orderDB->order_fee = $phi_giaodich;
                    $orderDB->status = $this->getStatusOder($order['order_status']);
                    // $orderDB->save();
                    $orderDB = Order::withoutEvents(function () use ($orderDB) {
                        $orderDB->save();
                        return $orderDB;
                    });
                }

                // Tạo order_status_time
                if ($orderDB) {
                    $orderStatusTimeDB = $orderStatusTimeModel->where('order_id', $orderDB->id)->first();
                    if (is_null($orderStatusTimeDB)) {
                        $histories = $item['lich_su_don_hang'];

                        $data_order_status_time = [
                            'thuongdo_sync' => 1
                        ];
                        foreach ($histories as $key => $history) {
                            $status = $this->getStatusOder($history['status_id']);
                            $data_order_status_time[$status] = date('Y-m-d H:i:s', $history['created']);
                        }
                        $data_order_status_time['order_id'] = $orderDB->id;

                        $orderStatusTimeDB = $orderStatusTimeModel->create($data_order_status_time);
                    }
                }


                // Tạo order history
                if ($orderDB) {
                    $histories = $item['lich_su_don_hang'];
                    foreach ($histories as $key => $history) {
                        $activitiOrderStatusDB = $activitiModel
                                                    ->where('log_name', 'order_log')
                                                    ->where('subject_type', 'App\Models\Order')
                                                    ->where('subject_id', $orderDB->id)
                                                    ->where('thuongdo_id', $history['id'])
                                                    ->first();

                        if (is_null($activitiOrderStatusDB)) {
                            $status = $this->getStatusOder($history['status_id']);
                            $user_actionDB = $userModel->where('thuongdo_uuid', $history['uid_xu_ly'])->first();
                            $user_is_customer = false;
                            if (is_null($user_actionDB)) {
                                $user_is_customer = true;
                                $user_actionDB = $customerModel->where('thuongdo_uuid', $history['uid_xu_ly'])->first();
                            }
                            if (!is_null($user_actionDB)) {
                                $status_x86TD = $this->getStatusOder($history['status_id']);
                                $data_x86TD = new ReportStatusResource($status_x86TD, OrderConstant::STATUSES, OrderConstant::STATUSES_COLOR);

                                $data_user_activity = [
                                    'log_name'        => 'order_log',
                                    'subject_type'    => 'App\Models\Order',
                                    'subject_id'      => $orderDB->id,
                                    'causer_type'     => $user_is_customer == false ? 'App\Models\User' : 'App\Models\Customer',
                                    'causer_id'       => $user_actionDB->id,
                                    'object_id'       => $orderDB->id,
                                    'content'         => trans("activity.order_$status_x86TD"),
                                    'properties'      => json_encode($data_x86TD),
                                    'organization_id' => $organizationTD->id,
                                    'thuongdo_id'     => $history['id'],
                                    'created_at'      => date('Y-m-d H:i:s', $history['created'])
                                ];

                                $activitiOrderStatusDB = $activitiModel->create($data_user_activity);
                            }
                        }
                    }
                }

                $orderSupplierFirst = null;
                // Tạo order Supplier
                if ($orderDB) {
                    $orders_suppliers = $item['orders_supplier'];
                    $orderSupplierFirst = null;

                    // Chỗ tồng tiền thanh toán thực tế thì lưu thêm vào orderSup vì hệ thống cũ không tách

                    if (count($orders_suppliers)) {

                        $listOrderDetailTemp = []; // Dữ liệu tạm chi tiết đơn
                        $listOrderSupplierTemp = []; // Dữ liệu tạm nhà cung cấp

                        foreach ($orders_suppliers as $key => $orders_supplier) {
                            $supplierDB = $supplierModel->where('thuongdo_id', $orders_supplier['shop_id'])->first();

                            if (is_null($supplierDB)) {
                                if ($showLog) {
                                    \Log::info('Không tìm thấy ShopID: ' . $orders_supplier['shop_id'] . ' nên không tạo Order Supplier');
                                }
                            }

                            if ($supplierDB) {
                                // Lấy ra Supplier theo khách hàng
                                $supplierOfCusDB = $supplierModel->where('thuongdo_id', $orders_supplier['shop_id'])
                                                                ->where('customer_id', $orderDB->customer_id)
                                                                ->first();
                                if (is_null($supplierOfCusDB)) {
                                    $data_supplier = [
                                        'name'                => $supplierDB->name,
                                        'code'                => 'CC_' . \Str::random(12),
                                        'address'             => $supplierDB->address,
                                        'customer_id'         => $orderDB->customer_id,
                                        'organization_id'     => $organizationTD->id,
                                        'website'             => $supplierDB->website,
                                        'logo'                => $supplierDB->logo,
                                        'type'                => $supplierDB->type,
                                        'industry'            => $supplierDB->industry,
                                        'thuongdo_id'         => $supplierDB->thuongdo_id,
                                        'thuongdo_shop_id'    => $supplierDB->thuongdo_shop_id,
                                        'thuongdo_name_en'    => $supplierDB->thuongdo_name_en,
                                        'thuongdo_address_en' => $supplierDB->thuongdo_address_en,
                                    ];
                                    $supplierOfCusDB = $supplierModel->create($data_supplier);
                                }

                                // Tạo Order Supplier
                                $orderSupplierDB = $orderSupplierModel->where('thuongdo_id', $orders_supplier['sid'])->first();

                                // Dữ liệu cần thiết
                                $total_dat_hang_xTD = $orders_supplier['tien_hang_vn'] == null ? 0 : $orders_supplier['tien_hang_vn']; // Tổng tiền hàng

                                $total_phi_van_chuyen_quoc_te_xTD = $orders_supplier['phi_van_chuyen_qt'] == null ? 0 : $orders_supplier['phi_van_chuyen_qt']; // Tổng tiền vận chuyển quốc tế

                                $total_tong_tien_kiem_hang_xTD = $orders_supplier['phi_kiem_dem'] == null ? 0 : $orders_supplier['phi_kiem_dem']; // Tổng tiền kiểm định
                                $total_tong_tien_thanh_toan_xTD = $orders_supplier['tong_tien_thanh_toan'] == null ? 0 : $orders_supplier['tong_tien_thanh_toan']; // Tổng tiền thanh toans

                                if (is_null($orderSupplierDB)) {
                                    $dong_go   = $orders_supplier['dong_go'] == null ? 0 : $orders_supplier['dong_go'];
                                    $kiem_hang = $orders_supplier['kiem_hang'] == null ? 0 : $orders_supplier['kiem_hang'];
                                    $chong_soc = $orders_supplier['chong_soc'] == null ? 0 : $orders_supplier['chong_soc'];

                                    // $orderFeeTmpX1 = $this->getOrderFeeForOrderSup($total_dat_hang_xTD, $orderDB->order_percent);
                                    // $orderDiscountTmpX1 = $this->getOrderDiscountForOrderSup($orderFeeTmpX1, $orderDB->discount_percent);

                                    $orderFeeTmpX1 = $orders_supplier['phi_dat_hang'] == null ? 0 : $orders_supplier['phi_dat_hang'];
                                    $orderDiscountTmpX1 = 0; // Mặc định cho = 0
                                    $orderDiscountTmpX1 = $this->getOrderDiscountForOrderSup($orderFeeTmpX1, $orderDB->discount_percent); // Logic mới

                                    $data_order_supplier = [
                                        'order_id'                    => $orderDB->id,
                                        'supplier_id'                 => $supplierOfCusDB->id,
                                        'order_cost'                  => $total_dat_hang_xTD,
                                        'order_fee'                   => $orderFeeTmpX1,
                                        'discount_cost'               => $orderDiscountTmpX1,
                                        'inspection_cost'             => $total_tong_tien_kiem_hang_xTD,
                                        'international_shipping_cost' => $total_phi_van_chuyen_quoc_te_xTD,

                                        'is_woodworking'              => $dong_go,
                                        'is_inspection'               => $kiem_hang,
                                        'is_shock_proof'              => $chong_soc,

                                        'china_shipping_cost'         => $orders_supplier['phi_ship'] == null ? 0 : $orders_supplier['phi_ship'] ,
                                        'thuongdo_id'                 => $orders_supplier['sid'],
                                        'thuongdo_sync'               => $pageCron,
                                        'thuongdo_tong_tien_thanh_toan'   => $total_tong_tien_thanh_toan_xTD,
                                    ];

                                    $orderSupplierDB = $orderSupplierModel->create($data_order_supplier);
                                } else {
                                    if ($showLog) {
                                        \Log::info('Cập nhật giá cho: ' . $pageCron . ' orderSup: ' . $orders_supplier['sid']);
                                    }
                                    $orderFeeTmpX1 = $orders_supplier['phi_dat_hang'] == null ? 0 : $orders_supplier['phi_dat_hang'];
                                    $orderDiscountTmpX1 = $this->getOrderDiscountForOrderSup($orderFeeTmpX1, $orderDB->discount_percent); // Logic mới

                                    $dong_go   = $orders_supplier['dong_go'] == null ? 0 : $orders_supplier['dong_go'];
                                    $kiem_hang = $orders_supplier['kiem_hang'] == null ? 0 : $orders_supplier['kiem_hang'];
                                    $chong_soc = $orders_supplier['chong_soc'] == null ? 0 : $orders_supplier['chong_soc'];

                                    $orderSupplierDB->is_woodworking = $dong_go;
                                    $orderSupplierDB->is_inspection = $kiem_hang;
                                    $orderSupplierDB->is_shock_proof = $chong_soc;

                                    $orderSupplierDB->china_shipping_cost = $orders_supplier['phi_ship'] == null ? 0 : $orders_supplier['phi_ship'];

                                    // Thêm phần ghi chú cho TD
                                    if ($orders_supplier['ghi_chu_cho_thuong_do'] != null) {
                                        $orderNoteDB = $orderNoteModel->where('thuongdo_id', $orders_supplier['sid'])
                                                                    ->where('subject_type', 'App\Models\Customer')
                                                                    ->first();
                                        if (is_null($orderNoteDB)) {
                                            $data_order_note = [
                                                'order_id'     => (int) $orderDB->id,
                                                'supplier_id'  => (int) $orderSupplierDB->supplier_id,
                                                'content'      => $orders_supplier['ghi_chu_cho_thuong_do'],
                                                'thuongdo_id'  => $orders_supplier['sid'],
                                                'subject_type' => 'App\Models\Customer',
                                                'subject_id'   => (int) $orderDB->customer_id,
                                                'is_public'    => 1
                                            ];
                                            $orderNoteDB = $orderNoteModel->create($data_order_note);
                                        }
                                    }

                                    // Thêm ghi chú nội bộ
                                    if ($orders_supplier['ghi_chu_noi_bo'] != null) {
                                        $orderNoteDB = $orderNoteModel->where('thuongdo_id', $orders_supplier['sid'])
                                                                    ->where('subject_type', 'App\Models\Staff')
                                                                    ->first();
                                        if (is_null($orderNoteDB)) {
                                            $data_order_note = [
                                                'order_id'     => (int) $orderDB->id,
                                                'supplier_id'  => (int) $orderSupplierDB->supplier_id,
                                                'content'      => $orders_supplier['ghi_chu_noi_bo'],
                                                'thuongdo_id'  => $orders_supplier['sid'],
                                                'is_public'    => 0
                                            ];
                                            if ($orderDB->staff_group_id) {
                                                $data_order_note['subject_type'] = 'App\Models\Staff';
                                                $data_order_note['subject_id'] = (int) $orderDB->staff_group_id;
                                            }
                                            $orderNoteDB = $orderNoteModel->create($data_order_note);
                                        }
                                    }

                                    // Thêm ngày đặt hàng
                                    if ($orders_supplier['ngay_dat_hang'] != null) {
                                        $data_dat_hang = date('Y-m-d H:i:s', $orders_supplier['ngay_dat_hang']);
                                        $orderNoteDB = $orderNoteModel->where('is_public', 2)
                                                                    ->where('order_id', (int) $orderDB->id)
                                                                    ->where('supplier_id', (int) $orderSupplierDB->supplier_id)
                                                                    ->first();
                                        if (is_null($orderNoteDB)) {
                                            $data_order_note = [
                                                'order_id'               => (int) $orderDB->id,
                                                'supplier_id'            => (int) $orderSupplierDB->supplier_id,
                                                'content'                => "",
                                                'thuongdo_id'            => $orders_supplier['sid'],
                                                'is_public'              => 2,
                                                'order_provisional_date' => $data_dat_hang
                                            ];
                                            $orderNoteDB = $orderNoteModel->create($data_order_note);
                                        } else {
                                            $orderNoteDB->order_provisional_date = $data_dat_hang;
                                            $orderNoteDB->save();
                                        }
                                    }

                                    // Cập nhật lại giá cho nhà cung cấp
                                    $orderSupplierDB->order_cost                  = $total_dat_hang_xTD;
                                    $orderSupplierDB->order_fee                   = $orderFeeTmpX1;
                                    $orderSupplierDB->discount_cost               = $orderDiscountTmpX1;
                                    $orderSupplierDB->inspection_cost             = $total_tong_tien_kiem_hang_xTD;
                                    $orderSupplierDB->international_shipping_cost = $total_phi_van_chuyen_quoc_te_xTD;
                                    $orderSupplierDB->thuongdo_sync               = $pageCron;
                                    $orderSupplierDB->thuongdo_tong_tien_thanh_toan  = $total_tong_tien_thanh_toan_xTD;
                                    $orderSupplierDB->order_id  = $orderDB->id;
                                    $orderSupplierDB->save();
                                }

                                // Lưu note
                                if ($orderSupplierDB) {
                                    $notes_historyTD = $orders_supplier['note_history'];
                                    if (count($notes_historyTD) > 0) {
                                        $notes_historyTDArr = reset($notes_historyTD);
                                        if (count($notes_historyTDArr) > 0) {
                                            foreach ($notes_historyTDArr as $key_notes_history => $arr_history) {
                                                $this->saveOrderSupplierHistory($key_notes_history, $arr_history, $orderSupplierDB);
                                            }
                                        }
                                    }
                                }

                                if ($orderSupplierFirst == null) {
                                    $orderSupplierFirst = $orderSupplierDB;
                                }

                                // Đẩy mảng tạm nhà cung cấp
                                // array_push($listOrderSupplierTemp, $orderSupplierDB);

                                // Tạo Order Product
                                // Nếu mà trùng shop_id thì là đính sản phẩm theo nhà cũng cấp đó của khách
                                $sanphams = $item['san_pham'];
                                if (count($sanphams)) {
                                    $arrOrderDetailCurrentIds = [];
                                    foreach ($sanphams as $key_sanpham => $sanpham) {
                                        if ($sanpham['shop_id'] == $orders_supplier['shop_id']) {
                                           $orderDetailDB = $orderDetailModel->where('thuongdo_id', $sanpham['pid'])->first();

                                           $quantity = $sanpham['qty'] == null ? 0 : $sanpham['qty'];
                                           $priceS = $sanpham['price'] == null ? 0 : $sanpham['price'];
                                           $nameS = $sanpham['name'] == null ? 'no-name' : $sanpham['name'];

                                           // Page 3104 nó lại trả giá ra 168.00168.00;
                                           if ($priceS == '168.00168.00') {
                                               $priceS = "168";
                                           }
                                           // Page 3104 nó lại trả giá ra 1.09 ;
                                           $priceS = str_replace(" ", "", $priceS);

                                           // Page 4331 nó lại trả giá ra 1700-2125;
                                           if ($priceS == '1700-2125') {
                                               $priceS = "1700";
                                           }

                                           // Page 4358 nó lại trả giá ra 88-145;
                                           if ($priceS == '88-145') {
                                               $priceS = "88";
                                           }

                                           // Page 4367 nó lại trả giá ra 4.8-58.8;
                                           if ($priceS == '4.8-58.8') {
                                               $priceS = "58";
                                           }

                                           // Page 4461 nó lại trả giá ra 133-600;
                                           if ($priceS == '133-600') {
                                               $priceS = "133.6";
                                           }

                                           // Page 4493 nó lại trả giá ra 599-609 ;
                                           $priceS = str_replace("-", ".", $priceS);

                                           // Page 4597 nó lại trả giá ra 13.0013.00;
                                           if ($priceS == '13.0013.00') {
                                               $priceS = "13";
                                           }

                                           // Page 4649 nó lại trả giá ra 5.005.00;
                                           if ($priceS == '5.005.00') {
                                               $priceS = "5";
                                           }

                                           // Page 4696 nó lại trả giá ra 17.5.44;
                                           if ($priceS == '17.5.44') {
                                               $priceS = "17";
                                           }

                                           // Page 5057 nó lại trả giá ra 62.82.98.82;
                                           if ($priceS == '62.82.98.82') {
                                               $priceS = "62";
                                           }

                                           // Page 5062 nó lại trả giá ra 389.00549.00;
                                           if ($priceS == '389.00549.00') {
                                               $priceS = "389";
                                           }

                                           // Page 5129 nó lại trả giá ra 280.00280.00;
                                           if ($priceS == '280.00280.00') {
                                               $priceS = "280";
                                           }

                                           // Page 5215 nó lại trả giá ra 280.00280.00;
                                           if ($priceS == '51.8851.88') {
                                               $priceS = "51";
                                           }

                                           // Page 5238 nó lại trả giá ra 54.8854.88;
                                           if ($priceS == '54.8854.88') {
                                               $priceS = "54";
                                           }

                                           // Page 5282 nó lại trả giá ra 54.8854.88;
                                           if ($priceS == '5.505.50') {
                                               $priceS = "5";
                                           }
                                           if ($priceS == '6.006.00') {
                                               $priceS = "6";
                                           }

                                           // Page 5377 nó lại trả giá ra 55.8855.888;
                                           if ($priceS == '55.8855.88') {
                                               $priceS = "55";
                                           }
                                           if ($priceS == '89.8889.88') {
                                               $priceS = "89";
                                           }

                                           // $priceS = "600000";
                                           // // Xử lý triệt luôn 5411
                                           $arrPriceS = explode(".",$priceS);

                                           if (count($arrPriceS) > 2) {
                                               $priceS = $arrPriceS[0];
                                           }

                                           // Page 8774 nó lại trả giá ra <fontstyle="vertical;
                                           if ($priceS == '<fontstyle="vertical') {
                                               $priceS = "0";
                                           }

                                           // Page 7396 nó lại trả giá ra 7giờ80
                                           if ($priceS == '7giờ80') {
                                               $priceS = "0";
                                           }
                                           // Page 7616 nó lại trả giá ra $1.80
                                           if ($priceS == '$1.80') {
                                               $priceS = "0";
                                           }

                                           // Page 8046 nó lại trả giá ra 18.918:34
                                           if ($priceS == '18.918:34') {
                                               $priceS = "0";
                                           }
                                           if ($priceS == '17.917:37') {
                                               $priceS = "0";
                                           }

                                           // Page 5 nó lại trả giá ra 18.918:34
                                           if ($priceS == '19:98') {
                                               $priceS = "0";
                                           }

                                           // Page 24 nó lại trả giá ra twentyfour
                                           if ($priceS == 'twentyfour') {
                                               $priceS = "0";
                                           }
                                           // Page 28 nó lại trả giá ra 390nhândântệ
                                           if ($priceS == '390nhândântệ') {
                                               $priceS = "0";
                                           }

                                           // Page 0 no trả haimươihai
                                           if ($priceS == 'haimươihai') {
                                               $priceS = "22";
                                           }

                                           // Page 0 no trả haimươiba
                                           if ($priceS == 'haimươiba') {
                                               $priceS = "23";
                                           }

                                           if (is_null($orderDetailDB)) {
                                                $data_order_detail = [
                                                    'organization_id'       => $organizationTD->id,
                                                    'order_id'              => $orderDB->id,
                                                    'name'                  => $nameS,
                                                    'link'                  => $sanpham['link'],
                                                    'image'                 => $sanpham['image'],
                                                    'note'                  => $sanpham['ghi_chu_khac'],
                                                    'classification'        => $sanpham['ghi_chu'],
                                                    'unit_price_cny'        => $priceS,
                                                    'quantity'              => $quantity,
                                                    'packages_number'       => 1,
                                                    'amount_cny'            => $priceS * $quantity,
                                                    'supplier_id'           => $supplierOfCusDB->id,
                                                    'thuongdo_id'           => $sanpham['pid'],
                                                    'thuongdo_kien_hang_id' => $sanpham['kien_hang_id'],
                                                ];
                                               $orderDetailDB = $orderDetailModel->create($data_order_detail);
                                           }

                                           if ($orderDetailDB) {
                                               $orderDetailDB->order_id = $orderDB->id;

                                               // Ghi chú của từng sản phẩm
                                               $orderDetailDB->note = $sanpham['ghi_chu_khac'];

                                                // Giá
                                                if ($priceS != $orderDetailDB->unit_price_cny) {
                                                    $orderDetailDB->unit_price_cny = $priceS;
                                                }
                                                if ($quantity != $orderDetailDB->quantity) {
                                                    $orderDetailDB->quantity = $quantity;
                                                }

                                               // $orderDetailDB->save();
                                                $orderDetailDB = OrderDetail::withoutEvents(function () use ($orderDetailDB) {
                                                    $orderDetailDB->save();
                                                    return $orderDetailDB;
                                                });

                                                // Xử lý phần ghi chú
                                                $notes_historyTD = $sanpham['notes_history'];
                                                if (count($notes_historyTD) > 0) {
                                                    $notes_historyTDArr = reset($notes_historyTD);
                                                    if (count($notes_historyTDArr) > 0) {
                                                        foreach ($notes_historyTDArr as $key_notes_history => $arr_history) {
                                                            if ($key_notes_history == "ghi_chu_cho_khach_hang") {
                                                                $this->saveOrderDetailNoteFirst($arr_history, $orderDetailDB, $sanpham, $orderDB);
                                                                $this->saveOrderDetailNote($key_notes_history, $arr_history, $orderDetailDB);
                                                            } else {
                                                                $this->saveOrderDetailHistory($key_notes_history, $arr_history, $orderDetailDB);
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    $this->saveOrderDetailNoteFirst([], $orderDetailDB, $sanpham, $orderDB);
                                                }
                                           }

                                           // Push vào mảng tạm
                                           array_push($arrOrderDetailCurrentIds, (int)$orderDetailDB->id);
                                        }
                                    }

                                    // Xử lý xóa sp đã lưu nhưng không trong đanh sách
                                    $orderDetailOldIds = [];
                                    $orderDetailOld = $orderDetailModel->where('supplier_id', $orderSupplierDB->supplier_id)
                                                                        ->where('order_id', $orderDB->id)
                                                                        ->get();
                                    if (count($orderDetailOld)) {
                                        foreach ($orderDetailOld as $key_orderDetailOld => $orderDetailOld_item) {
                                            array_push($orderDetailOldIds, (int) $orderDetailOld_item->id);
                                        }
                                    }
                                    // So sánh
                                    $result_diff = array_diff($orderDetailOldIds, $arrOrderDetailCurrentIds);
                                    // Xóa cái dư thừa
                                    if (count($result_diff) > 0) {
                                        \Log::info('Xóa dư thừa');
                                        $orderDetailDelete = $orderDetailModel->whereIn('id', $result_diff)->delete();
                                    }
                                }
                            }
                        }

                        // Cập nhật lại tổng tiền china_shipping_cost vào ordres
                        $orderSupplierSum = $orderDB->orderSuppliers;
                        $china_shipping_cost_all = $orderSupplierSum ? $orderSupplierSum->sum('china_shipping_cost') : 0;
                        if ($china_shipping_cost_all > 0) {
                            $orderDB->china_shipping_cost = $china_shipping_cost_all;
                            $orderDB = Order::withoutEvents(function () use ($orderDB) {
                                $orderDB->save();
                                return $orderDB;
                            });
                        }
                    }
                }


                // Tạo kiện
                $sum_international_shipping_cost = 0;
                if ($orderDB) {
                    $packs = $item['kien_hang'];
                    foreach ($packs as $key_pack => $pack) {
                        // continue;
                        $orderPackageDB = $orderPackageModel->where('thuongdo_id', $pack['id'])->first();

                        if (is_null($orderPackageDB)) {
                            $data_order_package = [
                                'order_id'   => $orderDB->id,
                                'order_code' => $orderDB->code,
                                'code'       => null,
                                'code_po'    => $pack['ma_dat_hang'],
                                'bill_code'  => $pack['ma_van_don']
                            ];

                            $data_order_package['delivery_id'] = null; // Chưa có

                            $data_order_package['organization_id'] = $organizationTD->id;

                            $arr_type_order_pack = [
                                null => null,
                                '1'  => 'App\Models\Order',
                                '2'  => 'App\Models\Consignment',
                                '3'  => null
                            ];
                            $data_order_package['order_type'] = $arr_type_order_pack[$pack['type']];

                            // Phần kho xem bị trung với cái truso_id ko
                            // Lấy kho VN
                            $warehouseVNDB = $warehouseModel->where('thuongdo_id', $pack['truso_id'])->first();
                            if (!is_null($warehouseVNDB)) {
                                $data_order_package['warehouse_id'] = $warehouseVNDB->id; // CHeck xem nó có không
                            }

                            // Lấy kho TQ
                            $warehouseCNDB = $warehouseModel->where('thuongdo_id', $pack['kho_tq_id'])->first();
                            if (!is_null($warehouseCNDB)) {
                                $data_order_package['warehouse_cn'] = $warehouseCNDB->id; // CHeck xem nó có không
                            }

                            $data_order_package['product_name'] = $pack['ten_hang_hoa'];

                            $so_kienTD = $pack['so_kien'] == null ? 0 : $pack['so_kien'];
                            $data_order_package['package_number'] = $so_kienTD;

                            $data_order_package['order_cost'] = $pack['gia_tri_bao_hiem'] == null ? 0 : $pack['gia_tri_bao_hiem'];

                            $kiem_demTD =  $pack['kiem_dem'] == null ? 0 : $pack['kiem_dem'];
                            $phi_kiem_demTD =  $pack['phi_kiem_dem'] == null ? 0 : $pack['phi_kiem_dem'];
                            $data_order_package['inspection_cost'] = $phi_kiem_demTD; // Phí kiểm đếm
                            $data_order_package['is_inspection'] = $kiem_demTD; // Có kiểm đếm hay không


                            $bao_hiemTD =  $pack['bao_hiem'] == null ? 0 : $pack['bao_hiem'];
                            $phi_bao_hiemTD =  $pack['phi_bao_hiem'] == null ? 0 : $pack['phi_bao_hiem'];
                            $data_order_package['insurance_cost'] = $phi_bao_hiemTD; // Phí bảo hiểm
                            $data_order_package['is_insurance'] = $bao_hiemTD; // Có bảo hiểm hay không


                            $dong_goTD =  $pack['dong_go'] == null ? 0 : $pack['dong_go'];
                            $phi_dong_goTD =  $pack['phi_dong_go'] == null ? 0 : $pack['phi_dong_go'];
                            $data_order_package['woodworking_cost'] = $phi_dong_goTD; // Phí đóng gỗ
                            $data_order_package['is_woodworking'] = $dong_goTD; // Có đóng gỗ hay không


                            $chieu_caoTD  =  $pack['chieu_cao'] == null ? 0 : $pack['chieu_cao'];
                            $chieu_daiTD  =  $pack['chieu_dai'] == null ? 0 : $pack['chieu_dai'];
                            $chieu_rongTD =  $pack['chieu_rong'] == null ? 0 : $pack['chieu_rong'];
                            $data_order_package['height'] = $chieu_caoTD;
                            $data_order_package['length'] = $chieu_daiTD;
                            $data_order_package['width'] = $chieu_rongTD;

                            if ($pack['tinh_theo'] == 1) {
                                $data_order_package['weight'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
                                $data_order_package['weight_real'] = $pack['total_kg_round'];
                            }

                            // tinh_theo:
                            // 1 - Trọng lượng
                            // 2 - Thể tích

                            $data_order_package['type_quantitative'] = 'weight'; // Tính theo cái nào
                            if ($pack['tinh_theo'] == 2) {
                                $data_order_package['type_quantitative'] = 'volumn';
                                $data_order_package['thuongdo_volumn'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
                            }

                            $is_deliveryTD = $pack['send_yc_ship'] == null ? 0 : $pack['send_yc_ship'];
                            $data_order_package['is_delivery'] = $is_deliveryTD;
                            $data_order_package['delivery_cost'] = 0;
                            $data_order_package['delivery_type'] = 'normal';
                            $data_order_package['in_transit'] = $pack['hang_van_chuyen'];
                            $data_order_package['transporter_id'] = null;

                            $exchange_rateTD = $pack['ti_gia'] == null ? 0 : $pack['ti_gia'];
                            $exchange_rateTD = $orderDB->exchange_rate;
                            $data_order_package['exchange_rate'] = $exchange_rateTD;

                            $data_order_package['international_shipping_cost'] = $pack['phi_can_nang'] == null ? 0 : $pack['phi_can_nang'];
                            $data_order_package['china_shipping_cost'] = $pack['phi_china'];

                            $chong_socTD = $pack['chong_soc'] == null ? 0 : $pack['chong_soc'];
                            $phi_chong_socTD = $pack['phi_chong_soc'] == null ? 0 : $pack['phi_chong_soc'];
                            $data_order_package['is_shock_proof'] = $chong_socTD;
                            $data_order_package['shock_proof_cost'] = $phi_chong_socTD;

                            $data_order_package['storage_cost'] = 0; // Phí lưu kho, chưa rõ

                            $phi_chiet_khauTD =  $pack['phi_chiet_khau'] == null ? 0 : $pack['phi_chiet_khau'];
                            $data_order_package['discount_cost'] = $phi_chiet_khauTD; // Giá chiết khẩu (giảm cái phí vận chuyển QT)
                            $phan_tram_chiet_khauTD =  $pack['phan_tram_chiet_khau'] == null ? 0 : $pack['phan_tram_chiet_khau'];
                            $data_order_package['discount_percent'] = $phan_tram_chiet_khauTD; // % chiết khẩu

                            $data_order_package['amount'] = 0; // đang chưa rõ, Tổng phí vận chuyển


                            $data_order_package['note'] = $pack['ghichu'];
                            $data_order_package['note_ordered'] = $pack['ghichu_kh'];
                            $data_order_package['description'] = null; // Check lại coi


                            if (!is_null($customerDB)) {
                                $data_order_package['customer_id'] = $customerDB->id;
                            } else {
                                \Log::info('Không tim khách');
                            }

                            $data_order_package['customer_delivery_id'] = $orderDB->customer_delivery_id; // Xem lúc lưu tại app/Http/Controllers/PackageController.php


                            // Danh mục
                            $categoryDB = $categoryModel->where('thuongdo_id', $pack['cate_id'])->first();
                            if (!is_null($categoryDB)) {
                                $data_order_package['category_id'] = $categoryDB->id;
                                $data_order_package['category'] = $categoryDB->name;
                            }

                            // Nhân viên
                            // nhan_vien_cs_uid
                            // nhan_vien_tu_van_uid
                            // nhan_vien_kinh_doanh_uid

                            $staffCareDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_cs_uid'])->first();
                            if (!is_null($staffCareDB)) {
                                $data_order_package['staff_care_id'] = $staffCareDB->id;
                            }

                            $staffCounselorDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_tu_van_uid'])->first();
                            if (!is_null($staffCounselorDB)) {
                                $data_order_package['staff_counselor_id'] = $staffCounselorDB->id;
                            }

                            // Chưa tìm dc đối tượng này
                            $staffOrderDB = $userModel->where('thuongdo_uuid', $order['uid_xu_ly'])->first();
                            if (!is_null($staffOrderDB)) {
                                $data_order_package['staff_order_id'] = $staffOrderDB->id;
                            }

                            // Đơn giá phí vận chuyển quốc tế
                            $data_order_package['unit_price'] = $pack['price'] == null ? 0 : $pack['price'];

                            // Status

                            $data_order_package['status'] = $this->getStatusPack($pack['status']);

                            // ID
                            $data_order_package['thuongdo_id'] = $pack['id'];

                            // Thời gian tạo
                            $data_order_package['created_at'] =  date('Y-m-d H:i:s', $pack['created']);

                            // staff_group_id
                            $data_order_package['staff_group_id'] = $customerDB->staff_group_id;

                            // Tổng phí vận chuyển quốc tế lưu vào phần đơn hàng + order_supplier
                            $sum_international_shipping_cost += $data_order_package['international_shipping_cost'];

                            // $orderPackageDB = $orderPackageModel->create($data_order_package);
                            $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageModel, $data_order_package) {
                                $orderPackageDB = $orderPackageModel->create($data_order_package);
                                return $orderPackageDB;
                            });

                            // Tạo lịch sử kiện
                            $history_listTD = $pack['history_list'];

                            if (count($history_listTD)) {
                                foreach ($history_listTD as $key => $history) {
                                    $orderPackageStatusTimeDB = $orderPackageStatusTimeModel->where('thuongdo_id', $history['id'])->first();
                                    if (is_null($orderPackageStatusTimeDB)) {
                                        $data_order_pack_status_time = [
                                            'order_package_id' => $orderPackageDB->id,
                                            'key'              => $this->getStatusPack($history['status']),
                                            'thuongdo_id'      => $history['id'],
                                            'thuongdo_uid'     => $history['uid'],
                                            'created_at'       => date('Y-m-d H:i:s', $history['created'])
                                        ];
                                        $orderPackageStatusTimeDB = $orderPackageStatusTimeModel->create($data_order_pack_status_time);
                                    }

                                    $activitiPackStatusDB = $activitiModel
                                                                ->where('log_name', 'order_package_status')
                                                                ->where('subject_type', 'App\Models\OrderPackage')
                                                                ->where('subject_id', $orderPackageDB->id)
                                                                ->where('thuongdo_id', $history['id'])
                                                                ->first();

                                    if (is_null($activitiPackStatusDB)) {
                                        $user_actionDB = $userModel->where('thuongdo_uuid', $history['uid'])->first();
                                        if ($user_actionDB) {
                                            $data_user_activity = [
                                                'log_name'        => 'order_package_status',
                                                'subject_type'    => 'App\Models\OrderPackage',
                                                'subject_id'      => $orderPackageDB->id,
                                                'causer_type'     => 'App\Models\User',
                                                'causer_id'       => $user_actionDB->id,
                                                'content'         => $this->getStatusPack($history['status']),
                                                'organization_id' => $organizationTD->id,
                                                'thuongdo_id'     => $history['id'],
                                                'created_at'       => date('Y-m-d H:i:s', $history['created'])
                                            ];
                                            $activitiPackStatusDB = $activitiModel->create($data_user_activity);
                                        }
                                    }
                                }

                                // Xử lý với cái report ban đầu
                                // $orderPackageStatusTimeStart = $orderPackageStatusTimeModel
                                //                             ->where('order_package_id', $orderPackageDB->id)
                                //                             ->where('key', 'status_0')
                                //                             ->where('thuongdo_id', null)
                                //                             ->first();
                                // if ($orderPackageStatusTimeStart) {
                                //     $orderPackageStatusTimeStart->created_at = date('Y-m-d H:i:s', $pack['created']);
                                //     $orderPackageStatusTimeStart->thuongdo_id = '999999999';
                                //     $orderPackageStatusTimeStart->save();
                                // }
                                // // Xử lý cái log đầu tiền
                                // $activitiPackStatusStart = $activitiModel
                                //                                 ->where('log_name', 'order_package_status')
                                //                                 ->where('subject_type', 'App\Models\OrderPackage')
                                //                                 ->where('subject_id', $orderPackageDB->id)
                                //                                 ->where('content', 'status_0')
                                //                                 ->where('thuongdo_id', null)
                                //                                 ->first();
                                // if ($activitiPackStatusStart) {
                                //     $activitiPackStatusStart->created_at = date('Y-m-d H:i:s', $pack['created']);
                                //     $activitiPackStatusStart->thuongdo_id = '999999999';
                                //     $activitiPackStatusStart->save();
                                // }
                            }

                            // Tạo sản phẩm của kiện
                            $product_listTD = $pack['product_list'];
                            if (count($product_listTD)) {
                                foreach ($product_listTD as $key_product => $product) {
                                    $orderDetail1809 = $orderDetailModel->where('thuongdo_id', $product['pid'])->first();
                                    if ($orderDetail1809) {
                                        $data_order_detail_pack = [
                                            'order_detail_id'  => $orderDetail1809->id,
                                            'order_package_id' => $orderPackageDB->id,
                                            'quantity'         => $product['qty'] == null ? 0 : $product['qty'],
                                            'thuongdo_sync'    => 1
                                        ];
                                        $data_order_detail_packDB = $orderDetailPackageModel->create($data_order_detail_pack);
                                    }
                                }
                            }
                        } else {
                            $orderPackageDB->status = $this->getStatusPack($pack['status']);
                            $orderPackageDB->exchange_rate = $orderDB->exchange_rate;
                            $orderPackageDB->staff_group_id = $customerDB->staff_group_id;
                            // $orderPackageDB->save();
                            $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB) {
                                $orderPackageDB->save();
                                return $orderPackageDB;
                            });

                            // Lịch sử
                            $history_listTD = $pack['history_list'];
                            if (count($history_listTD)) {
                                foreach ($history_listTD as $key => $history) {
                                    $orderPackageStatusTimeDB = $orderPackageStatusTimeModel->where('thuongdo_id', $history['id'])->first();
                                    if (is_null($orderPackageStatusTimeDB)) {
                                        $data_order_pack_status_time = [
                                            'order_package_id' => $orderPackageDB->id,
                                            'key'              => $this->getStatusPack($history['status']),
                                            'thuongdo_id'      => $history['id'],
                                            'thuongdo_uid'     => $history['uid'],
                                            'created_at'       => date('Y-m-d H:i:s', $history['created'])
                                        ];
                                        $orderPackageStatusTimeDB = $orderPackageStatusTimeModel->create($data_order_pack_status_time);
                                    }

                                    $activitiPackStatusDB = $activitiModel
                                                                ->where('log_name', 'order_package_status')
                                                                ->where('subject_type', 'App\Models\OrderPackage')
                                                                ->where('subject_id', $orderPackageDB->id)
                                                                ->where('thuongdo_id', $history['id'])
                                                                ->first();

                                    if (is_null($activitiPackStatusDB)) {
                                        $user_actionDB = $userModel->where('thuongdo_uuid', $history['uid'])->first();
                                        if ($user_actionDB) {
                                            $data_user_activity = [
                                                'log_name'        => 'order_package_status',
                                                'subject_type'    => 'App\Models\OrderPackage',
                                                'subject_id'      => $orderPackageDB->id,
                                                'causer_type'     => 'App\Models\User',
                                                'causer_id'       => $user_actionDB->id,
                                                'content'         => $this->getStatusPack($history['status']),
                                                'organization_id' => $organizationTD->id,
                                                'thuongdo_id'     => $history['id'],
                                                'created_at'       => date('Y-m-d H:i:s', $history['created'])
                                            ];
                                            $activitiPackStatusDB = $activitiModel->create($data_user_activity);
                                        }
                                    }
                                }
                            }

                            // Cập nhật giá
                            $this->updatePricePackage($orderPackageDB, $pack);
                        }
                    }
                }

                // Lưu thêm phí vận chuyển quốc tế vào đơn
                if ($orderDB) {
                    if ($showLog) {
                        \Log::info('Lưu thêm phí vận chuyển quốc tế vào đơn');
                        \Log::info($sum_international_shipping_cost);
                    }
                    $orderDB->international_shipping_cost = $sum_international_shipping_cost;
                    // $orderDB->save();
                    $orderDB = Order::withoutEvents(function () use ($orderDB) {
                        $orderDB->save();
                        return $orderDB;
                    });
                }
                // // Lưu thêm phí vận chuyển quốc tế vào orderSupplierFirst
                // if ($orderSupplierFirst) {
                //     if ($showLog) {
                //         \Log::info('orderSupplierFirst');
                //     }
                //     $orderSupplierFirst->international_shipping_cost = $sum_international_shipping_cost;
                //     $orderSupplierFirst->save();
                // }

                // // Lưu order_fee, discount_cost, inspection_cost từ order vào thằng đầu tiên
                // if ($orderDB && $orderSupplierFirst) {
                //     if ($showLog) {
                //         \Log::info('Lưu 4 key mới');
                //     }
                //     $orderSupplierFirst->order_cost = $orderDB->order_cost;
                //     $orderSupplierFirst->order_fee = $orderDB->order_fee;
                //     $orderSupplierFirst->discount_cost = $orderDB->discount_cost;
                //     $orderSupplierFirst->inspection_cost = $orderDB->inspection_cost;
                //     $orderSupplierFirst->save();
                // }

                // Tạo giao hàng
                if ($orderDB) {
                    if (count($item['delivery']) > 0) {
                        $deliveryTD = $item['delivery'];

                        if ($showLog) {
                            \Log::info('Lưu phần delivery đê');
                        }

                        foreach ($deliveryTD as $key_delivery => $delivery) {
                            $deliveryDB = $deliveryModel->where('thuongdo_id', $delivery['id'])->first();
                            if (is_null($deliveryDB)) {
                                // ID
                                $data_delivery['thuongdo_id']     = $delivery['id'];
                                $data_delivery['code']            = $delivery['id'];
                                $data_delivery['organization_id'] = $organizationTD->id;
                                if ($showLog) {
                                    \Log::info('created-delivey: ' . $delivery['created']);
                                }
                                // Thời gian tạo
                                $data_delivery['created_at'] =  date('Y-m-d H:i:s', $delivery['created']);
                                // Khách hàng

                                $data_delivery['customer_id'] = $customerDB->id;

                                // Địa chỉ giao hàng shipping_address_id
                                $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $delivery['shipping_address_id'])->first();
                                if (is_null($customerDeliveryDB)) {
                                    if ($showLog) {
                                        \Log::info('Không địa chỉ giao hàng của khách không lưu giao hàng luôn: ' .  $delivery['id']);
                                    }
                                    continue; // Chạy vòng lặp mới
                                }
                                $data_delivery['customer_delivery_id'] = $customerDeliveryDB->id;
                                $data_delivery['receiver']             = $customerDeliveryDB->receiver;
                                $data_delivery['address']              = $customerDeliveryDB->address;
                                $data_delivery['phone_number']         = $customerDeliveryDB->phone_number;

                                // Hình thức vận chuyển
                                $transporterDB = $transporterModel->where('thuongdo_id', $delivery['shipping_type'])->first();
                                if (!is_null($transporterDB)) {
                                    $data_delivery['transporter_id'] = $transporterDB->id;
                                } else {
                                    $data_delivery['transporter_id'] = 0;
                                }

                                // Thời gian giao

                                // Trang 44 nó trả ra time 18-01-2021 17:30 đến 18h00
                                if (strlen($delivery['time_delivery']) > 11) {
                                    $delivery['time_delivery'] = substr($delivery['time_delivery'], 0, 10);
                                }
                                // (Trang 31 dính lỗi này)
                                $data_delivery['date'] = str_replace("/", "-", $delivery['time_delivery']);

                                // Kiểm tra đúng định dạng thời gian hay không
                                // Trang 94 nó trả ra time 04 - 05-02
                                $checkTime1 = \DateTime::createFromFormat('Y-m-d', $data_delivery['date']);
                                $checkTime2 = \DateTime::createFromFormat('d-m-Y', $data_delivery['date']);
                                if ($checkTime1 == false && $checkTime2 == false) {
                                    $data_delivery['date'] = null;
                                }

                                // Trang 1303 nó trả ra time 17-18-7
                                if ($data_delivery['date'] && strlen($data_delivery['date']) < 10) {
                                    $data_delivery['date'] = null;
                                }

                                // Trang 2141 nó trả ra time 06-13-2021
                                // Trang 2149 nó trả ra time null

                                if (!is_null($data_delivery['date'])) {
                                    $arr_date_829 = explode("-", $data_delivery['date']);
                                    if ($arr_date_829[1] > 12) {
                                        $data_delivery['date'] = $arr_date_829[0] . "-12-" .  $arr_date_829[2];
                                    }
                                }

                                // Loại giao nhanh hay chậm type
                                $data_delivery['type'] = 'normal';
                                $data_delivery['payment'] = $this->getDeliveryPayment($delivery['shipping_code']);

                                // Phí
                                $data_delivery['international_shipping_cost'] = $delivery['phi_vc_quoc_te'];
                                $data_delivery['china_shipping_cost'] = $delivery['phi_ship_tq'];

                                $staffOrderDB = $userModel->where('thuongdo_uuid', $delivery['uid_xu_ly'])->first();
                                if (!is_null($staffOrderDB)) {
                                    $data_delivery['staff_order_id'] = $staffOrderDB->id;
                                }

                                // Status
                                // "1" => "Chờ xử lý",
                                // "4" => "Đang xử lý",
                                // "2" => "Đã hoàn thành"
                                // "3" => "Hủy"
                                $arr_status = [
                                    "1" => "status_0",
                                    "2" => "status_2",
                                    "3" => "status_3",
                                    "4" => "status_1",
                                ];
                                $data_delivery['status'] = array_key_exists($delivery['status'], $arr_status) ? $arr_status[$delivery['status']] : 'Không xác định';


                                // Status_payment
                                // 1. Chưa thanh toán hết
                                // 2. Đã thanh toán hết
                                $arr_status_payment = [
                                    '1' => 0,
                                    '2' => 1,
                                ];

                                $data_delivery['is_delivery_cost_paid'] = array_key_exists($delivery['status_thanhtoan'], $arr_status_payment) ? $arr_status_payment[$delivery['status_thanhtoan']] : null;

                                // Mã bưu kiện
                                $data_delivery['postcode'] = $delivery['post_code'];
                                $data_delivery['carrier_code'] = $delivery['post_code'];

                                if ($showLog) {
                                    \Log::info('post_code: ' . $delivery['post_code']);
                                    \Log::info('thanh_toan: ' . $delivery['thanh_toan']);
                                }

                                // Thanh toán
                                $thanh_toanTD = $delivery['thanh_toan'] == null ? 0 : $delivery['thanh_toan'];
                                $data_delivery['thuongdo_total_money'] = $thanh_toanTD;
                                $data_delivery['delivery_cost'] = $thanh_toanTD;

                                // Ghi chú
                                $data_delivery['note'] = $delivery['ghi_chu'];

                                // Tạo giao hàng
                                // $deliveryDB = $deliveryModel->create($data_delivery);
                                $deliveryDB = Delivery::withoutEvents(function () use ($deliveryModel, $data_delivery) {
                                    $deliveryDB = $deliveryModel->create($data_delivery);
                                    return $deliveryDB;
                                });
                                if ($showLog) {
                                    \Log::info('Tạo giao hàng nè');
                                }

                                // Kiện hàng
                                $kienhang_ids = $delivery['kienhang_id'];
                                $kienhang_ids = explode(",",$kienhang_ids);

                                $packInDeliverys = $orderPackageModel->whereIn('thuongdo_id', $kienhang_ids)->get();

                                // Thêm giao hàng vào kiện
                                if (count($packInDeliverys) > 0) {
                                    foreach ($packInDeliverys as $key_182 => $item_182) {
                                        $item_182->delivery_id = $deliveryDB->id;
                                        // $item_182->save();
                                        $item_182 = OrderPackage::withoutEvents(function () use ($item_182) {
                                            $item_182->save();
                                            return $item_182;
                                        });
                                    }
                                }

                                // // Thêm kiện vào giao hàng Logic 2
                                // if (count($kienhang_ids) > 0) {
                                //     foreach ($kienhang_ids as $key_182 => $item_182) {
                                //         $packInDelivery = $orderPackageModel->where('thuongdo_id', $item_182)->first();

                                //         if (!is_null($packInDelivery)) {
                                //             $packInDelivery->delivery_id = $deliveryDB->id;
                                //             $packInDelivery->save();
                                //         }

                                //         if (is_null($packInDelivery)) {
                                //             $data_pack_null_delivery = [
                                //                 'delivery_id' => $deliveryDB->id,
                                //                 'thuongdo_id' => $item_182,
                                //             ];
                                //             $packDB_182 = $orderPackageModel->create($data_pack_null_delivery);
                                //         }
                                //     }
                                // }

                                // Thêm vào quan hệ giữa giao hàng và đơn
                                $deliveryOrderDB = $deliveryOrderModel->where('order_id', $orderDB->id)
                                                                    ->where('delivery_id', $deliveryDB->id)
                                                                    ->first();

                                if (is_null($deliveryOrderDB)) {
                                    $data_delivery_order = [
                                        'delivery_id'   => $deliveryDB->id,
                                        'order_type'    => 'App\Models\Order', // Ở đây là mặc định các đơn đều là order hết
                                        'order_id'      => $orderDB->id,
                                        'thuongdo_sync' => 1,
                                    ];
                                    $deliveryOrderDB = $deliveryOrderModel->create($data_delivery_order);
                                }
                            } else {
                                $kienhang_ids = $delivery['kienhang_id'];
                                $kienhang_ids = explode(",",$kienhang_ids);

                                $packInDeliverys = $orderPackageModel->whereIn('thuongdo_id', $kienhang_ids)->get();

                                // Thêm giao hàng vào kiện
                                if (count($packInDeliverys) > 0) {
                                    foreach ($packInDeliverys as $key_182 => $item_182) {
                                        if ($item_182->delivery_id == null) {
                                            $item_182->delivery_id = $deliveryDB->id;
                                            // $item_182->save();
                                            $item_182 = OrderPackage::withoutEvents(function () use ($item_182) {
                                                $item_182->save();
                                                return $item_182;
                                            });
                                        }
                                    }
                                }

                                // Thêm lịch sử giao hàng
                                $history_listTD = $delivery['history'];
                                if (count($history_listTD)) {
                                    foreach ($history_listTD as $key => $history) {
                                        $activitiDeliveryStatusDB = $activitiModel
                                                                    ->where('log_name', 'delivery_status')
                                                                    ->where('subject_type', 'App\Models\Delivery')
                                                                    ->where('subject_id', $deliveryDB->id)
                                                                    ->where('thuongdo_id', $history['id'])
                                                                    ->first();

                                        if (is_null($activitiDeliveryStatusDB)) {
                                            $user_actionDB = $userModel->where('thuongdo_uuid', $history['uid'])->first();
                                            if ($user_actionDB) {
                                                $status_x86TD = $this->getStatusDelivery($history['status']);
                                                $data_x86TD = new ReportStatusResource($status_x86TD, DeliveryConstant::STATUSES, DeliveryConstant::STATUSES_COLOR);
                                                $data_user_activity = [
                                                    'log_name'        => 'delivery_status',
                                                    'subject_type'    => 'App\Models\Delivery',
                                                    'subject_id'      => $deliveryDB->id,
                                                    'causer_type'     => 'App\Models\User',
                                                    'causer_id'       => $user_actionDB ? $user_actionDB->id : null,
                                                    'content'         => DeliveryConstant::STATUSES[$status_x86TD],
                                                    'properties'      => json_encode(['status' => $data_x86TD]),
                                                    'organization_id' => $organizationTD->id,
                                                    'thuongdo_id'     => $history['id'],
                                                    'created_at'      => date('Y-m-d H:i:s', $history['created'])
                                                ];
                                                $activitiDeliveryStatusDB = $activitiModel->create($data_user_activity);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Phần khiếu nại
                if ($orderDB) {
                    $complainList = $item['complain'];
                    if (count($complainList) > 0) {

                        foreach ($complainList as $key_120 => $item_120) {
                            // complainTypeFullDB
                            // solutionFullDB
                            // dump($item_120);

                            $complainDB = $complainModel->where('thuongdo_id', $item_120['id'])->first();

                            if (is_null($complainDB)) {
                                $data_complain = [];

                                // ID
                                $data_complain['thuongdo_id'] = $item_120['id'];
                                $data_complain['organization_id'] = 1;
                                // Thời gian tạo
                                $data_complain['created_at'] =  date('Y-m-d H:i:s', $item_120['created']);
                                $data_complain['updated_at'] =  date('Y-m-d H:i:s', $item_120['changed']);

                                $data_complain['order_id'] = $orderDB->id;
                                if (isset($complainTypeFullDB[$item_120['loaikhieunai']])) {
                                    $data_complain['complain_type_id'] = $complainTypeFullDB[$item_120['loaikhieunai']];
                                }
                                if ($item_120['phuong_an']) {
                                    if (isset($solutionFullDB[$item_120['phuong_an']])) {
                                        $data_complain['solution_id'] = $solutionFullDB[$item_120['phuong_an']];
                                    }
                                }
                                $data_complain['customer_id'] = $customerDB->id;
                                $data_complain['status'] = $this->getStatusComplain($item_120['trang_thai_id']);


                                $data_complain['staff_care_id'] = $orderDB->staff_care_id;
                                $data_complain['staff_counselor_id'] = $orderDB->staff_counselor_id;
                                $data_complain['staff_order_id'] = $orderDB->staff_order_id;

                                if ($item_120['uid_tiep_nhan'] != null) {
                                   $userTiepNhanDB = $userModel->where('thuongdo_uuid', $item_120['uid_tiep_nhan'])->first();
                                    if (!is_null($userTiepNhanDB)) {
                                        $data['staff_group_id'] = $userTiepNhanDB->id;
                                    }
                                }

                                if ($item_120['uid_xu_ly'] != null) {
                                   $userXuLyDB = $userModel->where('thuongdo_uuid', $item_120['uid_xu_ly'])->first();
                                    if (!is_null($userXuLyDB)) {
                                        $data['staff_complain_id'] = $userXuLyDB->id;
                                    }
                                }

                                $data_complain['compensation'] = $item_120['tien_boi_thuong'] == null ? 0 : $item_120['tien_boi_thuong'];

                                // dd($data_complain);

                                // Loại phản hồi
                                // 1 => Bồi thường
                                // 2 => Đổi trả
                                // 3 => Phát bù

                                $complainDB = $complainModel->create($data_complain);
                            }


                            // Gán chi tiết đơn
                            $productsTD = [];
                            if (isset($item_120['products'])) {
                                $productsTD = $item_120['products'];
                            }
                            if (count($productsTD)) {
                                foreach ($productsTD as $key_92013 => $item_92013) {

                                    $complainDetailDB = $complainDetailModel->where('thuongdo_id', $item_120['id'])->first();

                                    if (is_null($complainDetailDB)) {
                                        $packDB = $orderPackageModel->where('thuongdo_id', $key_92013)->first();
                                        $orderDetailComplainDB = $orderDetailModel->where('thuongdo_kien_hang_id', $key_92013)->first();

                                        if (!is_null($packDB) && !is_null($orderDetailComplainDB)) {

                                            $data_complain_detail = [
                                                'complain_id'      => $complainDB->id,
                                                'order_detail_id'  => $orderDetailComplainDB->id,
                                                'order_package_id' => $packDB->id,
                                                'thuongdo_id'      => $item_120['id'],
                                            ];

                                            $complainDetailDB = $complainDetailModel->create($data_complain_detail);
                                        } else {
                                            if ($showLog) {
                                                \Log::info('Không đủ data tạo khiếu nại');
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Phần hoàn tiên
                if ($orderDB) {
                    $refundList = $item['refunds'];
                    if (count($refundList) > 0) {
                        $arr_status = [
                            '1' => RefundConstant::KEY_STATUS_PENDING, // Chờ xử lý
                            '4' => RefundConstant::KEY_STATUS_PROCESS, // Đang xử lý
                            '5' => RefundConstant::KEY_STATUS_DONE, // Đã xử lý
                            '2' => RefundConstant::KEY_STATUS_DONE, // Đã hoàn thành
                            '3' => RefundConstant::KEY_STATUS_CANCEL, // Đã hủy
                        ];

                        foreach ($refundList as $key => $refundItem) {
                            $staffDB = $userModel->where('thuongdo_uuid', $refundItem['uid'])->first();
                            $status_refund = array_key_exists($refundItem['status'], $arr_status) ? $arr_status[$refundItem['status']] : 0;

                            $file = "";
                            if (count($refundItem['files']) > 0) {
                                $firstKey = array_key_first($refundItem['files']);
                                $file = $refundItem['files'][$firstKey]['url'];
                            }

                            $sotienTD = $refundItem['sotien'] == null ? 0 : $refundItem['sotien'];

                            $data_refund = [
                                'organization_id' => 1,
                                'staff_id'        => is_null($staffDB) ? 0 : (int) $staffDB->id,
                                'order_id'        => (int) $orderDB->id,
                                'order_code'      => $orderDB->code,
                                'order_status'    => $orderDB->status,
                                'amount_cn'       => round($sotienTD / $refundItem['ty_gia'], 2),
                                'amount_vn'       => $sotienTD,
                                'total_refund_cny' => round($sotienTD / $refundItem['ty_gia'], 2),
                                'exchange_rate'   => $refundItem['ty_gia'],
                                'note'            => $refundItem['ghi_chu'],
                                'type'            => 1, // Hoàn tiền cho khách
                                'status'          => $status_refund,
                                'file'            => $file,
                                'files'           => json_encode($refundItem['files'], JSON_UNESCAPED_UNICODE),
                                'created_at'      => date('Y-m-d H:i:s', $refundItem['created']),
                                'thuongdo_id'     => $refundItem['id']
                            ];

                            $refundDB = $refundModel->where('thuongdo_id', $refundItem['id'])->first();
                            if (is_null($refundDB)) {
                                $refundDB = $refundModel->create($data_refund);
                            } else {
                                $refundDB->total_refund_cny = round($sotienTD / $refundItem['ty_gia'], 2);
                                $refundDB->save();
                            }
                        }
                    }
                }

                // Phần cập nhật tổng tiền thực tế
                if ($orderDB) {
                    $updateOrderCostList = $item['actual_updates'];
                    if (count($updateOrderCostList) > 0) {
                        $arr_status = [
                            '1' => OrderCostRealConstant::STATUS_WAITING, // Chờ xử lý
                            '4' => OrderCostRealConstant::STATUS_WAITING, // Đang xử lý
                            '5' => OrderCostRealConstant::STATUS_APPROVED, // Đã xử lý
                            '2' => OrderCostRealConstant::STATUS_APPROVED, // Đã hoàn thành
                            '3' => OrderCostRealConstant::STATUS_DONE, // Đã hủy
                        ];

                        foreach ($updateOrderCostList as $key => $updateOrderCostItem) {
                            $refundItem = $updateOrderCostItem;

                            $staffDB = $userModel->where('thuongdo_uuid', $updateOrderCostItem['uid'])->first();
                            $status_refund = array_key_exists($updateOrderCostItem['status'], $arr_status) ? $arr_status[$updateOrderCostItem['status']] : 0;

                            $file = "";
                            if (count($updateOrderCostItem['files']) > 0) {
                                $firstKey = array_key_first($updateOrderCostItem['files']);
                                $file = $updateOrderCostItem['files'][$firstKey]['url'];
                            }

                            $sotienTD = $refundItem['sotien'] == null ? 0 : $refundItem['sotien'];

                            $data_order_cost_real = [
                                'order_id'         => (int) $orderDB->id,
                                'order_code'       => $orderDB->code,
                                'order_code_po'    => $orderDB->code_po,
                                'staff_order_id'   => is_null($staffDB) ? 0 : (int) $staffDB->id,
                                'staff_order_name' => is_null($staffDB) ? "" : $staffDB->name,
                                'thuongdo_id'      => $updateOrderCostItem['id'],
                                'file'             => $file,
                                'files'           => json_encode($updateOrderCostItem['files'], JSON_UNESCAPED_UNICODE),
                                'status'           => $status_refund,
                                'note'             => $updateOrderCostItem['ghi_chu'],
                                'created_at'       => date('Y-m-d H:i:s', $updateOrderCostItem['created']),
                                'cost_old'         => round(($orderDB->order_cost / $orderDB->exchange_rate), 2),
                                'cost_new'         => round($sotienTD / $refundItem['ty_gia'], 2)
                            ];

                            $refundDB = $orderCostRealModel->where('thuongdo_id', $refundItem['id'])->first();
                            if (is_null($refundDB)) {
                                $refundDB = $orderCostRealModel->create($data_order_cost_real);
                            } else {
                                $refundDB->files = json_encode($updateOrderCostItem['files'], JSON_UNESCAPED_UNICODE);
                                $refundDB->save();
                            }
                        }
                    }
                }

                // dd("Done");
            }
        }
    }

    public function saveOrderSupplierHistory($key_notes_history, $arr_history, $orderSupplierDB)
    {
        $orderDetailHistoryModel = \App::make('App\Models\OrderSupplierHistory');
        $userModel = \App::make('App\Models\User');

        foreach ($arr_history as $key_arr_history => $arr_history_item) {
            $orderDetailHistoryDB = $orderDetailHistoryModel->where('thuongdo_id', $arr_history_item['id'])->first();
            if (is_null($orderDetailHistoryDB)) {
                $userDB = $userModel->where('thuongdo_uuid', $arr_history_item['uid_created'])->first();

                $arr_type = [
                    'phi_ship_china' => OrderSupplierHistory::OBJECT_CHINA_SHIPPING_COST,
                    'tien_hang_china' => OrderSupplierHistory::OBJECT_CHINA_ORDER_COST,
                ];
                $key_old = $key_notes_history;
                if ($key_notes_history == "phi_ship_china") {
                    $key_old = 'phi_ship';
                }
                if ($key_notes_history == "tien_hang_china") {
                    $key_old = 'tien_dat_hang_shop';
                }
                $key_new = $key_old . '_new';

                $data_create_note = [
                    'order_id'          => (int) $orderSupplierDB->order_id,
                    'order_supplier_id' => (int) $orderSupplierDB->id,
                    'supplier_id'       => (int) $orderSupplierDB->supplier_id,
                    'object'            => $arr_type[$key_notes_history],
                    'value'             => $arr_history_item['value'][$key_new],
                    'value_old'         => $arr_history_item['value'][$key_old],
                    'order_status'      => $this->getStatusOder($arr_history_item['order_status']),
                    'user_id'           => $userDB ? $userDB->id : 0,
                    'thuongdo_id'       => $arr_history_item['id'],
                    'created_at'        => date('Y-m-d H:i:s', $arr_history_item['created'])
                ];
                $orderDetailHistoryDB = $orderDetailHistoryModel->create($data_create_note);
            }
        }
    }

    public function saveOrderDetailHistory($key_notes_history, $arr_history,$orderDetailDB)
    {
        $orderDetailHistoryModel = \App::make('App\Models\OrderDetailHistory');
        $userModel = \App::make('App\Models\User');

        foreach ($arr_history as $key_arr_history => $arr_history_item) {
            $orderDetailHistoryDB = $orderDetailHistoryModel->where('thuongdo_id', $arr_history_item['id'])->first();
            if (is_null($orderDetailHistoryDB)) {
                $userDB = $userModel->where('thuongdo_uuid', $arr_history_item['uid_created'])->first();

                $arr_type = [
                    'ghi_chu_khac' => OrderDetailHistory::OBJECT_NOTE_OTHER,
                    'gia_san_pham' => OrderDetailHistory::OBJECT_PRICE,
                    'so_luong'     => OrderDetailHistory::OBJECT_QUANTITY,
                ];
                $key_old = $key_notes_history;
                if ($key_notes_history == "gia_san_pham") {
                    $key_old = 'price';
                }
                if ($key_notes_history == "so_luong") {
                    $key_old = 'qty';
                }
                $key_new = $key_old . '_new';

                $data_create_note = [
                    'order_detail_id' => (int) $orderDetailDB->id,
                    'object'          => $arr_type[$key_notes_history],
                    'value'           => $arr_history_item['value'][$key_new],
                    'value_old'       => $arr_history_item['value'][$key_old],
                    'order_status'    => $this->getStatusOder($arr_history_item['order_status']),
                    'user_id'         => $userDB ? $userDB->id : 0,
                    'thuongdo_id'     => $arr_history_item['id'],
                    'created_at'      => date('Y-m-d H:i:s', $arr_history_item['created'])
                ];
                $orderDetailHistoryDB = $orderDetailHistoryModel->create($data_create_note);
            }
        }
    }

    public function saveOrderDetailNote($key_notes_history, $arr_history,$orderDetailDB)
    {
        $orderDetailHistoryModel = \App::make('App\Models\OrderDetailNote');
        $userModel = \App::make('App\Models\User');

        foreach ($arr_history as $key_arr_history => $arr_history_item) {
            $orderDetailHistoryDB = $orderDetailHistoryModel->where('thuongdo_id', $arr_history_item['id'])->first();
            if (is_null($orderDetailHistoryDB)) {
                $userDB = $userModel->where('thuongdo_uuid', $arr_history_item['uid_created'])->first();
                $data_create_note = [
                    'order_id'        => (int)$orderDetailDB->order_id,
                    'order_detail_id' => (int) $orderDetailDB->id,
                    'supplier_id'     => (int) $orderDetailDB->supplier_id,
                    'content'         => $arr_history_item['value']["ghi_chu_cho_khach_hang_new"],
                    'order_status'    => $this->getStatusOder($arr_history_item['order_status']),
                    'subject_id'      => $userDB ? $userDB->id : 0,
                    'subject_type'    => $userDB ? 'App\Models\Staff' : "",
                    'thuongdo_id'     => $arr_history_item['id'],
                    'created_at'      => date('Y-m-d H:i:s', $arr_history_item['created'])
                ];

                $orderDetailHistoryDB = OrderDetailNote::withoutEvents(function () use ($orderDetailHistoryModel, $data_create_note) {
                    $orderDetailHistoryDB = $orderDetailHistoryModel->create($data_create_note);
                    return $orderDetailHistoryDB;
                });
            }
        }
    }

    public function saveOrderDetailNoteFirst($arr_history,$orderDetailDB, $sanpham, $orderDB)
    {
        $orderDetailHistoryModel = \App::make('App\Models\OrderDetailNote');
        $userModel = \App::make('App\Models\User');

        // Lưu thêm tin nhắn đầu tiên
        $orderDetailHistoryDB = $orderDetailHistoryModel->where('thuongdo_id', (int) $orderDetailDB->id)
                                                        ->where('order_status', 'status_start')
                                                        ->where('order_detail_id', (int) $orderDetailDB->id)
                                                        ->first();

        $contentNote = $sanpham["ghi_chu_cho_khach_hang"];
        if (count($arr_history) > 0) {
            $contentNote = $arr_history[0]['value']['ghi_chu_cho_khach_hang'];
        }
        if (is_null($orderDetailHistoryDB) && !is_null($contentNote)) {
            $data_create_note_start = [
                'order_id'        => (int)$orderDetailDB->order_id,
                'order_detail_id' => (int) $orderDetailDB->id,
                'supplier_id'     => (int) $orderDetailDB->supplier_id,
                'content'         => $contentNote,
                'order_status'    => 'status_start',
                'subject_id'      => 0,
                'subject_type'    => 'App\Models\Staff',
                'thuongdo_id'     => (int) $orderDetailDB->id,
                'created_at'      => $orderDB->created_at
            ];

            $orderDetailHistoryDB = OrderDetailNote::withoutEvents(function () use ($orderDetailHistoryModel, $data_create_note_start) {
                $orderDetailHistoryDB = $orderDetailHistoryModel->create($data_create_note_start);
                return $orderDetailHistoryDB;
            });
        }
    }

    public function syncOrderRefundLive(Request $request)
    {
        for ($i=0; $i < 9000; $i++) {
            $job = new \App\Jobs\SyncTD\SyncRefund($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        }
        return;

        // $pageCron = 59;
        $pageCron = (int)$request->page;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/orders-fulfillment';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        // dd($result);

        $showLog = false;

        if ($result && count($result)) {
            $organizationModel = \App::make('App\Models\Organization');
            $orderModel = \App::make('App\Models\Order');
            $userModel = \App::make('App\Models\User');

            // Phần hoàn tiền
            $refundModel = \App::make('App\Models\Refund');

            foreach ($result as $key => $item) {
                if ($showLog) {
                    \Log::info('ID:' . $key . ':code:' . $item['orders']['order_code']);
                }
                // dump($item);

                $order = $item['orders'];
                $orders_supplier = $item['orders_supplier'];

                if (count($item['refunds']) == 0) {
                   continue;
                } else {

                }

                $orderDB = $orderModel->where('thuongdo_id', $order['id'])->first();
                // Phần hoàn tiên
                if ($orderDB) {
                    $refundList = $item['refunds'];
                    if (count($refundList) > 0) {
                        $arr_status = [
                            '1' => RefundConstant::KEY_STATUS_PENDING, // Chờ xử lý
                            '4' => RefundConstant::KEY_STATUS_PROCESS, // Đang xử lý
                            '5' => RefundConstant::KEY_STATUS_DONE, // Đã xử lý
                            '2' => RefundConstant::KEY_STATUS_DONE, // Đã hoàn thành
                            '3' => RefundConstant::KEY_STATUS_CANCEL, // Đã hủy
                        ];

                        foreach ($refundList as $key => $refundItem) {
                            $staffDB = $userModel->where('thuongdo_uuid', $refundItem['uid'])->first();
                            $status_refund = array_key_exists($refundItem['status'], $arr_status) ? $arr_status[$refundItem['status']] : 0;

                            $file = "";
                            if (count($refundItem['files']) > 0) {
                                $firstKey = array_key_first($refundItem['files']);
                                $file = $refundItem['files'][$firstKey]['url'];
                            }

                            $sotienTD = $refundItem['sotien'] == null ? 0 : $refundItem['sotien'];

                            $data_refund = [
                                'organization_id' => 1,
                                'staff_id'        => is_null($staffDB) ? 0 : (int) $staffDB->id,
                                'order_id'        => (int) $orderDB->id,
                                'order_code'      => $orderDB->code,
                                'order_status'    => $orderDB->status,
                                'amount_cn'       => round($sotienTD / $refundItem['ty_gia'], 2),
                                'amount_vn'       => $sotienTD,
                                'exchange_rate'   => $refundItem['ty_gia'],
                                'note'            => $refundItem['ghi_chu'],
                                'type'            => 1, // Hoàn tiền cho khách
                                'status'          => $status_refund,
                                'file'            => $file,
                                'files'           => json_encode($refundItem['files'], JSON_UNESCAPED_UNICODE),
                                'created_at'      => date('Y-m-d H:i:s', $refundItem['created']),
                                'thuongdo_id'     => $refundItem['id']
                            ];

                            $refundDB = $refundModel->where('thuongdo_id', $refundItem['id'])->first();
                            if (is_null($refundDB)) {
                                $refundDB = $refundModel->create($data_refund);
                            }
                        }
                    }
                }
            }
        }
    }

    // Xử lý phần lấy order_free, discount_fee ở phần OrderSupplier
    public function getOrderFeeForOrderSup($totalOrder, $percent)
    {
        $default = 9000;
        return AccountingHelper::getCosts($percent > 100  ? $default : max(($totalOrder * ($percent / 100)), $default));
    }

    public function getOrderDiscountForOrderSup($orderFeeTmp, $percent)
    {
        return AccountingHelper::getCosts($orderFeeTmp * ($percent / 100));
    }


    // Các kiện này của bên giao hàng tạo trước, nó chưa có thông tin thì mới update
    public function syncPackOfDelivery($packageDB, $data)
    {
        if ($packageDB->thuongdo_id > 0 && $packageDB->delivery_id != null) {
            dd("Cập nhật đê");
        }
    }

    public function getStatusOder($status)
    {
        $arr_status_TD = [
            '1' => 'status_0',
            '2' => 'status_6',
            '3' => 'status_4',
            '4' => 'status_2',
            '13' => 'status_1',
            '14' => 'status_3',
            '15' => 'status_5',
        ];

        return $arr_status_TD[$status];
    }

    public function getStatusPack($status)
    {
        $arr_status_TD = [
            '1' => 'status_4', // Đang về kho TQ
            '3' => 'status_5', // Đến kho TQ
            '4' => 'status_6', // Đang về VN
            '5' => 'status_10', // Đến kho VN
            '6' => 'status_15', // Đang giao hàng
            '7' => 'status_16', // Đã nhận hàng
            '9' => 'status_0', // Chờ xử lý
            '10' => 'status_1', // Xác nhận lại
            '11' => 'status_17', // Đã hủy
            '12' => 'status_14', // Chờ COD
            '13' => 'status_11', // Đang về Tp.HCM
            '14' => 'status_13', // Đang về Hải Phòng
            '15' => 'status_2', // Đang ở kho QC
            '16' => 'status_3', // Đang về Bằng Tường
            '17' => 'status_7', // Đến kho Bằng Tường
            '18' => 'status_8', // Đang kiểm hàng
            '21' => 'status_9', // Đang chuyển hàng
            '22' => 'status_12', // Đang kiểm hàng HCM
            '23' => 'status_18', // Hàng chờ thanh lý
        ];

        // return $arr_status_TD[$status];
        return array_key_exists($status, $arr_status_TD) ? $arr_status_TD[$status] : 'status_null';
    }

    public function getDeliveryPayment($id)
    {
        $arr_payment_TD = [
            null => 'null2024',
            '1' => 'e-wallet',
            '2' => 'cod',
            '3' => 'bank', // Chuyển khoản ngân hàng
            '4' => 'cash', // Tiền mặt
        ];

        if (array_key_exists($id, $arr_payment_TD)) {
            return $arr_payment_TD[$id];
        }

        $code = 'eee:' . $id;
        return $code;
    }

    public function getStatusComplain($id)
    {
        $arr_complain_TD = [
            '1' => 'status_0',     // Chờ xử lý
            '2' => 'status_3',     // Đã hoàn thành
            '3' => 'status_4',     // Đã hủy
            '4' => 'status_1',     // Đang xử lý
            '5' => 'status_2',     // Đã xử lý
        ];
        return $arr_complain_TD[$id];
    }

    public function getStatusDelivery($id)
    {
        // "1" => "Chờ xử lý",
        // "4" => "Đang xử lý",
        // "2" => "Đã hoàn thành"
        // "3" => "Hủy"
        $arr_status = [
            "1" => "status_0",
            "2" => "status_2",
            "3" => "status_3",
            "4" => "status_1",
        ];
        return $arr_status[$id];
    }

    public function updatePricePackage($orderPackageDB, $pack)
    {
        $data_order_package = [];

        // Thêm đơn giá cho phần phí vận chuyển quốc tế:
        $data_order_package['unit_price'] = $pack['price'] == null ? 0 : $pack['price'];

        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('thuongdo_uuid', $pack['uid'])->first();
        if (!is_null($customerDB)) {
            $data_order_package['customer_id'] = (int) $customerDB->id;
        }

        $data_order_package['code_po'] = $pack['ma_dat_hang'];
        $data_order_package['bill_code'] = $pack['ma_van_don'];

        $so_kienTD = $pack['so_kien'] == null ? 0 : $pack['so_kien'];
        $data_order_package['package_number'] = $so_kienTD;

        $data_order_package['order_cost'] = $pack['gia_tri_bao_hiem'] == null ? 0 : $pack['gia_tri_bao_hiem'];

        $kiem_demTD =  $pack['kiem_dem'] == null ? 0 : $pack['kiem_dem'];
        $phi_kiem_demTD =  $pack['phi_kiem_dem'] == null ? 0 : $pack['phi_kiem_dem'];
        $data_order_package['inspection_cost'] = $phi_kiem_demTD; // Phí kiểm đếm
        $data_order_package['is_inspection'] = $kiem_demTD; // Có kiểm đếm hay không


        $bao_hiemTD =  $pack['bao_hiem'] == null ? 0 : $pack['bao_hiem'];
        $phi_bao_hiemTD =  $pack['phi_bao_hiem'] == null ? 0 : $pack['phi_bao_hiem'];
        $data_order_package['insurance_cost'] = $phi_bao_hiemTD; // Phí bảo hiểm
        $data_order_package['is_insurance'] = $bao_hiemTD; // Có bảo hiểm hay không


        $dong_goTD =  $pack['dong_go'] == null ? 0 : $pack['dong_go'];
        $phi_dong_goTD =  $pack['phi_dong_go'] == null ? 0 : $pack['phi_dong_go'];
        $data_order_package['woodworking_cost'] = $phi_dong_goTD; // Phí đóng gỗ
        $data_order_package['is_woodworking'] = $dong_goTD; // Có đóng gỗ hay không


        $chieu_caoTD  =  $pack['chieu_cao'] == null ? 0 : $pack['chieu_cao'];
        $chieu_daiTD  =  $pack['chieu_dai'] == null ? 0 : $pack['chieu_dai'];
        $chieu_rongTD =  $pack['chieu_rong'] == null ? 0 : $pack['chieu_rong'];
        $data_order_package['height'] = $chieu_caoTD;
        $data_order_package['length'] = $chieu_daiTD;
        $data_order_package['width'] = $chieu_rongTD;

        if ($pack['tinh_theo'] == 1) {
            $data_order_package['weight'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
            $data_order_package['weight_real'] = $pack['total_kg_round'];
        }

        // tinh_theo:
        // 1 - Trọng lượng
        // 2 - Thể tích

        $data_order_package['type_quantitative'] = 'weight'; // Tính theo cái nào
        if ($pack['tinh_theo'] == 2) {
            $data_order_package['type_quantitative'] = 'volumn';
            $data_order_package['thuongdo_volumn'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
        }

        $is_deliveryTD = $pack['send_yc_ship'] == null ? 0 : $pack['send_yc_ship'];
        $data_order_package['is_delivery'] = $is_deliveryTD;
        $data_order_package['delivery_cost'] = 0;
        $data_order_package['delivery_type'] = 'normal';
        $data_order_package['in_transit'] = $pack['hang_van_chuyen'];
        $data_order_package['transporter_id'] = null;

        $data_order_package['international_shipping_cost'] = $pack['phi_can_nang'] == null ? 0 : $pack['phi_can_nang'];
        $data_order_package['china_shipping_cost'] = $pack['phi_china'];

        $chong_socTD = $pack['chong_soc'] == null ? 0 : $pack['chong_soc'];
        $phi_chong_socTD = $pack['phi_chong_soc'] == null ? 0 : $pack['phi_chong_soc'];
        $data_order_package['is_shock_proof'] = $chong_socTD;
        $data_order_package['shock_proof_cost'] = $phi_chong_socTD;

        $data_order_package['storage_cost'] = 0; // Phí lưu kho, chưa rõ

        $phi_chiet_khauTD =  $pack['phi_chiet_khau'] == null ? 0 : $pack['phi_chiet_khau'];
        $data_order_package['discount_cost'] = $phi_chiet_khauTD; // Giá chiết khẩu (giảm cái phí vận chuyển QT)
        $phan_tram_chiet_khauTD =  $pack['phan_tram_chiet_khau'] == null ? 0 : $pack['phan_tram_chiet_khau'];
        $data_order_package['discount_percent'] = $phan_tram_chiet_khauTD; // % chiết khẩu

        $data_order_package['amount'] = 0; // đang chưa rõ, Tổng phí vận chuyển


        $data_order_package['note'] = $pack['ghichu'];
        $data_order_package['note_ordered'] = $pack['ghichu_kh'];
        $data_order_package['description'] = null; // Check lại coi

        // $orderPackageDB->update($data_order_package);
        $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB, $data_order_package) {
            $orderPackageDB->update($data_order_package);
            return $orderPackageDB;
        });
    }

    public function refundOrder($orderDB, $refunds)
    {
        if (count($refunds) == 0) {
            return;
        }

        $refundModel = \App::make('App\Models\Refund');

        dump($orderDB);
        foreach ($refunds as $key => $refund) {
            dd($refund);
        }
    }

    public function removeOrderLive()
    {
        // if (env('APP_ENV') == 'local1') {
            $orderModel = \App::make('App\Models\Order');
            $orderSupplierModel = \App::make('App\Models\OrderSupplier');
            $supplierModel = \App::make('App\Models\Supplier');
            $orderDetailModel = \App::make('App\Models\OrderDetail');
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
            $orderPackageStatusTimeModel = \App::make('App\Models\OrderPackageStatusTime');
            $orderDetailPackageModel = \App::make('App\Models\OrderDetailPackage');
            $activitiModel = \App::make('App\Models\Activity');
            $deliveryModel = \App::make('App\Models\Delivery');
            $deliveryOrderModel = \App::make('App\Models\DeliveryOrder');
            $complainModel = \App::make('App\Models\Complain');
            $complainDetailModel = \App::make('App\Models\ComplainDetail');
            $transactionModel = \App::make('App\Models\Transaction');

            $del_1 = $orderModel->where('thuongdo_id', '>', 0)->forceDelete();
            $del_2 = $orderSupplierModel->where('thuongdo_sync', '>', 0)->forceDelete();
            $del_3 = $supplierModel->where('thuongdo_id', '>', 0)->where('customer_id', '>', 0)->forceDelete();
            $del_4 = $orderDetailModel->where('thuongdo_id', '>', 0)->forceDelete();
            $del_5 = $orderPackageModel->where('thuongdo_id', '>', 0)->forceDelete();
            $del_6 = $orderStatusTimeModel->where('thuongdo_sync', '>', 0)->forceDelete();
            $del_7 = $orderPackageStatusTimeModel->where('thuongdo_id', '>', 0)->forceDelete();
            $del_8 = $orderDetailPackageModel->where('thuongdo_sync', '>', 0)->forceDelete();
            $del_9 = $activitiModel->where('thuongdo_id', '>', 0)->forceDelete();
            $del_10 = $deliveryModel->where('thuongdo_id', '>', 0)->forceDelete();
            $del_11 = $deliveryOrderModel->where('thuongdo_sync', '>', 0)->forceDelete();
            $del_12 = $complainModel->where('thuongdo_id', '>', 0)->forceDelete();
            $del_13 = $complainDetailModel->where('thuongdo_id', '>', 0)->forceDelete();

            // $del_14 = $activitiModel->where('log_name', 'delivery_package')
            //                         ->where('created_at', '>', '2024-02-19 09:39:27')
            //                         ->where('causer_id', '1edc48e2-849e-6de0-85be-00163e0109a7')
            //                         ->forceDelete();

            $del_15 = $transactionModel->where('thuongdo_id', '>', 0)->forceDelete();

            \Log::info('del_1:' . $del_1);
            \Log::info('del_2:' . $del_2);
            \Log::info('del_3:' . $del_3);
            \Log::info('del_4:' . $del_4);
            \Log::info('del_5:' . $del_5);
            \Log::info('del_6:' . $del_6);
            \Log::info('del_7:' . $del_7);
            \Log::info('del_8:' . $del_8);
            \Log::info('del_9:' . $del_9);
            \Log::info('del_10:' . $del_10);
            \Log::info('del_11:' . $del_11);
            \Log::info('del_12:' . $del_12);
            \Log::info('del_13:' . $del_13);
            // \Log::info('del_14:' . $del_14);
            \Log::info('del_15:' . $del_15);
        // }
    }

    public function removeOrderLiveV2()
    {
        // if (env('APP_ENV') == 'local1') {
            $orderModel = \App::make('App\Models\Order');
            $orderSupplierModel = \App::make('App\Models\OrderSupplier');
            $supplierModel = \App::make('App\Models\Supplier');
            $orderDetailModel = \App::make('App\Models\OrderDetail');
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
            $orderPackageStatusTimeModel = \App::make('App\Models\OrderPackageStatusTime');
            $orderDetailPackageModel = \App::make('App\Models\OrderDetailPackage');
            $activitiModel = \App::make('App\Models\Activity');
            $deliveryModel = \App::make('App\Models\Delivery');
            $deliveryOrderModel = \App::make('App\Models\DeliveryOrder');
            $complainModel = \App::make('App\Models\Complain');
            $complainDetailModel = \App::make('App\Models\ComplainDetail');
            $transactionModel = \App::make('App\Models\Transaction');

            $del_1  = $orderModel->truncate();
            $del_2  = $orderSupplierModel->truncate();
            $del_4  = $orderDetailModel->truncate();
            $del_5  = $orderPackageModel->truncate();
            $del_6  = $orderStatusTimeModel->truncate();
            $del_7  = $orderPackageStatusTimeModel->truncate();
            $del_8  = $orderDetailPackageModel->truncate();
            $del_9  = $activitiModel->truncate();
            $del_10 = $deliveryModel->truncate();
            $del_11 = $deliveryOrderModel->truncate();
            $del_12 = $complainModel->truncate();
            $del_13 = $complainDetailModel->truncate();
            $del_15 = $transactionModel->truncate();
        // }
    }

    public function syncCarrierLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/carriers';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => (int) $request->get('page'),
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            dump(count($result));
            $transporterModel = \App::make('App\Models\Transporter');
            foreach ($result as $key => $item) {
                $data_transporter = [
                    'name'                  => $item['name'],
                    'short_name'            => $item['code'],
                    'is_delivery_type'      => 1,
                    'is_get_delivery_price' => 0,
                    'order'                 => 3,
                    'has_children'          => 0,
                    'country'               => 'cn',
                    'thuongdo_id'           => $item['id'],
                    'thuongdo_hidden'       => $item['hidden'] == null ? 0 : 1,
                ];

                $transporterDB = $transporterModel->where('short_name', $item['code'])->first();

                if (is_null($transporterDB)) {
                    $transporterDB = $transporterModel->create($data_transporter);
                } else {
                    $transporterDB->thuongdo_id = $item['id'];
                    $transporterDB->thuongdo_hidden = $item['hidden'] == null ? 0 : 1;
                    $transporterDB->save();
                }
            }
        }
    }

    public function syncCategoryLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/package-category';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $organizationModel = \App::make('App\Models\Organization');
            $categoryModel = \App::make('App\Models\Category');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();
            foreach ($result as $key => $item) {
                $data_category = [
                    'name'                => $item['name_vn'],
                    'organization_id'     => $organizationTD->id,
                    'thuongdo_id'         => $item['id'],
                    'thuongdo_name_china' => $item['name_china'],
                    'thuongdo_name_en'    => $item['name_en'],
                ];

                $categoryDB = $categoryModel->where('name', $item['name_vn'])->first();

                if (is_null($categoryDB)) {
                    $categoryDB = $categoryModel->create($data_category);
                } else {
                    $categoryDB->thuongdo_id         = $item['id'];
                    $categoryDB->thuongdo_name_china = $item['name_china'];
                    $categoryDB->thuongdo_name_en    = $item['name_en'];
                    $categoryDB->save();
                }
            }
        }
    }

    // Không dùng
    public function syncOrderSupplierLive(Request $request)
    {
        return;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/orders-supplier';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 2000,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $orderSupplierModel = \App::make('App\Models\OrderSupplier');
            foreach ($result as $key => $item) {
                // dd($item);

                // Key: phi_ship,

                // Báo a Tùng các key sau:
                // Phí đặt hàng, phí chiết khấu, phí kiểm đếm, phí Ship TQ

                // Hỏi lại a ý cái phí ship QT con TD có đang lưu giống HV ko

                if ($item['dong_go']) {
                    // dd($order);
                }
            }
        }
    }

    // Đồng bộ kiện
    public function syncOrderPackageLive(Request $request)
    {
        $pageCron = (int)$request->page;
        $typePack = (int)$request->type;

        if ($typePack != 2 && $typePack != 3) {
            dd("Lỗi");
        }

        $maxPage = 5;
        if ($typePack == 3) {
            $maxPage = 20;
        }

        for ($i=0; $i < $maxPage; $i++) {
            $job = new \App\Jobs\SyncTD\SyncPackage($i, $typePack);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        }
        return;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/package';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'created',
                    'contents' => '1728266460',
                ],
                [
                    'name'     => 'type',
                    'contents' => $typePack,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $orderModel = \App::make('App\Models\Order');
            $customerModel = \App::make('App\Models\Customer');
            $warehouseModel = \App::make('App\Models\Warehouse');
            $categoryModel = \App::make('App\Models\Category');
            $userModel = \App::make('App\Models\User');

            foreach ($result as $key => $item) {

                // if ($item['ma_van_don'] == '78835981850228') {
                //     dd('done');
                // } else {
                //     continue;
                // }

                // dump($item);
                $pack = $item;
                $orderPackageDB = $orderPackageModel->where('thuongdo_id', $item['id'])->first();
                if (is_null($orderPackageDB)) {
                    $customerDB = $customerModel->where('thuongdo_uuid', $item['uid'])->first();
                    if (is_null($customerDB)) {
                        \Log::info('Không có khách kiện page: ' . $pageCron);
                    }

                    $pack = $item;

                    $data_order_package = [
                        'order_id'   => $item['order_id'],
                        'code'       => null,
                        'code_po'    => $pack['ma_dat_hang'],
                        'bill_code'  => $pack['ma_van_don']
                    ];

                    $data_order_package['delivery_id'] = null; // Chưa có

                    $data_order_package['organization_id'] = 1;

                    $arr_type_order_pack = [
                        '2'  => 'App\Models\Consignment',
                        '3'  => null
                    ];
                    $data_order_package['order_type'] = $arr_type_order_pack[$pack['type']];

                    // Phần kho xem bị trung với cái truso_id ko
                    // Lấy kho VN
                    $warehouseVNDB = $warehouseModel->where('thuongdo_id', $pack['truso_id'])->first();
                    if (!is_null($warehouseVNDB)) {
                        $data_order_package['warehouse_id'] = $warehouseVNDB->id; // CHeck xem nó có không
                    }

                    // Lấy kho TQ
                    $warehouseCNDB = $warehouseModel->where('thuongdo_id', $pack['kho_tq_id'])->first();
                    if (!is_null($warehouseCNDB)) {
                        $data_order_package['warehouse_cn'] = $warehouseCNDB->id; // CHeck xem nó có không
                    }

                    $data_order_package['product_name'] = $pack['ten_hang_hoa'];

                    $so_kienTD = $pack['so_kien'] == null ? 0 : $pack['so_kien'];
                    $data_order_package['package_number'] = $so_kienTD;

                    $data_order_package['order_cost'] = $pack['gia_tri_bao_hiem'] == null ? 0 : $pack['gia_tri_bao_hiem'];

                    $kiem_demTD =  $pack['kiem_dem'] == null ? 0 : $pack['kiem_dem'];
                    $phi_kiem_demTD =  $pack['phi_kiem_dem'] == null ? 0 : $pack['phi_kiem_dem'];
                    $data_order_package['inspection_cost'] = $phi_kiem_demTD; // Phí kiểm đếm
                    $data_order_package['is_inspection'] = $kiem_demTD; // Có kiểm đếm hay không


                    $bao_hiemTD =  $pack['bao_hiem'] == null ? 0 : $pack['bao_hiem'];
                    $phi_bao_hiemTD =  $pack['phi_bao_hiem'] == null ? 0 : $pack['phi_bao_hiem'];
                    $data_order_package['insurance_cost'] = $phi_bao_hiemTD; // Phí bảo hiểm
                    $data_order_package['is_insurance'] = $bao_hiemTD; // Có bảo hiểm hay không


                    $dong_goTD =  $pack['dong_go'] == null ? 0 : $pack['dong_go'];
                    $phi_dong_goTD =  $pack['phi_dong_go'] == null ? 0 : $pack['phi_dong_go'];
                    $data_order_package['woodworking_cost'] = $phi_dong_goTD; // Phí đóng gỗ
                    $data_order_package['is_woodworking'] = $dong_goTD; // Có đóng gỗ hay không



                    $chieu_caoTD  =  $pack['chieu_cao'] == null ? 0 : $pack['chieu_cao'];
                    $chieu_daiTD  =  $pack['chieu_dai'] == null ? 0 : $pack['chieu_dai'];
                    $chieu_rongTD =  $pack['chieu_rong'] == null ? 0 : $pack['chieu_rong'];
                    $data_order_package['height'] = $chieu_caoTD;
                    $data_order_package['length'] = $chieu_daiTD;
                    $data_order_package['width'] = $chieu_rongTD;

                    if ($pack['tinh_theo'] == 1) {
                        $data_order_package['weight'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
                        $data_order_package['weight_real'] = $pack['total_kg_round'];
                    }

                    // tinh_theo:
                    // 1 - Trọng lượng
                    // 2 - Thể tích

                    $data_order_package['type_quantitative'] = 'weight'; // Tính theo cái nào
                    if ($pack['tinh_theo'] == 2) {
                        $data_order_package['type_quantitative'] = 'volumn';
                        $data_order_package['thuongdo_volumn'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
                    }

                    $is_deliveryTD = $pack['send_yc_ship'] == null ? 0 : $pack['send_yc_ship'];
                    $data_order_package['is_delivery'] = $is_deliveryTD;
                    $data_order_package['delivery_cost'] = 0;
                    $data_order_package['delivery_type'] = 'normal';
                    $data_order_package['in_transit'] = $pack['hang_van_chuyen'];
                    $data_order_package['transporter_id'] = null;

                    $exchange_rateTD = $pack['ti_gia'] == null ? 0 : $pack['ti_gia'];
                    // $exchange_rateTD = $orderDB->exchange_rate;
                    $data_order_package['exchange_rate'] = $exchange_rateTD;


                    $data_order_package['international_shipping_cost'] = $pack['phi_can_nang'] == null ? 0 : $pack['phi_can_nang'];
                    $data_order_package['china_shipping_cost'] = $pack['phi_china'];

                    $chong_socTD = $pack['chong_soc'] == null ? 0 : $pack['chong_soc'];
                    $phi_chong_socTD = $pack['phi_chong_soc'] == null ? 0 : $pack['phi_chong_soc'];
                    $data_order_package['is_shock_proof'] = $chong_socTD;
                    $data_order_package['shock_proof_cost'] = $phi_chong_socTD;

                    $data_order_package['storage_cost'] = 0; // Phí lưu kho, chưa rõ



                    $phi_chiet_khauTD =  $pack['phi_chiet_khau'] == null ? 0 : $pack['phi_chiet_khau'];
                    $data_order_package['discount_cost'] = $phi_chiet_khauTD; // Giá chiết khẩu (giảm cái phí vận chuyển QT)
                    $phan_tram_chiet_khauTD =  $pack['phan_tram_chiet_khau'] == null ? 0 : $pack['phan_tram_chiet_khau'];
                    $data_order_package['discount_percent'] = $phan_tram_chiet_khauTD; // % chiết khẩu

                    $data_order_package['amount'] = 0; // đang chưa rõ, Tổng phí vận chuyển


                    $data_order_package['note'] = $pack['ghichu'];
                    $data_order_package['note_ordered'] = $pack['ghichu_kh'];
                    $data_order_package['description'] = null; // Check lại coi




                    if (!is_null($customerDB)) {
                        $data_order_package['customer_id'] = $customerDB->id;
                    } else {
                        \Log::info('Không tim khách');
                    }

                    // $data_order_package['customer_delivery_id'] = $orderDB->customer_delivery_id; // Xem lúc lưu tại app/Http/Controllers/PackageController.php




                    // Danh mục
                    $categoryDB = $categoryModel->where('thuongdo_id', $pack['cate_id'])->first();
                    if (!is_null($categoryDB)) {
                        $data_order_package['category_id'] = $categoryDB->id;
                        $data_order_package['category'] = $categoryDB->name;
                    }



                    // Nhân viên
                    // nhan_vien_cs_uid
                    // nhan_vien_tu_van_uid
                    // nhan_vien_kinh_doanh_uid

                    $staffCareDB = $userModel->where('thuongdo_uuid', $pack['nhan_vien_cs_uid'])->first();
                    if (!is_null($staffCareDB)) {
                        $data_order_package['staff_care_id'] = $staffCareDB->id;
                    }

                    $staffCounselorDB = $userModel->where('thuongdo_uuid', $pack['nhan_vien_tu_van_uid'])->first();
                    if (!is_null($staffCounselorDB)) {
                        $data_order_package['staff_counselor_id'] = $staffCounselorDB->id;
                    }

                    // Chưa tìm dc đối tượng này
                    $staffOrderDB = $userModel->where('thuongdo_uuid', $pack['uid_xu_ly'])->first();
                    if (!is_null($staffOrderDB)) {
                        $data_order_package['staff_order_id'] = $staffOrderDB->id;
                    }

                    // Đơn giá phí vận chuyển quốc tế
                    $data_order_package['unit_price'] = $pack['price'] == null ? 0 : $pack['price'];

                    // Status

                    $data_order_package['status'] = $this->getStatusPack($pack['status']);

                    // ID
                    $data_order_package['thuongdo_id'] = $pack['id'];

                    // Thời gian tạo
                    $data_order_package['created_at'] =  date('Y-m-d H:i:s', $pack['created']);

                    // staff_group_id
                    if (!is_null($customerDB)) {
                        $data_order_package['staff_group_id'] = $customerDB->staff_group_id;
                    }

                    $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageModel, $data_order_package) {
                        $orderPackageDB = $orderPackageModel->create($data_order_package);
                        return $orderPackageDB;
                    });
                } else {
                    $orderPackageDB->status = $this->getStatusPack($pack['status']);

                    $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB) {
                        $orderPackageDB->save();
                        return $orderPackageDB;
                    });

                    // Cập nhật giá
                    $this->updatePricePackage($orderPackageDB, $item);
                }
            }
        }
    }

    // Đồng bộ kiện Last
    public function syncPackageTDLast(Request $request)
    {
        $validationRules = [
            'pcode' => 'required',
            'token' => 'required',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $token = $params['token'];
        if ($token != 'Mm28391acnqmxnqueouxnqjr2xjqjrxhr1888') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }


        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/package';

        $options = [
            'multipart' => [
                [
                    'name'     => 'pcode',
                    'contents' => 'YT7495395808731',
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        dd($result);
    }

    public function syncComplainTypeLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/complain-type';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 2001,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $complainTypeModel = \App::make('App\Models\ComplainType');
            $orderModel = \App::make('App\Models\Order');

            $organizationModel = \App::make('App\Models\Organization');
            $categoryModel = \App::make('App\Models\Category');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            foreach ($result as $key => $item) {
                $complainTypeDB = $complainTypeModel->where('name', $item['name'])->first();
                if (is_null($complainTypeDB)) {
                    $data_complain_type = [
                        'thuongdo_id'     => $item['id'],
                        'name'            => $item['name'],
                        'organization_id' => $organizationTD->id,
                    ];
                    $complainTypeDB = $complainTypeModel->create($data_complain_type);
                } else {
                    $complainTypeDB->thuongdo_id = $item['id'];
                    $complainTypeDB->save();
                }
            }
        }
    }

    // Không dùng
    public function syncDeliveryLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/delivery';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 5,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $deliveryModel = \App::make('App\Models\Delivery');
            $customerModel = \App::make('App\Models\Customer');
            $customerDeliveryModel = \App::make('App\Models\CustomerDelivery');
            $organizationModel = \App::make('App\Models\Organization');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            foreach ($result as $key => $delivery) {
                // dump($delivery);
                $deliveryDB = $deliveryModel->where('thuongdo_id', $delivery['id'])->first();

                if (is_null($deliveryDB)) {
                    $data_delivery = [];

                    // ID
                    $data_delivery['thuongdo_id'] = $delivery['id'];
                    $data_delivery['organization_id'] = $organizationTD->id;
                    // Thời gian tạo
                    $data_delivery['created_at'] =  date('Y-m-d H:i:s', $delivery['created']);
                    // Khách hàng

                    // Thêm customer_id
                    $customerDB = $customerModel->where('thuongdo_uuid', $delivery['uid'])->first();
                    if (is_null($customerDB)) {
                        \Log::info('Không có khách hàng không lưu giao hàng luôn: ' .  $delivery['id']);
                        continue; // Chạy vòng lặp mới
                    }
                    $data_delivery['customer_id'] = $customerDB->id;

                    // Địa chỉ giao hàng shipping_address_id
                    $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $delivery['shipping_address_id'])->first();
                    if (is_null($customerDeliveryDB)) {
                        \Log::info('Không địa chỉ giao hàng của khách không lưu giao hàng luôn: ' .  $delivery['id']);
                        continue; // Chạy vòng lặp mới
                    }
                    $data_delivery['customer_delivery_id'] = $customerDeliveryDB->id;
                    $data_delivery['receiver']             = $customerDeliveryDB->receiver;
                    $data_delivery['address']              = $customerDeliveryDB->address;
                    $data_delivery['phone_number']         = $customerDeliveryDB->phone_number;

                    // Thời gian giao
                    $data_delivery['date'] = $delivery['time_delivery'];

                    // Loại giao nhanh hay chậm type
                    $data_delivery['type'] = 'normal';
                    $data_delivery['payment'] = 'code';

                    // Status
                    // "0" => "Chờ xử lý",
                    // "1" => "Đang xử lý",
                    // "2" => "Hoàn thành"
                    $arr_status = [
                        "0" => "status_0",
                        "1" => "status_1",
                        "2" => "status_2"
                    ];
                    $data_delivery['status'] = array_key_exists($delivery['status'], $arr_status) ? $arr_status[$delivery['status']] : 'Không xác định';
                }
            }
        }
    }

    // Đồng bộ hãng vận chyển Việt Nam
    public function syncCarrierVNLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/delivery-method';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $transporterModel = \App::make('App\Models\Transporter');
            foreach ($result as $key => $item) {
                $data_transporter = [
                    'name'                  => $item['name'],
                    'is_delivery_type'      => 1,
                    'is_get_delivery_price' => 0,
                    'order'                 => 3,
                    'has_children'          => 0,
                    'country'               => 'vi',
                    'thuongdo_id'           => $item['id'],
                ];

                $transporterDB = $transporterModel->where('name', $item['name'])->first();

                if (is_null($transporterDB)) {
                    $transporterDB = $transporterModel->create($data_transporter);
                } else {
                    $transporterDB->thuongdo_id = $item['id'];
                    $transporterDB->save();
                }
            }
        }
    }

    public function syncFundBalanceLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/fund-balance';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $fundModel = \App::make('App\Models\Fund');
            $organizationModel = \App::make('App\Models\Organization');
            $userOnline = \Auth::user();

            try {
                \DB::beginTransaction();
                // Lấy tổ chức TD ra
                $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

                foreach ($result as $key_1 => $item_1) {
                    // Quỹ tiền VN
                    if ($key_1 == 33) {
                        foreach ($item_1['list'] as $key_2 => $item_2) {
                            $data_fund_vn = [
                                'organization_id'         => $organizationTD->id,
                                'name'                    => $item_2['name'],
                                'thuongdo_id'             => (int) $item_2['id'],
                                'type_currency'           => 2, // Tiền ngân hàng
                                'unit_currency'           => 1, // Tiền VND
                                'initial_total_money_in'  => $item_2['tong_khoan_thu'],
                                'initial_total_money_out' => $item_2['tong_khoan_chi'],
                                'initial_balance'         => $item_2['so_du_cuoi_cung'],
                                'current_balance'         => $item_2['so_du_cuoi_cung'],
                                'status'                  => 1
                            ];

                            // Check có cụm từ tiền mặt thì type_currency là tiền mặt
                            if (str_contains($item_2['name'],'Tiền mặt')) {
                                $data_fund_vn['type_currency'] = 1; // Tiền mặt
                            }

                            //Check xem đã có Fund đó chưa
                            $fundDB = $fundModel->where('name', $item_2['name'])->first();

                            if (is_null($fundDB)) {
                                $fundDB = $fundModel->create($data_fund_vn);

                                // Tạo 1 giao dịch khởi tạo ban đầu
                                $fundTransactionModel = \App::make('App\Models\FundTransaction');
                                $fundTransactionService = \App::make('App\Services\FundTransactionService');
                                $data_fund_transaction = [
                                    'code'               => $fundTransactionService->generateRandomCode(),
                                    'organization_id'    => $organizationTD->id,
                                    'user_create_id'     => (int)$userOnline->id,
                                    'type_object'        => 3,
                                    'fund_id'            => (int) $fundDB->id,
                                    'fund_name'          => $fundDB->name,
                                    'fund_type_currency' => (int) $fundDB->type_currency,
                                    'fund_unit_currency' => (int) $fundDB->unit_currency,
                                    'money'              => $item_2['so_du_cuoi_cung'],
                                    'money_update'       => $item_2['so_du_cuoi_cung'],
                                    'balance'            => $item_2['so_du_cuoi_cung'],
                                    'ratio'              => isset($item_2['ratio']) ? $item_2['ratio'] : 0,
                                    'init'               => FundTransactionConstant::INIT_START,
                                    'note'               => 'Khởi tạo số dư ban đầu cho quỹ',
                                    'status'             => 1,
                                    'lock'               => 1
                                ];
                                $fundTransactionDB = $fundTransactionModel->create($data_fund_transaction);
                            } else {
                                $fundDB->thuongdo_id = $item_2['id'];
                                $fundDB->save();
                            }
                        }
                    }

                    // Quỹ tiền CN
                    if ($key_1 == 35) {
                        foreach ($item_1['list'] as $key_2 => $item_2) {
                            $data_fund_vn = [
                                'organization_id'         => $organizationTD->id,
                                'name'                    => $item_2['name'],
                                'thuongdo_id'             => $item_2['id'],
                                'type_currency'           => 2, // Tiền ngân hàng
                                'unit_currency'           => 2, // Tiền tệ
                                'initial_total_money_in'  => $item_2['tong_khoan_thu'],
                                'initial_total_money_out' => $item_2['tong_khoan_chi'],
                                'initial_balance'         => $item_2['so_du_cuoi_cung'],
                                'current_balance'         => $item_2['so_du_cuoi_cung'],
                                'status'                  => 1
                            ];

                            //Check xem đã có Fund đó chưa
                            $fundDB = $fundModel->where('name', $item_2['name'])->first();

                            if (is_null($fundDB)) {
                                $fundDB = $fundModel->create($data_fund_vn);

                                // Tạo 1 giao dịch khởi tạo ban đầu
                                $fundTransactionModel = \App::make('App\Models\FundTransaction');
                                $fundTransactionService = \App::make('App\Services\FundTransactionService');
                                $data_fund_transaction = [
                                    'code'               => $fundTransactionService->generateRandomCode(),
                                    'organization_id'    => $organizationTD->id,
                                    'user_create_id'     => (int)$userOnline->id,
                                    'type_object'        => 3,
                                    'fund_id'            => (int) $fundDB->id,
                                    'fund_name'          => $fundDB->name,
                                    'fund_type_currency' => (int) $fundDB->type_currency,
                                    'fund_unit_currency' => (int) $fundDB->unit_currency,
                                    'money'              => $item_2['so_du_cuoi_cung'],
                                    'money_update'       => $item_2['so_du_cuoi_cung'],
                                    'balance'            => $item_2['so_du_cuoi_cung'],
                                    'ratio'              => isset($item_2['ratio']) ? $item_2['ratio'] : 0,
                                    'init'               => FundTransactionConstant::INIT_START,
                                    'note'               => 'Khởi tạo số dư ban đầu cho quỹ',
                                    'status'             => 1,
                                    'lock'               => 1
                                ];
                                $fundTransactionDB = $fundTransactionModel->create($data_fund_transaction);
                            } else {
                                $fundDB->thuongdo_id = $item_2['id'];
                                $fundDB->save();
                            }
                        }
                    }
                }
                \DB::commit();
                return resSuccessWithinData($result);
            } catch (Exception $e) {
                \DB::rollback();
                throw $e;
            }
        }
    }

    public function syncPaymentTypeLive(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/payment-type';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 1,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);


        if ($result && count($result)) {
            $fundTypePayModel = \App::make('App\Models\FundTypePay');
            $organizationModel = \App::make('App\Models\Organization');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();
            foreach ($result as $key_1 => $item_1) {
                // Khoản thu
                if ($key_1 == 1) {
                    foreach ($item_1['list'] as $key_2 => $item_2) {
                        // dump($item_2);
                        $data_fund_vn = [
                            'organization_id' => $organizationTD->id,
                            'name'            => $item_2['name'],
                            'thuongdo_id'     => $item_2['id'],
                            'type'            => 1, // Khoản thu
                            'status'          => 1
                        ];
                        //Check xem đã có Fund đó chưa
                        $fundDB = $fundTypePayModel->where('name', $item_2['name'])->first();

                        if (is_null($fundDB)) {
                            $fundDB = $fundTypePayModel->create($data_fund_vn);
                        } else {
                            $fundDB->thuongdo_id = $item_2['id'];
                            $fundDB->save();
                        }
                    }
                }

                // Khoản chi
                if ($key_1 == 2) {
                    foreach ($item_1['list'] as $key_2 => $item_2) {
                        $data_fund_vn = [
                            'organization_id' => $organizationTD->id,
                            'name'            => $item_2['name'],
                            'thuongdo_id'     => $item_2['id'],
                            'type'            => 2, // Khoản chi
                            'status'          => 1
                        ];

                        //Check xem đã có Fund đó chưa
                        $fundDB = $fundTypePayModel->where('name', $item_2['name'])->first();

                        if (is_null($fundDB)) {
                            $fundDB = $fundTypePayModel->create($data_fund_vn);
                        } else {
                            $fundDB->thuongdo_id = $item_2['id'];
                            $fundDB->save();
                        }
                    }
                }
            }
        }
    }

    public function checkStaffOrder(Request $request)
    {
        $staffCode = $request->staff_code;
        $staffModel = \App::make('App\Models\Staff');
        $staff = $staffModel->select('users.id', 'users.code', 'users.name')->where('users.code', $staffCode)
                ->join('model_has_roles', 'model_has_roles.model_id', 'users.id')
                ->join('roles', 'roles.id', 'model_has_roles.role_id')
                ->where('roles.name', 'like', '%nhan-vien-dat-hang%')
                ->first();
        if (is_null($staff)) {
            $result = ['error' => 'Nhân viên không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        
        return resSuccessWithinData([
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'staff_code' => $staff->code
        ]);
    }

    public function syncPackStatusPending(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            PackageConstant::STATUS_PENDING,
            PackageConstant::STATUS_ON_THE_WAY_CHINA,
            // PackageConstant::STATUS_IN_GUANGZHOU,
        ];
        $packDBs = $packModel->whereIn('status', $arr_status)
                            ->where('order_type', 'App\Models\Order')
                            ->whereNotNull('bill_code')
                            ->whereNotNull('thuongdo_id')
                            ->get();

        if (count($packDBs) > 0) {
            foreach ($packDBs as $key => $packDB) {
                // $this->pushToOneWarehouse($packDB);

                $job = new \App\Jobs\OneWarehouse\SyncPackStatusPending($packDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    public function pushToOneWarehouse($packDB)
    {
        // Nếu không có mã vận đơn thì bỏ qua
        if ($packDB->bill_code == null) {
            return;
        }

        $packType = 'order';
        $packDetails = [];
        foreach($packDB->orderDetailsPackage as $key=>$orderDetailPack){
            $orderDetail = $orderDetailPack->orderDetail;
            array_push($packDetails, [
                'name' => data_get($orderDetail, 'name'),
                'image' => data_get($orderDetail, 'image'),
                'classification' => data_get($orderDetail, 'classification'),
                'quantity' => $orderDetailPack->quantity,
                'category_id' => data_get($orderDetail, 'category_id'),
                'category_name' => $orderDetail ? data_get($orderDetail->category, 'name') : "",
            ]);
        }
        $customerDB = $packDB->customer;
        $warehouse_id = $packDB->warehouse_id;
        if (
            $packDB->warehouse_id == 2 ||
            $packDB->warehouse_id == 6 ||
            $packDB->warehouse_id == null
        ) {
            \Log::info('set lại kho 1');
            $warehouse_id = 1;
        }
        $temp = [
            'pack_id'        => $packDB->id, // ID bên HV
            'pack_code'      => $packDB->bill_code, // Mã vận đơn
            'pack_type'      => $packType, // Loại hàng,
            'warehouse_id'   => $warehouse_id, // Kho trả hàng
            'order_code'     => $packDB->order_code, // Mã đơn hàng
            'customer_id'    => $packDB->customer_id,
            'customer_code'  => $customerDB ? $customerDB->code : '',
            'customer_name'  => $customerDB ? $customerDB->name : '',
            'delivery_type'  => $packDB->delivery_type, // Hình thức vận chuyển
            'is_inspection'  => (int) $packDB->is_inspection,
            'is_woodworking' => (int) $packDB->is_woodworking,
            'is_shock_proof' => (int) $packDB->is_shock_proof,
            'is_insurance'   => 0,
            'staff_order_id' => $packDB->staff_order_id, // Nhân viên đặt hàng
            'package_number' => $packDB->package_number, // Số kiện
            'pack_detail'    => $packDetails, // Danh sách sản phẩm của kiện
            'company_id'     => data_get($customerDB, 'organization_id'),
            'category_id'    => $packDB->category_id,
            'pack_code_po'   => $packDB->code_po,

            'height'      => $packDB->height,
            'length'      => $packDB->length,
            'width'       => $packDB->width,
            'weight'      => $packDB->weight,
            'weight_real' => $packDB->weight_real,
            'volumn'      => $packDB->thuongdo_volumn,
        ];

        $guzzle = new Guzzle;
        $options = [
            'headers' => [
                'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
            ],
            'json' => $temp
        ];
        $url = env('BASE_URL_DONGDONG') . 'open/sync/pack';
        $request = $guzzle->request('POST', $url, $options);
        $reponse = json_decode($request->getBody());

        if($reponse->code == 200) {
            $packDB->code_warehouse = data_get($reponse->data, 'code_warehouse');
            $packDB->status = PackageConstant::STATUS_ON_THE_WAY_CHINA;
            $packDB->save();
        }
    }

    public function syncPackStatusInChina(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            PackageConstant::STATUS_IN_GUANGZHOU,
        ];
        $packDBs = $packModel->whereIn('status', $arr_status)
                            ->where('order_type', 'App\Models\Order')
                            ->whereNotNull('bill_code')
                            ->whereNotNull('thuongdo_id')
                            ->get();

        if (count($packDBs) > 0) {
            foreach ($packDBs as $key => $packDB) {
                // $this->pushToOneWarehouseInChina($packDB);

                $job = new \App\Jobs\OneWarehouse\SyncPackStatusInChina($packDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    public function pushToOneWarehouseInChina($packDB)
    {
        // Nếu không có mã vận đơn thì bỏ qua
        if ($packDB->bill_code == null) {
            return;
        }

        $packType = 'order';
        $packDetails = [];
        foreach($packDB->orderDetailsPackage as $key=>$orderDetailPack){
            $orderDetail = $orderDetailPack->orderDetail;
            array_push($packDetails, [
                'name' => data_get($orderDetail, 'name'),
                'image' => data_get($orderDetail, 'image'),
                'classification' => data_get($orderDetail, 'classification'),
                'quantity' => $orderDetailPack->quantity,
                'category_id' => data_get($orderDetail, 'category_id'),
                'category_name' => $orderDetail ? data_get($orderDetail->category, 'name') : "",
            ]);
        }
        $customerDB = $packDB->customer;
        $warehouse_id = $packDB->warehouse_id;
        if (
            $packDB->warehouse_id == 2 ||
            $packDB->warehouse_id == 6 ||
            $packDB->warehouse_id == null
        ) {
            \Log::info('set lại kho');
            $warehouse_id = 1;
        }
        $temp = [
            'pack_id'        => $packDB->id, // ID bên HV
            'pack_code'      => $packDB->bill_code, // Mã vận đơn
            'pack_type'      => $packType, // Loại hàng,
            'warehouse_id'   => $warehouse_id, // Kho trả hàng
            'order_code'     => $packDB->order_code, // Mã đơn hàng
            'customer_id'    => $packDB->customer_id,
            'customer_code'  => $customerDB ? $customerDB->code : '',
            'customer_name'  => $customerDB ? $customerDB->name : '',
            'delivery_type'  => $packDB->delivery_type, // Hình thức vận chuyển
            'is_inspection'  => (int) $packDB->is_inspection,
            'is_woodworking' => (int) $packDB->is_woodworking,
            'is_shock_proof' => (int) $packDB->is_shock_proof,
            'is_insurance'   => 0,
            'staff_order_id' => $packDB->staff_order_id, // Nhân viên đặt hàng
            'package_number' => $packDB->package_number, // Số kiện
            'pack_detail'    => $packDetails, // Danh sách sản phẩm của kiện
            'company_id'     => data_get($customerDB, 'organization_id'),
            'category_id'    => $packDB->category_id,
            'pack_code_po'   => $packDB->code_po,

            'height'      => $packDB->height,
            'length'      => $packDB->length,
            'width'       => $packDB->width,
            'weight'      => $packDB->weight,
            'weight_real' => $packDB->weight_real,
            'volumn'      => $packDB->thuongdo_volumn,
        ];

        $guzzle = new Guzzle;
        $options = [
            'headers' => [
                'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
            ],
            'json' => $temp
        ];
        $url = env('BASE_URL_DONGDONG') . 'open/sync/pack-in-china';
        $request = $guzzle->request('POST', $url, $options);
        $reponse = json_decode($request->getBody());

        if($reponse->code == 200) {
            $packDB->code_warehouse = data_get($reponse->data, 'code_warehouse');
            $packDB->save();
        }
    }


    public function syncPackConsigment(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            PackageConstant::STATUS_PENDING,
            PackageConstant::STATUS_ON_THE_WAY_CHINA,
            // PackageConstant::STATUS_IN_GUANGZHOU,
        ];
        $packDBs = $packModel->whereIn('status', $arr_status)
                            ->where('order_type', 'App\Models\Consignment')
                            ->whereNotNull('bill_code')
                            ->whereNotNull('thuongdo_id')
                            ->where('created_at', '>', '2024-10-07 08:15:15')
                            ->get();
        \Log::info('syncPackConsigment');

        if (count($packDBs) > 0) {
            \Log::info(count($packDBs));
            foreach ($packDBs as $key => $packDB) {
                // $this->pushToOneWarehouseConsigment($packDB);

                $job = new \App\Jobs\OneWarehouse\SyncPackConsigmentStatusPending($packDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    public function syncPackConsigmentInChina(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            // PackageConstant::STATUS_PENDING,
            // PackageConstant::STATUS_ON_THE_WAY_CHINA,
            PackageConstant::STATUS_IN_GUANGZHOU,
        ];
        $packDBs = $packModel->whereIn('status', $arr_status)
                            ->where('order_type', 'App\Models\Consignment')
                            ->whereNotNull('bill_code')
                            ->whereNotNull('thuongdo_id')
                            ->where('created_at', '>', '2024-10-07 08:15:15')
                            ->get();
        \Log::info('syncPackConsigmentInChina');

        if (count($packDBs) > 0) {
            \Log::info(count($packDBs));
            foreach ($packDBs as $key => $packDB) {
                // $this->pushToOneWarehouseConsigment($packDB);

                $job = new \App\Jobs\OneWarehouse\SyncPackConsigmentInChina($packDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    public function pushToOneWarehouseConsigment($packDB)
    {
        // Nếu không có mã vận đơn thì bỏ qua
        if ($packDB->bill_code == null) {
            return;
        }

        $packType = 'consignment';
        $packDetails = [];
        foreach($packDB->orderDetailsPackage as $key=>$orderDetailPack){
            $orderDetail = $orderDetailPack->orderDetail;
            array_push($packDetails, [
                'name' => data_get($orderDetail, 'name'),
                'image' => data_get($orderDetail, 'image'),
                'classification' => data_get($orderDetail, 'classification'),
                'quantity' => $orderDetailPack->quantity,
                'category_id' => data_get($orderDetail, 'category_id'),
                'category_name' => $orderDetail ? data_get($orderDetail->category, 'name') : "",
            ]);
        }
        $customerDB = $packDB->customer;
        $warehouse_id = $packDB->warehouse_id;
        if (
            $packDB->warehouse_id == 2 ||
            $packDB->warehouse_id == 6 ||
            $packDB->warehouse_id == null
        ) {
            \Log::info('set lại kho 1');
            $warehouse_id = 1;
        }
        $temp = [
            'pack_id'        => $packDB->id, // ID bên HV
            'pack_code'      => $packDB->bill_code, // Mã vận đơn
            'pack_type'      => $packType, // Loại hàng,
            'warehouse_id'   => $warehouse_id, // Kho trả hàng
            'order_code'     => $packDB->order_code, // Mã đơn hàng
            'customer_id'    => $packDB->customer_id,
            'customer_code'  => $customerDB ? $customerDB->code : '',
            'customer_name'  => $customerDB ? $customerDB->name : '',
            'delivery_type'  => $packDB->delivery_type, // Hình thức vận chuyển
            'is_inspection'  => (int) $packDB->is_inspection,
            'is_woodworking' => (int) $packDB->is_woodworking,
            'is_shock_proof' => (int) $packDB->is_shock_proof,
            'is_insurance'   => 0,
            'staff_order_id' => $packDB->staff_order_id, // Nhân viên đặt hàng
            'package_number' => $packDB->package_number, // Số kiện
            'pack_detail'    => $packDetails, // Danh sách sản phẩm của kiện
            'company_id'     => data_get($customerDB, 'organization_id'),
            'category_id'    => $packDB->category_id,
            'pack_code_po'   => $packDB->code_po,

            'height'      => $packDB->height,
            'length'      => $packDB->length,
            'width'       => $packDB->width,
            'weight'      => $packDB->weight,
            'weight_real' => $packDB->weight_real,
            'volumn'      => $packDB->thuongdo_volumn,
        ];

        $guzzle = new Guzzle;
        $options = [
            'headers' => [
                'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
            ],
            'json' => $temp
        ];
        $url = env('BASE_URL_DONGDONG') . 'open/sync/pack-consignment-hot-fix';
        $request = $guzzle->request('POST', $url, $options);
        $reponse = json_decode($request->getBody());

        if($reponse->code == 200) {
            $packDB->code_warehouse = data_get($reponse->data, 'code_warehouse');
            $packDB->status = PackageConstant::STATUS_ON_THE_WAY_CHINA;
            $packDB->save();
        }
    }


    public function syncPackNot(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            PackageConstant::STATUS_PENDING,
            PackageConstant::STATUS_ON_THE_WAY_CHINA,
            // PackageConstant::STATUS_IN_GUANGZHOU,
        ];
        $packDBs = $packModel->whereIn('status', $arr_status)
                            ->whereNull('order_type')
                            ->whereNotNull('bill_code')
                            ->whereNotNull('thuongdo_id')
                            ->get();

        if (count($packDBs) > 0) {
            foreach ($packDBs as $key => $packDB) {
                // $this->pushToOneWarehouseNot($packDB);

                $job = new \App\Jobs\OneWarehouse\SyncPackNotStatusPending($packDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    public function syncPackNotInChina(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            // PackageConstant::STATUS_PENDING,
            // PackageConstant::STATUS_ON_THE_WAY_CHINA,
            PackageConstant::STATUS_IN_GUANGZHOU,
        ];
        $packDBs = $packModel->whereIn('status', $arr_status)
                            ->whereNull('order_type')
                            ->whereNotNull('bill_code')
                            ->whereNotNull('thuongdo_id')
                            ->get();

        if (count($packDBs) > 0) {
            foreach ($packDBs as $key => $packDB) {
                // $this->pushToOneWarehouseNot($packDB);

                $job = new \App\Jobs\OneWarehouse\SyncPackNotInChina($packDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    public function pushToOneWarehouseNot($packDB)
    {
        // Nếu không có mã vận đơn thì bỏ qua
        if ($packDB->bill_code == null) {
            return;
        }

        $packType = 'unconfirmed';
        $packDetails = [];
        foreach($packDB->orderDetailsPackage as $key=>$orderDetailPack){
            $orderDetail = $orderDetailPack->orderDetail;
            array_push($packDetails, [
                'name' => data_get($orderDetail, 'name'),
                'image' => data_get($orderDetail, 'image'),
                'classification' => data_get($orderDetail, 'classification'),
                'quantity' => $orderDetailPack->quantity,
                'category_id' => data_get($orderDetail, 'category_id'),
                'category_name' => $orderDetail ? data_get($orderDetail->category, 'name') : "",
            ]);
        }
        $customerDB = $packDB->customer;
        $warehouse_id = $packDB->warehouse_id;
        if (
            $packDB->warehouse_id == 2 ||
            $packDB->warehouse_id == 6 ||
            $packDB->warehouse_id == null
        ) {
            \Log::info('set lại kho 1');
            $warehouse_id = 1;
        }
        $temp = [
            'pack_id'        => $packDB->id, // ID bên HV
            'pack_code'      => $packDB->bill_code, // Mã vận đơn
            'pack_type'      => $packType, // Loại hàng,
            'warehouse_id'   => $warehouse_id, // Kho trả hàng
            'order_code'     => $packDB->order_code, // Mã đơn hàng
            'customer_id'    => $packDB->customer_id,
            'customer_code'  => $customerDB ? $customerDB->code : '',
            'customer_name'  => $customerDB ? $customerDB->name : '',
            'delivery_type'  => $packDB->delivery_type, // Hình thức vận chuyển
            'is_inspection'  => (int) $packDB->is_inspection,
            'is_woodworking' => (int) $packDB->is_woodworking,
            'is_shock_proof' => (int) $packDB->is_shock_proof,
            'is_insurance'   => 0,
            'staff_order_id' => $packDB->staff_order_id, // Nhân viên đặt hàng
            'package_number' => $packDB->package_number, // Số kiện
            'pack_detail'    => $packDetails, // Danh sách sản phẩm của kiện
            'company_id'     => 1,
            'category_id'    => $packDB->category_id,
            'pack_code_po'   => $packDB->code_po,

            'height'      => $packDB->height,
            'length'      => $packDB->length,
            'width'       => $packDB->width,
            'weight'      => $packDB->weight,
            'weight_real' => $packDB->weight_real,
            'volumn'      => $packDB->thuongdo_volumn,
        ];

        $guzzle = new Guzzle;
        $options = [
            'headers' => [
                'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
            ],
            'json' => $temp
        ];
        $url = env('BASE_URL_DONGDONG') . 'open/sync/pack-not-hot-fix';
        $request = $guzzle->request('POST', $url, $options);
        $reponse = json_decode($request->getBody());

        if($reponse->code == 200) {
            $packDB->code_warehouse = data_get($reponse->data, 'code_warehouse');
            $packDB->status = PackageConstant::STATUS_ON_THE_WAY_CHINA;
            $packDB->save();
        }
    }

    public function syncAllPackToOne(Request $request)
    {
        $job = new \App\Jobs\OneWarehouse\SyncAllPackToOne();
        dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        return;

        $auto_push = true;

        if ($auto_push) {
            // Đơn order chờ xác nhận
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
                // PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Order')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackStatusPending($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }


            // Đơn order đã đến kho China
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Order')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackStatusInChina($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }

            // Kiện kí gửi
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
                // PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Consignment')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->orderBy('updated_at', 'desc')
                                ->limit(500)->offset(0)
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackConsigmentStatusPending($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }

            // Kiện kí gửi đã đến kho China
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                // PackageConstant::STATUS_PENDING,
                // PackageConstant::STATUS_ON_THE_WAY_CHINA,
                PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Consignment')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->orderBy('updated_at', 'desc')
                                ->limit(500)->offset(0)
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackConsigmentInChina($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }


            // Kiện chờ xác định
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
                // PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->whereNull('order_type')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackNotStatusPending($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }


            // Kiện chờ xác định đã đến kho China
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                // PackageConstant::STATUS_PENDING,
                // PackageConstant::STATUS_ON_THE_WAY_CHINA,
                PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->whereNull('order_type')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackNotInChina($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }
        }
    }

    public function syncAndPushToOne(Request $request)
    {
        // Địa chỉ khách hàng
        for ($i=1250; $i < 1500; $i++) {
            $job = new \App\Jobs\SyncTD\SyncCustomerDelivery($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        }

        // Nhà cung cấp
        for ($i=3800; $i < 4200; $i++) {
            $job = new \App\Jobs\SyncTD\SyncSupplier($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
        }
        return;

        $auto_push = true;
        if ($auto_push) {
            // Khách hàng
            for ($i=2100; $i < 2500; $i++) {
                $job = new \App\Jobs\SyncTD\SyncCustomer($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Địa chỉ khách hàng
            for ($i=1250; $i < 1500; $i++) {
                $job = new \App\Jobs\SyncTD\SyncCustomerDelivery($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Nhà cung cấp
            for ($i=3800; $i < 4200; $i++) {
                $job = new \App\Jobs\SyncTD\SyncSupplier($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Đơn order
            for ($i=0; $i < 300; $i++) {
                $job = new \App\Jobs\SyncTD\SyncOrder($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Kiện kí gửi
            $maxPage = 200;
            $typePack = 2;
            for ($i=0; $i < $maxPage; $i++) {
                $job = new \App\Jobs\SyncTD\SyncPackage($i, $typePack);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Kiện không xác định
            $maxPage = 20;
            $typePack = 3;
            for ($i=0; $i < $maxPage; $i++) {
                $job = new \App\Jobs\SyncTD\SyncPackage($i, $typePack);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Đồng bộ sang kho
            $job = new \App\Jobs\OneWarehouse\SyncAllPackToOne();
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            return;
        }
    }

    public function syncImageToLive(Request $request)
    {
        $url = 'https://www.thuongdo.com/sites/default/files/2020/screenshot_1727431405.png';

        $date = date('Ymd');
        $path = public_path() . '/oss';
        $fileName = '77.jpg';

        $link  = $path . '/' . $fileName;
        $pathOSS = 'hangve/' . $date;
        $img = $path . '/' . $fileName;
        file_put_contents($img, file_get_contents($url));

        Storage::disk('oss')->putFileAs($pathOSS, new File($link), $fileName);

        $pathName = '/' . $pathOSS . '/' . $fileName;
        $full_path =  env('OSS_URL') . $pathName;
        $data = ['name' => $fileName, 'path' => $pathName, "full_path" => $full_path];
        unlink($link);

        dd($data);
    }

    public function syncImageRefundLive(Request $request)
    {
        $refundModel = \App::make('App\Models\Refund');
        $refundDBs = $refundModel->where('thuongdo_id', '>' , 0)
                                ->whereNull('thuongdo_files')
                                ->get();

        if (count($refundDBs) > 0) {
            foreach ($refundDBs as $key => $refundDB) {
                $job = new \App\Jobs\SyncTD\SyncRefundImage($refundDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }
        return;

        $refundModel = \App::make('App\Models\Refund');
        $refundDB = $refundModel->where('thuongdo_id', '>' , 0)
                                ->whereNull('thuongdo_files')
                                ->first();


        $files = json_decode($refundDB->files, true);
        $thuongdo_files = [];
        $file_first = null;

        foreach ($files as $key_1 => $file) {
            $url = $file['url'];

            $date = date('Ymd');
            $path = public_path() . '/oss';
            $min = 100000;
            $max = 999999;
            $fileName = rand($min, $max) . "_" . $file['filename'];

            $link  = $path . '/' . $fileName;
            $pathOSS = 'hangve/' . $date;
            $img = $path . '/' . $fileName;

            file_put_contents($img, file_get_contents($url));
            Storage::disk('oss')->putFileAs($pathOSS, new File($link), $fileName);

            $pathName = '/' . $pathOSS . '/' . $fileName;
            $full_path =  env('OSS_URL') . $pathName;
            unlink($link);

            if ($file_first == null) {
                $file_first = $pathName;
            }
            array_push($thuongdo_files, $pathName);
        }

        if ($file_first) {
            $refundDB->file = $full_path;
            $refundDB->thuongdo_files = $thuongdo_files;
            $refundDB->save();
        }
    }

    public function syncImageOrderCostRealLive(Request $request)
    {
        $refundModel = \App::make('App\Models\OrderCostReal');
        $refundDBs = $refundModel->where('thuongdo_id', '>' , 0)
                                ->whereNull('thuongdo_files')
                                ->get();

        if (count($refundDBs) > 0) {
            foreach ($refundDBs as $key => $refundDB) {
                $job = new \App\Jobs\SyncTD\SyncOrderCostRealImage($refundDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }
        return;


        $refundModel = \App::make('App\Models\OrderCostReal');
        $refundDB = $refundModel->where('thuongdo_id', '>' , 0)
                                ->whereNull('thuongdo_files')
                                ->first();


        $files = json_decode($refundDB->files, true);
        $thuongdo_files = [];
        $file_first = null;

        foreach ($files as $key_1 => $file) {
            $url = $file['url'];

            $date = date('Ymd');
            $path = public_path() . '/oss';
            $min = 10000000;
            $max = 99999999;
            $fileName = rand($min, $max) . "_" . $file['filename'];

            $link  = $path . '/' . $fileName;
            $pathOSS = 'hangve/' . $date;
            $img = $path . '/' . $fileName;

            file_put_contents($img, file_get_contents($url));
            Storage::disk('oss')->putFileAs($pathOSS, new File($link), $fileName);

            $pathName = '/' . $pathOSS . '/' . $fileName;
            $full_path =  env('OSS_URL') . $pathName;
            unlink($link);

            if ($file_first == null) {
                $file_first = $pathName;
            }
            array_push($thuongdo_files, $pathName);
        }

        if ($file_first) {
            $refundDB->file = $full_path;
            $refundDB->thuongdo_files = $thuongdo_files;
            $refundDB->save();
        }
    }

    public function syncUpdateWarehouseCustomer(Request $request)
    {
        for ($i=0; $i < 500; $i++) {
            $job = new \App\Jobs\SyncTD\SyncCustomerWarehouse($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('export-1800');
        }
        return;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/customer';

        $pageCron = (int)$request->page;

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'type_warehouse',
                    'contents' => 1,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $customerModel = \App::make('App\Models\Customer');
            $warehouseModel = \App::make('App\Models\Warehouse');

            foreach ($result as $key => $item) {
                $customerDB = $customerModel->where('username', $item['username'])->first();

                if (!is_null($customerDB)) {
                    // Thêm phần kho nhận hàng
                    $warehouseDB = $warehouseModel->where('thuongdo_id', $item['warehouse_default_id'])->first();

                    if (!is_null($warehouseDB)) {
                        \Log::info('zzz:' . $customerDB->username);
                        $customerDB->warehouse_id = (int) $warehouseDB->id;
                        // \Log::info('WH_name: ' . $warehouseDB->address_other);
                        // \Log::info('WH_code: ' . $warehouseDB->code);
                        // \Log::info('WH_id: ' . $warehouseDB->thuongdo_id);
                        // \Log::info('TD_id: ' . $customerDB->thuongdo_id);
                        // \Log::info('TD_username: ' . $customerDB->username);
                        // \Log::info("---------");
                        $customerDB = Customer::withoutEvents(function () use ($customerDB) {
                            $customerDB->save();
                            return $customerDB;
                        });
                    }
                }
            }
        }
    }

    public function syncUpdateWarehouseCustomerFromFile(Request $request)
    {
        $customerModel = \App::make('App\Models\Customer');
        $warehouseModel = \App::make('App\Models\Warehouse');
        $customers = json_decode(file_get_contents(database_path('mocks/thanh_vien.json')), true);
        $count = 0;
        foreach ($customers[2]['data'] as $key => $item) {
            $customerDB = $customerModel->where('username', $item['username'])->first();
            if (is_null($customerDB->warehouse_id)) {
                $warehouseDB = $warehouseModel->where('thuongdo_id', $item['warehouse_id'])->first();
                if (!is_null($warehouseDB)) {
                    $count += 1;
                    $customerDB->warehouse_id = (int) $warehouseDB->id;
                    $customerDB = Customer::withoutEvents(function () use ($customerDB) {
                        $customerDB->save();
                        return $customerDB;
                    });
                }
            }
        }
        dd($count);
    }

    public function syncUpdateDateDonePackage(Request $request)
    {
        for ($i=0; $i < 5800; $i++) {
            $job = new \App\Jobs\SyncTD\SyncDateDonePackage($i);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
        }
        return;


        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/package';

        $pageCron = 1;
        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'month_to',
                    'contents' => '2024-01-01',
                ],
                [
                    'name'     => 'month_end',
                    'contents' => '2024-10-07',
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $count = 0;
            foreach ($result as $key => $item) {
                if ($item['ngay_hoan_thanh']) {
                    $date_done = date('Y-m-d H:i:s', $item['ngay_hoan_thanh']);

                    $packDB = $orderPackageModel->where('thuongdo_id', $item['id'])
                                                ->where('status', 'status_16')
                                                ->first();
                    if (!is_null($packDB)) {
                        if ($packDB->date_done == null) {
                            // Lưu lại
                            $count += 1;
                            $packDB->date_done = $date_done;
                            $packDB = OrderPackage::withoutEvents(function () use ($packDB) {
                                $packDB->save();
                                return $packDB;
                            });
                        }
                    }
                }
            }
            \Log::info('syncUpdateDateDonePackage: ' . $pageCron . ' - ' . $count);
        }
    }
}

