<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PushNotificationToAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $token;
    protected $title;
    protected $content;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct($token, $title, $content, $data)
    {
        $this->token = $token;
        $this->title = $title;
        $this->content = $content;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $apiUrl = 'https://fcm.googleapis.com/v1/projects/hang-ve/messages:send';

            $headers = [
                'Authorization: Bearer ' . $this->getGoogleAccessToken(),
                'Content-Type: application/json'
            ];
            $notification_tray = [
                'title' => $this->title,
                'body' => $this->content,
            ];
            $message = [
                'message' => [
                    "token" => $this->token,
                    'notification' => $notification_tray,
                    "data" => $this->data
                ],
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

            $result = curl_exec($ch);

            if ($result === FALSE) {
                die('Curl failed: ' . curl_error($ch));
            }
            curl_close($ch);
            $res = json_decode($result, false);
            // Log::info('PushNotificationToAppJob Success ' . json_encode($res) . 'data ' . json_encode($this->data));
            return $res;
        } catch (\Exception $exception) {
            // Log::info('PushNotificationToAppJob Error ' . $exception->getMessage());
            return $exception->getMessage();
        }

    }

    private function getGoogleAccessToken()
    {
        $key = 'access_token_sent_notification_';
        $access_token = Cache::get($key, '');
        if (!$access_token) {
            $credentialsFilePath = storage_path('hang-ve-firebase-adminsdk-xravp-171c01b36f.json');
            $client = new \Google_Client();
            $client->setAuthConfig($credentialsFilePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            $access_token = $token['access_token'];
            Cache::put($key, $access_token, now()->addMinutes(60));
        }

        return $access_token;
    }
}
