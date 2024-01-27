<?php namespace common\contracts;

use common\services\notifier\Notification;

interface Notifier
{
    public function sendNotification(Notification $notification): void;
}