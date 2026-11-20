<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\User;
use App\Services\RoleService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use GuzzleHttp\Client as Guzzle;

/**
 * Class RoleController
 * @package App\Http\Controllers
 */
class RoleController extends Controller
{
      /**
     * DashboardController constructor.
     * @param  RoleService $service
     */
    public function __construct(RoleService $service)
    {
        $this->_service = $service;
    }

    public function storeRequest(): array
    {
        return [
            // 'display_name' => 'required|min:1|max:50|unique:roles,display_name',
            'display_name' => 'required|min:1|max:50',
            'description' => 'nullable|min:1|max:255',
        ];
    }

    public function storeMessage(): ?array
    {
        return [
            'display_name.required' => 'Nhập tên Role',
            'display_name.unique' => 'Tên role đã tồn tại',
        ];
    }

    public function store(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);
        $params = request()->all();
        $userOnline = Auth::user();
        $organization_id = $userOnline->organization_id ?? 0;
        if ($organization_id == 0) {
            return resError('Tài khoản này không thuộc công ty nào');
        }
        $roleModel = new Role();
        $role_name = Str::slug($params['display_name']) . '.' . $organization_id;
        $roleDB = $roleModel->query()->where('name', $role_name)->first();
        if (($roleDB)) {
            return resError('Role đã tồn tại');
        }

