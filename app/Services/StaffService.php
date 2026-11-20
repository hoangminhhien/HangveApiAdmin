<?php


namespace App\Services;
use App\Constants\ReportConstant;
use App\Constants\RoleConstant;
use App\Constants\UserConstant;
use App\Helpers\RandomHelper;
use App\Http\Resources\User\UserResource;
use App\Models\Department;
use App\Models\Group;
use App\Models\ReportUserComplainCustomer;
use App\Models\ReportUserCounselingCustomer;
use App\Models\ReportUserOrderedCustomer;
use App\Models\ReportUserQuotationCustomer;
use App\Models\ReportUserTakeCareCustomer;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class StaffService extends BaseService
{
    protected string $_resource = UserResource::class;

    protected const BY_FINE = 'fine';

    /**
     * @param int $perPage
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $this->throwModel();

        [$limit] = func_get_args();
        $by = request()->get('by');

        //$perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery();
        $query->with('userOne.roles');
        $query->select('id', 'name', 'email', 'code', 'phone_number', 'department_id', 'organization_id', 'status');

        if ($by === self::BY_FINE) {
            $query = $query->whereHas('fine');
        }

        //$query = $perService->getConditionByUserLogin($query);
        $data = $query->limit($limit)
            ->get();

        return resSuccessWithinData(new $this->_listResource($data, $this->_resource));
    }

    public function store(array $data): JsonResponse
    {
        if (!empty($data['group_id'])) {
            $month = date('m/Y');
            $group = Group::with('kpiMonth')->find($data['group_id']);
            if (!$group) {
                return resError('Group không tồn tại');
            }
            if (!$group->kpiNearest) {
                $result = ['err' => 'Nhóm kinh doanh chưa có KPI'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }
        // create random User code
        // $data['code'] = RandomHelper::userCode();
        // create User
        $data['password'] = Hash::make($data['password']);
        $record = Staff::query()->create($data);
        $groupService = app()->make(GroupService::class);
        if (!empty($data['group_id'])) {
            $groupService->assignUserToGroup($record->id, $data['group_id']);
        }
        // assign roles for User
        if(isset($data['role_id'])) {
            $role = Role::where('id', $data['role_id'])->first();

            // Sync Role
            $userModel = \App::make('App\Models\User');
            $user = $userModel->where('id', $record->id)->first();
            $user->syncRoles($role);

            // Sync Per vào
            $perOfRole = $role->permissions->pluck('name');
            // $user->syncPermissions($perOfRole);
        }
        // (new StaffService())->permissionToggle($data['department_id']);
        (new ReportService())->incrementQuantity(ReportConstant::QUANTITY_USER);
        return resSuccessWithinData($record);
    }

    public function update(string $id, array $data)
    {
        $data = (new UserService())->updatePassword($data);
        $record = parent::update($id, $data);

        // Nếu tồn tại việc đổi pass thì xóa hết token
        if (isset($data['password'])) {
            \Log::info('eeeeee');
            // Xóa luôn token
            $result = \DB::table('personal_access_tokens')
                                    ->where('tokenable_type', 'App\Models\User')
                                    ->where('tokenable_id', (int)$id)
                                    ->delete();
        }

        // update roles
        if(isset($data['role_id']) && $record != null) {
            $role = Role::where('id', $data['role_id'])->first();
            // $record->syncRoles($role);

            // Sync Role
            $userModel = \App::make('App\Models\User');
            $user = $userModel->where('id', $record->id)->first();

            // Nếu ko có role ý thì  mới sync
            if (!$user->hasRole($role)) {
                // Xóa token đăng nhập
                $user->tokens()->delete();

                // Sync role
                $user->syncRoles($role);

                // Sync Per vào
                $perOfRole = $role->permissions->pluck('name');
                // $user->syncPermissions($perOfRole);
            }
        }
        // (new StaffService())->permissionToggle($data['department_id']);

        if (isset($data['group_id']) && $record != null) {
            $currentMonth = now()->format('m/Y');
            // Tìm hoặc tạo record users_group tháng hiện tại
            $userGroup = \App\Models\UserGroup::firstOrNew([
                'user_id' => $record->id,
                'month'   => $currentMonth,
            ]);
            // Cập nhật group_id mới
            $userGroup->group_id = $data['group_id'];
            // Lấy KPI từ bảng group_kpi_months
            $groupKpi = \App\Models\GroupKpi::where('group_id', $data['group_id'])
                ->orderByRaw("
                                CASE
                                    WHEN month = ? THEN 0
                                    ELSE 1
                                END,
                                STR_TO_DATE(month, '%m/%Y') DESC
                            ", [$currentMonth])
                ->first();
            if ($groupKpi) {
                $userGroup->kpi            = $groupKpi->kpi;
                $userGroup->kpi_sales      = $groupKpi->kpi_sales;
                $userGroup->kpi_services   = $groupKpi->kpi_services;
                $userGroup->kpi_transports = $groupKpi->kpi_transports;
            }
            $userGroup->save();
        }

        return $record;
    }

    /**
     * @param  string  $role
     * @return mixed
     */
    public function getStaffByRole(string $role)
    {
        return Staff::query()->role($role)->select('id', 'name')->get();
    }

    public function getStaffCounselor()
    {
        $userIds = ReportUserTakeCareCustomer::query()->where('status', 1)->pluck('user_id');
        return Staff::query()->select('id', 'name')->find($userIds);
    }
    /**
     * @param  string  $departmentId
     * @param  bool  $isActive
     */
    public function permissionToggle(string $departmentId): void
    {
        $department = Department::query()->findOrFail($departmentId);
        $permissions = $department->getAllPermissions();
        $isActive = $permissions->where('name', RoleConstant::PERMISSION_TAKE_CARE_CUSTOMER)->isNotEmpty();
        $isActiveQuotation = $permissions->where('name', RoleConstant::PERMISSION_QUOTE_CUSTOMER)->isNotEmpty();
        $isActiveOrder = $permissions->where('name', RoleConstant::PERMISSION_ORDER_STAFF)->isNotEmpty();
        $isActiveCounselor = $permissions->where('name', RoleConstant::PERMISSION_CUSTOMER_CONSULTING)->isNotEmpty();
        $isActiveComplain = $permissions->where('name', RoleConstant::PERMISSION_COMPLAIN_STAFF)->isNotEmpty();
        Staff::query()->where('department_id', $departmentId)->each(
            function ($user) use (
                $isActive,
                $isActiveQuotation,
                $isActiveOrder,
                $isActiveCounselor,
                $isActiveComplain,
                $permissions
            ) {
                /** @var ReportUserTakeCareCustomer $report */
                $this->recordIsActive(ReportUserTakeCareCustomer::class, $user->id, $isActive);
                $this->recordIsActive(ReportUserQuotationCustomer::class, $user->id, $isActiveQuotation);
                $this->recordIsActive(ReportUserOrderedCustomer::class, $user->id, $isActiveOrder);
                $this->recordIsActive(ReportUserCounselingCustomer::class, $user->id, $isActiveCounselor);
                $this->recordIsActive(ReportUserComplainCustomer::class, $user->id, $isActiveComplain);
                $user->syncPermissions($permissions->pluck('name')->toArray());
            }
        );
    }

    private function recordIsActive(string $model, string $userId, bool $isActive): void
    {
        $report = (new $model)::query()->firstOrCreate(['user_id' => $userId]);
        $report->status = $isActive;
        $report->save();
    }

    public function getStaffHasPermission($permission, $status = UserConstant::STATUS_ACTIVE)
    {
        return User::query()->permission($permission)->select('id', 'name')
            ->where('reset_staff_allow', true)
            ->where('status', $status)
            ->get();
    }

    public function getStaffByDepartmentTypes($departmentTypes = [], $status = UserConstant::STATUS_ACTIVE)
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.department_id', 'departments.type as department_type'])
            ->join('departments', 'departments.id', 'users.department_id')
            ->whereIn('departments.type', $departmentTypes)
            ->where('users.status', $status)
            ->get()->transform(function ($user) {
                return [
                    'id' => data_get($user, 'id'),
                    'name' => data_get($user, 'name'),
                    'email' => data_get($user, 'email'),
                    'department_id' => data_get($user, 'department_id'),
                    'department_type' => data_get($user, 'department_type'),
                ];
            });
    }

    public function checkStaffByDepartmentTypes($userId, $departmentTypes = [], $status = UserConstant::STATUS_ACTIVE)
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.department_id', 'departments.type as department_type'])
            ->join('departments', 'departments.id', 'users.department_id')
            ->whereIn('departments.type', $departmentTypes)
            ->where('users.status', $status)
            ->where('users.id', $userId)
            ->exists();
        // ->where('users.reset_staff_allow', true) Tạm bỏ câu query này.
    }

    public function getUserGroup(): array
    {
        // Lấy danh sách ID nhân viên kinh doanh còn nhận KH trong tháng hiện tại
        $query = User::query();
        $query = $query->whereExists(function ($query){
                $query->select("users_group.id")
                    ->from('users_group')
                    ->whereRaw('users_group.user_id = users.id')
                    ->where('users_group.is_get_customer', 1)
                    ->where('users_group.month', date('m/Y'));
            })
            ->where('users.status', 1);
        return $query->inRandomOrder()->pluck('id')->toArray();
    }
}
