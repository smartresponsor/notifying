<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Entity\NotificationDispatchPlanEntity;
use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationDispatchPlanRepository;
use App\Notifying\Repository\NotificationPreferenceRepository;
use App\Notifying\Repository\NotificationRecipientRepository;
use App\Notifying\Repository\NotificationSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationDispatchPlanService
{
    public function __construct(
        private readonly NotificationDispatchPlanRepository $dispatchPlanRepository,
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly NotificationRecipientRepository $recipientRepository,
        private readonly NotificationSubscriptionRepository $subscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $recipientEntryId = null, int $limit = 100): array
    {
        if (null !== $recipientEntryId && '' !== $recipientEntryId) {
            $recipients = $this->recipientRepository->findByIds([$recipientEntryId]);
            if ([] === $recipients) {
                return [];
            }

            return NotificationService::dispatchPlanSummary($this->dispatchPlanRepository->listForRecipientEntry($recipients[0]));
        }

        return NotificationService::dispatchPlanSummary($this->dispatchPlanRepository->listPending($limit));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claim(string $claimedBy, int $limit = 100, int $leaseSeconds = 60): array
    {
        $claimedBy = trim($claimedBy);
        if ('' === $claimedBy) {
            throw new \InvalidArgumentException('claimedBy is required.');
        }

        $leaseSeconds = max(10, min(3600, $leaseSeconds));
        $claimedAt = new \DateTimeImmutable();
        $claimExpiresAt = $claimedAt->modify(sprintf('+%d seconds', $leaseSeconds));
        $claimLeaseId = bin2hex(random_bytes(24));
        $claimLeaseHash = hash('sha256', $claimLeaseId);

        $plans = $this->dispatchPlanRepository->claimHandoffReady(
            claimedBy: $claimedBy,
            claimLeaseHash: $claimLeaseHash,
            claimedAt: $claimedAt,
            claimExpiresAt: $claimExpiresAt,
            limit: $limit,
        );

        return array_map(
            fn ($plan): array => $this->claimSummary($plan, $claimLeaseId),
            $plans,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reactivatePushForSubscription(RecipientType $recipientType, string $recipientKey, string $tokenHash, ?string $modifiedBy = null): array
    {
        if ('' === $recipientKey || '' === $tokenHash) {
            return [];
        }

        $plans = $this->dispatchPlanRepository->listSuppressedPushForRecipient($recipientType, $recipientKey);
        $reactivatedPlans = [];
        $now = new \DateTimeImmutable();
        foreach ($plans as $plan) {
            $preference = $this->preferenceRepository->findForTopic($recipientType, $recipientKey, $plan->notification()->topic());
            $suppressionReason = $preference?->pushSuppressionReason();
            if (null !== $suppressionReason) {
                $plan->keepSuppressed(
                    reason: $suppressionReason,
                    metadata: ['reconciledBy' => 'subscription-registration'],
                    modifiedBy: $modifiedBy,
                );
                continue;
            }

            $deferredUntil = $preference?->pushDeferredUntil($now);
            $plan->markHandoffReady(
                target: 'subscription:'.$tokenHash,
                metadata: ['handoff' => 'delivering', 'reactivatedBy' => 'subscription-registration'],
                modifiedBy: $modifiedBy,
                at: $deferredUntil,
                reason: $deferredUntil instanceof \DateTimeImmutable ? 'push-policy-deferred' : 'push-handoff-ready',
            );
            $reactivatedPlans[] = $plan;
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($reactivatedPlans);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function retargetPushForSubscription(RecipientType $recipientType, string $recipientKey, string $oldTokenHash, string $newTokenHash, ?string $modifiedBy = null): array
    {
        if ('' === $recipientKey || '' === $oldTokenHash || '' === $newTokenHash || $oldTokenHash === $newTokenHash) {
            return [];
        }

        $plans = $this->dispatchPlanRepository->listHandoffReadyPushForRecipientTarget(
            $recipientType,
            $recipientKey,
            'subscription:'.$oldTokenHash,
        );
        foreach ($plans as $plan) {
            $plan->retargetHandoffReady(
                target: 'subscription:'.$newTokenHash,
                metadata: ['handoff' => 'delivering', 'retargetedBy' => 'subscription-token-rotation'],
                modifiedBy: $modifiedBy,
            );
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suppressPushForInvalidSubscription(RecipientType $recipientType, string $recipientKey, string $tokenHash, string $reasonCode, ?string $modifiedBy = null): array
    {
        if ('' === $recipientKey || '' === $tokenHash || '' === trim($reasonCode)) {
            return [];
        }

        $plans = $this->dispatchPlanRepository->listHandoffReadyPushForRecipientTarget(
            $recipientType,
            $recipientKey,
            'subscription:'.$tokenHash,
        );
        foreach ($plans as $plan) {
            $plan->suppress(
                reason: 'no-active-push-subscription',
                metadata: [
                    'suppressedBy' => 'delivering-invalid-subscription',
                    'providerReasonCode' => $reasonCode,
                ],
                modifiedBy: $modifiedBy,
            );
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suppressPushForPreference(RecipientType $recipientType, string $recipientKey, string $topic, string $reason, ?string $modifiedBy = null): array
    {
        if ('' === $recipientKey || '' === $topic || '' === trim($reason)) {
            return [];
        }

        $plans = $this->dispatchPlanRepository->listHandoffReadyPushForRecipientTopic($recipientType, $recipientKey, $topic);
        foreach ($plans as $plan) {
            $plan->suppress(
                reason: $reason,
                metadata: ['suppressedBy' => 'preference-update'],
                modifiedBy: $modifiedBy,
            );
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reactivatePushForPreference(RecipientType $recipientType, string $recipientKey, string $topic, ?string $modifiedBy = null): array
    {
        if ('' === $recipientKey || '' === $topic) {
            return [];
        }

        $subscriptions = $this->subscriptionRepository->listActiveForRecipient($recipientType, $recipientKey);
        if ([] === $subscriptions) {
            return [];
        }

        $plans = $this->dispatchPlanRepository->listPolicySuppressedPushForRecipientTopic($recipientType, $recipientKey, $topic);
        foreach ($plans as $plan) {
            $plan->markHandoffReady(
                target: 'subscription:'.$subscriptions[0]->tokenHash(),
                metadata: ['handoff' => 'delivering', 'reactivatedBy' => 'preference-update'],
                modifiedBy: $modifiedBy,
            );
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reschedulePushForPreference(RecipientType $recipientType, string $recipientKey, string $topic, ?\DateTimeImmutable $scheduledAt, ?string $modifiedBy = null): array
    {
        if ('' === $recipientKey || '' === $topic) {
            return [];
        }

        $now = new \DateTimeImmutable();
        $effectiveAt = $scheduledAt ?? $now;
        $reason = $scheduledAt instanceof \DateTimeImmutable && $scheduledAt > $now ? 'push-policy-deferred' : 'push-handoff-ready';
        $plans = $this->dispatchPlanRepository->listHandoffReadyPushForRecipientTopic($recipientType, $recipientKey, $topic);
        foreach ($plans as $plan) {
            $plan->rescheduleHandoffReady($effectiveAt, $reason, ['scheduledBy' => 'preference-update'], $modifiedBy);
        }
        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /**
     * @param list<string> $dispatchPlanIds
     * @return list<array<string, mixed>>
     */
    public function markHandedOff(array $dispatchPlanIds, string $claimedBy, string $claimLeaseId, ?string $modifiedBy = null): array
    {
        $claimedBy = trim($claimedBy);
        if ('' === $claimedBy) {
            throw new \InvalidArgumentException('claimedBy is required.');
        }
        if ('' === trim($claimLeaseId)) {
            throw new \InvalidArgumentException('claimLeaseId is required.');
        }

        $plans = $this->dispatchPlanRepository->findByIds($dispatchPlanIds);
        foreach ($plans as $plan) {
            $plan->markHandedOff($claimedBy, $claimLeaseId, $modifiedBy);
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /**
     * @param list<string> $dispatchPlanIds
     * @return list<array<string, mixed>>
     */
    public function markFailed(array $dispatchPlanIds, string $reason, ?string $claimedBy = null, ?string $claimLeaseId = null, ?string $modifiedBy = null): array
    {
        $plans = $this->dispatchPlanRepository->findByIds($dispatchPlanIds);
        foreach ($plans as $plan) {
            $plan->markFailed($reason, $claimedBy, $claimLeaseId, $modifiedBy);
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /**
     * @param list<string> $dispatchPlanIds
     * @return list<array<string, mixed>>
     */
    public function cancel(array $dispatchPlanIds, string $reason, ?string $modifiedBy = null): array
    {
        $plans = $this->dispatchPlanRepository->findByIds($dispatchPlanIds);
        foreach ($plans as $plan) {
            $plan->cancel($reason, $modifiedBy);
        }

        if ([] !== $plans) {
            $this->entityManager->flush();
        }

        return NotificationService::dispatchPlanSummary($plans);
    }

    /** @return array<string, mixed> */
    private function claimSummary(NotificationDispatchPlanEntity $plan, string $claimLeaseId): array
    {
        $summary = NotificationService::dispatchPlanSummary([$plan])[0] ?? [];
        $summary['claimLeaseId'] = $claimLeaseId;

        if ('push' !== $plan->channel()->value) {
            return $summary;
        }

        $target = (string) $plan->target();
        if (!str_starts_with($target, 'subscription:')) {
            $summary['delivery'] = null;

            return $summary;
        }

        $tokenHash = substr($target, strlen('subscription:'));
        $subscription = $this->subscriptionRepository->findByTokenHash($tokenHash);
        $now = new \DateTimeImmutable();
        if (null === $subscription || !$subscription->enabled() || ($subscription->expiresAt() instanceof \DateTimeImmutable && $subscription->expiresAt() <= $now)) {
            $summary['delivery'] = null;

            return $summary;
        }

        $notification = $plan->notification();
        $summary['delivery'] = [
            'provider' => $subscription->platform(),
            'token' => $subscription->token(),
            'appKey' => $subscription->appKey(),
            'deviceId' => $subscription->deviceId(),
            'title' => $notification->title(),
            'body' => $notification->body(),
            'priority' => $notification->priority()->value,
            'actionUrl' => $notification->actionUrl(),
            'payload' => $notification->payload(),
            'correlationId' => $notification->correlationId() ?? $plan->id(),
        ];

        return $summary;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    public static function idsFromPayload(array $payload): array
    {
        $ids = $payload['dispatchPlanIds'] ?? $payload['planIds'] ?? [];
        if (!is_array($ids)) {
            $ids = [$payload['dispatchPlanId'] ?? $payload['planId'] ?? ''];
        }

        return array_values(array_filter(array_map('strval', $ids)));
    }
}
