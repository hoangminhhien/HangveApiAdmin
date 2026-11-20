<?php

namespace App\Http\Controllers;

use App\Http\Requests\Group\CreateGroupRequest;
use App\Http\Requests\Group\EditUserGroupRequest;
use App\Http\Requests\Group\UpdateCustomerForUserRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\Group\GroupDetailResource;
use App\Models\Group;
use App\Models\UserGroup;
use App\Services\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function __construct(GroupService $service)
    {
        $this->_service = $service;
    }

    /**
     * @param CreateGroupRequest $request
     * @return JsonResponse
     */
    public function create(CreateGroupRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->only('name', 'users', 'kpi', 'kpi_sales', 'kpi_services', 'kpi_transports', 'manager_id', 'admin_id', 'note');
            $this->_service->store($data);

            DB::commit();

            return resSuccess('Tạo nhóm thành công');
        } catch (\Exception $e) {
            DB::rollBack();

            return resError($e->getMessage());
        }
    }

    /**
     * @param int $id
     * @param UpdateGroupRequest $request
     * @return JsonResponse
     */
    public function modifies(int $id, UpdateGroupRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->only('name', 'users', 'kpi', 'kpi_sales', 'kpi_services', 'kpi_transports', 'manager_id', 'admin_id', 'note');
            $this->_service->modifies($id, $data);

            DB::commit();

            return resSuccess('Sửa nhóm thành công');
        } catch (\Exception $e) {
            DB::rollBack();

            return resError($e->getMessage());
        }
    }

    /**
     * @param UserGroup $userGroup
     * @param EditUserGroupRequest $request
     * @return JsonResponse
     */
    public function edit(UserGroup $userGroup, EditUserGroupRequest $request): JsonResponse
    {
        try {
            $data = $request->only('group_id', 'kpi', 'kpi_sales', 'kpi_services', 'kpi_transports');
            $this->_service->edit($userGroup, $data);

            return resSuccess('Sửa nhân viên nhóm thành công');
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param UserGroup $userGroup
     * @param EditUserGroupRequest $request
     * @return JsonResponse
     */
    public function getList(): JsonResponse
    {
        try {
            $columns = ['id', 'name'];
            $data    = $this->_service->getList($columns);

            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resErrorWithinData([], $e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getListUser(Request $request): JsonResponse
    {
        try {
            $params = $request->only('key_search', 'type');
            $data   = $this->_service->getListUser($params);

            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param UpdateCustomerForUserRequest $request
     * @return JsonResponse
     */
    public function updateCustomerForUser(UpdateCustomerForUserRequest $request): JsonResponse
    {
        try {
            $params = $request->only('ids', 'customer_id');
            $this->_service->updateCustomerForUser($params);

            return resSuccess('Gán khách hàng thành công');
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getListCustomer(Request $request): JsonResponse
    {
        try {
            $params = $request->only('key_search');
            $data   = $this->_service->getListCustomer($params);

            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function changeStatusGetCustomer(Request $request): JsonResponse
    {
        try {
            $params = $request->only('ids', 'status');
            $this->_service->changeStatusGetCustomer($params);

            return resSuccess();
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param string $id
     * @return JsonResponse
     */
    public function detail(string $id): JsonResponse
    {
        try {
            return resSuccessWithinData(new GroupDetailResource(
                Group::query()->with(['usersMonth', 'manager', 'admins', 'kpiMonth'])->findOrFail($id))
            );
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resErrorWithinData([]);
        }
    }

    /**
     * @param string $id
     * @return JsonResponse
     */
    public function groupKpiMonthly(): JsonResponse
    {
        try {
            Artisan::call('group:kpi-monthly');

            return resSuccess('OK');
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }
}
