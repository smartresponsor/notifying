<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Enum\RecipientType;
use App\Notifying\ValueObject\NotificationIntent;

final class NotificationService
{
    public function createIntent(array $payload): NotificationIntent
    {
        return new NotificationIntent(
            sourceComponent: (string) ($payload['sourceComponent'] ?? 'unknown'),
            eventName: (string) ($payload['eventName'] ?? 'unknown'),
            recipientType: RecipientType::tryFrom((string) ($payload['recipientType'] ?? 'user')) ?? RecipientType::User,
            recipientKey: (string) ($payload['recipientKey'] ?? ''),
            title: (string) ($payload['title'] ?? ''),
            body: (string) ($payload['body'] ?? ''),
            payload: $payload['payload'] ?? [],
            metadata: $payload['metadata'] ?? [],
            correlationId: isset($payload['correlationId']) ? (string) $payload['correlationId'] : null,
        );
    }
}
