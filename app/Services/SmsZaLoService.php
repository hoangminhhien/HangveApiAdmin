<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsZaLoService
{
    protected string $url;
    protected array $errors;
    protected array $success;
    protected string $access_token;
    protected string $template_id;
    protected array $header;
    protected string $refresh_token = '';
    protected array $request;
    protected string $code_verifier;
    protected string $url_access_token = SmsService::URL_ACCESS_TOKEN_ZALO;
    protected string $url_template = SmsService::URL_TEMPLATE_ZALO;
    protected string $secret_key;
    protected $app_id;
    /**
     * @var mixed
     */
    protected $code;

    function __construct($request)
    {
        $this->request = $request;
        $this->template_id = $request['template_id'] ?? '';
        $this->secret_key = SmsService::SECRET_KEY;
        $this->refresh_token = "";
        $this->app_id = SmsService::APP_ID;
        $this->code = env('ZALO_OAUTH_CODE', '');
    }

    public function getAccessToken(): SmsZaLoService
    {
        $access_token_oa = Cache::get('access_token_oa', '');
        $refresh_token_oa = Cache::get('refresh_token_oa', '');
        if (!$access_token_oa) {
            $header = [
                'Content-Type: application/x-www-form-urlencoded',
                'secret_key: ' . $this->secret_key
            ];
            $this->setHeaderCurl($header);
            $this->refresh_token = $refresh_token_oa;
            $this->getAccessTokenByRefreshToKen();
        } else {
            $this->access_token = $access_token_oa;
        }
        return $this;
    }
    private function getAccessTokenByRefreshToKen()
    {
        $payload = 'refresh_token='.$this->refresh_token.'&app_id='.$this->app_id.'&grant_type=refresh_token';
        $response = $this->curl($payload, $this->url_access_token, 'POST');
        if ($response ) {
            if (empty($response->error_name)) {
                $this->access_token = $response->access_token;
                Cache::put('access_token_oa', $response->access_token, now()->addHours(20));
                Cache::put('refresh_token_oa', $response->refresh_token, now()->addDays(85));
                $this->success[] = 'Lấy access token thành công!';
            } else {
                $this->errors[] = $response->error_description ?? '';
            }
        }
    }

    private function getAccessTokenByAuthorizationCode()
    {
        $this->generateCodeVerify();
        $payload = 'code='.$this->code.'&app_id='.$this->app_id.'&grant_type=authorization_code&code_verifier='.$this->code_verifier;
        $response = $this->curl($payload, $this->url_access_token, 'POST');
        if ($response ) {
            if (empty($response->error_name)) {
                $this->access_token = $response->access_token;
                $this->refresh_token = $response->refresh_token;
                Cache::put('access_token_oa', $response->access_token, now()->addHours(24));
                Cache::put('refresh_token_oa', $response->refresh_token, now()->addDays(85));
                $this->success[] = 'Lấy access token thành công!';
            } else {
                $this->errors[] = $response->error_description ?? '';
            }
        }
    }

    public function validate(): SmsZaLoService
    {
        return $this;
    }

    public function sent(): SmsZaLoService
    {
        if (!empty($this->errors)) {
            return $this;
        }
        $header = [
            'Content-Type: application/json',
            'access_token: ' . $this->access_token
        ];
        $data = [
            "phone" => $this->request['phone_number'],
            "template_id" => $this->template_id,
            "template_data" => $this->request['template_data'],
            "tracking_id" => rand(1, 9999999999)
        ];
        $this->setHeaderCurl($header);
        $response = $this->curl(json_encode($data), $this->url_template, 'POST');
        if ($response) {
            if (empty($response->error)) {
                $this->success[] = 'Gửi OTP thành công!';
            } else {
                $this->errors[] = $response->message ?? '';
                Log::info('SMS ZALO error code : ' . $response->error . ' message : ' . $response->message);
            }
        }
        return $this;
    }

    public function getResult(): array
    {
        if (!empty($this->errors)) {
            return [
                'code' => 500,
                'message' => implode(', ' , $this->errors),
                'data' => []
            ];
        }
        return [
            'code' => 200,
            'message' => implode(', ' , $this->success),
            'data' => []
        ];
    }

    private function generateCodeVerify()
    {
        $this->code_verifier = generateRandomString(43);
    }

    private function genCodeChallenge()
    {

    }

    private function curl($data, $url, $method)
    {
        if (empty($data)) {
            $this->errors[] = 'Data không được để trống!';
            return false;
        }
        if (empty($url)) {
            $this->errors[] = 'Url không được để trống!';
            return false;
        }
        if (empty($method)) {
            $this->errors[] = 'Method không được để trống!';
            return false;
        }
        try {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                $this->header
            );
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result, false);
        } catch (\Throwable $e) {
            return $this->errors[] = $e->getMessage();
        }
    }

    private function setHeaderCurl($header)
    {
        $this->header = $header;
    }
}
