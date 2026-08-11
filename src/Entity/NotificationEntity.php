<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Enum\NotificationPriority;
use App\Notifying\Enum\NotificationStatus;
use App\Notifying\Repository\NotificationRepository;
use App\Objecting\EntityInterface\ObjectAuditedInterface;
use App\Objecting\EntityInterface\ObjectIdentifiedInterface;
use App\Objecting\EntityInterface\ObjectTitledInterface;
use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectTitleEmbeddableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifying_notification')]
#[ORM\Index(name: 'idx_notifying_notification_source_event', columns: ['source_component', 'event_name'])]
#[ORM\Index(name: 'idx_notifying_notification_topic', columns: ['topic'])]
#[ORM\Index(name: 'idx_notifying_notification_status', columns: ['status'])]
#[ORM\Index(name: 'idx_notifying_notification_correlation', columns: ['correlation_id'])]
class NotificationEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface, ObjectTitledInterface
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectTitleEmbeddableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 80)]
    private string $sourceComponent;

    #[ORM\Column(length: 120)]
    private string $eventName;

    #[ORM\Column(length: 120)]
    private string $topic;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(enumType: NotificationPriority::class)]
    private NotificationPriority $priority = NotificationPriority::Normal;

    #[ORM\Column(enumType: NotificationStatus::class)]
    private NotificationStatus $status = NotificationStatus::New;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $correlationId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $actionUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    public function __construct(
        string $id,
        string $sourceComponent,
        string $eventName,
        string $topic,
        string $title,
        string $body,
        ?string $createdBy = null,
    ) {
        $this->id = $id;
        $this->sourceComponent = $sourceComponent;
        $this->eventName = $eventName;
        $this->topic = $topic;
        $this->title = $title;
        $this->body = $body;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
        $this->initializeObjectTitle(firstTitle: $title, middleTitle: $topic, lastTitle: $eventName);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sourceComponent(): string
    {
        return $this->sourceComponent;
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function topic(): string
    {
        return $this->topic;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function priority(): NotificationPriority
    {
        return $this->priority;
    }

    public function status(): NotificationStatus
    {
        return $this->status;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    public function actionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
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

    /** @param array<string, mixed> $payload */
    public function withPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /** @param array<string, mixed> $metadata */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function withPriority(NotificationPriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function withCorrelationId(?string $correlationId): self
    {
        $this->correlationId = $correlationId;

        return $this;
    }

    public function withActionUrl(?string $actionUrl): self
    {
        $this->actionUrl = $actionUrl;

        return $this;
    }

    public function withExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function archive(?string $modifiedBy = null): void
    {
        $this->status = NotificationStatus::Archived;
        $this->touchModified(modifiedBy: $modifiedBy);
    }
}
