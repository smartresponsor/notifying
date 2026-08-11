<?php

declare(strict_types=1);

namespace App\Notifying\Service;

final class NotificationPreferenceService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upsertPreference(array $payload): array
    {
        return [
            'recipientKey' => (string) ($payload['recipientKey'] ?? ''),
            'topic' => (string) ($payload['topic'] ?? 'default'),
            'channels' => $payload['channels'] ?? [],
            'muted' => (bool) ($payload['muted'] ?? false),
        ];
    }
}
