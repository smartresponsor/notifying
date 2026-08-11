<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Repository\NotificationSubscriptionRepository;
use App\Objecting\EntityInterface\ObjectAuditedInterface;
use App\Objecting\EntityInterface\ObjectIdentifiedInterface;
use App\Objecting\EntityInterface\ObjectTitledInterface;
use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectTitleEmbeddableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationSubscriptionRepository::class)]
#[ORM\Table(name: 'notifying_notification_subscription')]
class NotificationSubscriptionEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface, ObjectTitledInterface
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectTitleEmbeddableTrait;

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

    public function __construct(string $id, string $recipientKey, string $platform, string $token, ?string $createdBy = null)
    {
        $this->id = $id;
        $this->recipientKey = $recipientKey;
        $this->platform = $platform;
        $this->token = $token;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
        $this->initializeObjectTitle(firstTitle: $platform, lastTitle: $recipientKey);
    }
}
