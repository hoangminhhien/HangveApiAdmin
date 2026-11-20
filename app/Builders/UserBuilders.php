<?php

namespace App\Builders;

use App\Constants\UserConstant;
use Illuminate\Database\Eloquent\Builder;

class UserBuilders extends Builder
{
    /**
     * Tìm kiếm bản ghi có trạng thái active
     *
     * @return $this
     */
    public function whereActive(): self
    {
        return $this->where('status', UserConstant::STATUS_ACTIVE);
    }

    /**
     * Tìm kiếm bản ghi theo email
     *
     * @param string $email
     * @return $this
     */
    public function whereEmail(string $email): self
    {
        return $this->where('email', $email);
    }

    /**
     * Tìm kiếm bản ghi theo email
     *
     * @param string $email
     * @return $this
     */
    public function whereLikeEmail(string $email): self
    {
        return $this->where('email', 'LIKE', "%{$email}%");
    }

    /**
     * Tìm kiếm bản ghi theo số điện thoại
     *
     * @param string $phoneNumber
     * @return $this
     */
    public function wherePhone(string $phoneNumber): self
    {
        return $this->where('phone_number', $phoneNumber);
    }

    /**
     * Tìm kiếm bản ghi theo số điện thoại
     *
     * @param string $phoneNumber
     * @return $this
     */
    public function whereLikePhone(string $phoneNumber): self
    {
        return $this->where('phone_number', 'LIKE', "%{$phoneNumber}%");
    }

    /**
     * Tìm kiếm bản ghi theo số điện thoại
     *
     * @param string $phoneNumber
     * @return $this
     */
    public function orWhereLikePhone(string $phoneNumber): self
    {
        return $this->orWhere('phone_number', 'LIKE', "%{$phoneNumber}%");
    }
}
