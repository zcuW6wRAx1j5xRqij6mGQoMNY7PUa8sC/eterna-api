<?php

namespace Internal\Tools\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ViaMsg {

    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toMsg($notifiable);

        $phone = $message->routes['phone'] ?? '';
        $captcha = $notification->captcha ?? '';

        if (!$phone || !$captcha) {
            Log::error('发送手机验证码失败 , 没有手机号或验证码',[$notifiable, $notification]);
            return;
        }
        (new MessageSend)->send($phone, $captcha);
    }

}