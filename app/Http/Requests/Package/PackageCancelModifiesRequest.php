<?php

namespace App\Http\Requests\Package;

use App\Constants\PackageConstant;
use Illuminate\Foundation\Http\FormRequest;

class PackageCancelModifiesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'status' => 'in:' . implode(',', array_keys(PackageConstant::STATUSES_PACKAGE_CANCEL)),
        ];
    }

    public function attributes()
    {
        return [
            'status' => 'trạng thái',
        ];
    }
}
