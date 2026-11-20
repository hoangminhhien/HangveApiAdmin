<?php

namespace ThuongDo\Buyder\src\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;
use ThuongDo\Buyder\src\Constants\UpdatePlatformOrdersConstant;

class UpdatePlatformOrderRequest extends FormRequest
{
    /**
     * Mapping messagePlatform trong $data
     *
     * @var array
     */
    private array $platformMapping = [
        BuyderServiceConstant::SOURCE_TAOBAO,
        BuyderServiceConstant::SOURCE_1688,
    ];

    /**
     * Chuẩn bị dữ liệu trước khi validate
     *
     * @return void
     */
    public function prepareForValidation()
    {
        $this->offsetSet('source', $this->platformMapping[$this->offsetGet('messagePlatform')]);
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'source' => [
                'required',
                Rule::in(BuyderServiceConstant::AVAILABLE_SOURCES)
            ],
            'messageType' => [
                'required',
                Rule::in(UpdatePlatformOrdersConstant::AVAILABLE_MESSAGE_TYPES)
            ],
            'messageBody' => ['required', 'string'],
            'messagePlatform' => ['required', 'numeric']
        ];
    }

    /**
     * Định nghĩa các validation messages khi validation fails
     *
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'source.required' => 'Nguồn đặt hàng là bắt buộc',
            'source.in' => 'Nguồn đặt hàng chưa được hỗ trợ',

            'messageType.required' => 'Message Type là bắt buộc',
            'messageType.in' => 'Message Type chưa được hỗ trợ',

            'messageBody.required' => 'Message Body là bắt buộc',
            'messageBody.string' => 'Message Body phải là dạng chuỗi',

            'messagePlatform.required' => 'Message Platform là bắt buộc',
            'messagePlatform.numeric' => 'Message Platform phải là số hoặc chuỗi số',
        ];
    }
}
