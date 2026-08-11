<?php

declare(strict_types=1);

namespace App\Notifying\ValueObject;

use App\Notifying\Enum\NotificationChannel;

final readonly class NotificationDeliveryPlan
{
    /**
     * @param list<NotificationChannel> $channels
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $policy
     */
    public function __construct(
        public string $notificationId,
        public string $recipientKey,
        public array $channels,
        public string $title,
        public string $body,
        public array $payload = [],
        public array $policy = [],
        public ?string $digestKey = null,
    ) {
    }
}
