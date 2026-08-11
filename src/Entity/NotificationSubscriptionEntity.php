<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Repository\NotificationSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationSubscriptionRepository::class)]
#[ORM\Table(name: 'notifying_notification_subscription')]
class NotificationSubscriptionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 160)]
    private string $recipientKey;

    #[ORM\Column(length: 80)]
    private string $platform;

    #[ORM\Column(type: Types::TEXT)]
    private string $token;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $id, string $recipientKey, string $platform, string $token)
    {
        $this->id = $id;
        $this->recipientKey = $recipientKey;
        $this->platform = $platform;
        $this->token = $token;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
