<?php

namespace App\Traits\Builders;

use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

trait PlatformSourceTrait
{
    /**
     * Lấy các record có source là Taobao
     *
     * @return $this
     */
    public function sourceTaobao(): self
    {
        return $this->where('source', BuyderServiceConstant::SOURCE_TAOBAO);
    }

    /**
     * Lấy các record có source là 1688
     *
     * @return $this
     */
    public function source1688(): self
    {
        return $this->where('source', BuyderServiceConstant::SOURCE_1688);
    }

    /**
     * Lấy các record có 'source' tương ứng
     *
     * @param string $source
     * @return $this
     */
    public function whereSource(string $source): self
    {
        return $this->where('source', $source);
    }
}
