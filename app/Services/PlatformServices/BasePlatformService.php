<?php

namespace App\Services\PlatformServices;

use App\Interfaces\BasePlatformInterface;

class BasePlatformService
{
    protected BasePlatformInterface $baseBuyer;

    public function __construct(BasePlatformInterface $baseBuyer)
    {
        $this->baseBuyer = $baseBuyer;

    }

    public function previewOrderPlatform(array $request)
    {
        return $this->baseBuyer->previewOrderPlatform($request);
    }

    public function createOrderPlatform(array $request)
    {
        return $this->baseBuyer->createOrderPlatform($request);
    }

    public function createManyOrderPlatform(array $request)
    {
        return $this->baseBuyer->createManyOrderPlatform($request);
    }

    public function detailOrderPlatform(array $request)
    {
        return $this->baseBuyer->detailOrderPlatform($request);
    }

    public function cancelOrderPlatform(array $request)
    {
        return $this->baseBuyer->cancelOrderPlatform($request);
    }

    public function paymentOrderPlatform(array $request)
    {
        return $this->baseBuyer->paymentOrderPlatform($request);
    }

    public function connectChat(array $request)
    {
        return $this->baseBuyer->connectChat($request);
    }

    public function getMpIdSkuIdTaoBao(array $request)
    {
        return $this->baseBuyer->getMpIdSkuIdTaoBao($request);
    }

    public function getProductPlatformDetail(array $request)
    {
        return $this->baseBuyer->getProductPlatformDetail($request);
    }

    public function createOrUpdateProductDetail($request)
    {
        return $this->baseBuyer->createOrUpdateProductDetail($request);
    }
}
