<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Entity\NotificationEntity;
use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Enum\NotificationPriority;
use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationRepository;
use App\Notifying\ValueObject\NotificationIntent;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function createIntent(array $payload): NotificationIntent
    {
        $recipient = $payload['recipient'] ?? [];
        if (!is_array($recipient)) {
            $recipient = [];
        }

        $bodyPayload = $payload['payload'] ?? [];
        if (!is_array($bodyPayload)) {
            $bodyPayload = [];
        }

        $metadata = $payload['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return new NotificationIntent(
            sourceComponent: (string) ($payload['sourceComponent'] ?? 'unknown'),
            eventName: (string) ($payload['eventName'] ?? 'unknown'),
            recipientType: RecipientType::tryFrom((string) ($payload['recipientType'] ?? $recipient['type'] ?? 'user')) ?? RecipientType::User,
            recipientKey: (string) ($payload['recipientKey'] ?? $recipient['key'] ?? ''),
            title: (string) ($payload['title'] ?? ''),
            body: (string) ($payload['body'] ?? ''),
            priority: NotificationPriority::tryFrom((string) ($payload['priority'] ?? 'normal')) ?? NotificationPriority::Normal,
            payload: $bodyPayload,
            metadata: $metadata,
            correlationId: isset($payload['correlationId']) ? (string) $payload['correlationId'] : null,
            topic: (string) ($payload['topic'] ?? $payload['eventName'] ?? 'default'),
            actionUrl: isset($payload['actionUrl']) ? (string) $payload['actionUrl'] : null,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function ingest(array $payload, ?string $createdBy = null): array
    {
        $intent = $this->createIntent($payload);

        if ('' === trim($intent->recipientKey)) {
            throw new \InvalidArgumentException('recipientKey is required.');
        }
        if ('' === trim($intent->title)) {
            throw new \InvalidArgumentException('title is required.');
        }

        $existing = null;
        if (null !== $intent->correlationId && '' !== $intent->correlationId) {
            $existing = $this->notificationRepository->findByCorrelationId($intent->correlationId);
        }

        $notification = $existing ?? (new NotificationEntity(
            id: self::newUuid(),
            sourceComponent: $intent->sourceComponent,
            eventName: $intent->eventName,
            topic: $intent->topic,
            title: $intent->title,
            body: $intent->body,
            createdBy: $createdBy,
        ))
            ->withPriority($intent->priority)
            ->withPayload($intent->payload)
            ->withMetadata($intent->metadata)
            ->withCorrelationId($intent->correlationId)
            ->withActionUrl($intent->actionUrl);

        if (null === $existing) {
            $this->entityManager->persist($notification);
        }

        $recipient = new NotificationRecipientEntity(
            id: self::newUuid(),
            notification: $notification,
            recipientType: $intent->recipientType,
            recipientKey: $intent->recipientKey,
            createdBy: $createdBy,
        );
        $this->entityManager->persist($recipient);
        $this->entityManager->flush();

        return [
            'notificationId' => $notification->id(),
            'recipientEntryId' => $recipient->id(),
            'recipientKey' => $recipient->recipientKey(),
            'topic' => $notification->topic(),
            'status' => $recipient->status()->value,
            'created' => null === $existing,
        ];
    }

    private static function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
