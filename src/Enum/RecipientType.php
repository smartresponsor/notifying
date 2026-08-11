<?php

declare(strict_types=1);

namespace App\Notifying\Enum;

enum RecipientType: string
{
    case User = 'user';
    case Vendor = 'vendor';
    case Customer = 'customer';
    case System = 'system';
}
