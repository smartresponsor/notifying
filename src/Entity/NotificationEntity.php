<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Enum\NotificationPriority;
use App\Notifying\Enum\NotificationStatus;
use App\Notifying\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifying_notification')]
class NotificationEntity
{
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

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $sourceComponent,
        string $eventName,
        string $title,
        string $body,
    ) {
        $this->id = $id;
        $this->sourceComponent = $sourceComponent;
        $this->eventName = $eventName;
        $this->title = $title;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function markRead(): void
    {
        $this->status = NotificationStatus::Read;
    }

    public function ack(): void
    {
        $this->status = NotificationStatus::Acked;
    }
}
