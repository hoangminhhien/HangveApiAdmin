<?php

namespace App\Http\Requests;

use App\Constants\OrderConstant;
use App\Constants\OrderCostRealConstant;
use App\Models\Order;
use App\Models\OrderCostReal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OrderCostRealRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->route('id');

        return [
            'order_code'     => 'required|exists:orders,code',
            'order_id'       => [
                'nullable',
                'exists:orders,id',
                //$id ? 'unique:order_cost_real,order_id,'. $id : 'unique:order_cost_real,order_id'
            ],
            'order_code_po'  => 'nullable',
            'file'           => 'nullable|file|max:8192',
            'cost_old'       => 'required|numeric|min:0',
            'cost_new'       => 'required|numeric|min:0',
            'status'         => 'nullable|in:'. implode(',', OrderCostRealConstant::getStatusKeys()),
            'note'           => 'nullable',
            'files'          => 'required|array'
        ];
    }

    public function messages()
    {
        return [
            'order_code.required' => 'Mã đơn không được để trống',
            'order_code.exists' => 'Mã đơn không đúng',
            'order_id.exists' => 'Mã đơn không đúng',
            'order_id.unique' => 'Mã đơn đã được tạo yêu cầu',
            'file.file' => 'Tập tin phải là dạng file',
            'file.max' => 'Dung lượng tập tin không được quá 8Mb',
            'cost_old.required' => 'Tiền đặt hàng cũ không được để trống',
            'cost_old.numeric' => 'Tiền đặt hàng cũ phải là dạng số',
            'cost_old.min' => 'Tiền đặt hàng cũ nhỏ nhất là 0',
            'cost_new.required' => 'Tiền đặt hàng mới không được để trống',
            'cost_new.numeric' => 'Tiền đặt hàng mới phải là dạng số',
            'cost_new.min' => 'Tiền đặt hàng mới nhỏ nhất là 0',
        ];
    }

    /**
     * Chuẩn bị dữ liệu trước validation
     *
     * @return void
     */
    public function prepareForValidation(): void
    {
        $orderCode = $this->input('order_code');

        if (!empty($orderCode)) {
            $order = Order::query()->with('staffOrder')->where('code', $orderCode)->first();
            if ($order) {
                $this->merge([
                    'order_id'         => $order->id,
                    'order_code_po'    => $order->code_po,
                    'staff_order_id'   => $order->staff_order_id,
                    'staff_order_name' => $order->staffOrder->name ?? '',
                ]);
            }
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $id  = $this->route('id');

            list($hasPer, $isAdmin) = $this->checkUserHasPermission();
            if (!$hasPer) {
                return $validator->errors()->add('order_code', 'Bạn không có quyền thực hiện hành động này');
            }

            $orderCode = request()->input('order_code');
            if (!empty($orderCode)) {
                if (!$id) {
                    $itemByCode = OrderCostReal::query()->where([
                        ['order_code', $orderCode], ['status', OrderCostRealConstant::STATUS_WAITING]
                    ])->exists();
                    if ($itemByCode) {
                        return $validator->errors()->add('order_code', 'Mã đơn hàng đã được tạo yêu cầu!');
                    }
                }

                $order = Order::query()->select('id', 'code', 'staff_order_id')
                    ->where('code', $orderCode)
                    ->first();

                if (!$order) {
                    return $validator->errors()->add('order_code', 'Mã đơn hàng không đúng!');
                }

                if ($order->staff_order_id != Auth::id() && !$isAdmin) {
                    $msg = $id ? 'Bạn không có quyền sửa yêu cầu với mã đơn này!' :
                        'Bạn không có quyền tạo yêu cầu với mã đơn này!';
                    $validator->errors()->add('order_code', $msg);
                }
            }
        });
    }

    public function checkUserHasPermission()
    {
        $hasPer  = false;
        $isAdmin = false;

        $roleHasPermission = ['admin', 'nhan-vien-dat-hang'];
        foreach ($roleHasPermission as $role) {
            if (auth()->user()->hasRoleName($role)) {
                $hasPer = true;
                if ($role == 'admin') {
                    $isAdmin = true;
                }
            }
        }

        return [$hasPer, $isAdmin];
    }
}
