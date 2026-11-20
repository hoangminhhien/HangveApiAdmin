<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Jobs\PushNotificationToAppJob;
use Illuminate\Support\Facades\Log;

class FcmTokenService extends AbstractBaseService
{
    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function selectField($query)
    {
        return $query->select('id', 'user_id', 'token', 'device_id');
    }

    public function sendMessageForApp($params, $title, $content, $data = [])
    {
        if (!empty($params['user_id'])) {
            $fcmTokens = $this->_model->query()->where('user_id', $params['user_id'])->get();
            // Log::info('FCM TOKEN customer_id ' . $params['user_id'] .' data ' . json_encode($fcmTokens));
        }
        if (!empty($fcmTokens)) {
            foreach ($fcmTokens as $fcm) {
                $fcmToken = $fcm->token;
                $job = new PushNotificationToAppJob($fcmToken, $title, $content, $data);
                dispatch($job)->onQueue('sent-noti-app');
                // Log::info('Đẩy FcmTokenService');
            }
        }
    }
}
