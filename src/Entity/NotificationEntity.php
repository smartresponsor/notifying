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

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(enumType: NotificationPriority::class)]
    private NotificationPriority $priority = NotificationPriority::Normal;

    #[ORM\Column(enumType: NotificationStatus::class)]
    private NotificationStatus $status = NotificationStatus::New;

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    public function __construct(
        string $id,
        string $sourceComponent,
        string $eventName,
        string $title,
        string $body,
        ?string $createdBy = null,
    ) {
        $this->id = $id;
        $this->sourceComponent = $sourceComponent;
        $this->eventName = $eventName;
        $this->title = $title;
        $this->body = $body;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
        $this->initializeObjectTitle(firstTitle: $title, lastTitle: $eventName);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function markRead(?string $modifiedBy = null): void
    {
        $this->status = NotificationStatus::Read;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function ack(?string $modifiedBy = null): void
    {
        $this->status = NotificationStatus::Acked;
        $this->touchModified(modifiedBy: $modifiedBy);
    }
}
