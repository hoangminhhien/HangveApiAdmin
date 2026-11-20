<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Label;

use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client as Guzzle;

use App\Models\OrderPackage;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\ReportCustomer;
use App\Models\LogWebhook;

use App\Constants\OrderConstant;
use App\Constants\PackageConstant;

use App\Helpers\RandomHelper;

class DemoController extends Controller
{
    public function __construct()
    {

    }

    // Hàm này k được chạy live, chỉ để chạy local thôi nhé
    public function initLabelDefault(Request $request): string
    {
        try {
            $labelModel = new Label();
            $arr_label = [
                [
                    'name' => 'Không tương tác được',
                    'code' =>  'KHONG_TUONG_TAC_DUOC'
                ],
                [
                    'name' => 'Không có nhu cầu',
                    'code' =>  'KHONG_CO_NHU_CAU'
                ],
                [
                    'name' => 'Mặt hàng không nhập được',
                    'code' =>  'MAT_HANG_KHONG_NHAP_DUOC'
                ],
//                [
//                    'name' => 'Tham khảo, chưa có nhu cầu ngay',
//                    'code' =>  'THAM_KHAO_CHUA_CO_NHU_CAU_NGAY'
//                ],
//                [
//                    'name' => 'Hẹn gọi lại',
//                    'code' =>  'HEN_GOI_LAI'
//                ],
                [
                    'name' => 'Tham khảo',
                    'code' =>  'THAM_KHAO'
                ],
                [
                    'name' => 'Đang follow',
                    'code' =>  'DANG_FOLLOW'
                ],
                [
                    'name' => 'Đã lên đơn',
                    'code' =>  'DA_LEN_DON'
                ],
//                [
//                    'name' => 'Tự tìm kiếm',
//                    'code' =>  'TU_TIM_KIEM'
//                ],
                [
                    'name' => 'Chuyển tiền quốc tế',
                    'code' =>  'CHUYEN_TIEN_QUOC_TE'
                ],
                [
                    'name' => 'Kí gửi',
                    'code' =>  'KI_GUI'
                ],
                [
                    'name' => 'Kí gửi đã lên đơn',
                    'code' =>  'KI_GUI_DA_LEN_DON'
                ],
                [
                    'name' => 'Đã đặt hàng',
                    'code' =>  'DA_DAT_HANG'
                ],
                [
                    'name' => 'Liên hệ lại',
                    'code' =>  'LIEN_HE_LAI'
                ]
            ];
            foreach ($arr_label as  $label) {
                $data = [
                    'name' => $label['name'],
                    'code' => $label['code']
                ];
                $labelModel->firstOrCreate([
                    'code' => $label['code']
                ], $data);
            }
            return "Init Success";
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function updateOrgOrderPackage2024(Request $request)
    {
        $packageModel = \App::make('App\Models\OrderPackage');

        $packageDB = $packageModel->where('thuongdo_id', '>', 0)->update([
            'organization_id' => 1
        ]);

        dd($packageDB);
    }

    public function updateOrgOrderPackage2024V2(Request $request)
    {
        $packageModel = \App::make('App\Models\OrderPackage');

        $packageDB = $packageModel->where('organization_id', 0)->update([
            'organization_id' => 1
        ]);

        dd($packageDB);
    }

    public function updateOrgComplain2024(Request $request)
    {
        $complainModel = \App::make('App\Models\Complain');

        $complainDB = $complainModel->where('organization_id', 0)->update([
            'organization_id' => 1
        ]);

        dd($complainDB);
    }

    public function deleteWarehouseInit(Request $request)
    {
        $warehouseModel = \App::make('App\Models\Warehouse');
        $warehouseDel = $warehouseModel->where('country', 'vi')->where('thuongdo_id', 0)->get();
        if(count($warehouseDel)) {
            foreach ($warehouseDel as $key => $item) {
                $item->delete();
            }
        } else {
            dd("Done");
        }
    }

    public function delete2Warehouse(Request $request)
    {
        $warehouseModel = \App::make('App\Models\Warehouse');
        $warehouseDel = $warehouseModel->where('country', 'vi')->whereIn('thuongdo_id', [2, 11])->get();
        if(count($warehouseDel)) {
            foreach ($warehouseDel as $key => $item) {
                $item->delete();
            }
        } else {
            dd("Done 1");
        }
    }

    public function initUserAdminDD(Request $request)
    {
        $organizationModel = \App::make('App\Models\Organization');
        $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

        $userRepo = \App::make('App\Models\User');
        $userAdminDD = $userRepo->where('email', 'admindongdong@gmail.com')->first();
        if (is_null($userAdminDD)) {
            $data_user_admin_dd = [
                'email'           => 'admindongdong@gmail.com',
                'name'            => 'Admin DongDong',
                'password'        => bcrypt('9888242ATkm29k'),
                'organization_id' => $organizationTD->id,
                'dongdong_id'     => 1
            ];
            $userAdminDD = $userRepo->create($data_user_admin_dd);
        }
        dd($userAdminDD);
    }

    public function initCategoryItem(Request $request)
    {
        $categoryItemModel = \App::make('App\Models\CategoryItem');
        $categoryItemModel->truncate();

        $arr_category = [
            [
                'name' => 'Thời trang nữ',
                'image' => "",
                'child' => [
                    [
                        'name' => 'Quần áo cotton',
                        'child' => []
                    ],
                    [
                        'name' => 'Áo khoác nỉ',
                        'child' => []
                    ],
                    [
                        'name' => 'Chân váy',
                        'child' => []
                    ],
                    [
                        'name' => 'Váy liền',
                        'child' => []
                    ],
                    [
                        'name' => 'Áo sơ mi',
                        'child' => []
                    ],
                    [
                        'name' => 'Bộ đồ thời trang',
                        'child' => []
                    ],
                    [
                        'name' => 'Quần legging',
                        'child' => []
                    ],
                    [
                        'name' => 'Quần bò',
                        'child' => []
                    ],
                    [
                        'name' => 'Quần áo nữa size lớn',
                        'child' => []
                    ],
                    [
                        'name' => 'Trang phục trung niên. Người già',
                        'child' => []
                    ],
                    [
                        'name' => 'Váy cưới, lễ phục',
                        'child' => []
                    ],
                    [
                        'name' => 'Xu hướng phong cách',
                        'child' => [
                            'Thời trang phong cách thanh lịch',
                            'Thời trang phong cách tươi trẻ',
                            'Thời trang phong cách đường phố',
                            'Trang phục ngoài trời',
                        ]
                    ],
                ]
            ],
            [
                'name' => 'Thời trang nam',
                'image' => "",
                'child' => [
                    [
                        'name' => 'Áo phông',
                        'child' => []
                    ],
                    [
                        'name' => 'Áo sơ mi',
                        'child' => []
                    ],
                    [
                        'name' => 'Áo khoác jacket',
                        'child' => []
                    ],
                    [
                        'name' => 'Quần âu',
                        'child' => []
                    ],
                    [
                        'name' => 'Quần bò',
                        'child' => []
                    ],

                    [
                        'name' => 'Áo dệt kim',
                        'child' => []
                    ],
                    [
                        'name' => 'Áo hoodie',
                        'child' => []
                    ],
                    [
                        'name' => 'Áo vest nam',
                        'child' => []
                    ],
                ]
            ],
            [
                'name' => 'Thời trang trẻ em',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Áo khoác trẻ em',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Váy trẻ em',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ trẻ em',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Sản phẩm mẹ và bé',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Quần áo trẻ sơ sinh',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ bà bầu',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ trẻ em',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Bỉm tã, giấy, sữa',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Giày dép',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Giày nữ',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Dép đi trong nhà',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Giày vải',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Giày nam',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Trang phục thể thao',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Đồ bơi',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Quần thể thao',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Trang phục thể thao',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Áo polo thể thao',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Đồ chơi',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Đồ chơi nam châm',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ chơi bông',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ chơi gậy gỗ',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ chơi xếp hình ghép khối',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Túi xách, vali',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Túi nam',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Túi nữ',
                        'child' => [],
                    ],
                    [
                        'name'=> 'cặp sách',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Ví da',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Điện thoại',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Phụ kiện di động',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Hãng điện thoại',
                        'child' => [
                            'iphone',
                            'sam sung',
                            'oppo',
                        ],
                    ],
                    [
                        'name'=> 'Phụ kiện Apple',
                        'child' => [
                            'Vỏ iphone',
                            'Kính iphone',
                            'Vỏ ipad',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Linh kiện điện tử',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Sản phẩm internet',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Bán dẫn',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Thiết bị thụ động',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Module điệnt tử',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Phụ kiện thể thao',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Vợt cầu lông',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Vợ bóng bàn',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Bóng hơi',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Ba lô',
                        'child' => [],
                    ],
                ],
            ],
            [
                'name' => 'Điện gia dụng',
                'image' => '',
                'child' => [
                    [
                        'name'=> 'Chăm sóc sức khỏe',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ bếp, đồ gia dụng',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Đồ điện gia dụng',
                        'child' => [],
                    ],
                    [
                        'name'=> 'Thiết bị âm thanh',
                        'child' => [],
                    ],
                ],
            ],

        ];

        foreach ($arr_category as $key => $category) {
            $categoryItemDB = $categoryItemModel->where('name', $category['name'])->first();
            if (is_null($categoryItemDB)) {
                $data_category = [
                    'name'      => $category['name'],
                    'slug'      => \Str::slug($category['name']),
                    'parant_id' => 0
                ];
                $categoryItemDB = $categoryItemModel->create($data_category);
            }
            $categoryItemDB = $categoryItemModel->where('name', $category['name'])->first();

            // Cấp độ 1
            foreach ($category['child'] as $key_child => $category_child) {
                $categoryItemChildDB = $categoryItemModel->where('name', $category_child['name'])
                                                        ->where('parant_id', $categoryItemDB->id)
                                                        ->first();
                if (is_null($categoryItemChildDB)) {
                    $data_category_child = [
                        'name'      => $category_child['name'],
                        'slug'      => \Str::slug($category_child['name']),
                        'parant_id' => $categoryItemDB->id
                    ];
                    $categoryItemChildDB = $categoryItemModel->create($data_category_child);
                }

                $categoryItemChildDB = $categoryItemModel->where('name', $category_child['name'])
                                                        ->where('parant_id', $categoryItemDB->id)
                                                        ->first();

                // Cấp độ 2
                foreach ($category_child['child'] as $key_child_2 => $category_child_2) {

                    $categoryItemChild_2DB = $categoryItemModel->where('name', $category_child_2)
                                                            ->where('parant_id', $categoryItemChildDB->id)
                                                            ->first();
                    if (is_null($categoryItemChild_2DB)) {
                        $data_category_child_2 = [
                            'name'      => $category_child_2,
                            'slug'      => \Str::slug($category_child_2),
                            'parant_id' => $categoryItemChildDB->id
                        ];
                        $categoryItemChild_2DB = $categoryItemModel->create($data_category_child_2);
                    }
                }
            }
        }

    }

    public function synCodeUser()
    {
        $users = User::all();
        foreach($users as $key => $user){
            $user->update(['code' => str_pad($key + 1, 4, "0", STR_PAD_LEFT)]);
        }
        return resSuccess();
    }

    public function updateTotalCustomer(Request $request)
    {
        $userRepo = \App::make('App\Models\User');

        $usersDB = $userRepo->where('total_customers', '>', 0)->update([
           'total_customers' => 0
        ]);

        dd($usersDB);
    }

    public function webhookTest(Request $request)
    {
        $data = $request->all();
        \Log::info('WebhookBuyder : ' . request()->ip() . ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        // return response()->json(['code' => 200, 'message' => 'Thành công'], 200);
    }

    public function resetDbDev(Request $request)
    {
        if (env('APP_NAME') == 'LaravelNT19') {
            $orderModel = \App::make('App\Models\Order');
            $orderDetailModel = \App::make('App\Models\OrderDetail');
            $orderSupModel = \App::make('App\Models\OrderSupplier');
            $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $deliveryModel = \App::make('App\Models\Delivery');
            $deliveryOrderModel = \App::make('App\Models\DeliveryOrder');
            $activityModel = \App::make('App\Models\Activity');
            $consignmentModel = \App::make('App\Models\Consignment');
            $consignmentDetailModel = \App::make('App\Models\ConsignmentDetail');
            $consignmentStatusTimeModel = \App::make('App\Models\ConsignmentStatusTime');


            $orderModel->truncate();
            $orderDetailModel->truncate();
            $orderSupModel->truncate();
            $orderStatusTimeModel->truncate();
            $orderPackageModel->truncate();
            $deliveryModel->truncate();
            $deliveryOrderModel->truncate();
            $activityModel->truncate();
            $consignmentModel->truncate();
            $consignmentDetailModel->truncate();
            $consignmentStatusTimeModel->truncate();
        }
    }

    public function resetWarehouse2024(Request $request)
    {
        $warehouseModel = \App::make('App\Models\Warehouse');

        $warehouseDB = $warehouseModel->whereIn('id', [2, 6])->update([
            'status' => 0
        ]);
    }

    public function testPer2024(Request $request)
    {
        $roleModel = \App::make('App\Models\Role');
        $perModel = \App::make('App\Models\Permission');


        // $roleModel->create(['name' => 'okRole']);
        // $perModel->create(['name' => 'per1']);
        // $perModel->create(['name' => 'per2']);

        $userOnline = \Auth::user();

        // dd($userOnline);


        $role = $roleModel->where('name', 'okRole')->first();
        $permission = $perModel->where('name', 'per1')->first();
        // $role->syncPermissions(['per1']);
        // $permission->assignRole($role);


        // $userOnline->assignRole('admin.1');
        // $userOnline->syncPermissions('per1');

        $permissions = $userOnline->getAllPermissions();

        $perOfRole = \Arr::pluck($permissions, 'name');
        dd($perOfRole);

        // $userOnline->givePermissionTo('per1');
        dd($userOnline->can('per1'));
        dd($userOnline);
    }

    public function resetPassAdmin(Request $request)
    {
        $userModel = \App::make('App\Models\User');
        $userAdmin = $userModel->where('email', 'nhungadmin@gmail.com')->first();
        if ($userAdmin) {
            $userAdmin->password = \Hash::make('Bma01831828119');
            $userAdmin->save();
        }
    }
    public function testPHP(Request $request)
    {
        echo phpinfo();
    }

    public function hotFixNhung(Request $request)
    {
        // $amount = 980000;
        // $customer_id = 214159;
        // $content = "Hoàn tiền phí vận chuyển cho mã vận đơn 109166328823 !!";

        // $transactionModel = \App::make('App\Models\Transaction');
        // $filePrintModel = \App::make('App\Models\FilePrint');
        // $transactionDB = $transactionModel
        //                             ->where('customer_id', $customer_id)
        //                             ->where('content', $content)->first();

        // if (is_null($transactionDB)) {
        //     $soure = $filePrintModel->where('id', 221)->first();
        //     if (!is_null($soure)) {
        //         (new \App\Services\TransactionService())->setTransactionIncrement(
        //             $amount,
        //             \App\Constants\TransactionConstant::STATUS_REFUND,
        //             $content,
        //             $customer_id,
        //             $soure
        //         );
        //     }
        // }


        // $amount = 1483378;
        // $customer_id = 89450;
        // $content = "Hoàn lại tiền cho mã vận đơn 1897024912 !!";

        // $transactionModel = \App::make('App\Models\Transaction');
        // $filePrintModel = \App::make('App\Models\Order');
        // $transactionDB = $transactionModel
        //                             ->where('customer_id', $customer_id)
        //                             ->where('content', $content)->first();

        // if (is_null($transactionDB)) {
        //     $soure = $filePrintModel->where('id', 13392)->first();
        //     if (!is_null($soure)) {
        //         (new \App\Services\TransactionService())->setTransactionIncrement(
        //             $amount,
        //             \App\Constants\TransactionConstant::STATUS_REFUND,
        //             $content,
        //             $customer_id,
        //             $soure,
        //             $soure
        //         );
        //     }
        // }


        // $amount = 19440650;
        // $customer_id = 170286;
        // $content = "Hoàn tiền phí vận chuyển cho mã vận đơn 78838312632599 thuộc mã đơn hàng 2706910063 !!";

        // $transactionModel = \App::make('App\Models\Transaction');
        // $filePrintModel = \App::make('App\Models\FilePrint');
        // $transactionDB = $transactionModel
        //                             ->where('customer_id', $customer_id)
        //                             ->where('content', $content)->first();

        // if (is_null($transactionDB)) {
        //     $soure = $filePrintModel->where('id', 123)->first();
        //     if (!is_null($soure)) {
        //         (new \App\Services\TransactionService())->setTransactionIncrement(
        //             $amount,
        //             \App\Constants\TransactionConstant::STATUS_REFUND,
        //             $content,
        //             $customer_id,
        //             $soure
        //         );
        //     }
        // }


        // $amount = 15156650;
        // $customer_id = 170286;
        // $content = "Hoàn tiền phí vận chuyển cho mã vận đơn 78838804024666 thuộc mã đơn hàng 2706910065 !!";

        // $transactionModel = \App::make('App\Models\Transaction');
        // $filePrintModel = \App::make('App\Models\FilePrint');
        // $transactionDB = $transactionModel
        //                             ->where('customer_id', $customer_id)
        //                             ->where('content', $content)->first();

        // if (is_null($transactionDB)) {
        //     $soure = $filePrintModel->where('id', 123)->first();
        //     if (!is_null($soure)) {
        //         (new \App\Services\TransactionService())->setTransactionIncrement(
        //             $amount,
        //             \App\Constants\TransactionConstant::STATUS_REFUND,
        //             $content,
        //             $customer_id,
        //             $soure
        //         );
        //     }
        // }

    }

    public function syncNotePack(Request $request)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $packageDBs = $packModel->whereNotNull('note')
                                ->whereNotNull('code_warehouse')
                                ->get();

        if (count($packageDBs) > 0) {
            \Log::info('syncNotePack');
            \Log::info(count($packageDBs));

            foreach ($packageDBs as $key => $packageDB) {
                // $this->pushNoteToOne($packageDB);

                $job = new \App\Jobs\OneWarehouse\SyncNote($packageDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }
    }

    public function pushNoteToOne($packageDB)
    {
        // Đồng bộ ghi chú sang kho
        $data_sync = [
            'id'   => (int)$packageDB->id,
            'note' => $packageDB->note
        ];

        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
        $options = [
            'json' => [
                'event_name' => 'update_note',
                'data'       => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }

    public function deletePackOneError(Request $request)
    {
        $newarraynama = '836103,836104,836105,836106,836107,836108,836109,836110,836111,836112,836113,836114,836115,836116,836117,836118,836119,836120,836121,836122,836123,836124,836125,836126,836127,836128,836129,836130,836131,836132,836133,836134,836135,836136,836137,836138,836139,836140,836141,836142,836143,836144,836145,836146,836147,836148,836149,836150,836151,836152,836153,836154,836155,836156,836157,836158,836159,836160,836161,836162,836163,836164,836165,836166,836167,836168,836169,836170,836171,836172,836173,836174,836175,836176,836177,836178,836179,836180,836181,836182,836183,836184,836185,836186,836187,836188,836189,836190,836191,836192,836193,836194,836195,836196,836197,836198,836199,836200,836201,836202,836203,836204,836205,836206,836207,836208,836209,836210,836211,836212,836213,836214,836215,836216,836217,836218,836219,836220,836221,836222,836223,836224,836225,836226,836227,836228,836229,836230,836231,836232,836233,836234,836235,836236,836237,836238,836239,836240,836241,836242,836243,836244,836245,836246,836247,836248,836249,836250,836251,836252,836253,836254,836255,836256,836257,836258,836259,836260,836261,836262,836263,836264,836265,836266,836267,836268,836269,836270,836271,836272,836273,836274,836275,836276,836277,836278,836279,836280,836281,836282,836283,836284,836285,836286,836287,836288,836289,836290,836291,836292,836293,836294,836295,836296,836297,836298,836299,836300,836301,836302,836303,836304,836305,836306,836307';

        $arr_code = explode(",",$newarraynama);
        $a = count($arr_code);

        $packModel = \App::make('App\Models\OrderPackage');
        $packageDBs = $packModel->where('created_at', '>', '2024-10-15 11:30:15')
                                ->where('created_at', '<', '2024-10-15 12:30:15')
                                ->whereIn('id', $arr_code)
                                ->get();
        if (count($packageDBs)) {
            foreach ($packageDBs as $key => $packageDB) {
                $orderPackageDB = OrderPackage::withoutEvents(function () use ($packageDB) {
                    $packageDB->forceDelete();
                    return null;
                });
            }
        }
    }

    public function deletePackOneError261224(Request $request)
    {
        $newarraynama = '894752,894753,894754,894755,894756,894757,894758,894759,894760,894761,894762,894763,894764,894765,894766,894767,894768,894769,894770,894771,894772,894773,894774,894775,894776,894777,894778,894779,894780,894781,894782,894783,894784,894785,894786,894787,894788,894789,894790,894791,894792,894793,894794,894795,894796,894797,894798,894799,894800,894801,894802,894803,894804,894805,894806,894807,894808,894809,894810,894811,894812,894813,894814,894815,894816,894817,894818,894819,894820,894821,894822,894823,894824,894825,894826,894827,894828,894830,894831,894832,894833,894834,894835,894836,894837,894838,894839,894840,894841,894842,894843,894844,894845,894846,894847,894848,894849,894850,894851,894852,894853,894854,894855,894856,894857,894858,894859,894860,894861,894862,894863,894864,894865,894866,894867,894868,894869,894870,894871,894872,894873,894874,894875,894876,894877,894878,894879,894880,894881,894882,894883,894884,894885,894886,894887,894888,894889,894890,894891,894892,894893,894894,894895,894896,894897,894898,894899,894900,894901,894902,894903,894904,894905,894906,894907,894908,894909,894910,894911,894912,894913,894914,894915,894916,894917,894918,894919,894920,894921,894922,894923,894924,894925,894926,894927,894928,894929,894930,894931,894932,894933,894934,894935,894936,894937,894938,894939,894940,894941,894942,894943,894944,894945,894946,894947,894948,894949,894950,894951,894952,894953,894954,894955,894956,894957,894958,894959,894960,894961,894962,894963,894964,894965,894966,894967,894968,894969,894970,894971,894972,894973,894974,894975,894976,894978,894979,894980,894981,894982,894983,894984,894985,894986,894987,894988,894989,894990,894991,894992,894993,894994,894995,894996,894997,894998,894999,895000,895001,895002,895003,895004,895005,895006,895007,895008,895009,895010,895011,895012,895013,895014,895015,895016,895017,895018,895019,895020,895021,895022,895023,895024,895025,895026,895027,895028,895029,895030,895031,895032,895033,895034,895035,895036,895037,895038,895039,895040,895041,895042,895043,895044,895045,895046,895047,895048,895049,895050,895052,895053,895054,895055,895056,895057,895058,895059,895060,895061,895062,895063,895064,895065,895066,895067,895068,895069,895070,895071,895072,895073,895074,895075,895076,895077,895078,895079,895080,895081,895082,895083,895084,895085,895086,895087,895088,895089,895090,895091,895092,895093,895094,895095,895096,895097,895098,895099,895100,895101,895102,895103,895104,895105,895106,895107,895108,895109,895110,895111,895112,895113,895114,895115,895116,895117,895118,895119,895120,895121,895122,895123,895124,895125,895126,895127,895128,895129,895130,895131,895132,895133,895134,895135,895136,895137,895138,895139,895140,895141,895142,895143,895144,895145,895146,895147,895148,895149,895150,895151,895152,895153,895154,895155,895156,895157,895158,895159,895160,895161,895162,895163,895164,895165,895166,895167,895168,895169,895170,895171,895172,895173,895174,895175,895177,895178,895179,895180,895181,895182,895183,895184,895185,895186,895187,895188,895189,895190,895191,895192,895193,895195,895196,895197,895198,895199,895200,895201,895202,895203,895204,895205,895206,895207,895208,895209,895210,895211,895212,895213,895214,895215,895216,895217,895218,895219,895220,895221,895222,895223,895224,895225,895226,895227,895228,895229,895230,895231,895232,895233,895234,895236,895237,895238,895239,895240,895241,895242,895243,895244,895245,895246,895247,895248,895249,895250';

        $arr_code = explode(",",$newarraynama);
        $a = count($arr_code);

        $packModel = \App::make('App\Models\OrderPackage');
        $packageDBs = $packModel->where('created_at', '>', '2024-12-26 16:30:15')
                                ->where('created_at', '<', '2024-12-26 16:50:15')
                                ->whereIn('id', $arr_code)
                                ->get();

        if (count($packageDBs)) {
            foreach ($packageDBs as $key => $packageDB) {
                $orderPackageDB = OrderPackage::withoutEvents(function () use ($packageDB) {
                    $packageDB->forceDelete();
                    return null;
                });
            }
        }
    }

    public function testShipOrderSup(Request $request)
    {
        $order = Order::query()->findOrFail(515);


        $totalCostPackages = optional($order->packagesOrder)->sum('international_shipping_cost');
        $totalCostPackages = $totalCostPackages ?? 0;
        $totalWoodWorkingCostPackages = optional($order->packagesOrder)->sum('woodworking_cost');
        $totalWoodWorkingCostPackages = $totalWoodWorkingCostPackages ?? 0;

        dump($totalCostPackages);
        dd($totalWoodWorkingCostPackages);

        //$order->international_shipping_cost = $totalCostPackages;
        //$order->save();

        // update internal shipping cost for order supplier first
        $orderSupplierFirst = optional($order->orderSuppliers)->first();
        if ($orderSupplierFirst) {
            $orderSupplierFirst->international_shipping_cost = $totalCostPackages;
            $orderSupplierFirst->woodworking_cost = $totalWoodWorkingCostPackages;
            // $orderSupplierFirst->save();
        }
    }

    public function fixForMrKho(Request $request)
    {
        // Các kiện này là trả trước kho khách, đã nhận hàng, trừ tiền
        // Kho quét bth theo chu trình, lại đổi trạng thái đã về VN
        // Giờ muốn đổi lại đã nhận hàng để không bị thu tiền lần nữa

        $arr_bill_code = [
            '78451655170102',
            '777250060750106',
            'YT7497246647524',
            'YT7497409823237',
            'YT7497410584211',
            'YT7497411925994',
            'YT7497441655865',
            'YT7497442822185',
            'YT7497447155545',
            'YT8707776689734',
            'YT8707776691793',
            'yt8707819516252',
            'yt8707819532681',
            'yt8707819626913',
            'yt8707822478448',
        ];

        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderPackageDBs = $orderPackageModel
                                            ->whereIn('bill_code', $arr_bill_code)
                                            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
                                            ->get();

        if (count($orderPackageDBs) > 0) {
            foreach ($orderPackageDBs as $key => $orderPackageDB) {
                $orderPackageDB->status = PackageConstant::STATUS_RECEIVED_GOODS;
                $orderPackageDB->save();
            }
        }
    }

    public function fixForMrKhoV2(Request $request)
    {
        // Kiện chờ COD, muốn cho về đã nhận hàng

        $arr_bill_code = [
            '434135587009261',
            'YT7488542817232'
        ];

        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderPackageDBs = $orderPackageModel
                                            ->whereIn('bill_code', $arr_bill_code)
                                            ->where('status', PackageConstant::STATUS_WAITING_CODE)
                                            ->get();

        if (count($orderPackageDBs) > 0) {
            foreach ($orderPackageDBs as $key => $orderPackageDB) {
                $orderPackageDB->status = PackageConstant::STATUS_RECEIVED_GOODS;
                $orderPackageDB->save();
            }
        }
    }

    public function updateInternationalShippingCost(Request $request)
    {
        $validationRules = [
            'token'      => 'required',
            'order_id'   => 'required|exists:orders,id',
            'checked'    => 'required|in:1,0',
            'money_diff' => 'required|min:1'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        \Log::info('updateInternationalShippingCost : ' . request()->ip());

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn18882431510044QwmObgbRmn') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $params['order_id'])->first();

        $orderSuppliers = $orderDB->orderSuppliers;
        $shipOld = $orderSuppliers->sum('international_shipping_cost');

        $packageDBs = $orderDB->packagesOrder;
        $shipNew = $packageDBs->sum('international_shipping_cost');
        $goodNew = $packageDBs->sum('woodworking_cost');

        if ($params['checked'] == 1) {
            return $shipOld - $shipNew;
        }

        $diff = $shipOld - $shipNew;

        if ($params['checked'] == 0 && $diff == $params['money_diff']) {
            try {
                \DB::beginTransaction();

                // Cập nhật vào đơn phụ
                $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
                if ($orderSupplierFirst) {
                    $orderSupplierFirst->international_shipping_cost = $shipNew;
                    $orderSupplierFirst->woodworking_cost = $goodNew;
                    $orderSupplierFirst->save();
                }

                // Cập nhật vào đơn tổng
                $orderDB->international_shipping_cost = $shipNew;
                $orderDB->woodworking_cost = $goodNew;
                $orderDB->save();

                \DB::commit();
                return resSuccessWithinData([
                    "Thành công"
                ]);
            } catch (Exception $e) {
                \DB::rollback();
                throw $e;
            }
        }
    }

    public function updateStatusReciceGoodPack(Request $request)
    {
        // Dùng cho Nhung xử lý các kiện chờ COD, tiền đã thu bên TD
        $validationRules = [
            'token'           => 'required',
            'arr_bill_code'   => 'required|array',
            'arr_bill_code.*' => 'required|exists:order_package,bill_code',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        \Log::info('updateInternationalShippingCost : ' . request()->ip());

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn18882431510044QwmObgbRmnbm') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderPackageDBs = $orderPackageModel->whereIn('bill_code', $params['arr_bill_code'])->get();

        if (count($orderPackageDBs)) {
            foreach ($orderPackageDBs as $key => $orderPackageDB) {
                if ($orderPackageDB->status == PackageConstant::STATUS_RECEIVED_GOODS) {
                    $result = [
                        'unit' => 'Kiện ' . $orderPackageDB->bill_code . ' đã nhận hàng'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                    break;
                }

                if ($orderPackageDB->created_at >= '2024-10-07 00:00:00') {
                    // $result = [
                    //     'unit' => 'Kiện ' . $orderPackageDB->bill_code . ' thời gian không hợp lệ'
                    // ];
                    // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                    // break;
                }
            }
        }

        try {
            $logEditModel = \App::make('App\Models\LogEdit');
            \DB::beginTransaction();
            foreach ($orderPackageDBs as $key => $orderPackageDB) {
                // Ghi log
                $data_log_pack = [
                    'old' => ['status' => $orderPackageDB->status,],
                    'new' => ['status' => PackageConstant::STATUS_RECEIVED_GOODS]
                ];
                $log_order = [
                    'log_name'    => 'order_package',
                    'description' => 'status',
                    'log_id'      => $orderPackageDB->id,
                    'data'        => json_encode($data_log_pack, JSON_UNESCAPED_UNICODE)
                ];
                $logEditDB = $logEditModel->create($log_order);

                // Đổi trạng thái
                $orderPackageDB->status = PackageConstant::STATUS_RECEIVED_GOODS;
                $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB) {
                    $orderPackageDB->save();
                    return $orderPackageDB;
                });
            }
            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateInfoOrder(Request $request)
    {
        // Dùng cho Nhung
        $validationRules = [
            'token'         => 'required',
            'order_id'      => 'required|exists:orders,id',
            'order_fee'     => 'required|numeric|min:1',
            'discount_cost' => 'required|numeric|min:0',
            'order_cost'    => 'required|numeric|min:1',
            'time'          => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        \Log::info('updateInfoOrder : ' . request()->ip());

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn18882431510044QwmObgbRmnbmT1') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check trùng thời gian hiện tại
        $minuteCurrent = (int)date('i', time());

        if ($minuteCurrent != $params['time']) {
            $result = [
                'unit' => 'Lỗi time'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $orderModel = \App::make('App\Models\Order');
        $logEditModel = \App::make('App\Models\LogEdit');

        $orderDB = $orderModel->where('id', $params['order_id'])->first();

        try {
            \DB::beginTransaction();

            // Ghi log
            $data_log_pack = [
                'old' => [
                    'order_fee'       => $orderDB->order_fee,
                    'discount_cost'   => $orderDB->discount_cost,
                    'order_cost'      => $orderDB->order_cost,
                    'order_cost_real' => $orderDB->order_cost_real,
                ],
                'new' => [
                    'order_fee'       => $params['order_fee'],
                    'discount_cost'   => $params['discount_cost'],
                    'order_cost'      => $params['order_cost'],
                    'order_cost_real' => $params['order_cost'],
                ]
            ];
            $log_order = [
                'log_name'    => 'order',
                'description' => 'order_fee, discount_cost in updateInfoOrder',
                'log_id'      => $orderDB->id,
                'data'        => json_encode($data_log_pack, JSON_UNESCAPED_UNICODE)
            ];
            $logEditDB = $logEditModel->create($log_order);

            // Lưu data
            $orderDB->order_fee = $params['order_fee'];
            $orderDB->discount_cost = $params['discount_cost'];
            $orderDB->order_cost = $params['order_cost'];
            $orderDB->order_cost_real = $params['order_cost'];
            $orderDB = Order::withoutEvents(function () use ($orderDB) {
                $orderDB->save();
                return $orderDB;
            });


            // Ghi Log OrderSup
            $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
            if ($orderSupplierFirst) {
                // Lưu log
                $data_log_pack = [
                    'old' => [
                        'order_fee'     => $orderSupplierFirst->order_fee,
                        'discount_cost' => $orderSupplierFirst->discount_cost,
                        'order_cost'    => $orderSupplierFirst->order_cost,
                    ],
                    'new' => [
                        'order_fee'     => $params['order_fee'],
                        'discount_cost' => $params['discount_cost'],
                        'order_cost'    => $params['order_cost'],
                    ]
                ];
                $log_order = [
                    'log_name'    => 'order_supplier',
                    'description' => 'order_fee, discount_cost in updateInfoOrder',
                    'log_id'      => $orderSupplierFirst->id,
                    'data'        => json_encode($data_log_pack, JSON_UNESCAPED_UNICODE)
                ];
                $logEditDB = $logEditModel->create($log_order);

                // Lưu data
                $orderSupplierFirst->order_fee = $params['order_fee'];
                $orderSupplierFirst->discount_cost = $params['discount_cost'];
                $orderSupplierFirst->order_cost = $params['order_cost'];
                $orderSupplierFirst->save();
            }

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateAllInternalShippingCost(Request $request)
    {
        // $orderModel = \App::make('App\Models\Order');
        // $orderDBs = $orderModel->where('deposit_percent', 100)
        //                         ->where('remaining_amount', '>', 0)
        //                         ->get();
        // $arr_ids = \Arr::pluck($orderDBs, 'id');
        // dump($arr_ids);


        $orderModel = \App::make('App\Models\Order');
        $orderDBs = $orderModel->where('created_at', '>', '2024-09-01 00:00:00')
                                ->where('created_at', '<', '2024-10-05 00:00:00')
                                ->get();
        if (count($orderDBs)) {
            dump(count($orderDBs));
            $count = 0;
            foreach ($orderDBs as $key => $orderDB) {
                $countCheck = $this->checkInternalShippingCost($orderDB);
                $count += $countCheck;
            }
            dd($count);
        }
    }

    public function checkInternalShippingCost($orderDB)
    {
        $countCheck = 0;
        $orderSuppliers = $orderDB->orderSuppliers;
        $shipOldInSup = $orderSuppliers->sum('international_shipping_cost');
        $shipOldInOrder = $orderDB->international_shipping_cost;

        $packageDBs = $orderDB->packagesOrder;
        $shipNew = $packageDBs->sum('international_shipping_cost');
        $goodNew = $packageDBs->sum('woodworking_cost');

        if (
            $shipNew != $shipOldInSup ||
            $shipNew != $shipOldInOrder
        ) {
            $countCheck = 1;
            try {
                \DB::beginTransaction();

                // Cập nhật vào đơn phụ
                $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
                if ($orderSupplierFirst) {
                    $orderSupplierFirst->international_shipping_cost = $shipNew;
                    $orderSupplierFirst->woodworking_cost = $goodNew;
                    $orderSupplierFirst->save();
                }

                // Cập nhật vào đơn tổng
                $orderDB->international_shipping_cost = $shipNew;
                $orderDB->woodworking_cost = $goodNew;
                $orderDB->save();

                \DB::commit();
            } catch (Exception $e) {
                \DB::rollback();
                throw $e;
            }
        }
        return $countCheck;
    }

    public function updateAll5Cost(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderDBs = $orderModel->where('date_done', '>', '2024-10-03 00:00:00')
                                ->whereNotNull('thuongdo_id')
                                // ->where('status', 'status_4')
                                ->get();
        if (count($orderDBs)) {
            dump(count($orderDBs));
            $count = 0;
            foreach ($orderDBs as $key => $orderDB) {
                $countCheck = $this->updateAll5CostAction($orderDB);
                $count += $countCheck;

            }
            dd($count);
        }
    }

    public function updateAll5CostAction($orderDB)
    {
        $countCheck = 0;
        $orderSuppliers = $orderDB->orderSuppliers;
        $order_cost = $orderDB->order_cost;
        $order_sup_cost = 0;
        if (count($orderSuppliers)) {
            $order_sup_cost = $orderSuppliers->sum('order_cost');
        }
        $orderStop = 88;
        if ($order_cost != $order_sup_cost && $orderDB->id > $orderStop) {
            dump($orderDB->id);
            dump($orderDB->code);
            $arr_order = [
                'order_cost'          => $orderDB->order_cost,
                'order_fee'           => $orderDB->order_fee,
                'discount_cost'       => $orderDB->discount_cost,
                'inspection_cost'     => $orderDB->inspection_cost,
                'china_shipping_cost' => $orderDB->china_shipping_cost,
            ];

            dump($arr_order);

            $orderSuppliers = $orderDB->orderSuppliers;
            if (count($orderSuppliers)) {
                $arr_order_sup = [
                    'order_cost'          => $orderSuppliers->sum('order_cost'),
                    'order_fee'           => $orderSuppliers->sum('order_fee'),
                    'discount_cost'       => $orderSuppliers->sum('discount_cost'),
                    'inspection_cost'     => $orderSuppliers->sum('inspection_cost'),
                    'china_shipping_cost' => $orderSuppliers->sum('china_shipping_cost')
                ];
                dump($arr_order_sup);
            }
            $countCheck = 1;

            if ($orderDB->id > $orderStop) {
                dd();
            }
        }
        return $countCheck;
    }

    public function getListOrderError(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');

        $select = '
            id, code, order_cost,
            (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) as total_order,
            (deposit_cost + delivery_pay_debt_cost) as total_pay';


        $selectRaw = "select id, code, order_cost, thuongdo_tong_tien_don_hang,
            (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) as total_order,
            (deposit_cost + delivery_pay_debt_cost) as total_pay
            from orders
            where status = 'status_4' and remaining_amount = 0 and
            (deposit_cost + delivery_pay_debt_cost) > (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) and date_done > '2024-10-03 00:00:00' and thuongdo_id is not null and has_edit = 0
        ";

        $orderDBs = DB::select(DB::raw($selectRaw));

        $arr_pack_code = "";
        if (count($orderDBs) > 0) {
            foreach ($orderDBs as $key => $packDB) {
                $arr_pack_code .= $packDB->id . ",";
            }
        }
        $newarraynama = rtrim($arr_pack_code, ",");
        \Log::info($newarraynama);

        $arr_code = explode(",",$newarraynama);
        \Log::info($arr_code);

        dd($arr_code);
    }


    public function updateListOrderError(Request $request)
    {
        $selectRaw = "select id, code, order_cost, deposit_percent, deposit_cost, delivery_pay_debt_cost,thuongdo_tong_tien_tam_ung,thuongdo_tong_tien_don_hang,
            (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) as total_order,
            (deposit_cost + delivery_pay_debt_cost) as total_pay,
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung) as total_pay_real
            from orders
            where status = 'status_4' and remaining_amount = 0 and
            (deposit_cost + delivery_pay_debt_cost) > (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) and date_done > '2024-10-03 00:00:00' and thuongdo_id is not null and has_edit = 0
        ";

        $orderDBs = DB::select(DB::raw($selectRaw));

        if (count($orderDBs)) {
            $count = 0;
            foreach ($orderDBs as $key => $orderDB) {
               // $countCheck =  $this->updateListOrderErrorAction($orderDB);
               $countCheck =  $this->updateListOrderErrorActionV2($orderDB);
               $count += $countCheck;
            }

            dd($count);
        }
    }

    public function updateListOrderErrorAction($orderDB)
    {
        $countCheck = 0;
        $orderModel = \App::make('App\Models\Order');
        try {
            \DB::beginTransaction();

            $orderInDB = $orderModel->where('id', $orderDB->id)->first();
            dump($orderDB);

            if ($orderInDB) {
                $countCheck = 1;
                // Xử lý phần chênh
                $chenh = $orderDB->total_pay - $orderDB->total_order;
                dump($chenh);

                if ($chenh > 0) {


                    $orderSuppliers = $orderInDB->orderSuppliers;
                    $arr_order = [
                        'order_cost'          => $orderInDB->order_cost,
                        'order_fee'           => $orderInDB->order_fee,
                        'discount_cost'       => $orderInDB->discount_cost,
                        'inspection_cost'     => $orderInDB->inspection_cost,
                        'china_shipping_cost' => $orderInDB->china_shipping_cost,
                    ];
                    dump($arr_order);

                    if (count($orderSuppliers)) {
                        $arr_order_sup = [
                            'order_cost'          => $orderSuppliers->sum('order_cost'),
                            'order_fee'           => $orderSuppliers->sum('order_fee'),
                            'discount_cost'       => $orderSuppliers->sum('discount_cost'),
                            'inspection_cost'     => $orderSuppliers->sum('inspection_cost'),
                            'china_shipping_cost' => $orderSuppliers->sum('china_shipping_cost')
                        ];
                        dump($arr_order_sup);
                    }

                    if ($chenh <= 1000) {
                        // Cộng tiền vào tiền hàng vào đơn chính
                        $orderInDB->order_cost += $chenh;

                        // Cộng tiền vào tiền hàng vào đơn phụ
                        $orderSupplierFirst = optional($orderInDB->orderSuppliers)->first();
                        if ($orderSupplierFirst) {
                            $orderSupplierFirst->order_cost = $orderInDB->order_cost;
                            $orderSupplierFirst->save();
                        }
                    }

                    if ($chenh > 1000) {
                        $orderSuppliers = $orderInDB->orderSuppliers;
                        $arr_order = [
                            'order_cost'          => $orderInDB->order_cost,
                            'order_fee'           => $orderInDB->order_fee,
                            'discount_cost'       => $orderInDB->discount_cost,
                            'inspection_cost'     => $orderInDB->inspection_cost,
                            'china_shipping_cost' => $orderInDB->china_shipping_cost,
                        ];
                        dump($arr_order);

                        if (count($orderSuppliers)) {
                            $arr_order_sup = [
                                'order_cost'          => $orderSuppliers->sum('order_cost'),
                                'order_fee'           => $orderSuppliers->sum('order_fee'),
                                'discount_cost'       => $orderSuppliers->sum('discount_cost'),
                                'inspection_cost'     => $orderSuppliers->sum('inspection_cost'),
                                'china_shipping_cost' => $orderSuppliers->sum('china_shipping_cost')
                            ];
                            dump($arr_order_sup);
                        }
                        $orderInDB->order_cost = $arr_order_sup['order_cost'];


                        // Hoàn tiền cho khách
                        // $content     = 'Hoàn ' . number_format($chenh,0,",",".") . 'đ cho đơn hàng mã ' . $orderInDB->code .  ' vì thay đổi đơn hàng';
                        // $soure       = $orderInDB;
                        // $amount      = $chenh;
                        // $customer_id = (int)$orderInDB->customer_id;

                        // (new \App\Services\TransactionService())->setTransactionIncrement(
                        //     $amount,
                        //     \App\Constants\TransactionConstant::STATUS_REFUND,
                        //     $content,
                        //     $customer_id,
                        //     $soure
                        // );
                    }
                }


                // Xử lý phần đặt cọc
                if ($orderDB->deposit_percent == 0) {
                    $percent = $orderDB->deposit_cost / $orderDB->total_pay * 100;
                    $percent = round($percent, 0);
                    $orderInDB->deposit_percent = $percent;
                }

                // Xóa thuongdo_id
                $orderInDB->thuongdo_id_old = $orderInDB->thuongdo_id;
                $orderInDB->thuongdo_id = null;


                // Lưu lại
                $orderInDB->save();

                $orderSuppliers = $orderInDB->orderSuppliers;
                $arr_order = [
                    'order_cost'          => $orderInDB->order_cost,
                    'order_fee'           => $orderInDB->order_fee,
                    'discount_cost'       => $orderInDB->discount_cost,
                    'inspection_cost'     => $orderInDB->inspection_cost,
                    'china_shipping_cost' => $orderInDB->china_shipping_cost,
                    'dat_coc'             => $orderInDB->thuongdo_tong_tien_tam_ung,
                    'tong_tien'           => $orderInDB->total_amount,
                    'con_lai_code'        => $orderInDB->total_amount - $orderInDB->thuongdo_tong_tien_tam_ung,
                    'con_lai_da_tra'      => $orderInDB->delivery_pay_debt_cost,
                    "chenh"               => $orderInDB->total_amount - $orderInDB->thuongdo_tong_tien_tam_ung - $orderInDB->delivery_pay_debt_cost,
                    'phiship'             => $orderInDB->delivery_pay_shipping_cost,
                ];
                // dump($arr_order);

                if (count($orderSuppliers)) {
                    $arr_order_sup = [
                        'order_cost'          => $orderSuppliers->sum('order_cost'),
                        'order_fee'           => $orderSuppliers->sum('order_fee'),
                        'discount_cost'       => $orderSuppliers->sum('discount_cost'),
                        'inspection_cost'     => $orderSuppliers->sum('inspection_cost'),
                        'china_shipping_cost' => $orderSuppliers->sum('china_shipping_cost')
                    ];
                    // dump($arr_order_sup);
                }
            }
            // \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }


    public function updateListOrderErrorActionV2($orderDB)
    {
        $countCheck = 0;
        $orderModel = \App::make('App\Models\Order');
        try {
            \DB::beginTransaction();

            $orderInDB = $orderModel->where('id', $orderDB->id)->first();
            if ($orderInDB) {

                $flagSave = false;

                $chenh = $orderInDB->thuongdo_tong_tien_don_hang - $orderInDB->thuongdo_tong_tien_tam_ung - $orderInDB->delivery_pay_debt_cost;

                if ($chenh < -1000) {
                    $countCheck = 1;
                    $flagSave = true;
                    // dump($orderInDB->id . '-' . round($chenh));

                    // Hoàn tiền cho khách
                    $content     = 'Hoàn ' . number_format(abs($chenh),0,",",".") . 'đ cho đơn hàng mã ' . $orderInDB->code .  ' vì thay đổi đơn hàng';
                    \Log::info($content);
                    $soure       = $orderInDB;
                    $amount      = abs($chenh);
                    $customer_id = (int)$orderInDB->customer_id;

                    (new \App\Services\TransactionService())->setTransactionIncrement(
                        $amount,
                        \App\Constants\TransactionConstant::STATUS_REFUND,
                        $content,
                        $customer_id,
                        $soure,
                        $orderInDB
                    );

                    // Cập nhật lại delivery_pay_debt_cost
                    $orderInDB->delivery_pay_debt_cost -= $amount;
                }

                // Xử lý phần đặt cọc
                if ($orderDB->deposit_percent == 0) {
                    $flagSave = true;
                    $percent = $orderDB->deposit_cost / $orderDB->total_pay * 100;
                    $percent = round($percent, 0);
                    $orderInDB->deposit_percent = $percent;
                    // dump($orderInDB->id . '-' . round($percent));
                    // \Log::info($orderInDB->id . '-' . round($percent));
                }

                // Lưu lại
                if ($flagSave == true) {
                    $orderInDB->save();
                }
            }
            \DB::commit();
            return $countCheck;
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }


    public function getListOrderErrorV2(Request $request)
    {
        $selectRaw = "select id, code, order_cost, deposit_percent, deposit_cost, delivery_pay_debt_cost,thuongdo_tong_tien_tam_ung,thuongdo_tong_tien_don_hang,
            (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) as total_order,
            (deposit_cost + delivery_pay_debt_cost) as total_pay,
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung) as total_pay_real,
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) as sub_chenh
            from orders
            where
            status = 'status_4' and
            remaining_amount = 0 and
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) >  -1000 and
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) <  1000 and
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) !=  0 and
            date_done > '2024-10-03 00:00:00' and
            thuongdo_id is not null and
            has_edit = 0
        ";

        $orderDBs = DB::select(DB::raw($selectRaw));

        $arr_pack_code = "";
        if (count($orderDBs) > 0) {
            foreach ($orderDBs as $key => $packDB) {
                $arr_pack_code .= $packDB->id . ",";
            }
        }
        $newarraynama = rtrim($arr_pack_code, ",");
        \Log::info($newarraynama);

        $arr_code = explode(",",$newarraynama);
        \Log::info($arr_code);

        dd($arr_code);
    }

    public function updateListOrderErrorV2(Request $request)
    {
        $selectRaw = "select id, code, order_cost, deposit_percent, deposit_cost, delivery_pay_debt_cost,thuongdo_tong_tien_tam_ung,thuongdo_tong_tien_don_hang,
            (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) as total_order,
            (deposit_cost + delivery_pay_debt_cost) as total_pay,
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung) as total_pay_real,
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) as sub_chenh
            from orders
            where
            status = 'status_4' and
            remaining_amount = 0 and
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) >  -1000 and
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) <  1000 and
            (thuongdo_tong_tien_don_hang - thuongdo_tong_tien_tam_ung - delivery_pay_debt_cost) !=  0 and
            date_done > '2024-10-03 00:00:00' and
            thuongdo_id is not null and
            has_edit = 0
        ";

        $orderDBs = DB::select(DB::raw($selectRaw));


        if (count($orderDBs)) {
            $count = 0;
            foreach ($orderDBs as $key => $orderDB) {
               $countCheck =  $this->updateListOrderErrorV2Action($orderDB);
               $count += $countCheck;
            }
            dd($count);
        }
    }

    public function updateListOrderErrorV2Action($orderDB)
    {
        $countCheck = 0;
        $orderModel = \App::make('App\Models\Order');
        try {
            \DB::beginTransaction();

            $orderInDB = $orderModel->where('id', $orderDB->id)->first();
            if ($orderInDB) {
                $countCheck = 1;
                $chenh = $orderDB->sub_chenh;
                if ($chenh > 0) {
                    $orderInDB->delivery_pay_debt_cost += $chenh;
                } else {
                    $orderInDB->delivery_pay_debt_cost -= abs($chenh);
                }
                $orderInDB->save();

                // $abs = $chenh < 0 ? 'MIN' : 'MAX';
                // \Log::info($abs . '-' . $orderInDB->id . ':' . $chenh);
            }
            \DB::commit();
            return $countCheck;
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function revertThuongDoID(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderDBs = $orderModel->where('id', '<', 444724)
                                ->where('status', 'status_4')
                                ->whereNull('thuongdo_id')
                                ->where('thuongdo_tong_tien_tam_ung', '>', 0)
                                ->where('thuongdo_tong_tien_don_hang', '>', 0)
                                // ->where('customer_id', 182507)
                                ->get();
        if (count($orderDBs)) {
            dump(count($orderDBs));
            foreach ($orderDBs as $key => $orderDB) {
                $orderDB->thuongdo_id = "TD_241024";
                $orderDB->save();
            }
        }
    }








































    public function checkAllNotLoginFirst(Request $request)
    {
        $selectRaw = "
            select *
            from personal_access_tokens
            where
            tokenable_type LIKE '%Customer'
            group by tokenable_id
        ";

        // $loginTokens = DB::select(DB::raw($selectRaw));

        $loginTokens = DB::table('personal_access_tokens')
                                ->where('tokenable_type', 'App\Models\Customer')
                                ->groupBy('tokenable_id')
                                ->get();

        $arr_ids = \Arr::pluck($loginTokens, 'tokenable_id');
        $arr_ids = array_unique($arr_ids);

        $offset = $request->get('offset');
        $limit = 20000;
        $offsetNew = $offset * $limit;

        $customerModel = \App::make('App\Models\Customer');
        $customerDBs = $customerModel->whereNotIn('id', $arr_ids)
                                    ->limit($limit)
                                    ->offset($offsetNew)
                                    ->get();

        if (count($customerDBs)) {
            \Log::info($offset . '-' . count($customerDBs));
            try {
                // \DB::beginTransaction();
                $randNumber = 'RK1tIPZe8nLt7qX';
                $passDefault = \Hash::make($randNumber);
                foreach ($customerDBs as $key => $customerDB) {
                    // $this->resetPassCustomer($customerDB, $passDefault);
                    $job = new \App\Jobs\SyncTD\SyncResetPassCustomerNotLogin($customerDB, $passDefault);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
                }
                // \DB::commit();
                return count($customerDBs);
            } catch (Exception $e) {
                // \DB::rollback();
            }
        }
    }

    public function resetPassCustomer($customerDB, $passDefault)
    {
        $customerDB->password = $passDefault;
        $customerDB = Customer::withoutEvents(function () use ($customerDB) {
            $customerDB->save();
            return $customerDB;
        });
    }











































    public function getListOrderErrorV3(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderDBs = $orderModel->where('status', 'status_4')
                                // ->where('customer_id', 146020)
                                ->where('created_at', '>', '2024-08-15')
                                ->get();

        if (count($orderDBs)) {
            \Log::info('hehe: ' . count($orderDBs));
            foreach ($orderDBs as $key => $orderDB) {
                // $this->updateSubOrder($orderDB);
                $job = new \App\Jobs\SyncTD\SyncUpdateSubOrder($orderDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }
    }

    public function updateSubOrder($orderDB)
    {
        $totalAmountDisplay = $orderDB->getTotalAmount(false);
        $delivery_pay_debt_cost = $orderDB->delivery_pay_debt_cost;
        $delivery_pay_shipping_cost = $orderDB->delivery_pay_shipping_cost;
        $deposit_cost = $orderDB->deposit_cost;

        $diff =  round($totalAmountDisplay) - $delivery_pay_debt_cost - $delivery_pay_shipping_cost - $deposit_cost;

        try {
            \DB::beginTransaction();

            // Nếu lệch nhỏ
            if (
                $diff >= -500 &&
                $diff <= 500 &&
                $diff != 0
            ) {
                // // Lưu log
                // $this->saveLogEditOrderPayDebtCost($orderDB, $diff);

                // // Xử lý
                // if ($diff > 0) {
                //     $orderDB->delivery_pay_debt_cost += $diff;
                // } else {
                //     $orderDB->delivery_pay_debt_cost -= abs($diff);
                // }
                // $orderDB = Order::withoutEvents(function () use ($orderDB) {
                //     $orderDB->save();
                //     return $orderDB;
                // });
            }

            // Nếu lệch dưới 9000
            if ($diff > 500 && $diff < 9000) {
                if ($orderDB->order_fee == 9000) {
                    if ($orderDB->order_percent > 0) {
                        $orderFeeNew = $orderDB->order_cost * $orderDB->order_percent / 100;
                        $orderFeeNew = round($orderFeeNew);

                        // Lưu log
                        $this->saveLogEditOrderFee($orderDB, $orderFeeNew);

                        // Lưu vào order
                        $orderDB->order_fee = $orderFeeNew;
                        $orderDB = Order::withoutEvents(function () use ($orderDB) {
                            $orderDB->save();
                            return $orderDB;
                        });

                        // Lưu vào OrderSup
                        $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
                        if ($orderSupplierFirst) {
                            $orderSupplierFirst->order_fee = $orderFeeNew;
                            $orderSupplierFirst->discount_cost = $orderDB->discount_cost;
                            $orderSupplierFirst->save();
                        }
                    }
                }
            }


            // Nếu lệch trên 9000

            if ($diff > 9000) {
                if ($orderDB->delivery_pay_shipping_cost == 0) {
                    $packageDBs = $orderDB->packagesOrder;

                    $pay_shipping_cost = $packageDBs->sum('amount');
                    $diff_ship = $pay_shipping_cost - $diff;

                    if (abs($diff_ship) < 9000) {
                        // Lưu log
                        $this->saveLogEditOrderPayShipingCost($orderDB, $pay_shipping_cost);

                        // Lưu lại
                        $orderDB->delivery_pay_shipping_cost = $pay_shipping_cost;
                        $orderDB = Order::withoutEvents(function () use ($orderDB) {
                            $orderDB->save();
                            return $orderDB;
                        });
                    }
                }
            }

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function saveLogEditOrderPayDebtCost($orderDB, $diff)
    {
        $logEditModel = \App::make('App\Models\LogEdit');
        $delivery_pay_debt_cost_new = $orderDB->delivery_pay_debt_cost;
        if ($diff > 0) {
            $delivery_pay_debt_cost_new += $diff;
        }
        if ($diff < 0) {
            $delivery_pay_debt_cost_new -= abs($diff);
        }

        $data_log_order = [
            'old' => [
                'delivery_pay_debt_cost' => $orderDB->delivery_pay_debt_cost,
            ],
            'new' => [
                'delivery_pay_debt_cost' => $delivery_pay_debt_cost_new
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'delivery_pay_debt_cost',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];
        $logEditDB = $logEditModel->create($log_order);
    }

    public function saveLogEditOrderFee($orderDB, $orderFeeNew)
    {
        $logEditModel = \App::make('App\Models\LogEdit');
        // Lưu log Order
        $data_log_order = [
            'old' => [
                'order_fee' => $orderDB->order_fee,
            ],
            'new' => [
                'order_fee' => $orderFeeNew
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'order_fee',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];
        $logEditDB = $logEditModel->create($log_order);

        // Lưu log OrderSup
        $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
        if ($orderSupplierFirst) {
            $data_log_order = [
                'old' => [
                    'order_fee'     => $orderDB->order_fee,
                    'discount_cost' => $orderSupplierFirst->discount_cost,
                ],
                'new' => [
                    'order_fee'     => $orderFeeNew,
                    'discount_cost' => $orderDB->discount_cost
                ]
            ];
            $log_order_sup = [
                'log_name'    => 'order_supplier',
                'description' => 'order_fee, discount_cost',
                'log_id'      => $orderSupplierFirst->id,
                'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
            ];
            $logEditDB = $logEditModel->create($log_order_sup);
        }
    }

    public function saveLogEditOrderPayShipingCost($orderDB, $pay_shipping_cost)
    {
        $logEditModel = \App::make('App\Models\LogEdit');
        // Lưu log Order
        $data_log_order = [
            'old' => [
                'delivery_pay_shipping_cost' => $orderDB->delivery_pay_shipping_cost,
            ],
            'new' => [
                'delivery_pay_shipping_cost' => $pay_shipping_cost
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'delivery_pay_shipping_cost',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];

        $logEditDB = $logEditModel->create($log_order);
    }



    public function updateDepositCost(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');
        $logEditModel = \App::make('App\Models\LogEdit');
        $arr_update = [
            '973500063' => 224601,
            '973500095' => 297706,
            '973500101' => 446558,
            '973500108' => 219613,
            '973500107' => 443693,
            '973500122' => 221596
        ];
        foreach ($arr_update as $key => $order) {
            $orderDB = $orderModel->where('code', $key)->first();
            if (!is_null($orderDB)) {
                if ($orderDB->deposit_percent == 100) {
                    // Lưu log

                    // Lưu log Order
                    $data_log_order = [
                        'old' => [
                            'deposit_cost' => $orderDB->deposit_cost,
                        ],
                        'new' => [
                            'deposit_cost' => $order
                        ]
                    ];

                    $log_order = [
                        'log_name'    => 'orders',
                        'description' => 'deposit_cost',
                        'log_id'      => $orderDB->id,
                        'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
                    ];

                    $logEditDB = $logEditModel->create($log_order);


                    // Lưu lại

                    $orderDB->deposit_cost = $order;
                    // $orderDB->save();
                    $orderDB = Order::withoutEvents(function () use ($orderDB) {
                        $orderDB->save();
                        return $orderDB;
                    });
                }
            }
        }
    }

    public function addBankTPB(Request $request)
    {
        $bank_id = 970423;
        $bank_name = 'Ngân hàng TMCP Tiên Phong';
        $bank_shotname = 'TPBank';

        $bankModel = \App::make('App\Models\Bank');
        $bankTP = $bankModel->where('bank_id', $bank_id)->first();
        if (is_null($bankTP)) {
            $data_bank_tp = [
                'name'        => $bank_name,
                'short_name'  => $bank_shotname,
                'bank_id'     => $bank_id,
                'country'     => 'vi',
                'priority'    => 0,
                'charge_code' => 'HV',
            ];
            $bankTP = $bankModel->create($data_bank_tp);
        }
    }

    public function udpateMrTruong(Request $request)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderPackageDB = $orderPackageModel->where('code_warehouse', 'XD400895297433994')
                                    ->where('id', 844265)
                                    ->first();

        if (!is_null($orderPackageDB)) {
            $orderPackageDB->bill_code = 'SF3111313624786'; // Gốc lúc edit là SF3138206174409
            $orderPackageDB->code_warehouse = NULL; // Lúc đầu là XD400895297433994
            // Mã đặt hàng:2342549499840133353
            $orderPackageDB->save();
            // $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB) {
            //     $orderPackageDB->save();
            //     return $orderPackageDB;
            // });
        }


        $orderPackageDB = $orderPackageModel->where('code_warehouse', 'XD547406858871768')
                                    ->where('id', 847831)
                                    ->first();

        if (!is_null($orderPackageDB)) {
            $orderPackageDB->bill_code = '73535257714984'; // Gốc lúc edit là 74100352703444
            $orderPackageDB->code_warehouse = NULL; // Lúc đầu là XD547406858871768
            // Mã đặt hàng:2348503286931133353
            $orderPackageDB->save();
            // $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB) {
            //     $orderPackageDB->save();
            //     return $orderPackageDB;
            // });
        }
    }

    public function resetBillCode(Request $request)
    {
        $logEditModel = \App::make('App\Models\LogEdit');

        $validationRules = [
            'id'        => 'required|exists:order_package,id',
            'bill_code' => 'required|exists:order_package,bill_code',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderPackageDB = $orderPackageModel->where('id', $params['id'])
                                            ->where('bill_code', $params['bill_code'])
                                            ->where('status', 'status_0')
                                            ->where('created_at', '>', '2024-10-07')
                                            ->first();

        if (!is_null($orderPackageDB)) {
            // Lưu log Order
            $data_log_order = [
                'old' => [
                    'bill_code' => $orderPackageDB->bill_code,
                ],
                'new' => [
                    'bill_code' => null
                ]
            ];

            $log_order = [
                'log_name'    => 'order_package',
                'description' => 'update',
                'log_id'      => $orderPackageDB->id,
                'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
            ];

            $logEditDB = $logEditModel->create($log_order);
            $orderPackageDB->bill_code = null;
            // $orderPackageDB->save();
            $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB) {
                $orderPackageDB->save();
                return $orderPackageDB;
            });
        }
    }

    public function getListPackNotCodeXD(Request $request)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderDBs = $orderPackageModel->whereNull('code_warehouse')
                ->whereNotNull('bill_code')
                ->where('status', 'status_0')
                ->where('created_at', '>', '2024-10-01')
                ->get();

        $arr_pack_code = "";
        if (count($orderDBs) > 0) {
            foreach ($orderDBs as $key => $packDB) {
                $arr_pack_code .= $packDB->bill_code . ",";
            }
        }
        $newarraynama = rtrim($arr_pack_code, ",");
        \Log::info($newarraynama);

        $arr_code = explode(",",$newarraynama);
        \Log::info($arr_code);

        dd($arr_code);
    }

    public function updateListPackNotCodeXD(Request $request)
    {
        $arr_bill_code = [
            'SF3111313624786',
            '73535257714984'
        ];
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderDBs = $orderPackageModel->whereNull('code_warehouse')
                ->whereNotNull('bill_code')
                ->where('status', 'status_0')
                ->where('created_at', '>', '2024-10-01')
                ->where('order_type', 'App\Models\Order')
                ->get();

        if (count($orderDBs) > 0) {
            \Log::info('updateListPackNotCodeXD: ' . count($orderDBs));
            foreach ($orderDBs as $key => $packDB) {
                $job = new \App\Jobs\OneWarehouse\SyncPackStatusPending($packDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }
    }

    public function updateDatHang(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', 456028)->first();

        if (!is_null($orderDB)) {
            if ($orderDB->status != OrderConstant::KEY_STATUS_ORDERED) {
                $orderDB->status = OrderConstant::KEY_STATUS_ORDERED;
                $orderDB->save();
                dd("Zoo done");
            }
        }
    }


    public function checkOrderCostRealAndShippingReal(Request $request)
    {
        // Dùng cho Nhung
        $validationRules = [
            'token'         => 'required',
            'order_id'      => 'required|exists:orders,id',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        \Log::info('checkOrderCostRealAndShippingReal : ' . request()->ip());

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn18882431510044QwmObgbRmnbmT1B91') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $params['order_id'])->first();


        // Bắn sang TD để lấy tiền hàng thực tế
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/orders-fulfillment';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 0,
                ],
                [
                    'name'     => 'order_code',
                    'contents' => $orderDB->code
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);


        $tong_tien_dat_hang_thuc_te  = 0;

        if ($result && count($result)) {
            $orders_supplierTD = $result[0]['orders_supplier'];
            $orders_supplierTD = reset($orders_supplierTD);
            $tong_tien_dat_hang_thuc_te = $orders_supplierTD['tong_tien_dat_hang_thuc_te'];
        }

        return [
            'tong_tien_dat_hang_thuc_te_api' => $tong_tien_dat_hang_thuc_te,
            'china_shipping_cost_real_db' => $orderDB->china_shipping_cost_real,
            'order_cost_real_db' => $orderDB->order_cost_real / $orderDB->exchange_rate,
        ];
    }

    public function updateCostAndShipReal(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderDBs = $orderModel->where('date_done', '>=', '2024-10-01 00:00:00')
                                ->where('date_done', '<=', '2024-10-31 23:59:59')
                                ->whereNotNull('thuongdo_id')
                                // ->where('id', 7)
                                ->get();

        \Log::info('updateCostAndShipRealCount: ' .  count($orderDBs));

        if (count($orderDBs) > 0) {
            foreach ($orderDBs as $key => $orderDB) {
                // $this->updateCostAndShipRealAction($orderDB);
                $job = new \App\Jobs\SyncTD\SyncOrderCostAndShippingReal($orderDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    public function updateCostAndShipRealAction($orderDB)
    {
        // Bắn sang TD để lấy tiền hàng thực tế
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/orders-fulfillment';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 0,
                ],
                [
                    'name'     => 'order_code',
                    'contents' => $orderDB->code
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
            $orders_supplierTD = $result[0]['orders_supplier'];
            $orders_supplierTD = reset($orders_supplierTD);

            \Log::info('saveLogEditOrderCostReal response: ' . $orderDB->id . " : " . $orders_supplierTD['tong_tien_dat_hang_thuc_te']);

            try {
                \DB::beginTransaction();
                $this->saveLogEditOrderCostReal($orderDB, $orders_supplierTD['tong_tien_dat_hang_thuc_te']);
                \DB::commit();
            } catch (Exception $e) {
                \DB::rollback();
                throw $e;
            }
        } else {
            \Log::info('Không có data: ' .  $orderDB->id );
        }
    }

    public function saveLogEditOrderCostReal($orderDB, $tong_tien_dat_hang_thuc_te)
    {
        $logEditModel = \App::make('App\Models\LogEdit');

        $tong_tien_dat_hang_thuc_te_new = $tong_tien_dat_hang_thuc_te;

        if ($tong_tien_dat_hang_thuc_te_new == null) {
            return;
        }
        if ($orderDB->china_shipping_cost_real > 0) {
            $tong_tien_dat_hang_thuc_te_new = $tong_tien_dat_hang_thuc_te_new - $orderDB->china_shipping_cost_real;
        }
        // Lưu log Order
        $data_log_order = [
            'old' => [
                'order_cost_real' => $orderDB->order_cost_real,
            ],
            'new' => [
                'order_cost_real' => round($tong_tien_dat_hang_thuc_te_new * $orderDB->exchange_rate, 2)
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'update',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];

        $data_log_order['old']['order_cost'] = $orderDB->order_cost;
        $data_log_order['old']['exchange_rate'] = $orderDB->exchange_rate;
        $data_log_order['old']['china_shipping_cost_real'] = $orderDB->china_shipping_cost_real;


        if ($data_log_order['old']['order_cost_real'] != $data_log_order['new']['order_cost_real']) {
            \Log::info('saveLogEditOrderCostReal: ' . $orderDB->id . " : " . json_encode($data_log_order, JSON_UNESCAPED_UNICODE));
            $logEditDB = $logEditModel->create($log_order);
        }

        // Lưu log OrderSup
        $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
        if ($orderSupplierFirst) {
            $data_log_order = [
                'old' => [
                    'order_cost_real' => $orderSupplierFirst->order_cost_real,
                ],
                'new' => [
                    'order_cost_real' => $tong_tien_dat_hang_thuc_te_new
                ]
            ];
            $log_order_sup = [
                'log_name'    => 'order_supplier',
                'description' => 'update',
                'log_id'      => $orderSupplierFirst->id,
                'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
            ];

            if ($data_log_order['old']['order_cost_real'] != $data_log_order['new']['order_cost_real']) {
                $logEditDB = $logEditModel->create($log_order_sup);
            }
        }

        // Lưu vào đơn
        $orderDB->order_cost_real = $tong_tien_dat_hang_thuc_te_new * $orderDB->exchange_rate;
        // $orderDB->save();
        $orderDB = Order::withoutEvents(function () use ($orderDB) {
            $orderDB->save();
            return $orderDB;
        });

        // Lưu vào đơn phụ
        if ($orderSupplierFirst) {
            $orderSupplierFirst->order_cost_real = $tong_tien_dat_hang_thuc_te_new;
            $orderSupplierFirst->save();
        }
    }

    public function resetNoti2024(Request $request)
    {
        $userModel = \App::make('App\Models\User');
        $customerModel = \App::make('App\Models\Customer');

        $userDBs = $userModel->where('total_chat_unread', '>', 0)->get();
        if (count($userDBs)) {
            foreach ($userDBs as $key => $userDB) {
                $userDB->total_chat_unread = 0;
                $userDB->save();
            }
        }
        $customerDBs = $customerModel->where('total_chat_unread', '>', 0)->get();
        if (count($customerDBs)) {
            foreach ($customerDBs as $key => $customerDB) {
                $customerDB->total_chat_unread = 0;
                $customerDB->save();
            }
        }
    }

    public function fix471PackForVuong271224(Request $request)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $newarraynama = '895493,895494,895495,895496,895497,895498,895499,895500,895501,895502,895503,895504,895505,895506,895507,895508,895509,895510,895511,895512,895513,895514,895515,895516,895517,895518,895519,895520,895521,895522,895523,895524,895525,895526,895527,895528,895529,895530,895531,895532,895533,895534,895535,895536,895537,895538,895539,895540,895541,895542,895543,895544,895545,895546,895547,895548,895549,895550,895551,895552,895553,895554,895555,895556,895557,895558,895559,895560,895561,895562,895564,895565,895566,895567,895568,895569,895570,895571,895572,895573,895574,895575,895576,895577,895578,895579,895580,895581,895582,895583,895584,895585,895586,895587,895588,895589,895590,895591,895592,895593,895594,895595,895596,895597,895598,895599,895600,895601,895602,895603,895604,895605,895606,895607,895608,895609,895610,895611,895612,895613,895614,895615,895616,895617,895618,895619,895620,895621,895622,895623,895624,895625,895626,895627,895628,895629,895630,895631,895632,895633,895634,895635,895636,895637,895638,895639,895640,895641,895642,895643,895644,895645,895646,895647,895648,895649,895650,895651,895652,895653,895654,895655,895656,895657,895658,895659,895660,895661,895662,895663,895664,895665,895666,895667,895668,895669,895670,895671,895672,895673,895674,895675,895676,895677,895678,895679,895680,895681,895682,895683,895684,895685,895686,895687,895688,895689,895690,895691,895692,895693,895694,895695,895696,895697,895698,895699,895700,895701,895702,895703,895704,895705,895706,895707,895708,895709,895710,895711,895712,895713,895714,895715,895716,895717,895718,895719,895720,895721,895722,895723,895724,895725,895726,895727,895728,895729,895730,895731,895732,895733,895734,895735,895736,895737,895738,895739,895740,895741,895742,895743,895744,895745,895746,895747,895748,895749,895750,895751,895752,895753,895754,895755,895756,895757,895758,895759,895760,895761,895762,895763,895764,895765,895766,895767,895768,895769,895770,895771,895772,895773,895774,895775,895776,895777,895778,895779,895780,895781,895782,895783,895784,895785,895786,895787,895788,895789,895790,895791,895792,895793,895794,895795,895796,895797,895798,895799,895800,895801,895802,895803,895804,895805,895806,895807,895808,895809,895810,895811,895812,895813,895814,895815,895816,895817,895818,895819,895820,895821,895822,895823,895824,895825,895826,895827,895828,895829,895830,895831,895832,895833,895834,895835,895836,895837,895838,895839,895840,895841,895842,895843,895844,895845,895846,895847,895848,895849,895850,895851,895852,895853,895854,895855,895856,895857,895858,895859,895860,895861,895862,895863,895864,895865,895866,895867,895868,895869,895871,895872,895873,895874,895875,895876,895877,895878,895879,895880,895881,895882,895883,895884,895885,895886,895887,895888,895889,895890,895891,895892,895893,895894,895895,895896,895897,895898,895899,895900,895901,895902,895903,895904,895905,895906,895907,895908,895909,895910,895911,895912,895913,895914,895915,895916,895917,895918,895919,895920,895921,895922,895923,895924,895925,895926,895927,895928,895929,895930,895931,895932,895933,895934,895935,895936,895937,895938,895939,895940,895941,895942,895943,895944,895945,895946,895947,895948,895949,895950,895951,895952,895953,895954,895955,895956,895957,895958,895959,895960,895961,895962,895963,895964,895965';
        $arr_code = explode(",",$newarraynama);

        $orderDBs = $orderPackageModel->whereNull('staff_group_id')
                ->whereIn('id', $arr_code)
                ->where('created_at', '>', '2024-12-27 09:00:00')
                ->where('created_at', '<', '2024-12-27 09:30:00')
                ->get();

        if (count($orderDBs)) {
            $count = 0;
            foreach ($orderDBs as $key => $packDB) {
                $customerDB = $packDB->customer;
                if ($customerDB) {
                    $staff_group_id = $customerDB->staff_group_id;

                    if ($staff_group_id) {
                        $count += 1;
                        $packDB->staff_group_id = $staff_group_id;

                        $packDB = OrderPackage::withoutEvents(function () use ($packDB) {
                            $packDB->save();
                            return $packDB;
                        });
                    }
                }
            }
            dd($count);
        }
    }

    public function fix471PackForVuong271224V2(Request $request)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $newarraynama = '895493,895494,895495,895496,895497,895498,895499,895500,895501,895502,895503,895504,895505,895506,895507,895508,895509,895510,895511,895512,895513,895514,895515,895516,895517,895518,895519,895520,895521,895522,895523,895524,895525,895526,895527,895528,895529,895530,895531,895532,895533,895534,895535,895536,895537,895538,895539,895540,895541,895542,895543,895544,895545,895546,895547,895548,895549,895550,895551,895552,895553,895554,895555,895556,895557,895558,895559,895560,895561,895562,895564,895565,895566,895567,895568,895569,895570,895571,895572,895573,895574,895575,895576,895577,895578,895579,895580,895581,895582,895583,895584,895585,895586,895587,895588,895589,895590,895591,895592,895593,895594,895595,895596,895597,895598,895599,895600,895601,895602,895603,895604,895605,895606,895607,895608,895609,895610,895611,895612,895613,895614,895615,895616,895617,895618,895619,895620,895621,895622,895623,895624,895625,895626,895627,895628,895629,895630,895631,895632,895633,895634,895635,895636,895637,895638,895639,895640,895641,895642,895643,895644,895645,895646,895647,895648,895649,895650,895651,895652,895653,895654,895655,895656,895657,895658,895659,895660,895661,895662,895663,895664,895665,895666,895667,895668,895669,895670,895671,895672,895673,895674,895675,895676,895677,895678,895679,895680,895681,895682,895683,895684,895685,895686,895687,895688,895689,895690,895691,895692,895693,895694,895695,895696,895697,895698,895699,895700,895701,895702,895703,895704,895705,895706,895707,895708,895709,895710,895711,895712,895713,895714,895715,895716,895717,895718,895719,895720,895721,895722,895723,895724,895725,895726,895727,895728,895729,895730,895731,895732,895733,895734,895735,895736,895737,895738,895739,895740,895741,895742,895743,895744,895745,895746,895747,895748,895749,895750,895751,895752,895753,895754,895755,895756,895757,895758,895759,895760,895761,895762,895763,895764,895765,895766,895767,895768,895769,895770,895771,895772,895773,895774,895775,895776,895777,895778,895779,895780,895781,895782,895783,895784,895785,895786,895787,895788,895789,895790,895791,895792,895793,895794,895795,895796,895797,895798,895799,895800,895801,895802,895803,895804,895805,895806,895807,895808,895809,895810,895811,895812,895813,895814,895815,895816,895817,895818,895819,895820,895821,895822,895823,895824,895825,895826,895827,895828,895829,895830,895831,895832,895833,895834,895835,895836,895837,895838,895839,895840,895841,895842,895843,895844,895845,895846,895847,895848,895849,895850,895851,895852,895853,895854,895855,895856,895857,895858,895859,895860,895861,895862,895863,895864,895865,895866,895867,895868,895869,895871,895872,895873,895874,895875,895876,895877,895878,895879,895880,895881,895882,895883,895884,895885,895886,895887,895888,895889,895890,895891,895892,895893,895894,895895,895896,895897,895898,895899,895900,895901,895902,895903,895904,895905,895906,895907,895908,895909,895910,895911,895912,895913,895914,895915,895916,895917,895918,895919,895920,895921,895922,895923,895924,895925,895926,895927,895928,895929,895930,895931,895932,895933,895934,895935,895936,895937,895938,895939,895940,895941,895942,895943,895944,895945,895946,895947,895948,895949,895950,895951,895952,895953,895954,895955,895956,895957,895958,895959,895960,895961,895962,895963,895964,895965';
        $arr_code = explode(",",$newarraynama);

        $orderDBs = $orderPackageModel
                ->whereIn('id', $arr_code)
                ->where('status', PackageConstant::STATUS_PENDING)
                ->where('created_at', '>', '2024-12-27 09:00:00')
                ->where('created_at', '<', '2024-12-27 09:30:00')
                ->get();

        if (count($orderDBs)) {
            $count = 0;
            foreach ($orderDBs as $key => $packDB) {
                $count += 1;
                $packDB->status = PackageConstant::STATUS_IN_CHINA;
                $packDB->save();
            }
            dd($count);
        }
    }

    public function clearTokenUserNotActive(Request $request)
    {
        $userModel = \App::make('App\Models\User');
        $userDBs = $userModel->where('status', 0)->get();

        if (count($userDBs) > 0) {
            $user_ids = [];
            foreach ($userDBs as $key => $userDB) {
                array_push($user_ids, (int)$userDB->id);
            }
            $loginTokens = DB::table('personal_access_tokens')
                                    ->where('tokenable_type', 'App\Models\User')
                                    ->whereIn('tokenable_id', $user_ids)
                                    ->get();

            if (count($loginTokens) > 0) {
                $result = DB::table('personal_access_tokens')
                                        ->where('tokenable_type', 'App\Models\User')
                                        ->whereIn('tokenable_id', $user_ids)
                                        ->delete();
                dd(count($loginTokens));
            }
        }
    }

    public function changeCustomerFrom2User(Request $request)
    {
        $customerModel = \App::make('App\Models\Customer');
        $customerDBs = $customerModel->where('staff_group_id', 322)
                                    ->limit(3000)
                                    ->offset(0)
                                    ->get();
        if (count($customerDBs)) {
            foreach ($customerDBs as $key => $customerDB) {
                $customerDB->staff_group_id = 507;
                $customerDB = Customer::withoutEvents(function () use ($customerDB) {
                    $customerDB->save();
                    return $customerDB;
                });
            }

            dd(count($customerDBs));
        }
    }

    public function fixFundDiff2tr130125(Request $request)
    {
        try {
            \DB::beginTransaction();

            $fundModel = \App::make('App\Models\Fund');
            $fundTransactionModel = \App::make('App\Models\FundTransaction');

            $fundTech = $fundModel->where('bank_id', 33)
                                    ->where('name', 'Techcombank ')
                                    ->first();

            if (is_null($fundTech)) {
                dd("Lỗi quỹ");
            }

            $fundTransactionDBs = $fundTransactionModel->where('fund_id', (int) $fundTech->id)
                                                        // ->where('created_at', '>=', '2025-01-09 13:49')
                                                        ->where('created_at', '>=', '2025-01-13 11:27')
                                                        ->get();

            foreach ($fundTransactionDBs as $key => $fundTransactionDB) {
                $fundTransactionDB->balance += 2000000;
                // $fundTransactionDB->save();
            }

            $fundTech->current_balance += 2000000;
            // $fundTech->save();

            \DB::commit();
            dd("done");
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function changePassCustomer2025(Request $request)
    {
        $validationRules = [
            'customer_id' => 'required|exists:customers,id',
            'password'    => 'required|min:6',
            'token'       => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn188824he91N28192') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            $customerModel = \App::make('App\Models\Customer');
            $customerDB = $customerModel->where('id', $params['customer_id'])->first();

            $customerDB->password = \Hash::make($params['password']);
            $customerDB->save();

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateCustomerLevel0To1(Request $request)
    {
        $customerModel = \App::make('App\Models\Customer');
        $customerDBs = $customerModel->where('level', 0)->update([
            'level' => 1
        ]);
        dd($customerDBs);
    }

    public function fixFundDiff13k080425(Request $request)
    {
        try {
            \DB::beginTransaction();

            $fundModel = \App::make('App\Models\Fund');
            $fundTransactionModel = \App::make('App\Models\FundTransaction');

            $fundBIDV = $fundModel->where('bank_id', 9)
                                    ->where('name', 'BIDV ')
                                    ->first();

            if (is_null($fundBIDV)) {
                dd("Lỗi quỹ");
            }


            $fundTransactionDBs = $fundTransactionModel->where('fund_id', (int) $fundBIDV->id)
                                                        // ->where('created_at', '>=', '2025-01-09 13:49')
                                                        ->where('created_at', '>=', '2025-04-04 14:32')
                                                        ->get();

            foreach ($fundTransactionDBs as $key => $fundTransactionDB) {
                $fundTransactionDB->balance += 13000;
                $fundTransactionDB->save();
            }

            $fundBIDV->current_balance += 13000;
            $fundBIDV->save();
            dd('222');
            \DB::commit();
            dd("done");
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function fixDuplicateCodeCustomer220425(Request $request)
    {
        try {
            \DB::beginTransaction();

            $duplicateCodes = DB::table('customers')
                ->where('deleted_at', null)
                ->select('code', 'id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(created_at) as first_created'))
                ->groupBy('code')
                ->havingRaw('COUNT(*) >= 2')
                ->orderBy('first_created')
                ->get();

            $arr_code = $duplicateCodes->toArray();

            $customer_ids = [];
            $customerModel = \App::make('App\Models\Customer');
            foreach ($arr_code as $key_1 => $cus_1) {
                $arr_cus = $customerModel->where('code', $cus_1->code)->get();

                $arr_temp = [];
                foreach ($arr_cus as $key_2 => $cus_2) {
                    $temp = [
                        'id'   => (int) $cus_2->id,
                        'code' => $cus_2->code,
                    ];
                    array_push($arr_temp, $temp);
                }
                array_push($customer_ids, $arr_temp);
            }

            \Log::info('fixDuplicateCodeCustomer220425 : '. json_encode($customer_ids, JSON_UNESCAPED_UNICODE));


            $arr_reCode = [];
            $orderModel = \App::make('App\Models\Order');
            foreach ($arr_code as $key_1 => $cus_1) {
                $arr_cus = $customerModel->where('code', $cus_1->code)->get();
                $check = 0;
                foreach ($arr_cus as $key_2 => $cus_2) {
                    // Kiểm tra xem đứa nào chưa tạo đơn thì đổi mã code cho nó
                    $orderDBs = $orderModel->where('customer_id', (int) $cus_2->id)->first();
                    if (is_null($orderDBs)) {
                        $text = "Đổi cho: " . $cus_2->code . ':' . $cus_2->id;
                        array_push($arr_reCode, $text);
                        $check = 1;
                        $cus_2->code = RandomHelper::customerCode();
                        $cus_2->save();
                    }
                    if ($check == 1) {
                        break;
                    }
                }
            }

            \Log::info('arr_reCode : '. json_encode($arr_reCode, JSON_UNESCAPED_UNICODE));

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function fixHackCustomer300425(Request $request)
    {
        try {
            \DB::beginTransaction();
            $customerModel = \App::make('App\Models\Customer');
            $customerDBs = $customerModel->where('creator_id', 1)
                                        ->where('id', '>', 226749)
                                        ->where('created_at', '>', '2025-04-29 17:30:00')
                                        ->where('created_at', '<', '2025-04-30 09:30:00')
                                        ->limit(4000)
                                        ->offset(0)
                                        ->get();

            if (count($customerDBs) > 0) {
                dump(count($customerDBs));
                \Log::info('fixHackCustomer300425:' . count($customerDBs));
                $arr_phone = [];
                foreach ($customerDBs as $key => $customerDB) {
                    // $result = Customer::withoutEvents(function () use ($customerDB) {
                    //     $customerDB->forceDelete();
                    //     return $customerDB;
                    // });
                    array_push($arr_phone, $customerDB->phone_number);
                }

                // Bắn sang CB check
                $guzzle = new Guzzle;
                // $url = 'http://172.19.0.18/check-hack-02052025-kuh18mk';
                $url = 'https://api-v2.chatbiz.io/check-hack-02052025-kuh18mk';

                $options = [
                    'json' => [
                        "list_phone" => $arr_phone,
                    ]
                ];

                $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
                $result = json_decode($result, true);

                if (isset($result)) {
                    if ($result['status_code'] ==  200) {
                        dump(count($result['data']));
                        \Log::info('fixHackCustomer300425 Check:' . count($result['data']));
                        $data_check = $result['data'];
                        foreach ($customerDBs as $key => $customerDB) {
                            if (in_array($customerDB->phone_number, $data_check)) {
                                $result = Customer::withoutEvents(function () use ($customerDB) {
                                    $customerDB->forceDelete();
                                    return $customerDB;
                                });
                            }
                        }
                    }
                }

            } else {
                dd('Xong');
            }
            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function testReportCustomer150525(Request $request)
    {
        $reportCustomerModel = \App::make('App\Models\ReportCustomer');
        $reportCustomeDB = $reportCustomerModel->where('customer_id', 1)->first();

        try {
            \DB::beginTransaction();
            $reportCustomeDB->balance_amount = 5;
            $reportCustomeDB->save();
            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function changeServiceOrderAndPack(Request $request)
    {
        $validationRules = [
            'order_id'              => 'required|exists:orders,id',
            'hangve_is_inspection'  => 'required|in:0,1,99',
            'hangve_is_woodworking' => 'required|in:0,1,99',
            'hangve_is_shock_proof' => 'required|in:0,1,99',
            'hangve_is_advanced_inspection'  => 'required|in:0,1,99',
            'token'                 => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn188824he91N28192M1') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        if (
            $params['hangve_is_inspection'] == 99 &&
            $params['hangve_is_woodworking'] == 99 &&
            $params['hangve_is_shock_proof'] == 99 &&
            $params['hangve_is_advanced_inspection'] == 99
        ) {
            $result = [
                'unit' => 'Cần chọn ít nhất 1 key để cập nhật'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $params['order_id'])->first();

        if ($orderDB->status == OrderConstant::KEY_STATUS_DONE) {
            $result = [
                'unit' => 'Đơn đã hoàn thành, không thể xử lý'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Chế cháo lại cho HV
        if ($params['hangve_is_advanced_inspection'] == 0) {
            $params['hangve_is_advanced_inspection'] = 1;
        } else if ($params['hangve_is_advanced_inspection'] == 1) {
            $params['hangve_is_advanced_inspection'] = 2;
        }

        // Lấy ra data cần update
        $param_update = [];
        if ($orderDB->is_inspection != $params['hangve_is_inspection'] && $params['hangve_is_inspection'] != 99) {
            $param_update['is_inspection'] = $params['hangve_is_inspection'];
        }
        if ($orderDB->is_woodworking != $params['hangve_is_woodworking'] && $params['hangve_is_woodworking'] != 99) {
            $param_update['is_woodworking'] = $params['hangve_is_woodworking'];
        }
        if ($orderDB->is_shock_proof != $params['hangve_is_shock_proof'] && $params['hangve_is_shock_proof'] != 99) {
            $param_update['is_shock_proof'] = $params['hangve_is_shock_proof'];
        }
        if ($orderDB->is_advanced_inspection != $params['hangve_is_advanced_inspection'] && $params['hangve_is_advanced_inspection'] != 99) {
            $param_update['is_advanced_inspection'] = $params['hangve_is_advanced_inspection'];
        }

        if (count($param_update) == 0) {
            $result = [
                'unit' => 'Không có gì phải update'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $orderSup = $orderDB->orderSuppliers;
        if (count($orderSup) > 1) {
            $result = [
                'unit' => 'Đơn có 2 NCC'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            // Lưu Log
            $logEditModel = \App::make('App\Models\LogEdit');
            $log_order = [
                'log_name'    => 'orders',
                'description' => 'change-service',
                'log_id'      => $params['order_id'],
                'data'        => json_encode($params, JSON_UNESCAPED_UNICODE)
            ];
            $logEditDB = $logEditModel->create($log_order);

            // Thêm cho bên order và order_sup
            $param_update_order         = $param_update;
            $param_update_order_and_sup = $param_update;

            if (isset($param_update['is_inspection']) && $param_update['is_inspection'] == 0) {
                $param_update_order['inspection_cost']         = 0;
                $param_update_order_and_sup['inspection_cost'] = 0;

                // Nếu có số tiền đặt cọc thì trừ đi
                if ($orderDB->deposit_cost > 0) {
                    $param_update_order['deposit_cost'] = $orderDB->deposit_cost - $orderDB->inspection_cost;
                }
            }

            // Order
            $orderDB->fill($param_update_order)->save();

            // OrderSup
            $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
            $orderSupplierFirst->fill($param_update_order_and_sup)->save();

            // OrderPack
            $packModel = \App::make('App\Models\OrderPackage');
            $packDBs = $packModel->where('order_id', $params['order_id'])
                               ->where('order_type', 'App\Models\Order')
                               ->get();

            if (count($packDBs) > 0) {
                $arr_ids = [];
                foreach ($packDBs as $key => $packDB) {
                    $packDB->fill($param_update)->save();
                    array_push($arr_ids, (int) $packDB->id);
                }

                // Chế lại cho bên kho
                if (isset($param_update['is_advanced_inspection'])) {
                    if ($param_update['is_advanced_inspection'] == 1) {
                        $param_update['is_advanced_inspection'] = 0;
                    }
                    if ($param_update['is_advanced_inspection'] == 2) {
                        $param_update['is_advanced_inspection'] = 1;
                    }
                }

                // Đẩy sang bên One để cập nhật
                $guzzle = new Guzzle;
                $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
                $options = [
                    'json' => [
                        'event_name' => 'update_pack_service',
                        'data'       => [
                            'arr_ids'        => $arr_ids,
                            'service_update' => $param_update
                        ]
                    ],
                    'verify' => false,
                    'http_errors' => false,
                ];

                $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
                $result = json_decode($result, true);
            }

            \DB::commit();
            return resSuccessWithinData([
                "Thành công"
            ]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function changePackConsigment(Request $request)
    {
        $validationRules = [
            'pack_mvd'              => 'required|exists:order_package,bill_code',
            'hangve_is_insurance'  => 'required|in:0,1,99',
            'hangve_is_woodworking' => 'required|in:0,1,99',
            'hangve_is_shock_proof' => 'required|in:0,1,99',
            'token'                 => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn188824he91N28192M2') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        if (
            $params['hangve_is_insurance'] == 99 &&
            $params['hangve_is_woodworking'] == 99 &&
            $params['hangve_is_shock_proof'] == 99

        ) {
            $result = [
                'unit' => 'Cần chọn ít nhất 1 key để cập nhật'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $arr_status = [
            PackageConstant::STATUS_RECEIVED_GOODS,
            PackageConstant::STATUS_CANCEL,
        ];
        $packModel = \App::make('App\Models\OrderPackage');
        $packDB = $packModel->where('bill_code', $params['pack_mvd'])
                                    ->where('order_type', 'App\Models\Consignment')
                                    ->whereNotIn('status', $arr_status)
                                    ->first();

        if (is_null($packDB)) {
            $result = [
                'unit' => 'Kiện kí gửi không tồn tại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Lấy ra data cần update
        $param_update = [];
        if ($packDB->is_insurance != $params['hangve_is_insurance'] && $params['hangve_is_insurance'] != 99) {
            $param_update['is_insurance'] = $params['hangve_is_insurance'];
        }
        if ($packDB->is_woodworking != $params['hangve_is_woodworking'] && $params['hangve_is_woodworking'] != 99) {
            $param_update['is_woodworking'] = $params['hangve_is_woodworking'];
        }
        if ($packDB->is_shock_proof != $params['hangve_is_shock_proof'] && $params['hangve_is_shock_proof'] != 99) {
            $param_update['is_shock_proof'] = $params['hangve_is_shock_proof'];
        }

        if (count($param_update) == 0) {
            $result = [
                'unit' => 'Không có gì phải update'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }


        try {
            \DB::beginTransaction();
            // Lưu Log
            $logEditModel = \App::make('App\Models\LogEdit');
            $log_order = [
                'log_name'    => 'order_packages',
                'description' => 'change-service',
                'log_id'      => $packDB->id,
                'data'        => json_encode($params, JSON_UNESCAPED_UNICODE)
            ];
            $logEditDB = $logEditModel->create($log_order);

            // OrderPack
            $packDBs = [$packDB];

            if (count($packDBs) > 0) {
                $arr_ids = [];
                foreach ($packDBs as $key => $packDB) {
                    $packDB->fill($param_update)->save();
                    array_push($arr_ids, (int) $packDB->id);
                }

                // Đẩy sang bên One để cập nhật
                $guzzle = new Guzzle;
                $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
                $options = [
                    'json' => [
                        'event_name' => 'update_pack_service',
                        'data'       => [
                            'arr_ids'        => $arr_ids,
                            'service_update' => $param_update
                        ]
                    ],
                    'verify' => false,
                    'http_errors' => false,
                ];

                $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
                $result = json_decode($result, true);
            }

            \DB::commit();
            return resSuccessWithinData([
                "Thành công"
            ]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function rePushPackToQuote(Request $request)
    {
        $validationRules = [
            'arr_ids' => 'required',
            'time'     => 'required',
            'token'    => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn188824he91N28192M1') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check trùng thời gian hiện tại
        $minuteCurrent = (int)date('i', time());

        if ($minuteCurrent != $params['time']) {
            $result = [
                'unit' => 'Lỗi time'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $arr_ids = explode(",", $params['arr_ids']);

        $packModel = \App::make('App\Models\OrderPackage');
        foreach ($arr_ids as $key_1 => $order_id) {
            $packDBs = $packModel->where('order_id', $order_id)
                                ->where('order_type', 'App\Models\Order')
                                ->get();

            foreach ($packDBs as $key_2 => $packDB) {
                if ($packDB->bill_code) {
                    $job = new \App\Jobs\Quote\SyncAddPackOrder($packDB);
                    dispatch($job)->delay(now()->addSeconds(5))->onQueue('cron-data-2023');
                }
            }
        }
    }

    public function reSyncCallOutChatbiz(Request $request)
    {
        $validationRules = [
            'time'     => 'required',
            'token'    => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn188824he91N28192M1') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check trùng thời gian hiện tại
        $minuteCurrent = (int)date('i', time());

        if ($minuteCurrent != $params['time']) {
            $result = [
                'unit' => 'Lỗi time'
            ];
            // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Đồng bộ

        $dateCurrent = date('Y-m-d', time());
        $dateCurrent = '2025-10-31';

        $notiService = \App::make('App\Services\NotificationService');
        $notiService->reSyncCallOutChatbiz($dateCurrent);

        return resSuccessWithinData([
            "Thành công"
        ]);
    }

    public function resetEmailAgent151225(Request $request)
    {
        $validationRules = [
            'time'     => 'required',
            'token'    => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $token = $params['token'];

        if ($token != 'tCQ7DEwncn188824he91N28192M1') {
            $result = [
                'unit' => 'Token không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check trùng thời gian hiện tại
        $minuteCurrent = (int)date('i', time());

        if ($minuteCurrent != $params['time']) {
            $result = [
                'unit' => 'Lỗi time'
            ];
            // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $arr_change = [
            'mynguyen@thuongdo.com'    => 'nguyenhuyenmy2508@gmail.com',
            'hongnt@thuongdo.com'      => 'nguyenhong.neu123@gmail.com',
            'trangptc@thuongdo.com'    => 'trangptc1301@gmail.com',
            'chiap@thuongdo.com'       => 'anchi2521999@gmail.com',
            'duongptt@thuongdo.com'    => 'duong.phamtinx@gmail.com',
            'duyenhtm@thuongdo.com'    => 'hoangduyen.030801@gmail.com',
            'huuongphung@thuongdo.com' => 'thuyhuong8989@gmail.com',
        ];

        foreach ($arr_change as $oldEmail => $newEmail) {
            // Check trùng
            $userNewDB = User::where('email', $newEmail)->first();
            if ($userNewDB) {
                \Log::info('Trùng tài khoản: ' . $newEmail);
                continue;
            }

            $userDB = User::where('email', $oldEmail)->first();
            if (!is_null($userDB)) {
                \Log::info('Cập nhật lại email tài khoản ' . $oldEmail . ' thành ' . $newEmail);
                // Đá nó ra
                $userDB->tokens()->delete();

                // Lưu email mới
                $userDB->email = $newEmail;
                $userDB->save();
            }
        }
    }

    public function checkDeliveryErr080126(Request $request)
    {
        $tranModel = \App::make('App\Models\Transaction');

        $tranDBs = $tranModel->where('sourceable_id', 144480)
                            ->where('customer_id', 276144)
                            ->get();
        $arr_dept = [];
        $arr_ship = [];
        foreach ($tranDBs as $key => $tranDB) {
            $content = $tranDB->content;
            if (str_contains($content, "Thanh toán tiền hàng còn lại")) {
                array_push($arr_dept, $content);
            }
            if (str_contains($content, "phí vận chuyển có mã vận đơn là")) {
                array_push($arr_ship, $content);
            }
        }
        $grouped = [];
        foreach ($arr_ship as $message) {
            $grouped[$message][] = $message;
        }

        $shipSum = 0;
        foreach ($grouped as $key => $item) {
            \Log::info("Count: " . count($item));
            $int = $this->getAmountAfterThanhToan($key);
            $shipSum += $int;
        }

        dd($shipSum);
    }

    public function getAmountAfterThanhToan(string $text): ?int
    {
        if (preg_match('/Thanh toán\s+([\d,.]+)đ/u', $text, $m)) {
            return (int) str_replace([',', '.'], '', $m[1]);
        }
        return null;
    }

    public function updateBlanceTranErr230126(Request $request)
    {
        $customerId = 279626;
        $transactionCheck = Transaction::query()->where('id', 745127)->first();
        $reportCustomerDB = ReportCustomer::query()->where('customer_id', $customerId)->first();

        if ($transactionCheck && $reportCustomerDB) {
            if ($transactionCheck->balance == 12903245) {
                $transactionDBs = Transaction::query()->where('id', '>=', 745127)
                                                    ->where('customer_id', $customerId)
                                                    ->orderBy('id', 'ASC')
                                                    ->get();
                $id_updates = [];
                foreach ($transactionDBs as $key => $transactionDB) {
                    array_push($id_updates, $transactionDB->id);
                    $balanceNew = $transactionDB->balance - 8554390;
                    $transactionDB->balance = $balanceNew;
                    $transactionDB->save();
                }

                $reportCustomerDB->balance_amount = $balanceNew;
                $reportCustomerDB->save();

                \Log::info('updateBlanceTranErr230126: ' . json_encode($id_updates));
            }
        }
    }

    public function updatePackCnLag280126(Request $request)
    {
        $validationRules = [
            'origin_code'   => 'required',
            'time_enter' => 'required|date_format:Y-m-d H:i',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $time_enter  = $validated['time_enter'];
        $origin_code = $validated['origin_code'];

        $intTime = strtotime($time_enter);
        $now     = time();
        $diff    = $now - $intTime;
        if ($diff > 300 || $diff < 0) {
            dd('Thời gian lỗi');
        }

        $logDB = LogWebhook::query()->where('body', 'like', '%' . $origin_code . '%')
                                ->where('description', 'CNController@cnSyncData')
                                ->orderBy('id', 'DESC')
                                ->first();

        if (!is_null($logDB)) {
            $bodyArr = json_decode($logDB->body, true);
            $bodyArr['resent'] = true;
            $request = new Request($bodyArr);

            $CnControler    = \App::make('App\Http\Controllers\CNController');
            $CnControler->cnSyncData($request);
        }
    }
}

