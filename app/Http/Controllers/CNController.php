<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderPackage;
use App\Constants\PackageConstant;
use App\Models\LogWebhook;

class CNController extends Controller
{
    public function writeLog($params)
    {
        $logWebhookModel = \App::make('App\Models\LogWebhook');
        unset($params['token2000a']);
        $data_log = [
            'direction'   => LogWebhook::LOGWEBHOOK_DIRECTION_IN,
            'log_name'    => isset($params['event_name']) ? $params['event_name'] : 'no-name',
            'description' => 'CNController@cnSyncData',
            'body'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'from_ip'     => request()->ip()
        ];
        $logWebhookModel->create($data_log);
    }
    
    public function cnSyncData(Request $request)
    {

        $params = $request->only('origin_code', 'total_weight', 'total_volume', 'china_shipping_cost');
        $this->writeLog($params);

        // \Log::info('cnSyncData : ' . request()->ip() . ' - ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        try {
            \DB::beginTransaction();
            $code = data_get($params, 'origin_code'); // Mã vận đơn gốc
            $totalWeight = data_get($params, 'total_weight');
            $totalVolume = data_get($params, 'total_volume');
            $chinaShippingCost = data_get($params, 'china_shipping_cost');
            $orderPackage = OrderPackage::where('bill_code', $code)->first();
            if($orderPackage) {
                $webhook    = \App::make('App\Http\Controllers\WebhookController');
                $data = [
                    'length' => $orderPackage->length,
                    'width' => $orderPackage->width,
                    'height' => $orderPackage->height,
                    'package_number' => $orderPackage->package_number,
                    'volumn_enter' => $totalVolume,
                    'weight' => $totalWeight,
                ];
                $dataCal = $webhook->calFeeHook($orderPackage, $data);
                $dataCal['china_shipping_cost'] = $chinaShippingCost;
                // $dataCal['status'] = PackageConstant::STATUS_IN_CHINA;
                $orderPackage->update($dataCal);
            }else {
                return resError('Không tìm thấy kiện trên hệ thống Hangve');
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }
}
