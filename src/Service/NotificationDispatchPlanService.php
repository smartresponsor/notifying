<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Repository\NotificationDispatchPlanRepository;
use App\Notifying\Repository\NotificationRecipientRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationDispatchPlanService
{
    public function __construct(
        private readonly NotificationDispatchPlanRepository $dispatchPlanRepository,
        private readonly NotificationRecipientRepository $recipientRepository,
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
     * @param list<string> $dispatchPlanIds
     * @return list<array<string, mixed>>
     */
    public function markHandedOff(array $dispatchPlanIds, ?string $modifiedBy = null): array
    {
        $plans = $this->dispatchPlanRepository->findByIds($dispatchPlanIds);
        foreach ($plans as $plan) {
            $plan->markHandedOff($modifiedBy);
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
    public function markFailed(array $dispatchPlanIds, string $reason, ?string $modifiedBy = null): array
    {
        $plans = $this->dispatchPlanRepository->findByIds($dispatchPlanIds);
        foreach ($plans as $plan) {
            $plan->markFailed($reason, $modifiedBy);
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
