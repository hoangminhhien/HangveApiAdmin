<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class ProductsPipelineStatisticsBuilders extends Builder
{
    /**
     * Tìm kiếm bản ghi theo nguồn thực hiện hành động
     *
     * @param string $source
     * @return $this
     */
    public function whereSource(string $source): self
    {
        return $this->where('source', $source);
    }

    /**
     * Tìm kiếm bản ghi theo hành động (tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng)
     *
     * @param string $action
     * @return $this
     */
    public function whereAction(string $action): self
    {
        return $this->where('action', $action);
    }

    /**
     * Tìm kiếm bản ghi theo nền tảng TMĐT
     *
     * @param string $platform
     * @return $this
     */
    public function wherePlatform(string $platform): self
    {
        return $this->where('platform', $platform);
    }

    /**
     * Tìm kiếm bản ghi theo từ khóa
     *
     * @param string $keyword
     * @return $this
     */
    public function whereKeyword(string $keyword): self
    {
        return $this->where('keyword', $keyword);
    }

    /**
     * Tìm kiếm bản ghi theo từ khóa
     *
     * @param string $keyword
     * @return $this
     */
    public function whereKeywordLike(string $keyword): self
    {
        return $this->where('keyword', 'LIKE', "%{$keyword}%");
    }

    /**
     * Tìm kiếm bản ghi theo địa chỉ IP
     *
     * @param string $ip
     * @return $this
     */
    public function whereIp(string $ip): self
    {
        return $this->where('ip_address', $ip);
    }

    /**
     * Tìm kiếm bản ghi theo thành phố
     *
     * @param string $city
     * @return $this
     */
    public function whereCity(string $city): self
    {
        return $this->where('city', $city);
    }

    /**
     * Tìm kiếm bản ghi theo id khách hàng
     *
     * @param string|null $customerId
     * @return $this
     */
    public function whereCustomerId(string $customerId): self
    {
        return $this->where('customer_id', $customerId);
    }

    /**
     * Tìm kiếm bản ghi theo mã khách hàng
     *
     * @param string $customerCode
     * @return $this
     */
    public function whereCustomerCode(string $customerCode): self
    {
        return $this->where('customer_code', $customerCode);
    }

    /**
     * Tìm kiếm bản ghi theo ngày trong tuần
     *
     * @param int $week
     * @return $this
     */
    public function whereDay(int $day): self
    {
        return $this->where('day', $day);
    }

    /**
     * Tìm kiếm bản ghi theo tuần trong tháng
     *
     * @param int $week
     * @return $this
     */
    public function whereWeek(int $week): self
    {
        return $this->where('week', $week);
    }

    /**
     * Tìm kiếm bản ghi theo tháng trong năm
     *
     * @param int $month
     * @return $this
     */
    public function whereMonth(int $month): self
    {
        return $this->where('month', $month);
    }

    /**
     * Tìm kiếm bản ghi theo năm
     *
     * @param int $year
     * @return $this
     */
    public function whereYear(int $year): self
    {
        return $this->where('year', $year);
    }
}
