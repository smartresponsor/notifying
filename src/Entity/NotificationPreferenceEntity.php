<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Repository\NotificationPreferenceRepository;
use App\Objecting\EntityInterface\ObjectAuditedInterface;
use App\Objecting\EntityInterface\ObjectIdentifiedInterface;
use App\Objecting\EntityInterface\ObjectTitledInterface;
use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectTitleEmbeddableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\Table(name: 'notifying_notification_preference')]
class NotificationPreferenceEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface, ObjectTitledInterface
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectTitleEmbeddableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 160)]
    private string $recipientKey;

    #[ORM\Column(length: 120)]
    private string $topic;

    #[ORM\Column(type: Types::JSON)]
    private array $channels = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $muted = false;

    public function __construct(string $id, string $recipientKey, string $topic, ?string $createdBy = null)
    {
        $this->id = $id;
        $this->recipientKey = $recipientKey;
        $this->topic = $topic;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
        $this->initializeObjectTitle(firstTitle: $topic, lastTitle: $recipientKey);
    }
}
