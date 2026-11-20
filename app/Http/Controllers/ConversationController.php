<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\ConversationService;
use App\Constants\PackageConstant;
use App\Constants\ConversationConstant;
use App\Constants\DepartmentConstant;
use App\Models\Order;

class ConversationController extends Controller
{
    public function __construct(ConversationService $service)
    {
        $this->_service = $service;
    }

    public function storeV2(Request $request)
    {
        $validationRules = [
            'type'             => 'required|in:1,2,3,4',
            'customer_id'      => 'required|exists:customers,id',
            'order_id'         => 'nullable|exists:orders,id',
            'order_package_id' => 'nullable|exists:order_package,id',
            'complain_id'      => 'nullable|exists:complains,id'
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $customerModel = \App::make('App\Models\Customer');

        $userOnline = $customerModel->where('id', $params['customer_id'])->first();
        $result_check = $this->handleBeforeStoreV2($params, $userOnline);

        if(!is_array($result_check)) {
            $result = [
                'error' => $result_check
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check nếu có hội thoại mà hội thoại này đứa khác cầm thì cũng báo lỗi
        $result_check_v2 = $this->checkOutConversation($userOnline, $result_check);
        if(!is_null($result_check_v2)) {
            $result = [
                'error' => $result_check_v2
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }


        try {
            \DB::beginTransaction();
            $conversationDB = $this->handlerStoreV2($userOnline, $result_check);

            $result_result = new \App\Http\Resources\Conversation\ConversationResource($conversationDB);
            \DB::commit();
            return resSuccessWithinData($result_result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeStoreV2($params, $userOnline)
    {
        // không theo gì
        if ($params['type'] == ConversationConstant::TYPE_FREE) {
            $staffGroupDB = $userOnline->staffGroup;
            if (is_null($staffGroupDB)) {
                return "Không tồn tại nhân viên quản lý";
            }
            return [
                'type'           => $params['type'],
                'type_id'        => 0,
                'type_code'      => null,
                'type_status'    => null,
                'order_id'       => null,
                'supplier_id'    => 0,
                'supplier_name'  => '',
                'supplier_image' => '',
                'user_id'        => (int) $staffGroupDB->id,
                'user_code'      => $staffGroupDB->code,
                'user_name'      => $staffGroupDB->name,
                'user_email'     => $staffGroupDB->email,
                'user_image'     => $staffGroupDB->avatar_origin,
            ];
        }
        if (
            $params['type'] != ConversationConstant::TYPE_FREE &&
            isset($params['order_id'])         == false &&
            isset($params['order_package_id']) == false &&
            isset($params['complain_id'])      == false
        ) {
            return "Tham số không đủ";
        }

        // Nếu là theo đơn
        if ($params['type'] == ConversationConstant::TYPE_ORDER) {
            $orderModel = \App::make('App\Models\Order');
            $orderDB = $orderModel->where('id', $params['order_id'])
                                    ->where('customer_id', $userOnline->id)
                                    ->first();
            if (is_null($orderDB)) {
                return "Đơn hàng không hợp lệ";
            }

            $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
            $supplierDB         = $orderSupplierFirst->supplier;
            $staffGroupDB       = $orderDB->staffGroup;

            if (is_null($supplierDB)) {
                return "Nhà cung cấp không hợp lệ";
            }
            if (is_null($staffGroupDB)) {
                return "Không tồn tại nhân viên quản lý";
            }

            return [
                'type'           => $params['type'],
                'type_id'        => (int) $orderDB->id,
                'type_code'      => $orderDB->code,
                'type_status'    => $orderDB->status,

                'order_id'      => (int) $orderDB->id,

                'supplier_id'    => $orderSupplierFirst->supplier_id,
                'supplier_name'  => $supplierDB->name,
                'supplier_image' => $supplierDB->logo,

                'user_id'        => (int) $staffGroupDB->id,
                'user_code'      => $staffGroupDB->code,
                'user_name'      => $staffGroupDB->name,
                'user_email'     => $staffGroupDB->email,
                'user_image'     => $staffGroupDB->avatar_origin,
            ];
        }

        // Nếu tạo theo kiện
        if ($params['type'] == ConversationConstant::TYPE_PACKAGE) {
            $packModel = \App::make('App\Models\OrderPackage');
            $packkDB = $packModel->where('id', $params['order_package_id'])
                                    ->where('customer_id', $userOnline->id)
                                    ->first();
            if (is_null($packkDB)) {
                return "Kiện hàng không hợp lệ";
            }

            if ($packkDB->bill_code == null) {
                return "Kiện hàng chưa có mã vận đơn";
            }

            $staffGroupDB = $packkDB->staffGroup;

            if (is_null($staffGroupDB)) {
                return "Không tồn tại nhân viên quản lý";
            }

            $data_result = [
                'type'           => $params['type'],
                'type_id'        => (int) $packkDB->id,
                'type_code'      => $packkDB->bill_code,
                'type_status'    => $packkDB->status,

                'supplier_id'    => 0,
                'supplier_name'  => '',
                'supplier_image' => '',

                'user_id'        => (int) $staffGroupDB->id,
                'user_code'      => $staffGroupDB->code,
                'user_name'      => $staffGroupDB->name,
                'user_email'     => $staffGroupDB->email,
                'user_image'     => $staffGroupDB->avatar_origin,
            ];

            // Nếu là kiện order
            if ($packkDB->order_type == 'App\Models\Order') {
                $orderModel = \App::make('App\Models\Order');
                $orderDB = $orderModel->where('id', $packkDB->order_id)
                                                    ->where('customer_id', $userOnline->id)
                                                    ->first();
                $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
                $supplierDB         = $orderSupplierFirst->supplier;

                $data_result['supplier_id']    = (int) $supplierDB->id;
                $data_result['supplier_name']  = $supplierDB->name;
                $data_result['supplier_image'] = $supplierDB->logo;

                $data_result['order_id'] = (int) $orderDB->id;
            }
            return $data_result;
        }

        // Nếu tạo theo khiếu nại
        if ($params['type'] == ConversationConstant::TYPE_COMPLAIN) {
            $complainModel = \App::make('App\Models\Complain');
            $complainDB = $complainModel->where('id', $params['complain_id'])
                                    ->where('customer_id', $userOnline->id)
                                    ->first();
            if (is_null($complainDB)) {
                return "Khiếu nại không hợp lệ";
            }

            $orderModel = \App::make('App\Models\Order');
            $orderDB = $orderModel->where('id', $complainDB->order_id)
                                                ->where('customer_id', $userOnline->id)
                                                ->first();
            $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
            $supplierDB         = $orderSupplierFirst->supplier;
            $staffGroupDB       = $orderDB->staffGroup;

            if (is_null($supplierDB)) {
                return "Nhà cung cấp không hợp lệ";
            }
            if (is_null($staffGroupDB)) {
                return "Không tồn tại nhân viên quản lý";
            }


            return [
                'type'           => $params['type'],
                'type_id'        => (int) $complainDB->id,
                'type_code'      => $orderDB->code,
                'type_status'    => $complainDB->status,

                'order_id'      => (int) $orderDB->id,

                'supplier_id'    => $orderSupplierFirst->supplier_id,
                'supplier_name'  => $supplierDB->name,
                'supplier_image' => $supplierDB->logo,

                'user_id'        => (int) $staffGroupDB->id,
                'user_code'      => $staffGroupDB->code,
                'user_name'      => $staffGroupDB->name,
                'user_email'     => $staffGroupDB->email,
                'user_image'     => $staffGroupDB->avatar_origin,
            ];
        }
    }

    public function checkOutConversation($userOnline, $result_check)
    {
        $conversationModel = \App::make('App\Models\Conversation');
        /*$conversationDB = $conversationModel->where('customer_id', (int)$userOnline->id)
                                            ->where('type', $result_check['type'])
                                            ->where('type_id', $result_check['type_id'])
                                            ->where('supplier_id', $result_check['supplier_id'])
                                            ->first();*/
        $query = $conversationModel->where('customer_id', (int)$userOnline->id)
            ->where('type', $result_check['type']);
        //        TYPE_FREE không có type_id, supplier_id
        if ($result_check['type'] != ConversationConstant::TYPE_FREE) {
            $query->where('type_id', $result_check['type_id'])
                ->where('supplier_id', $result_check['supplier_id']);
        }

        $conversationDB = $query->first();
        if (!is_null($conversationDB)) {
            $userLogin = \Auth::user();
            if ($conversationDB->user_id != $userLogin->id) {
                return "Chat của bạn đã được chuyển cho nhân viên " . $conversationDB->user_name . ". Vui lòng liên hệ quản lý để biết thêm chi tiết! ";
            }
        }

        return null;
    }

    public function handlerStoreV2($userOnline, $result_check)
    {
        // Kiểm tra xem đã có hội thoại chưa
        $conversationModel = \App::make('App\Models\Conversation');
        /*$conversationDB = $conversationModel->where('customer_id', (int)$userOnline->id)
                                            ->where('type', $result_check['type'])
                                            ->where('type_id', $result_check['type_id'])
                                            ->where('supplier_id', $result_check['supplier_id'])
                                            ->first();*/
        $query = $conversationModel->where('customer_id', (int)$userOnline->id)
            ->where('type', $result_check['type']);

        if ($result_check['type'] != ConversationConstant::TYPE_FREE) {
            $query->where('type_id', $result_check['type_id'])
                ->where('supplier_id', $result_check['supplier_id']);
        }

        $conversationDB = $query->first();

        if (is_null($conversationDB)) {
            $data_more = [
                'organization_id'        => $userOnline->organization_id,
                'customer_id'            => $userOnline->id,
                'customer_code'          => $userOnline->code,
                'customer_name'          => $userOnline->name,
                'customer_phone'         => $userOnline->phone_number,
                'customer_image'         => $userOnline->avatar_origin,
                'unread_client'          => 0,
                'unread_admin'           => 0,
                'time_last_send'         => date('Y-m-d H:i:s', time()),
                'time_last_send_admin'   => date('Y-m-d H:i:s', time()),
                'conversation_config_id' => 2,
            ];

            $data_conversation = array_merge($result_check, $data_more);
            $conversationDB = $conversationModel->create($data_conversation);
            $conversationDB['new'] = 1;
        } else {
            $conversationDB->time_last_send = date('Y-m-d H:i:s', time());
            $conversationDB->time_last_send_admin = date('Y-m-d H:i:s', time());
            $conversationDB->save();

            $conversationDB['new'] = 0;
        }

        return $conversationDB;
    }

    public function checkConversationNull(Request $request)
    {
        $conversationModel = \App::make('App\Models\Conversation');
        $time = time() - 3 * 3600;
        $time_text = date('Y-m-d H:i:s', $time);
        $conversationDBs = $conversationModel->whereNull('content_last_send')
                                            ->where('created_at', '>', $time_text)
                                            ->get();

        if (count($conversationDBs) > 0) {
            // \Log::info('checkConversationNull: ' . count($conversationDBs));
            foreach ($conversationDBs as $key => $conversationDB) {
                $conversationDB->delete();
            }
        }
    }

    public function storeSearchNull(Request $request)
    {
        $validationRules = [
            'content' => 'required',
            'check'   => 'required|in:0,1'
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userOnline = \Auth::user();

        $orderModel = \App::make('App\Models\Order');
        $packModel  = \App::make('App\Models\OrderPackage');

        $orderOrPackDB = null;
        $data_check = [
            'type' => 1
        ];

        $orderOrPackDB = $orderModel->where('code', $params['content'])
                            ->where('staff_group_id', $userOnline->id)
                            ->first();

        if (is_null($orderOrPackDB)) {
            $orderOrPackDB = $packModel->where('bill_code', $params['content'])
                            ->where('staff_group_id', $userOnline->id)
                            ->first();
            if (!is_null($orderOrPackDB)) {
                $data_check['type'] = 2;
                $data_check['order_package_id'] = $orderOrPackDB->id;
                $data_check['customer_id'] = $orderOrPackDB->customer_id;
            }
        } else {
            $data_check['order_id'] = $orderOrPackDB->id;
            $data_check['customer_id'] = $orderOrPackDB->customer_id;
        }

        if ($params['check'] == 1) {
            $has_order_or_pack = true;
            if (is_null($orderOrPackDB)) {
                $has_order_or_pack = false;
            }
            return resSuccessWithinData([
                'has_order_or_pack' => $has_order_or_pack
            ]);
        }

        $customerModel = \App::make('App\Models\Customer');
        $userOnline = $customerModel->where('id', $data_check['customer_id'])->first();

        $result_check = $this->handleBeforeStoreV2($data_check, $userOnline);

        try {
            \DB::beginTransaction();
            $conversationDB = $this->handlerStoreV2($userOnline, $result_check);

            $result_result = new \App\Http\Resources\Conversation\ConversationResource($conversationDB);
            \DB::commit();
            return resSuccessWithinData($result_result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function changeAgent(Request $request)
    {
        $validationRules = [
            'conversation_id' => 'required|exists:conversations,id',
            'user_id'         => 'required|exists:users,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userModel = \App::make('App\Models\User');
        $departmentModel = \App::make('App\Models\Department');
        $conversationModel = \App::make('App\Models\Conversation');

        $departmentDB = $departmentModel->where('type', DepartmentConstant::TYPE_BUSINESS)->first();
        $userDB = $userModel->where('id', $params['user_id'])
                            ->where('department_id', $departmentDB->id)
                            ->where('status', 1)
                            ->first();

        if(is_null($userDB)) {
            $result = [
                'error' => 'Nhân viên không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $conversationDB = $conversationModel->where('id', $params['conversation_id'])->first();

        if ($conversationDB->user_id == $params['user_id']) {
            $result = [
                'error' => 'Nhân viên không phù hợp'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            // Chuyển nhượng
            $userOldDB = $userModel->where('id', $conversationDB->user_id)->first();

            $conversationDB->user_id    = $userDB->id;
            $conversationDB->user_code  = $userDB->code;
            $conversationDB->user_name  = $userDB->name;
            // $conversationDB->user_email = $userDB->email;
            $conversationDB->user_image = $userDB->avatar_origin;

            $conversationDB->time_last_send = date('Y-m-d H:i:s');
            $conversationDB->time_last_send_admin = date('Y-m-d H:i:s');
            $conversationDB->save();

            // Cập nhật số chưa đếm
            if ($conversationDB->unread_admin > 0) {
                // Chuyển số chưa đợc từ người cũ sang người mới
                // $userOldDB->total_chat_unread -= 1; // Tp070125
                // $userOldDB->save();

                // $userDB->total_chat_unread += 1; // Tp070125
                // $userDB->save();
            } else {
                $conversationDB->unread_admin = 1;
                $conversationDB->save();

                // $userDB->total_chat_unread += 1; // Tp070125
                // $userDB->save();
            }

            \DB::commit();

            $result_result = new \App\Http\Resources\Conversation\ConversationResource($conversationDB);
            $result_result['user_code_old'] = $userOldDB->code;
            return resSuccessWithinData($result_result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function findCustomerByPhone(Request $request)
    {
        $validationRules = [
            'phone' => 'required'
        ];
        $validated = $request->validate($validationRules);
        $phone = $request->phone;
        if (!preg_match('/^(0|\+84)[0-9]{9}$/', $phone)) {
            return resErrorWithinData([
                'error' => 'Số điện thoại không đúng định dạng'
            ], 'Có lỗi xảy ra', 422);
        }
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('phone_number', $phone)->first();
        if (is_null($customer)) {
            return resErrorWithinData([
                'error' => 'Số điện thoại không tồn tại trong hệ thống'
            ], 'Có lỗi xảy ra', 422);
        }
        $admin = \Auth::user();
        if ($customer->staff_group_id != $admin->id) {
            return resErrorWithinData([
                'error' => 'Khách hàng này không thuộc diện quản lý của bạn'
            ], 'Có lỗi xảy ra', 422);
        }
        return resSuccessWithinData([
            'id' => $customer->id,
            'code' => $customer->code,
            'name' => $customer->name,
            'phone' => $customer->phone_number,
        ]);
    }
}
