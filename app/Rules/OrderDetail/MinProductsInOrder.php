<?php

namespace App\Rules\OrderDetail;

use Illuminate\Contracts\Validation\Rule;

class MinProductsInOrder implements Rule
{
    protected $minQuantity = 1;
    protected $isUpdate    = false;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($isUpdate = false)
    {
        $this->isUpdate = $isUpdate;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $value = collect($value);

        if ($this->isUpdate) {
            $sumQuantities = $value->sum('modifies.quantity');
        } else {
            $sumQuantities = $value->sum('quantity');
        }

        return $sumQuantities >= $this->minQuantity;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Đơn hàng không được ít hơn ' . $this->minQuantity . ' sản phẩm';
    }
}
