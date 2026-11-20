<?php

namespace ThuongDo\Buyder\src\Services;

use GuzzleHttp\Client as Guzzle;

class ServiceRequest implements BaseService
{
    /**
     * Instance của guzzle để thực hiện HTTP request
     *
     * @var Guzzle
     */
    protected Guzzle $guzzle;

    /**
     * Khởi tạo new instance
     */
    public function __construct()
    {
        $this->guzzle = new Guzzle();
    }

    /**
     * Trung tâm thực hiện HTTP Request, sử dụng Guzzle
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return mixed
     */
    public function handleRequest(string $method, string $url, array $options): array
    {
        $rawResult = $this->guzzle->{$method}($url, $options)->getBody()->getContents();
        return json_decode($rawResult, true);
    }

    /**
     * Thực hiện HTTP POST Request
     *
     * @param string $url
     * @param array $options
     * @return mixed
     */
    public function handlePost(string $url, array $options): array
    {
       return $this->handleRequest('post', $url, $options);
    }

    /**
     * Thực hiện HTTP GET Request
     *
     * @param string $url
     * @param array $options
     * @return mixed
     */
    public function handleGet(string $url, array $options): array
    {
        return $this->handleRequest('get', $url, $options);
    }
}
