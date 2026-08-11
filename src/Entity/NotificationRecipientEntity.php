<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

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
class NotificationRecipientEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(enumType: RecipientType::class)]
    private RecipientType $recipientType;

    #[ORM\Column(length: 160)]
    private string $recipientKey;

    public function __construct(string $id, RecipientType $recipientType, string $recipientKey, ?string $createdBy = null)
    {
        $this->id = $id;
        $this->recipientType = $recipientType;
        $this->recipientKey = $recipientKey;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
    }

    public function recipientKey(): string
    {
        return $this->recipientKey;
    }
}
