<?php

declare(strict_types=1);

namespace App\Notifying\Service;

final class NotificationInboxService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $recipientKey): array
    {
        if ($recipientKey === '') {
            return [];
        }

        return [];
    }

    public function countUnread(string $recipientKey): int
    {
        if ($recipientKey === '') {
            return 0;
        }

        return 0;
    }

    /**
     * @param list<string> $notificationIds
     */
    public function markRead(array $notificationIds): int
    {
        return count($notificationIds);
    }

    public function ack(string $notificationId): bool
    {
        return $notificationId !== '';
    }
}
