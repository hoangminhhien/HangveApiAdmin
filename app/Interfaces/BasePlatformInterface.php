<?php

namespace App\Interfaces;

interface BasePlatformInterface
{
    public function previewOrderPlatform(array $request, string $method = 'POST');
    public function createOrderPlatform(array $request, string $method = 'POST');
    public function createManyOrderPlatform(array $request, string $method = 'POST');
    public function detailOrderPlatform(array $request, string $method = 'POST');
    public function cancelOrderPlatform(array $request, string $method = 'POST');
    public function paymentOrderPlatform(array $request, string $method = 'POST');
    public function connectChat(array $request, string $method = 'POST');
    public function getMpIdSkuIdTaoBao(array $request, string $method = 'POST');
    public function getProductPlatformDetail(array $request, string $method = 'POST');

    public function createOrUpdateProductDetail($request);

}
