<?php

declare(strict_types=1);

namespace App\Notifying\Enum;

enum NotificationStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Acked = 'acked';
    case Snoozed = 'snoozed';
    case Muted = 'muted';
    case Archived = 'archived';
}
