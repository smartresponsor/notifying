<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\NotificationDispatchStatus;
use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationDispatchPlanRepository;
use App\Objecting\EntityInterface\ObjectAuditedInterface;
use App\Objecting\EntityInterface\ObjectIdentifiedInterface;
use App\Objecting\EntityInterface\ObjectTitledInterface;
use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectTitleEmbeddableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationDispatchPlanRepository::class)]
#[ORM\Table(name: 'notifying_notification_dispatch_plan')]
#[ORM\UniqueConstraint(name: 'uniq_notifying_dispatch_recipient_channel', columns: ['recipient_entry_id', 'channel'])]
#[ORM\Index(name: 'idx_notifying_dispatch_recipient_status', columns: ['recipient_key', 'status', 'object_created_at'])]
#[ORM\Index(name: 'idx_notifying_dispatch_notification', columns: ['notification_id'])]
#[ORM\Index(name: 'idx_notifying_dispatch_recipient_entry', columns: ['recipient_entry_id'])]
#[ORM\Index(name: 'idx_notifying_dispatch_channel_status', columns: ['channel', 'status'])]
#[ORM\Index(name: 'idx_notifying_dispatch_scheduled', columns: ['scheduled_at'])]
class NotificationDispatchPlanEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface, ObjectTitledInterface
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectTitleEmbeddableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: NotificationEntity::class)]
    #[ORM\JoinColumn(name: 'notification_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private NotificationEntity $notification;

    #[ORM\ManyToOne(targetEntity: NotificationRecipientEntity::class)]
    #[ORM\JoinColumn(name: 'recipient_entry_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private NotificationRecipientEntity $recipientEntry;

    #[ORM\Column(enumType: RecipientType::class)]
    private RecipientType $recipientType;

    #[ORM\Column(length: 160)]
    private string $recipientKey;

    #[ORM\Column(enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column(enumType: NotificationDispatchStatus::class)]
    private NotificationDispatchStatus $status;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $target = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $handedOffAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $id,
        NotificationEntity $notification,
        NotificationRecipientEntity $recipientEntry,
        NotificationChannel $channel,
        NotificationDispatchStatus $status,
        ?string $reason = null,
        ?string $target = null,
        ?\DateTimeImmutable $scheduledAt = null,
        array $payload = [],
        array $metadata = [],
        ?string $createdBy = null,
    ) {
        $this->id = $id;
        $this->notification = $notification;
        $this->recipientEntry = $recipientEntry;
        $this->recipientType = $recipientEntry->recipientType();
        $this->recipientKey = $recipientEntry->recipientKey();
        $this->channel = $channel;
        $this->status = $status;
        $this->reason = $reason;
        $this->target = $target;
        $this->scheduledAt = $scheduledAt;
        $this->payload = $payload;
        $this->metadata = $metadata;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
        $this->initializeObjectTitle(firstTitle: $channel->value, middleTitle: $status->value, lastTitle: $recipientEntry->recipientKey());
    }

    public function id(): string
    {
        return $this->id;
    }

    public function notification(): NotificationEntity
    {
        return $this->notification;
    }

    public function recipientEntry(): NotificationRecipientEntity
    {
        return $this->recipientEntry;
    }

    public function recipientType(): RecipientType
    {
        return $this->recipientType;
    }

    public function recipientKey(): string
    {
        return $this->recipientKey;
    }

    public function channel(): NotificationChannel
    {
        return $this->channel;
    }

    public function status(): NotificationDispatchStatus
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function target(): ?string
    {
        return $this->target;
    }

    public function scheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function handedOffAt(): ?\DateTimeImmutable
    {
        return $this->handedOffAt;
    }

    public function failedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function markHandoffReady(string $target, array $metadata = [], ?string $modifiedBy = null, ?\DateTimeImmutable $at = null): void
    {
        $this->status = NotificationDispatchStatus::HandoffReady;
        $this->reason = 'push-handoff-ready';
        $this->target = $target;
        $this->scheduledAt = $at ?? new \DateTimeImmutable();
        $this->metadata = array_replace($this->metadata, $metadata);
        $this->failedAt = null;
        $this->cancelledAt = null;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function markHandedOff(?string $modifiedBy = null, ?\DateTimeImmutable $at = null): void
    {
        $this->status = NotificationDispatchStatus::HandedOff;
        $this->handedOffAt = $at ?? new \DateTimeImmutable();
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function markFailed(string $reason, ?string $modifiedBy = null, ?\DateTimeImmutable $at = null): void
    {
        $this->status = NotificationDispatchStatus::Failed;
        $this->reason = $reason;
        $this->failedAt = $at ?? new \DateTimeImmutable();
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function cancel(string $reason, ?string $modifiedBy = null, ?\DateTimeImmutable $at = null): void
    {
        $this->status = NotificationDispatchStatus::Cancelled;
        $this->reason = $reason;
        $this->cancelledAt = $at ?? new \DateTimeImmutable();
        $this->touchModified(modifiedBy: $modifiedBy);
    }
}
