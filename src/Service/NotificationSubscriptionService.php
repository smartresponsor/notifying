<?php

declare(strict_types=1);

namespace App\Notifying\Service;

final class NotificationSubscriptionService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registerSubscription(array $payload): array
    {
        return [
            'recipientKey' => (string) ($payload['recipientKey'] ?? ''),
            'platform' => (string) ($payload['platform'] ?? ''),
            'registered' => ((string) ($payload['token'] ?? '')) !== '',
        ];
    }
}
