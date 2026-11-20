<?php

namespace App\Services;

use App\Constants\CustomerConstant;
use App\Constants\RoleConstant;
use App\Constants\UserConstant;
use App\Helpers\RandomHelper;
use App\Models\Department;
use App\Models\Organization;
use App\Models\ReportLevel;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Class OrganizationService
 * @package App\Services
 */
class OrganizationService extends BaseService
{

    public function store(array $data): JsonResponse
    {
        $res = DB::Transaction(function () use ($data) {
            /** @var Organization $organization */
            $organizationLast = Organization::where('code', '!=', '')->orderBy('created_at', 'desc')->first();
            if($organizationLast){
                $intCode = explode('_', data_get($organizationLast, 'code'))[1];
                $data['code'] = 'TC_'.str_pad($intCode + 1, 4, "0", STR_PAD_LEFT);
            }
            $organization = Organization::query()->create($data);
            return $organization;
        });
        return resSuccessWithinData($res);
    }

    /**
     * @param string $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy(string $id): JsonResponse
    {
        $organization = Organization::findOrFail($id);
        if($organization->customers()->count() > 0){
            return resError('Cty đã có khách hàng, không được phép xóa!');
        }
        $departments = Department::query()->where('organization_id', $id)->get();
        if (count($departments) > 0) {
            return resError(trans('organization.organization_has_department'));
        }
        if(Organization::where('id', $id)->where('code', 'TC_0001')->first()){
            return resError('Không thể xóa cty này!');
        }
        return parent::destroy($id);
    }

    public function addReportLevel(string $organization)
    {
        $data = [];
        foreach (CustomerConstant::CUSTOMER_LEVEL as $key => $name) {
            $data[] = [
                // 'id' => getUuid(),
                'organization_id' => $organization,
                'level' => $key,
                'name' => $name
            ];
        }
        ReportLevel::query()->insert($data);
    }

    public function getOrganizationByDomain(string $domain): string
    {
        return optional(Organization::query()->where('domain', $domain)->first())->id ?? $this->getOrganizationDefault(
            )->id;
    }

    public function getOrganizationDefault(): string
    {
        return $this->getOrganizationByDomain('*');
    }
}
