<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Enum\RecipientType;
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
#[ORM\UniqueConstraint(name: 'uniq_notifying_subscription_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_notifying_subscription_recipient', columns: ['recipient_key', 'enabled'])]
#[ORM\Index(name: 'idx_notifying_subscription_device', columns: ['app_key', 'platform', 'device_id'])]
class NotificationSubscriptionEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface, ObjectTitledInterface
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectTitleEmbeddableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(enumType: RecipientType::class)]
    private RecipientType $recipientType;

    #[ORM\Column(length: 160)]
    private string $recipientKey;

    #[ORM\Column(length: 80)]
    private string $platform;

    #[ORM\Column(length: 120)]
    private string $appKey;

    #[ORM\Column(length: 190)]
    private string $deviceId;

    #[ORM\Column(type: Types::TEXT)]
    private string $token;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $disabledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    /** @param array<string, mixed> $metadata */
    public function __construct(
        string $id,
        RecipientType $recipientType,
        string $recipientKey,
        string $platform,
        string $appKey,
        string $deviceId,
        string $token,
        array $metadata = [],
        ?string $createdBy = null,
    ) {
        $this->id = $id;
        $this->recipientType = $recipientType;
        $this->recipientKey = $recipientKey;
        $this->platform = $platform;
        $this->appKey = $appKey;
        $this->deviceId = $deviceId;
        $this->token = $token;
        $this->tokenHash = hash('sha256', $token);
        $this->metadata = $metadata;
        $this->lastSeenAt = new \DateTimeImmutable();
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
        $this->initializeObjectTitle(firstTitle: $platform, middleTitle: $appKey, lastTitle: $recipientKey);
    }

    public function recipientType(): RecipientType
    {
        return $this->recipientType;
    }

    public function recipientKey(): string
    {
        return $this->recipientKey;
    }

    public function platform(): string
    {
        return $this->platform;
    }

    public function appKey(): string
    {
        return $this->appKey;
    }

    public function deviceId(): string
    {
        return $this->deviceId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function rotateToken(string $token, ?string $modifiedBy = null): void
    {
        $this->token = $token;
        $this->tokenHash = hash('sha256', $token);
        $this->lastSeenAt = new \DateTimeImmutable();
        $this->enabled = true;
        $this->disabledAt = null;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function touchSeen(?string $modifiedBy = null): void
    {
        $this->lastSeenAt = new \DateTimeImmutable();
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function disable(?string $modifiedBy = null): void
    {
        $this->enabled = false;
        $this->disabledAt = new \DateTimeImmutable();
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt, ?string $modifiedBy = null): void
    {
        $this->expiresAt = $expiresAt;
        $this->touchModified(modifiedBy: $modifiedBy);
    }
}
