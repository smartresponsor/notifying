<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Repository\NotificationRecipientRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationInboxService
{
    public function __construct(
        private readonly NotificationRecipientRepository $recipientRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(string $recipientKey, int $limit = 50, int $offset = 0): array
    {
        if ($recipientKey === '') {
            return [];
        }

        return array_map(
            static fn (NotificationRecipientEntity $entry): array => self::toInboxItem($entry),
            $this->recipientRepository->listInbox($recipientKey, $limit, $offset),
        );
    }

    public function countUnread(string $recipientKey): int
    {
        if ($recipientKey === '') {
            return 0;
        }

        return $this->recipientRepository->countUnread($recipientKey);
    }

    /**
     * @param list<string> $recipientEntryIds
     */
    public function markRead(array $recipientEntryIds, ?string $modifiedBy = null): int
    {
        $updated = 0;
        foreach ($this->recipientRepository->findByIds($recipientEntryIds) as $entry) {
            if (!$entry->isUnread()) {
                continue;
            }
            $entry->markRead($modifiedBy);
            ++$updated;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        return $updated;
    }

    public function ack(string $recipientEntryId, ?string $modifiedBy = null): bool
    {
        if ('' === $recipientEntryId) {
            return false;
        }

        $entry = $this->recipientRepository->find($recipientEntryId);
        if (!$entry instanceof NotificationRecipientEntity) {
            return false;
        }

        $entry->ack($modifiedBy);
        $this->entityManager->flush();

        return true;
    }

    public function archive(string $recipientEntryId, ?string $modifiedBy = null): bool
    {
        if ('' === $recipientEntryId) {
            return false;
        }

        $entry = $this->recipientRepository->find($recipientEntryId);
        if (!$entry instanceof NotificationRecipientEntity) {
            return false;
        }

        $entry->archive($modifiedBy);
        $this->entityManager->flush();

        return true;
    }

    public function snooze(string $recipientEntryId, \DateTimeImmutable $until, ?string $modifiedBy = null): bool
    {
        if ('' === $recipientEntryId) {
            return false;
        }

        $entry = $this->recipientRepository->find($recipientEntryId);
        if (!$entry instanceof NotificationRecipientEntity) {
            return false;
        }

        $entry->snoozeUntil($until, $modifiedBy);
        $this->entityManager->flush();

        return true;
    }

    /** @return array<string, mixed> */
    private static function toInboxItem(NotificationRecipientEntity $entry): array
    {
        $notification = $entry->notification();

        return [
            'id' => $entry->id(),
            'notificationId' => $notification->id(),
            'recipientKey' => $entry->recipientKey(),
            'status' => $entry->status()->value,
            'readAt' => $entry->readAt()?->format(DATE_ATOM),
            'ackedAt' => $entry->ackedAt()?->format(DATE_ATOM),
            'snoozedUntil' => $entry->snoozedUntil()?->format(DATE_ATOM),
            'sourceComponent' => $notification->sourceComponent(),
            'eventName' => $notification->eventName(),
            'topic' => $notification->topic(),
            'title' => $notification->title(),
            'body' => $notification->body(),
            'priority' => $notification->priority()->value,
            'actionUrl' => $notification->actionUrl(),
            'createdAt' => $entry->getCreatedAt()->format(DATE_ATOM),
            'payload' => $notification->payload(),
            'metadata' => $notification->metadata(),
        ];
    }
}
