<?php

namespace App\Http\Requests\Package;

use App\Constants\PackageConstant;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusPackCancelRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'packages_cancel' => 'required|array',
            'packages_cancel.*' => 'required|exists:order_package_cancels,id',
            "status" => 'required|in:' . implode(',', array_keys(PackageConstant::STATUSES_PACKAGE_CANCEL))
        ];
    }

    public function attributes()
    {
        return [
            'packages_cancel' => 'Kiện hàng',
            'packages_cancel.*' => 'Kiện hàng',
        ];
    }
}
