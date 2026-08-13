<?php

declare(strict_types=1);

namespace App\Notifying\Entity;

use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\RecipientType;
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
#[ORM\UniqueConstraint(name: 'uniq_notifying_pref_recipient_topic', columns: ['recipient_type', 'recipient_key', 'topic'])]
#[ORM\Index(name: 'idx_notifying_pref_recipient', columns: ['recipient_key'])]
class NotificationPreferenceEntity implements ObjectIdentifiedInterface, ObjectAuditedInterface, ObjectTitledInterface
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

    #[ORM\Column(length: 120)]
    private string $topic;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $enabledChannels = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $disabledChannels = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $muted = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mutedUntil = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $quietHoursStart = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $quietHoursEnd = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $digestEnabled = false;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $digestFrequency = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $policy = [];

    public function __construct(string $id, RecipientType $recipientType, string $recipientKey, string $topic, ?string $createdBy = null)
    {
        $this->id = $id;
        $this->recipientType = $recipientType;
        $this->recipientKey = $recipientKey;
        $this->topic = $topic;
        $this->initializeObjectIdentity(objectUuid: $id);
        $this->initializeObjectAudit(createdBy: $createdBy);
        $this->initializeObjectTitle(firstTitle: $topic, lastTitle: $recipientKey);
    }

    public function recipientType(): RecipientType
    {
        return $this->recipientType;
    }

    public function recipientKey(): string
    {
        return $this->recipientKey;
    }

    public function topic(): string
    {
        return $this->topic;
    }

    /** @return list<string> */
    public function enabledChannels(): array
    {
        return $this->enabledChannels;
    }

    /** @return list<string> */
    public function disabledChannels(): array
    {
        return $this->disabledChannels;
    }

    public function muted(): bool
    {
        return $this->muted;
    }

    public function mutedUntil(): ?\DateTimeImmutable
    {
        return $this->mutedUntil;
    }

    public function quietHoursStart(): ?string
    {
        return $this->quietHoursStart;
    }

    public function quietHoursEnd(): ?string
    {
        return $this->quietHoursEnd;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public function quietHoursEndAfter(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if (null === $this->quietHoursStart || null === $this->quietHoursEnd || null === $this->timezone) {
            return null;
        }

        $timezone = new \DateTimeZone($this->timezone);
        $localNow = $now->setTimezone($timezone);
        [$startHour, $startMinute] = array_map('intval', explode(':', $this->quietHoursStart));
        [$endHour, $endMinute] = array_map('intval', explode(':', $this->quietHoursEnd));
        $start = $localNow->setTime($startHour, $startMinute, 0);
        $end = $localNow->setTime($endHour, $endMinute, 0);

        if ($start < $end) {
            return $localNow >= $start && $localNow < $end ? $end : null;
        }

        if ($localNow >= $start) {
            return $end->modify('+1 day');
        }

        return $localNow < $end ? $end : null;
    }

    public function digestEnabled(): bool
    {
        return $this->digestEnabled;
    }

    /** @param list<NotificationChannel> $channels */
    public function setEnabledChannels(array $channels, ?string $modifiedBy = null): void
    {
        $this->enabledChannels = array_values(array_map(static fn (NotificationChannel $channel): string => $channel->value, $channels));
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    /** @param list<NotificationChannel> $channels */
    public function setDisabledChannels(array $channels, ?string $modifiedBy = null): void
    {
        $this->disabledChannels = array_values(array_map(static fn (NotificationChannel $channel): string => $channel->value, $channels));
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function mute(?\DateTimeImmutable $until = null, ?string $modifiedBy = null): void
    {
        $this->muted = true;
        $this->mutedUntil = $until;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function unmute(?string $modifiedBy = null): void
    {
        $this->muted = false;
        $this->mutedUntil = null;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function setQuietHours(?string $start, ?string $end, ?string $timezone, ?string $modifiedBy = null): void
    {
        $this->quietHoursStart = $start;
        $this->quietHoursEnd = $end;
        $this->timezone = $timezone;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    public function setDigest(bool $enabled, ?string $frequency = null, ?string $modifiedBy = null): void
    {
        $this->digestEnabled = $enabled;
        $this->digestFrequency = $frequency;
        $this->touchModified(modifiedBy: $modifiedBy);
    }

    /** @param array<string, mixed> $policy */
    public function setPolicy(array $policy, ?string $modifiedBy = null): void
    {
        $this->policy = $policy;
        $this->touchModified(modifiedBy: $modifiedBy);
    }
}
