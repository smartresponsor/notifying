<?php

declare(strict_types=1);

namespace App\Notifying\Enum;

enum NotificationChannel: string
{
    case Inbox = 'inbox';
    case Push = 'push';
    case Email = 'email';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Digest = 'digest';
}
