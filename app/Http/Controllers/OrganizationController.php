<?php

namespace App\Http\Controllers;

use App\Interfaces\Validation\StoreValidationInterface;
use App\Interfaces\Validation\UpdateValidationInterface;
use App\Models\Staff;
use App\Services\OrganizationService;
use App\Services\UserService;
use App\Services\PermissionService;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Class OrganizationController
 * @package App\Http\Controllers
 */
class OrganizationController extends Controller implements StoreValidationInterface, UpdateValidationInterface
{
      /**
     * DashboardController constructor.
     * @param  OrganizationService $service
     */
    public function __construct(OrganizationService $service)
    {
        $this->_service = $service;
    }

    public function storeMessage(): ?array
    {
       return [];
    }

    public function storeRequest(): array
    {
        return [
            'name' => 'required|max:255',
            'short_name' => 'required|max:255',
            'email' => 'required|max:255|email|unique:organizations,email',
            'phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|max:10|unique:organizations,phone_number',
            'domain' => 'required|unique:organizations,domain,NULL,id,deleted_at,NULL|url',
            'domain_mobile' => 'required|unique:organizations,domain_mobile,NULL,id,deleted_at,NULL|url',
            'address_ip' => 'ip'
        ];
    }

    public function updateMessage(): array
    {
        return [];
    }

    public function updateRequest(string $id): array
    {
        return [
            'name' => 'required|max:255',
            'short_name' => 'required|max:255',
            'email' => [
                'required',
                'max:255',
                'email',
                Rule::unique('organizations')->ignore($id),
            ],
            'phone_number' => [
                'required',
                'regex:/^([0-9\s\-\+\(\)]*)$/',
                'max:10',
                Rule::unique('organizations')->ignore($id),
            ],
            'domain' => [
                'required',
                'unique:organizations,domain,'.$id.',id,deleted_at,NULL',
                'url'
            ],
            'domain_mobile' => [
                'required',
                'unique:organizations,domain_mobile,'.$id.',id,deleted_at,NULL',
                'url'
            ],
            'address_ip' => [
                'ip',
            ],
        ];
    }

    public function createAdmin(Request $request, $orgId)
    {
        $request->validate([
            'email' => 'required|unique:users,email',
            'password' => 'required'
        ]);
        $organization = Organization::findOrFail($orgId);
        \DB::beginTransaction();
        try {
            $params = $request->all();
            $user = (new UserService())->store([
                'name' => 'Admin',
                'email' => data_get($params, 'email'),
                'password' => Hash::make(data_get($params, 'password')),
                'organization_id' => data_get($organization, 'id')
            ]);
            // Check role Admin nếu chưa có thì tạo
            $roleNameAdmin = 'admin.'. data_get($organization, 'id');
            $role = Role::firstOrCreate([
                'name' => $roleNameAdmin,
                'organization_id' => data_get($organization, 'id')

            ], [
                'name' => $roleNameAdmin,
                'display_name' => 'Admin',
                'guard_name'      => 'api',
                'organization_id' => data_get($organization, 'id'),
                'type'            => 1 // Hệ thống tạo
            ]);
            $pers = (new PermissionService())->getAllPermission();
            // Gán per cho role vừa được tạo ra
            $role->syncPermissions($pers);
            if($user){
                $user->syncPermissions($pers);
                $user->assignRole($role);
            }
            \DB::commit();
            // all good
            return resSuccessWithinData($user);
        } catch (\Exception $e) {
            \Log::error($e);
            \DB::rollback();
            return resError();
        }
    }

    public function initDataStart(Request $request)
    {
        $params = $request->all();

        $orgModel = \App::make('App\Models\Organization');

        $orgDB = $orgModel->where('email', $params['org_email'])->first();

        if (is_null($orgDB)) {
            dd("Dừng");
        }

        $departmentModel = \App::make('App\Models\Department');
        $departmentSiteMain = $departmentModel->where('organization_id', 1)
                                            ->withoutGlobalScopes()
                                            ->whereNotNull('type')
                                            ->orderBy('id', 'asc')
                                            ->get();
        if (count($departmentSiteMain) > 0) {
            $count = 0;
            foreach ($departmentSiteMain as $key => $item) {
                $departmentNewDB = $departmentModel->withoutGlobalScopes()
                                                    ->where('organization_id', $orgDB->id)
                                                    ->where('type', $item['type'])
                                                    ->first();
                if (is_null($departmentNewDB)) {
                    $data_department_new = [
                        'name'            => $item['name'],
                        'organization_id' => $orgDB->id,
                        'type'            => $item['type']
                    ];
                    // $departmentNewDB = $departmentModel->create($data_department_new);
                    $count += 1;
                }
            }

            dump($count);
        }

        // Xử lý phần config
        $configModel = \App::make('App\Models\Config');

        $configSiteMain = $configModel->where('organization_id', 1)
                                            ->withoutGlobalScopes()
                                            ->orderBy('id', 'asc')
                                            ->get();

        if (count($configSiteMain) > 0) {
            $count = 0;
            foreach ($configSiteMain as $key => $item) {
                $configNewDB = $configModel->withoutGlobalScopes()
                                                    ->where('organization_id', $orgDB->id)
                                                    ->where('key', $item['key'])
                                                    ->first();
                if (is_null($configNewDB)) {
                    $data_config_new = [
                        'organization_id' => (int)$orgDB->id,
                        'category'        => $item['category'],
                        'name'            => $item['name'],
                        'key'             => $item['key'],
                        'value'           => $item['value'],
                        'is_publish'      => $item['is_publish'],
                    ];
                    // $configNewDB = $configModel->create($data_config_new);
                    $count += 1;
                }
            }

            dump($count);
        }
    }
}
