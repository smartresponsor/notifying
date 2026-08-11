<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationRecipientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRecipientRepository::class)]
#[ORM\Table(name: 'notifying_notification_recipient')]
class NotificationRecipientEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(enumType: RecipientType::class)]
    private RecipientType $recipientType;

    #[ORM\Column(length: 160)]
    private string $recipientKey;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, RecipientType $recipientType, string $recipientKey)
    {
        $this->id = $id;
        $this->recipientType = $recipientType;
        $this->recipientKey = $recipientKey;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function recipientKey(): string
    {
        return $this->recipientKey;
    }
}
