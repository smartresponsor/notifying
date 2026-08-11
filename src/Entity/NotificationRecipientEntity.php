<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Enum\NotificationStatus;
use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationRecipientRepository;
use App\Objecting\EntityInterface\ObjectAuditedInterface;
use App\Objecting\EntityInterface\ObjectIdentifiedInterface;
use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRecipientRepository::class)]
#[ORM\Table(name: 'notifying_notification_recipient')]
#[ORM\Index(name: 'idx_notifying_recipient_inbox', columns: ['recipient_key', 'status', 'object_created_at'])]
#[ORM\Index(name: 'idx_notifying_recipient_notification', columns: ['notification_id'])]
#[ORM\Index(name: 'idx_notifying_recipient_snoozed', columns: ['snoozed_until'])]
class NotificationRecipientEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: NotificationEntity::class)]
    #[ORM\JoinColumn(name: 'notification_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private NotificationEntity $notification;

    #[ORM\Column(enumType: RecipientType::class)]
    private RecipientType $recipientType;

    #[ORM\Column(length: 160)]
    private string $recipientKey;

    #[ORM\Column(enumType: NotificationStatus::class)]
    private NotificationStatus $status = NotificationStatus::New;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $ackedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $snoozedUntil = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mutedUntil = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    public function __construct(
        string $id,
        NotificationEntity $notification,
        RecipientType $recipientType,
        string $recipientKey,
        ?string $createdBy = null,
    ) {
        $this->id = $id;
        $this->notification = $notification;
        $this->recipientType = $recipientType;
        $this->recipientKey = $recipientKey;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function notification(): NotificationEntity
    {
        return $this->notification;
    }

    public function recipientType(): RecipientType
    {
        return $this->recipientType;
    }

    public function recipientKey(): string
    {
        return $this->recipientKey;
    }

    public function status(): NotificationStatus
    {
        return $this->status;
    }

    public function readAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function ackedAt(): ?\DateTimeImmutable
    {
        return $this->ackedAt;
    }

    public function snoozedUntil(): ?\DateTimeImmutable
    {
        return $this->snoozedUntil;
    }

    public function isUnread(): bool
    {
        return null === $this->readAt && NotificationStatus::New === $this->status;
    }

    public function markRead(?string $modifiedBy = null, ?\DateTimeImmutable $at = null): void
    {
        $this->readAt = $at ?? new \DateTimeImmutable();
        $this->status = NotificationStatus::Read;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function ack(?string $modifiedBy = null, ?\DateTimeImmutable $at = null): void
    {
        $this->ackedAt = $at ?? new \DateTimeImmutable();
        if (null === $this->readAt) {
            $this->readAt = $this->ackedAt;
        }
        $this->status = NotificationStatus::Acked;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function snoozeUntil(\DateTimeImmutable $until, ?string $modifiedBy = null): void
    {
        $this->snoozedUntil = $until;
        $this->status = NotificationStatus::Snoozed;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function muteUntil(?\DateTimeImmutable $until, ?string $modifiedBy = null): void
    {
        $this->mutedUntil = $until;
        $this->status = NotificationStatus::Muted;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function archive(?string $modifiedBy = null, ?\DateTimeImmutable $at = null): void
    {
        $this->archivedAt = $at ?? new \DateTimeImmutable();
        $this->status = NotificationStatus::Archived;
        $this->touchModified(modifiedBy: $modifiedBy);
    }
}
