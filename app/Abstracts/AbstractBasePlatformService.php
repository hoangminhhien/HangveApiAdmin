<?php

namespace App\Abstracts;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;

class AbstractBasePlatformService
{
    protected string $url;
    protected string $api_token;
    protected string $uuId;
    function __construct(){

    }

    /**
     * @throws GuzzleException
     */
    public function curl1($data, $url, $method)
    {
        try {
            $guzzle = new Guzzle;
            $data['api_provider'] = 'x_port';
            $options['json'] = $data;
            $options['headers'] = $this->setHeaderCurl(json_encode($data));
            $request = $guzzle->request($method, $url, $options);
            return json_decode($request->getBody());
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    public function curl($data, $url, $method)
    {
        try {
            $data['api_provider'] = 'x_port';
            $data_string = json_encode($data);
            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                $this->setHeaderCurl($data_string)
            );
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result, false);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function setHeaderCurl($data_string = null): array
    {
        /*return [
            'Content-Type' => 'application/json',
            'Content-Length' => $data_string ? strlen($data_string) : 0,
            'X-Token-Auth' => $this->api_token,
        ];*/

        return [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
            'X-Token-Auth: '.$this->api_token,
        ];
    }

    public function generateRandomString($length = 9): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function generateRandom($length = 13): string
    {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
