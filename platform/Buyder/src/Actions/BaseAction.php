<?php

namespace ThuongDo\Buyder\src\Actions;

use GuzzleHttp\Client as Guzzle;
use ThuongDo\Buyder\src\Services\BaseService;
use ThuongDo\Buyder\src\Services\ServiceRequest;
use ThuongDo\Buyder\src\Traits\ActionTrait;

/**
 * Mọi actions đều phải extends abstract BaseAction này
 */
abstract class BaseAction
{
    use ActionTrait;

    /**
     * Instance của guzzle để thực hiện HTTP request
     *
     * @var Guzzle
     */
    protected Guzzle $guzzle;

    /**
     * Instance của ServiceRequest, dùng để thực hiện HTTP Request
     *
     * @var BaseAction
     */
    protected BaseService $serviceRequest;

    /**
     * Default options gửi kèm request tới RapidApi
     *
     * @var array
     */
    protected array $rapidApiOptions;

    /**
     * Default options gửi kèm request tới Buyder khi dùng gói Taobao
     *
     * @var array
     */
    protected array $buyderTaobaoOptions;

    /**
     * Default options gửi kèm request tới Buyder khi dùng gói 1688
     *
     * @var array
     */
    protected array $buyder1688Options;

    /**
     * Khởi tạo new instance
     */
    public function __construct()
    {
        $this->guzzle = new Guzzle();

        $this->serviceRequest = app()->make(ServiceRequest::class);

        $this->rapidApiOptions = [
            'headers' => ['X-RapidAPI-Key' => config('partner.rapidApi.tokenAuth')],
            'verify' => false,
            'http_errors' => true,
        ];

        $this->buyderTaobaoOptions = [
            'headers' => [
                'X-Token-Auth' => config('partner.buyder.tokenAuth'),
            ],
        ];

        $this->buyder1688Options = [
            'headers' => [
                'X-Token-Auth' => config('partner.buyder.tokenAuth1688'),
            ],
        ];
    }

    /**
     * Chứa logic thực thi chính của action
     *
     * @param array $data
     * @return mixed
     */
    abstract public function handle(?array $params);
}