        return resSuccessWithinData($this->_service->store(request()->all()));
    }

    public function updateRequest(string $id): array
    {
        return [
            // 'display_name'  => 'nullable|min:1|max:50|unique:roles,display_name,' . $id,
            'description'   => 'nullable|min:1|max:255',
            'permissions'   => 'array',
            'permissions.*' => 'nullable|exists:permissions,name'
        ];
    }

    public function updateMessage(): array
    {
        return [
            'display_name.required' => 'Nhập tên Role',
            'display_name.unique' => 'Tên role đã tồn tại',
        ];
    }

    public function update(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

        $data = request()->only([
            'description',
            'permissions'
        ]);

        $roleModel = new Role();
        $roleDB = $roleModel->query()->where('id', $id)->first();
        if (is_null($roleDB)) {
            return resError('Bản ghi không tồn tại');
        }
        try {
            DB::beginTransaction();
            $data_fill = [
                'description' => $data['description']
            ];
            $roleDB->fill($data_fill);
            $roleDB->save();
            $permissions = $data['permissions'] ?? [];
            $this->syncLogoutAccountToDepositApp($roleDB, $permissions);
            $roleDB->syncPermissions($permissions);
            DB::commit();
            return resSuccess();
        } catch (\Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    /**
     * @throws GuzzleException
     */
    public function syncLogoutAccountToDepositApp($roleDB, $permissions)
    {
        // Kiểm tra xem role đó có Per cần check không
        $permissionDBs = $roleDB->permissions;
        $perOfRole = Arr::pluck($permissionDBs, 'name');
        if (in_array('fund.view-app-deposit-auto', $perOfRole)) {
            // Check trong mảng mới lại không có
            if (!in_array('fund.view-app-deposit-auto', $permissions)) {
                // Nếu có thì gửi update sang bên con App
                $userModel = new User();
                $userOfRole = $userModel->query()->role($roleDB->name)->get();
                $arr_account = [];
                if (count($userOfRole)) {
                    $arr_account = Arr::pluck($userOfRole, 'email');
                }

                if (count($arr_account)) {
                    $guzzle = new Guzzle;
                    $url = env('SYNC_CHARGE_APP_URL') . 'logout-account';
                    $options = [
                        'json' => [
                            'arr_account' => $arr_account,
                        ],
                        'headers' => [
                            'X-Token-Auth' => env('SYNC_CHARGE_APP_TOKEN')
                        ],
                        'verify' => false,
                        'http_errors' => false,
                    ];
                    $guzzle->request('POST', $url, $options)->getBody()->getContents();
                }
            }
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $roleModel = new Role();
        $roleDB = $roleModel->query()->where('id', $id)->where('type', 0)->first();
        if (is_null($roleDB)) {
            return resError('Bản ghi không tồn tại');
        }
        try {
            DB::beginTransaction();
            $roleDB->syncPermissions([]);
            $roleDB->delete();
            DB::commit();
            return resSuccess();
        } catch (\Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }

    }

    public function makeRoleDefault(): JsonResponse
    {
        app()['cache']->forget('maklad.permission.cache');
        DB::beginTransaction();
        try {
            $userOnline = Auth::user();
            $permissions = [
                [
                    'type' => 1,
                    'text' => 'Đơn hàng',
                    'pers' => [
                        ['order.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['order.add-pack', 'Thêm kiện hàng'], // Done
                        ['order.edit', 'Sửa đơn'], // Done
                        ['order.edit-status', 'Sửa trạng thái đơn'], // Done
                        ['order.add-fine', 'Thêm nộp phạt'], // Done
                        ['order.wait-assign-consulting', 'Chờ gán nhân viên Tư vấn'],
                        ['order.wait-assign-care', 'Chờ gán nhân viên chăm sóc'],

                        ['order.edit-in-status-wait-to-pay-or-ordered', 'Sửa đơn ở trạng thái chờ thanh toán hoặc đặt hàng'],
                        ['order.add-advance-money', 'Thêm ứng tiền'],
                        ['order.show_phone_number', 'Hiển thị số điện thoại'],
                        ['order.request_order_contract', 'Yêu cầu kí hợp đồng đơn hàng'],
                        ['order.info', 'Thông tin mua hàng'],
                        ['order.supplier', 'Nhà cung cấp + Kiện hàng'],
                        ['order.shipping_cost', 'Thay đổi phí ship'],
                        ['order.cancel', 'Hủy đơn hàng'],
                        ['order.all', 'Xem tất cả đơn hàng'],
                        ['order.show-money-actual', 'Xem tổng tiền thực tế'],
                        ['order.edit-note-product', 'Sửa ghi chú sản phẩm'],
                        ['order.edit-order-cost-and-fee', 'Sửa tiền hàng thực tế + phí ship'],
                        ['order.update_unit_price_and_quantity', 'Sửa đơn giá + số lượng'],
                        ['order.add_note', 'Thêm ghi chú'],
                        ['order.show_negotiate_excel', 'Hiển thị phí đàm phán khi xuất excel'],
                        ['order.order_percent_deposit', 'Sửa % phí đặt cọc, đặt hàng'],
                        ['order.split_order', 'Tách đơn hàng'],
                        ['order.change_staff_quotaion', 'Thay đổi nhân viên báo giá'],
                        ['order.change_staff_order', 'Thay đổi nhân viên đặt hàng'],
                        ['order.change_staff_order_circle', 'Thay đổi nhân viên đặt hàng (gán xoay vòng)'],
                        ['order.change_staff_group', 'Thay đổi nhân viên quản lý'],
                        ['order.delete', 'Xóa đơn hàng'],
                        ['order.create_cancel', 'Tạo yêu cầu hủy đơn hàng'],
                        ['order.create_refund', 'Tạo yêu cầu hoàn tiền'],
                        ['order.view_refund', 'Xem yêu cầu hoàn tiền'],
                        ['order.change_staff_with_status_done', 'Thay đổi nhân viên quản lý khi đơn hoàn thành'],
                        ['order.change_staff_with_status_deposited', 'Thay đổi nhân viên quản lý đơn Đã đặt cọc'],
                        ['order.edit_service', 'Sửa dịch vụ ở đơn Chờ thanh toán/ Đặt hàng'],
                        ['order.tick_waiting_for_quote', 'Tick chọn chờ báo giá'],
                        ['order.is_up_order_cost', 'Lưu lại những đơn bị chênh tiền cọc'],
                        ['order.deposit_for_supplier', 'Tích yêu cầu cọc trước cho NCC'],
                        ['order.request_deposit_customer', 'Yêu cầu khách cọc tiền'],
                        ['order.change_exchange_rate', 'Thay đổi tỉ giá'],
                        ['order.edit_note_china', 'Sửa ghi chú tiếng trung'],
                        ['order.is_china_shipping', 'Chọn NCC chưa báo phí ship'],

                        ['order.is_edit_when_pack_in_qc', 'Tích chọn dịch vụ khi kiện Đang ở kho QC'],

                        ['order.is_edit_note_private', 'Chỉnh sửa ghi chú nội bộ'],
                        ['order.is_edit_note_business', 'Chỉnh sửa ghi chú nội bộ kinh doanh'],

                        ['order.is_waiting_confirm', 'Tích đơn chờ xác nhận thông tin'],

                        ['order.edit_quantity_unit_price_status_8', 'Sửa số lượng, đơn giá đơn Chờ thanh toán'],
                        ['order.edit_quantity_unit_price_status_3', 'Sửa số lượng, đơn giá đơn Đặt hàng'],

                        ['order.change_staff_quote_order_status_0_1_5', 'Thay đổi nv báo giá ở đơn Chờ báo giá, Chờ đặt cọc, Cần xác nhận lại'],
                        ['order.change_staff_quote_order_status_2_8', 'Thay đổi nv Đặt hàng ở đơn Đã đặt cọc, Chờ thanh toán'],

                    ]
                ],
                [
                    'type' => 2,
                    'text' => 'Kiện hàng',
                    'pers' => [
                        ['pack.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['pack.edit', 'Sửa thông tin kiện'], // Done
                        ['pack.print-bill', 'In phiếu xuất kho'],
                        ['pack.cancel', 'Hủy kiện'], // Đang bị dùng chung với cái pack.edit
                        ['pack.add-delivery', 'Thêm mới giao hàng'], // Done
                        ['pack.add', 'Thêm mới kiện hàng'], // Done
                        ['pack.update-for-tbp', 'Cập nhật tình trạng (TBP)'],
                        ['pack.wait-confirm', 'Chờ xác nhận'],
                        ['pack.check-refund', 'Kiểm Hàng'],
                        ['pack.receive-goods', 'Trả hàng'],
                        ['pack.all', 'Xem tất cả kiện hàng'],
                        ['pack.statistical-print', 'Thống kê phiếu in'],
                        ['pack.change-status-16', 'Chuyển trạng thái sau đã nhận hàng'],
                        ['pack.change-staff-group', 'Thay đổi nhân viên quản lý'],
                        ['pack.all-pack.wait-confirm', 'Xem tất cả kiện chờ xác nhận'],
                        ['pack.change-price', 'Thay đổi giá nhiều kiện'],
                        ['pack.change-staff-group-status-16', 'Thay đổi nhân viên quản lý khi đã nhận hàng'],
                        ['pack.report-control', 'Báo cáo đối soát kiện'],
                        ['pack.change-type-order', 'Đổi loại đơn Order/ Kí gửi'],
                        ['pack.edit-package-number-is-order', 'Chỉnh sửa số kiện dự kiến của Kiện order'],
                        ['pack.edit-package-number-is-consignment', 'Chỉnh sửa số kiện dự kiến của Kiện kí gửi'],
                        ['pack.show-edit-all-inspection', 'Xem/Sửa tất cả kiện cần kiểm hàng'],
                        ['pack.show-refuns-order-completed', ' Kiểm hàng/ hiển thị đơn hoàn thành'],
                        ['pack.is_block_package', 'Chặn xuất kho'],
                        ['pack.edit-service-consignment-not-in-stock', 'Sửa dịch vụ kiện kí gửi khi chưa về kho (Chờ xử lý, Đang về kho TQ, Xác nhận lại)'],
                        ['pack.edit-service-consignment-at-qc', 'Sửa dịch vụ kiện Đang ở kho QC'],
                        ['pack.request-change-unit-price', 'Yêu cầu đổi đơn giá kiện hàng'],
                        ['pack.approve-unit-price-hangve', 'Xem + Duyệt yêu cầu duyệt đơn giá VCQT của Hàng Về'],
                        ['pack.approve-unit-price-quote', 'Xem + Duyệt yêu cầu duyệt đơn giá VCQT của Báo giá'],
                    ]
                ],
                [
                    'type' => 3,
                    'text' => 'Giao hàng',
                    'pers' => [
                        ['delivery.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['delivery.edit-delivery', 'Sửa thông tin phiếu giao hàng'], // Done
                        ['delivery.print-delivery', 'In phiếu giao hàng '], // Done
                        ['delivery.print-bill', 'In phiếu xuất kho'], // Done
                        ['delivery.add-pack-to-delivery', 'Thêm kiện vào phiếu giao hàng'], // Done
                        ['delivery.create-order-in-carrier', 'Tạo đơn hàng bên hãng vận chuyển'], // Done
                        ['delivery.delete', 'Xóa phiếu giao hàng'], // Done
                        ['delivery.all', 'Xem tất cả giao hàng'],
                        ['delivery.edit_carrier_code', 'Sửa mã bưu kiện'],
                    ]
                ],
                [
                    'type' => 4,
                    'text' => 'Khiếu nại',
                    'pers' => [
                        ['complain.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['complain.edit', 'Sửa thông tin khiếu nại'], // Done
                        ['complain.all', 'Xem tất cả khiếu nại'],
                        ['complain.change-status', 'Sửa trạng thái khiếu nại'],
                        ['complain.send-feedback-customer', 'Gửi phản hồi khách hàng'],
                        ['complain.send-feedback-private', 'Gửi phản hồi nội bộ'],
                    ]
                ],
                [
                    'type' => 5,
                    'text' => 'Nộp phạt',
                    'pers' => [
                        ['fine.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['fine.add', 'Thêm mới'], // Done
                        ['fine.edit', 'Sửa thông tin'], // Done
                    ]
                ],
                [
                    'type' => 6,
                    'text' => 'Kế toán',
                    'pers' => [
                        ['fund.index', 'Quỹ tồn'], // Done
                        ['fund.history-vn', 'Lịch sử thu chi tiền việt Nam'], // Done
                        ['fund.history-tq', 'Lịch sử thu chi tiền trung quốc'], // Done
                        ['fund.recharge-tq', 'Nạp tiền ngân hàng TQ'], // Done
                        ['fund.detail-real-ncc', 'Chi tiết đặt hàng thực tế nhà cc'], // Done
                        ['fund.index-withdraw', 'Danh sách rút tiền'], // Done
                        ['fund.type_payment', 'Loại thanh toán'], // Done
                        ['fund.fund-debt', 'Công nợ khách hàng'],
                        ['fund.revenue-expenditure', 'Báo cáo thu chi'],
                        ['fund.view-app-deposit-auto', 'Xem app Nạp tiền (Deposit Autobot)'],

                        ['fund.index-withdraw-managed', 'Chỉ xem danh sách rút tiền theo tài khoản quản lý'],
                        ['fund.edit-withdraw', 'Chỉnh sửa rút tiền'],

                        // Bổ sung mới
                        ['fund.ls-vn-view-all', 'Xem toàn bộ Ls thu chi VN'], // Xem tất cả
                        ['fund.ls-vn-all-new-and-view', 'Thêm mới + Xem Ls đã thêm mới VN'], // Xem cái nó đã tạo
                        ['fund.ls-vn-edit', 'Sửa Ls VN'],
                        ['fund.ls-vn-delete', 'Xóa Ls VN'],
                        ['fund.ls-vn-print', 'In phiếu thu chi VN'],
                        ['fund.ls-vn-view-history-edit', 'Xem Ls chỉnh sửa VN'],
                        ['fund.ls-vn-view-history-delete', 'Xem Ls đã xóa'],

                        ['fund.rmb', 'Xem báo cáo lợi nhuận đổi RMB'],
                        ['fund.fund-ncc.view-only', 'Chỉ xem thanh toán thực tế NCC'],

                        // Bổ sung ngày 02-12-2024
                        ['fund.view-reserve-all', 'Xem quỹ tồn mặc định và quỹ tồn kho'],
                        ['fund.view-reserve-warehouse', 'Xem quỹ tồn kho'],
                        ['fund.add', 'Thêm quỹ'],
                        ['fund.transfer', 'Chuyển quỹ'],
                        ['fund.change-rmb', 'Đổi RMB'],
                        ['fund.setting-type', 'Cài đặt loại quỹ'],
                    ]
                ],
                [
                    'type' => 7,
                    'text' => 'Khách hàng',
                    'pers' => [
                        ['customer.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['customer.add', 'Thêm mới khách hàng'], // Done
                        ['customer.edit', 'Sửa thông tin khách hàng'],
                        ['customer.delete', 'Xóa khách hàng'], // Done
                        ['customer.change-staff', 'Thay đổi nhân viên hỗ trợ'],
                        ['customer.with-trashed', 'Lịch sử xóa khách hàng'],
                        ['customer.show-mail', 'Xem email + số điện thoại'],
                        ['customer.all', 'Xem tất cả khách hàng'],
                        ['customer.change_staff_group', 'Thay đổi nhân viên quản lý'],
                        ['customer.edit-offer', 'Chỉnh sửa ưu đãi'],
                        ['customer.edit-label', 'Chỉnh sửa nhãn'],
                        ['customer.list-close', 'Xem danh sách yêu cầu đóng tài khoản'],
                        ['customer.update-status-close', 'Duyệt đóng tài khoản'],
                        ['customer.view-waiting-for-care', 'Xem danh sách khách hàng chờ chăm sóc'],
                        ['customer.view-close-account', 'Xem danh sách khách hàng đã đóng tài khoản'],

                        ['customer.advise-cn-view-all', 'Xem toàn bộ Yêu cầu tư vấn '],
                        ['customer.advise-cn-view-only-me', 'Chỉ xem yêu cầu tư vấn của chính mình'],
                        ['customer.advise-cn-change-status', 'Chuyển trạng thái '],
                        ['customer.advise-cn-assign-agent', 'Gán nhân viên tư vấn'],
                    ]
                ],
                [
                    'type' => 8,
                    'text' => 'Cài đặt',
                    'pers' => [
                        ['setting.index', 'Danh sách loại thanh toán'], // Done
                        ['setting.delete', 'Xóa dữ liệu'], // Done
                        ['setting.show-config', 'Xem bảng phí'],
                        ['setting.edit-config', 'Chỉnh sửa các bảng phí'],
                        ['setting.edit-config-exchange-rate', 'Chỉnh sửa tỉ giá'],

                        ['setting.respond-complain', 'Phản hồi khiếu nại'], // Done
                        ['setting.default-shipping-address', 'Địa chỉ nhận hàng mặc định'], // Done
                        ['setting.reason-cancel', 'Lí do hủy đơn'], // Done
                        ['setting.estimated-shipping-fee', 'Phí vận chuyển ước tính'], // Done
                        ['setting.note-order', 'Ghi chú đơn hàng'], // Done
                        ['setting.reason-order-refund', 'Lý do đơn hàng hoàn tiền'], // Done
                        ['setting.fee-ship-min', 'Phí vận chuyển tối thiểu'], // Done
                        ['setting.bank', 'Quản lý ngân hàng'], // Done
                        ['setting.warning-quote-list', 'Xem danh sách Cảnh báo báo giá'], // Done
                        ['setting.warning-quote-update', 'Cập nhật Cảnh báo báo giá'], // Done
                        ['setting.holiday', 'Cài đặt ngày nghỉ lễ'], // Done
                        ['setting.warning', 'Cài đặt cảnh báo'], // Done
                        ['setting.reason-confirm-order', 'Lý do xác nhận lại đơn'],
                        ['setting.reason-confirm-order-add', 'Thêm lý do xác nhận lại đơn'],
                        ['setting.reason-confirm-order-edit', 'Sửa lý do xác nhận lại đơn'],
                        ['setting.reason-confirm-order-delete', 'Xóa lý do xác nhận lại đơn'],
                        ['setting.auto-cancel-order-index', 'Xem danh sách tự động hủy đơn'],
                        ['setting.auto-cancel-order-edit', 'Sửa tự động hủy đơn'],
                        ['setting.kpi-index', 'Xem bảng tính KPI'],
                        ['setting.kpi-add-edit-delete', 'Thêm/Sửa/Xóa bảng tính KPI'],
                    ]
                ],
                [
                    'type' => 9,
                    'text' => 'Nhân viên',
                    'pers' => [
                        ['staff.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['staff.add', 'Thêm nhân viên'], // Done
                        ['staff.edit', 'Sửa nhân viên'], // Done
                        ['staff.delete', 'Xóa nhân viên'], // Done
                        ['staff.create-group', 'Tạo nhóm kinh doanh'],
                        ['staff.list-group', 'Danh sách nhóm kinh doanh'],
                        ['staff.on-off-receive-order', 'Bật/tắt nhận đơn'],
                    ]
                ],
                [
                    'type' => 10,
                    'text' => 'Phòng ban',
                    'pers' => [
                        ['department.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['department.add', 'Thêm phòng ban'], // Done
                        ['department.edit', 'Sửa phòng ban'], // Done
                        ['department.delete', 'Xóa phòng ban'], // Done
                    ]
                ],
                [
                    'type' => 11,
                    'text' => 'Quyền',
                    'pers' => [
                        ['role.index', 'Xem danh sách + Xem chi tiết'],
                        ['role.add', 'Thêm quyền'],
                        ['role.edit', 'Sửa quyền'],
                        ['role.delete', 'Xóa quyền'],
                    ]
                ],
                [
                    'type' => 12,
                    'text' => 'Dashboard',
                    'pers' => [
                        ['dashboard.type-1', 'CEO'],
                        ['dashboard.type-2', 'Trưởng phòng đặt hàng'],
                        ['dashboard.type-3', 'Nhân viên báo giá'],
                        ['dashboard.type-4', 'Nhân viên đặt hàng'],
                        ['dashboard.type-5', 'Trưởng phòng tư vấn'],
                        ['dashboard.type-6', 'Nhân viên tư vấn'],
                        ['dashboard.type-7', 'Trưởng phòng chăm sóc'],
                        ['dashboard.type-8', 'Nhân viên chăm sóc'],
                        ['dashboard.type-9', 'Kế toán'],
                        ['dashboard.type-10', 'Maketing'],
                        ['dashboard.type-11', 'Kho trung quốc'],
                        ['dashboard.type-12', 'Kho trung chuyển'],
                        ['dashboard.type-13', 'Kho chi nhánh : all'],
                        ['dashboard.type-13-1', 'Kho chi nhánh : Trần điền'],
                        ['dashboard.type-13-2', 'Kho chi nhánh : Tạ quang Bửu '],
                        ['dashboard.type-13-3', 'Kho chi nhánh : Văn Quán '],
                        ['dashboard.type-13-4', 'Kho chi nhánh : Nguyễn văn luông'],
                        ['dashboard.report-gdqt', 'Báo cáo giao dịch quốc tế'],
                    ]
                ],
                [
                    'type' => 13,
                    'text' => 'Chức năng gán cho nhân viên để xử lý đơn',
                    'pers' => [
                        ['customer-consulting', 'Nhân viên tư vấn'],
                        ['quotation-for-customer', 'Nhân viên báo giá'],
                        ['take-care-customer', 'Nhân viên chăm sóc'],
                        ['order-staff', 'Nhân viên đặt hàng'],
                        ['complain-staff', 'Nhân viên khiếu nại'],
                        ['customer-group-staff', 'Nhân viên quản lý'],
                    ]
                ],
                [
                    'type' => 14,
                    'text' => 'Ẩn hiện menu, chức năng',
                    'pers' => [
                        ['hide.order-search', 'Ẩn lọc 1 số trường tìm kiếm đơn'],
                        ['hide.customer-phone', 'Ẩn số điện thoại khách hàng'],
                        ['show.order-search-by-supplier', 'Lọc đơn hàng theo nhà cung cấp'],
                    ]
                ],
                [
                    'type' => 15,
                    'text' => 'Báo cáo',
                    'pers' => [
                        ['report.consulting-manage-customer-potential', 'Tư vấn quản lý khách hàng tiềm năng'],
                        ['report.care-customer-active', 'Chăm sóc khách hàng đang hoạt động'],
                        ['report.consulting-management-customer-by-orders', 'Tư vấn quản lý khách hàng theo đơn'],
                        ['report.order-done', 'Đơn hoàn thành'],
                        ['report.consulting-customer-pack-to-vn', 'Tư vấn khách hàng kiện hàng về VN'],
                        ['report.tbp-rate', 'Trưởng bộ phận đánh giá'],
                        ['report.order-staff-activity', 'Hoạt động nhân viên đặt hàng'],
                        ['report.complain-staff-activity', 'Hoạt động nhân viên khiếu nại'],
                        ['report.quote-activity', 'Hoạt động báo giá'],
                        ['report.cancel-order', 'Nhân viên đặt hàng hủy đơn'],
                        ['report.performance-sale-and-care-consulting', 'Hiệu suất công việc nhân viên phòng kinh doanh / tư vấn chăm sóc'],
                        ['report.purchase-channel', 'Thống kê nhanh'],
                        ['report.sale', 'Báo cáo doanh số'],
                        ['report.transaction', 'Báo cáo giao dịch'],
                        ['report.sale-staff', 'Doanh số nhân viên kinh doanh'],
                        ['report.package-consignment', 'Bác cáo kiện hàng ký gửi hoàn thành'],
                        ['report.view-run-marketing', 'Xem báo cáo đối soát marketing'],
                        ['report.view-all-run-marketing', 'Xem toàn bộ báo cáo đối soát marketing'],
                        ['report.view-week', 'Xem báo cáo tuần'],

                        ['report.view-all-package-consignment', 'Xem toàn bộ kiện hàng ký gửi hoàn thành'],
                        ['report.view-all-consulting-manage-customer-potential', 'Xem toàn bộ tư vấn quản lý khách hàng tiềm năng'],
                        ['report.view-all-order-done', 'Xem toàn bộ tư vấn đơn hàng hoàn thành'],

                        ['report.view-all-view-week', 'Xem toàn bộ báo cáo tuần'],
                        ['report.fee-service', 'Xem báo cáo phí dịch vụ'],
                        ['report.slow-quote', 'Xem báo cáo báo giá chậm'],
                        ['report.deposit-customer', 'Xem báo cáo khách đặt cọc'],
                        ['report.reconciliation-warehouse', 'Xem báo cáo đối soát phiếu xuất kho'],
                        ['report.view-only-quote-by-self', 'Chỉ xem báo cáo đặt hàng của chính mình'],
                    ]
                ],
                [
                    'type' => 16,
                    'text' => 'Hoàn tiền',
                    'pers' => [
                        ['refund.index', 'Xem danh sách + Xem chi tiết'], // Done
                        ['refund.add', 'Thêm mới hoàn tiền'], // Done
                        ['refund.payment', 'Thêm mới hoàn tiền (Ở cột thao tác)'], // Done
                        ['refund.edit', 'Chỉnh sửa hoàn tiền + Hủy hoàn tiền'],
                        ['refund.feedback', 'Nhập ý kiến'],
                        ['refund.confirm', 'Xác nhận hoàn tiền'],
                        ['refund.feedback-multiple', 'Thêm ý kiến cho nhiều đơn']
                    ]
                ],
                [
                    'type' => 17,
                    'text' => 'Ứng tiền',
                    'pers' => [
                        ['advanceMoney.index', 'Xem danh sách'], // Done
                        ['advanceMoney.edit', 'Duyệt ứng tiền + Hủy yêu cầu'], // Done
                    ]
                ],
                [
                    'type' => 18,
                    'text' => 'Đánh giá',
                    'pers' => [
                        ['rate.index', 'Xem danh sách'],
                        ['rate.delete', 'Xóa đánh giá'],
                    ]
                ],
                [
                    'type' => 19,
                    'text' => 'Đặt hàng',
                    'pers' => [
                        ['place_order.index', 'Xem danh sách đơn đặt hàng'],
                        ['place_order.assign_support_list', 'Xem danh sách đơn hỗ trợ'],
                        ['place_order.assign_support', 'Tạo đơn hỗ trợ'],
                        ['place_order.assign_support_cancel', 'Hủy đơn hỗ trợ'],
                        ['place_order.place_order', 'Đặt hàng'],
                        ['place_order.place_order_cancel', 'Hủy đặt hàng'],
                        ['place_order.payment', 'Thanh toán'],
                        ['place_order.report_list', 'Xem báo cáo đặt hàng'],
                        ['actual_amount.index', 'Xem danh sách cập nhật tổng tiền thực tế'],
                        ['actual_amount.approve', 'Duyệt cập nhật tổng tiền thực tế'],
                        ['actual_amount.index_one', 'Xem danh sách cập nhật tổng tiền thực tế theo tài khoản'],
                    ]
                ],
                [
                    'type' => 22,
                    'text' => 'ChatAi',
                    'pers' => [
                        ['chat_ai.can-chat', 'ChatAi'],
                        ['chat_ai.view-full-list', 'Xem tất cả ChatAi'],
                    ]
                ],
                [
                    'type' => 20,
                    'text' => 'LiveChat',
                    'pers' => [
                        ['live_chat.view-full-list', 'Xem tất cả Live chat'],
                        ['live_chat.config-setting', 'Cài đặt trạng thái chat'],
                        ['live_chat.change-agent-conversation', 'Thay đổi nhân viên'],
                    ]
                ],
            ];

            $moduleModel = new Module();
            $roleModel = new Role();
            $permissionModel = new Permission();

            $listPermissionOfAdmin = [];
            foreach ($permissions as $permission){
                $moduleDB = $moduleModel->query()->where('type', $permission['type'])->first();
                if (is_null($moduleDB)) {
                    $data_module = [
                        'name' => $permission['text'],
                        'type' => $permission['type'],
                        'priority' => $permission['type'],
                    ];
                    $moduleDB = $moduleModel->query()->create($data_module);
                }
                foreach ($permission['pers'] as $keyPer =>$per){
                    $permissionDB = $permissionModel->query()->where('name', $per[0])->first();
                    if (is_null($permissionDB)) {
                        $data_permission = [
                            'name' => $per[0],
                            'description' => $per[1],
                            'guard_name' => 'api',
                            'module_id' => $moduleDB->id ?? 0,
                            'priority' => $keyPer + 1,
                        ];
                        $permissionModel->create($data_permission);
                    }

                    if ($permission['type'] != 13) {
                        $listPermissionOfAdmin[] = $per[0];
                    }
                }
            }

            // Tạo role admin sẵn
            $organization_id = $userOnline->organization_id ?? 0;
            $name_role = 'admin.' . $organization_id;
            $roleDB = $roleModel->query()->where('type', 1)
                                ->where('name', $name_role)
                                ->where('organization_id', $organization_id)
                                ->first();
            if (is_null($roleDB)) {
                $data_role = [
                    'name' => $name_role,
                    'display_name' => 'Admin',
                    'description' => 'Admin hệ thống',
                    'guard_name' => 'api',
                    'organization_id' => $organization_id,
                    'type' => 1 // Hệ thống tạo
                ];
                $roleDB = $roleModel->create($data_role);

                // Sync Per vào role
                $roleDB->syncPermissions($listPermissionOfAdmin);
            }

            $admin = User::query()->where('email', 'admin@thuongdo.com')->first();
            if ($admin) {
                $admin->assignRole($roleDB);
            }

            DB::commit();
            return resSuccessWithinData('OK');
        } catch (\Exception $e) {
            DB::rollback();
            return resError($e->getMessage() . ' line ' . $e->getLine() . ' file ' . $e->getFile());
        }
    }

    public function fixDuplicateRole(): JsonResponse
    {
        app()['cache']->forget('maklad.permission.cache');
        DB::beginTransaction();
        try {
            $userOnline = Auth::user();
            $userRepo = new User();
            $admin = $userRepo->query()->where('email', 'admin@thuongdo.com')->first();
            $users = $userRepo->query()->get();
            $organization_id = $userOnline->organization_id ?? 0;
            $name_role = 'admin.' . $organization_id;
            $admin->syncRoles([$name_role]);
            foreach ($users as $user) {
                $roleUsers = $user->roles;
                if (count($roleUsers) > 1) {
                    $role = $roleUsers[0];
                    $user->syncRoles($role);
                    $perOfRole = $role->permissions->pluck('name');
                    $user->syncPermissions($perOfRole);
                }
            }

            DB::commit();
            return resSuccessWithinData('OK');
        } catch (\Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

}
