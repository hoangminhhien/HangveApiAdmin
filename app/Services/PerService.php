<?php

namespace App\Services;

use App\Constants\UserConstant;
use App\Models\User;

class PerService extends BaseService
{
    /**
     * Lấy ngẫu nhiên 1 nhân viên theo quyền
     *
     * @param $permission
     * @return string|null
     */
    public function getStaffRandom($permission): ?string
    {
        $userRepo = app()->make(User::class);
        $user = $userRepo->whereHas(
            'permissions',
            fn ($role) => $role->where('name', '=', $permission),
        )->inRandomOrder()->firstWhere('status', 1);

        return $user ? data_get($user, 'id') : null;
    }

    public function getConditionByUserLogin($query, $model = null, ?User $authUser = null)
    {
        // Nhân viên: ở các màn của ai nhìn dc của người ý
        //   + Nhân viên đặt hàng
        //   + Nhân viên báo giá
        //   + Nhân viên Chăm sóc
        //   + Nhân viên Tư Vấn

        // Trưởng bộ phận
        //   + Trưởng bộ phận Giao dịch quốc tế: Hiển thị tất cả khách, đơn, kiện...
        //   + Trưởng bộ phận tư vấn: Hiển thị tất cả khách, đơn... đang do tư vấn quản lý
        //   + Trưởng bộ phận chăm soc: Hiển thị tất cả khách, đơn... đang do chăm sóc quản lý

        $userLogin = $authUser ?? auth()->user();
        if ($userLogin){
            $nameRole = $this->getNameRole($userLogin->roles);

            $params = request()->all();
            $query = $this->getQueryByRole($nameRole, $params, $userLogin, $query, $model);
        }

        return $query;
    }

    public function getQueryByRole($role, $params, $userLogin, $query, $model = null)
    {
        $tableName = $query->getModel()->getTable();
        // logic mới
        switch ($tableName) {
            case 'orders':
                if ($userLogin->hasPermissionName('order.all', $userLogin) == false ) {
                    if($role == UserConstant::ROLE_STAFF_QUOTATION) {
                        $query = $query->where("{$tableName}.staff_quotation_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_ORDER) {
                        $query = $query->where("{$tableName}.staff_order_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_GROUP) {
                        $query = $query->where("{$tableName}.staff_group_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_COMPLAIN) {
                        // Để nv khiếu nại k xem dc
                        $query = $query->where("{$tableName}.staff_group_id", UserConstant::ROLE_STAFF_COMPLAIN);
                    }
                }
                break;
            case 'order_package':
                if ($userLogin->hasPermissionName('pack.all', $userLogin) == false) {
                    if($role == UserConstant::ROLE_STAFF_QUOTATION) {
                        $query = $query->where("{$tableName}.staff_quotation_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_ORDER) {
                        $query = $query->where("{$tableName}.staff_order_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_GROUP) {
                        $query = $query->where("{$tableName}.staff_group_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_COMPLAIN) {
                        // Để nv khiếu nại k xem dc
                        $query = $query->where("{$tableName}.staff_group_id", UserConstant::ROLE_STAFF_COMPLAIN);
                    }
                }
                break;
            case 'customers':
                if ($userLogin->hasPermissionName('customer.all', $userLogin) == false) {
                    if($role == UserConstant::ROLE_STAFF_QUOTATION) {
                        $query = $query->where("{$tableName}.staff_quotation_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_ORDER) {
                        $query = $query->where("{$tableName}.staff_order_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_GROUP) {
                        $query = $query->where("{$tableName}.staff_group_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_COMPLAIN) {
                        // Để nv khiếu nại k xem dc
                        $query = $query->where("{$tableName}.staff_group_id", UserConstant::ROLE_STAFF_COMPLAIN);
                    }
                }
                break;
            case 'deliveries':
                if ($userLogin->hasPermissionName('delivery.all', $userLogin) == false) {
                    if($role == UserConstant::ROLE_STAFF_QUOTATION) {
                        $query = $query->where("{$tableName}.staff_quotation_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_ORDER) {
                        $query = $query->where("{$tableName}.staff_order_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_GROUP) {
                        $query = $query->where("{$tableName}.staff_group_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_COMPLAIN) {
                        // Để nv khiếu nại k xem dc
                        $query = $query->where("{$tableName}.staff_group_id", UserConstant::ROLE_STAFF_COMPLAIN);
                    }
                }
                break;
            case 'complains':
                if ($userLogin->hasPermissionName('complain.all', $userLogin) == false) {
                    if($role == UserConstant::ROLE_STAFF_QUOTATION) {
                        $query = $query->where("{$tableName}.staff_quotation_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_ORDER) {
                        $query = $query->where("{$tableName}.staff_order_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_GROUP) {
                        $query = $query->where("{$tableName}.staff_group_id", $userLogin->id);
                    }
                    if($role == UserConstant::ROLE_STAFF_COMPLAIN) {
                        $query = $query->where("{$tableName}.staff_complain_id", $userLogin->id);
                    }
                }
                break;
        }


        // switch ($role) {
        //     case UserConstant::ROLE_MANAGER_GROUP:
        //         if (isset($params['null_staff_group_id'])) {
        //             $query = $query->whereNull("{$tableName}.staff_group_id");
        //             break;
        //         }
        //          $query = $query->whereNotNull("{$tableName}.staff_group_id");
        //         break;
        //     case UserConstant::ROLE_MANAGER_CARE:
        //         if (isset($params['null_staff_care_id']) || isset($params['null_staff_counselor_id'])) {
        //             $query = $query->whereNull("{$tableName}.staff_care_id");
        //             break;
        //         }

        //         $query = $query->whereNotNull("{$tableName}.staff_care_id");
        //         break;
        //     case UserConstant::ROLE_STAFF_GROUP:
        //         $query = $query->where("{$tableName}.staff_group_id", $userLogin->id);
        //         break;
        //     case UserConstant::ROLE_STAFF_CARE:
        //         $query = $query->where("{$tableName}.staff_care_id", $userLogin->id);
        //         break;
        //     case UserConstant::ROLE_STAFF_QUOTATION:
        //         $query = $query->where("{$tableName}.staff_quotation_id", $userLogin->id);
        //         break;
        //     case UserConstant::ROLE_STAFF_ORDER:
        //         $query = $query->where("{$tableName}.staff_order_id", $userLogin->id);
        //         break;
        //     case UserConstant::ROLE_STAFF_COMPLAIN:
        //         if ($model == 'complain') {
        //             $query = $query->where("{$tableName}.staff_complain_id", $userLogin->id);
        //             break;
        //         }

        //         break;
        // }

        return $query;
    }

    public function getNameRole($roles)
    {
        $nameRole = '';
        if (count($roles)) {
            foreach ($roles as $key => $role) {
                $nameRole = \Str::slug($role->display_name);
                if ($nameRole == 'admin') {
                    break;
                }
            }
        }
        return $nameRole;
    }

}
