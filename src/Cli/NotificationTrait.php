<?php

namespace mglaman\DrupalOrgCli;

use Joli\JoliNotif\Notification;
use Joli\JoliNotif\NotifierFactory;

trait NotificationTrait
{

    protected function sendNotification(string $title, string $message, string $icon = ''): void
    {
        $notification = (new Notification())
            ->setTitle($title)
            ->setBody($message);
        if ($icon !== '') {
            $notification->setIcon($icon);
        }
        NotifierFactory::create()->send($notification);
    }
}
