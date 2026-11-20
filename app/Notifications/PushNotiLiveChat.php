<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PushNotiLiveChat extends Notification
{
    use Queueable;

    protected $body;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($body)
    {
        $this->body = $body;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        $body  = $this->body;
        return (new WebPushMessage)
            ->title('Hàng Về có tin nhắn mới !')
            ->icon('/notification-icon.png')
            ->body($body)
            ->action('View App', 'notification_action');
    }
}
