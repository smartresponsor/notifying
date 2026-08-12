<?php

declare(strict_types=1);

namespace App\Notifying\Enum;

enum NotificationDispatchStatus: string
{
    case Planned = 'planned';
    case Suppressed = 'suppressed';
    case HandoffReady = 'handoff_ready';
    case Claimed = 'claimed';
    case HandedOff = 'handed_off';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
