<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Entity\NotificationDispatchPlanEntity;
use App\Notifying\Entity\NotificationPreferenceEntity;
use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\NotificationDispatchStatus;
use App\Notifying\Repository\NotificationDispatchPlanRepository;
use App\Notifying\Repository\NotificationPreferenceRepository;
use App\Notifying\Repository\NotificationSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationDispatchPlannerService
{
    public function __construct(
        private readonly NotificationDispatchPlanRepository $dispatchPlanRepository,
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly NotificationSubscriptionRepository $subscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<NotificationDispatchPlanEntity>
     */
    public function planForRecipient(NotificationRecipientEntity $recipient, ?string $createdBy = null): array
    {
        $notification = $recipient->notification();
        $preference = $this->preferenceRepository->findForTopic($recipient->recipientType(), $recipient->recipientKey(), $notification->topic());
        $plans = [];

        $plans[] = $this->ensurePlan(
            recipient: $recipient,
            channel: NotificationChannel::Inbox,
            status: $this->isChannelSuppressed(NotificationChannel::Inbox, $preference) ? NotificationDispatchStatus::Suppressed : NotificationDispatchStatus::Planned,
            reason: $this->suppressionReason(NotificationChannel::Inbox, $preference) ?? 'inbox-entry-created',
            target: 'notifying:inbox',
            payload: $notification->payload(),
            metadata: ['topic' => $notification->topic(), 'local' => true],
            createdBy: $createdBy,
        );

        $pushReason = $this->suppressionReason(NotificationChannel::Push, $preference);
        $pushTarget = null;
        $pushScheduledAt = new \DateTimeImmutable();
        $pushDeferred = false;
        if (null === $pushReason) {
            $subscriptions = $this->subscriptionRepository->listActiveForRecipient($recipient->recipientType(), $recipient->recipientKey());
            if ([] === $subscriptions) {
                $pushReason = 'no-active-push-subscription';
            } else {
                $pushTarget = 'subscription:'.$subscriptions[0]->tokenHash();
                $deferredUntil = $preference?->pushDeferredUntil($pushScheduledAt);
                if ($deferredUntil instanceof \DateTimeImmutable) {
                    $pushScheduledAt = $deferredUntil;
                    $pushDeferred = true;
                }
            }
        }

        $plans[] = $this->ensurePlan(
            recipient: $recipient,
            channel: NotificationChannel::Push,
            status: null === $pushReason ? NotificationDispatchStatus::HandoffReady : NotificationDispatchStatus::Suppressed,
            reason: $pushReason ?? ($pushDeferred ? 'push-policy-deferred' : 'push-handoff-ready'),
            target: $pushTarget,
            payload: $notification->payload(),
            scheduledAt: $pushScheduledAt,
            metadata: ['topic' => $notification->topic(), 'handoff' => 'delivering'],
            createdBy: $createdBy,
        );

        return $plans;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    private function ensurePlan(
        NotificationRecipientEntity $recipient,
        NotificationChannel $channel,
        NotificationDispatchStatus $status,
        ?string $reason,
        ?string $target,
        array $payload,
        array $metadata,
        ?\DateTimeImmutable $scheduledAt = null,
        ?string $createdBy = null,
    ): NotificationDispatchPlanEntity {
        $existing = $this->dispatchPlanRepository->findForRecipientEntryAndChannel($recipient, $channel);
        if ($existing instanceof NotificationDispatchPlanEntity) {
            return $existing;
        }

        $plan = new NotificationDispatchPlanEntity(
            id: self::newUuid(),
            notification: $recipient->notification(),
            recipientEntry: $recipient,
            channel: $channel,
            status: $status,
            reason: $reason,
            target: $target,
            scheduledAt: $scheduledAt ?? new \DateTimeImmutable(),
            payload: $payload,
            metadata: $metadata,
            createdBy: $createdBy,
        );
        $this->entityManager->persist($plan);

        return $plan;
    }

    private function isChannelSuppressed(NotificationChannel $channel, ?NotificationPreferenceEntity $preference): bool
    {
        return null !== $this->suppressionReason($channel, $preference);
    }

    private function suppressionReason(NotificationChannel $channel, ?NotificationPreferenceEntity $preference): ?string
    {
        if (!$preference instanceof NotificationPreferenceEntity) {
            return null;
        }

        if (NotificationChannel::Push === $channel) {
            return $preference->pushSuppressionReason();
        }

        $now = new \DateTimeImmutable();
        if ($preference->muted() && (null === $preference->mutedUntil() || $preference->mutedUntil() > $now)) {
            return 'recipient-muted';
        }

        $enabledChannels = $preference->enabledChannels();
        if ([] !== $enabledChannels && !in_array($channel->value, $enabledChannels, true)) {
            return 'channel-not-enabled';
        }

        if (in_array($channel->value, $preference->disabledChannels(), true)) {
            return 'channel-disabled';
        }

        return null;
    }

    private static function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
