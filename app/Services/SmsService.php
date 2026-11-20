<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SoapClient;

class SmsService
{
    /**
     * @throws BindingResolutionException
     */
    const URL_ACCESS_TOKEN_ZALO = 'https://oauth.zaloapp.com/v4/oa/access_token';
    const URL_TEMPLATE_ZALO = 'https://business.openapi.zalo.me/message/template';
    const APP_ID = 2200095799446617055;
    const SECRET_KEY = 'WmCjaI4z0WG7YpP7OO24';
    public static function send(array $data): bool
    {
        try {
            $min = 10000000000000000;
            $max = 99999999999999999;
            $phone_number = data_get($data, 'phone_number', '');
            $phone_number = self::convertPhoneNumber($phone_number);
            $log_type = data_get($data, 'log_type', 0);
            $user_code = data_get($data, 'user_code', '');
            $message = data_get($data, 'message', '');
            $params = [
                "RequestID" => rand($min,$max),
                "UserID" => $phone_number,
                "Message" => $message,
                "PartnerUsername" => 'THUONGDO',
                "PartnerPassword" => 'THUONG1@D!@#123'
            ];

            $client = new SoapClient('http://115.84.179.61:8888/doitac.asmx?wsdl');
            $response = $client->DoiTac_To_Nisco($params)->DoiTac_To_NiscoResult;
            $smsLogService = app()->make(SmsLogService::class);
            $smsLogService->store([
                'phone_number' => $phone_number,
                'content' => $message,
                'user_code' => $user_code,
                'log_type' => $log_type,
                'sms_source' => SmsLog::SMS_SOURCE_PHONE,
                'response_status' => $response
            ]);
            $data['phone_number'] = $phone_number;
            Log::info('Data SMS ' . json_encode($data) . ' Data Response ' . json_encode($response));
            return $response;
        } catch (\Exception $exception) {
            Log::info('Data SMS Error ' . json_encode($exception->getMessage()));
            return false;
        }
    }

    private static function convertPhoneNumber($phone_number): string
    {
        return '84'.ltrim($phone_number, "0");
    }

    public static function sendZaLoOTP(array $params): bool
    {
        $params['phone_number'] = self::convertPhoneNumber($params['phone_number'] ?? 0);
        $response = (new SmsZaLoService($params))
            ->getAccessToken()
            ->validate()
            ->sent()
            ->getResult();
        if ($response['code'] == 500) {
            Log::info('SMS ZaLo Error ' . $response['message'] . ' phone ' . $params['phone_number']);
            return 0;
        } else {
            Log::info('SMS ZaLo Success!' . ' phone ' . $params['phone_number']);
            SmsLog::query()->create([
                'phone_number' => $params['phone_number'] ?? 0,
                'content' => $params['message'] ?? '',
                'user_code' => $params['user_code'] ?? '',
                'log_type' => $params['log_type'] ?? 0,
                'sms_source' => SmsLog::SMS_SOURCE_ZALO,
                'response_status' => 0
            ]);
            return 1;
        }
    }
}
