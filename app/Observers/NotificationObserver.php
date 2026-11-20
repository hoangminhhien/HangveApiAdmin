<?php

namespace App\Observers;

use App\Models\Notification;

class NotificationObserver
{
    public function created(Notification $notification)
    {
        // \Log::info('Mới tạo Noti: ' . $notification->id_number);
    }
}
