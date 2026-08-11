<?php

declare(strict_types=1);

namespace App\Notifying\Message;

final readonly class NotificationIntentMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public array $payload,
    ) {
    }
}
