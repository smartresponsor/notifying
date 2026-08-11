<?php

declare(strict_types=1);

namespace App\Notifying\ValueObject;

use App\Notifying\Enum\NotificationPriority;
use App\Notifying\Enum\RecipientType;

final readonly class NotificationIntent
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $sourceComponent,
        public string $eventName,
        public RecipientType $recipientType,
        public string $recipientKey,
        public string $title,
        public string $body,
        public NotificationPriority $priority = NotificationPriority::Normal,
        public array $payload = [],
        public array $metadata = [],
        public ?string $correlationId = null,
        public string $topic = 'default',
        public ?string $actionUrl = null,
    ) {
    }
}
