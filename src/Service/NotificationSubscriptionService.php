<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Entity\NotificationSubscriptionEntity;
use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationSubscriptionService
{
    public function __construct(
        private readonly NotificationSubscriptionRepository $subscriptionRepository,
        private readonly NotificationDispatchPlanService $dispatchPlanService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registerSubscription(array $payload, ?string $modifiedBy = null): array
    {
        $recipientType = RecipientType::tryFrom((string) ($payload['recipientType'] ?? 'user')) ?? RecipientType::User;
        $recipientKey = (string) ($payload['recipientKey'] ?? '');
        $platform = (string) ($payload['platform'] ?? '');
        $appKey = (string) ($payload['appKey'] ?? 'default');
        $deviceId = (string) ($payload['deviceId'] ?? 'default');
        $token = (string) ($payload['token'] ?? '');
        $metadata = $payload['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        if ('' === trim($recipientKey)) {
            throw new \InvalidArgumentException('recipientKey is required.');
        }
        if ('' === trim($platform)) {
            throw new \InvalidArgumentException('platform is required.');
        }
        if ('' === trim($token)) {
            throw new \InvalidArgumentException('token is required.');
        }

        $tokenHash = hash('sha256', $token);
        $deviceSubscription = $this->subscriptionRepository->findForDevice($recipientType, $recipientKey, $appKey, $platform, $deviceId);
        $tokenSubscription = $this->subscriptionRepository->findByTokenHash($tokenHash);

        if ($tokenSubscription instanceof NotificationSubscriptionEntity
            && $deviceSubscription instanceof NotificationSubscriptionEntity
            && $tokenSubscription !== $deviceSubscription) {
            throw new \InvalidArgumentException('Push token is already registered to another subscription identity.');
        }
        if ($tokenSubscription instanceof NotificationSubscriptionEntity && !$deviceSubscription instanceof NotificationSubscriptionEntity) {
            throw new \InvalidArgumentException('Push token is already registered to another subscription identity.');
        }

        $subscription = $deviceSubscription ?? $tokenSubscription;
        $created = false;
        $previousTokenHash = null;

        if (!$subscription instanceof NotificationSubscriptionEntity) {
            $subscription = new NotificationSubscriptionEntity(
                id: self::newUuid(),
                recipientType: $recipientType,
                recipientKey: $recipientKey,
                platform: $platform,
                appKey: $appKey,
                deviceId: $deviceId,
                token: $token,
                metadata: $metadata,
                createdBy: $modifiedBy,
            );
            $this->entityManager->persist($subscription);
            $created = true;
        } else {
            $previousTokenHash = $subscription->tokenHash();
            $subscription->rotateToken($token, $modifiedBy);
            $subscription->touchSeen($modifiedBy);
        }

        if (isset($payload['expiresAt']) && is_string($payload['expiresAt']) && '' !== $payload['expiresAt']) {
            $subscription->setExpiresAt(new \DateTimeImmutable($payload['expiresAt']), $modifiedBy);
        }

        $this->entityManager->flush();

        $retargetedDispatchPlans = null === $previousTokenHash ? [] : $this->dispatchPlanService->retargetPushForSubscription(
            recipientType: $subscription->recipientType(),
            recipientKey: $subscription->recipientKey(),
            oldTokenHash: $previousTokenHash,
            newTokenHash: $subscription->tokenHash(),
            modifiedBy: $modifiedBy,
        );
        $reactivatedDispatchPlans = $this->dispatchPlanService->reactivatePushForSubscription(
            recipientType: $subscription->recipientType(),
            recipientKey: $subscription->recipientKey(),
            tokenHash: $subscription->tokenHash(),
            modifiedBy: $modifiedBy,
        );

        return self::subscriptionSummary($subscription) + [
            'created' => $created,
            'retargetedDispatchPlans' => $retargetedDispatchPlans,
            'reactivatedDispatchPlans' => $reactivatedDispatchPlans,
        ];
    }

    public function resolveActiveToken(string $tokenHash, string $platform, string $appKey): ?string
    {
        $subscription = $this->subscriptionRepository->findByTokenHash(trim($tokenHash));
        if (!$subscription instanceof NotificationSubscriptionEntity || !$subscription->enabled()) {
            return null;
        }
        if ($subscription->platform() !== $platform || $subscription->appKey() !== $appKey) {
            return null;
        }
        if ($subscription->expiresAt() instanceof \DateTimeImmutable && $subscription->expiresAt() <= new \DateTimeImmutable()) {
            return null;
        }

        return $subscription->token();
    }

    /** @return array{disabled: bool, subscription: array<string, mixed>|null, suppressedDispatchPlans: list<array<string, mixed>>} */
    public function disableInvalidSubscription(string $tokenHash, string $reasonCode, ?string $modifiedBy = null): array
    {
        $tokenHash = trim($tokenHash);
        $reasonCode = trim($reasonCode);
        if ('' === $tokenHash || '' === $reasonCode) {
            throw new \InvalidArgumentException('tokenHash and reasonCode are required.');
        }

        $subscription = $this->subscriptionRepository->findByTokenHash($tokenHash);
        if (!$subscription instanceof NotificationSubscriptionEntity) {
            return [
                'disabled' => false,
                'subscription' => null,
                'suppressedDispatchPlans' => [],
            ];
        }

        if ($subscription->enabled()) {
            $subscription->disable($modifiedBy);
            $this->entityManager->flush();
        }

        $suppressedDispatchPlans = $this->dispatchPlanService->suppressPushForInvalidSubscription(
            recipientType: $subscription->recipientType(),
            recipientKey: $subscription->recipientKey(),
            tokenHash: $subscription->tokenHash(),
            reasonCode: $reasonCode,
            modifiedBy: $modifiedBy,
        );

        return [
            'disabled' => true,
            'subscription' => self::subscriptionSummary($subscription),
            'suppressedDispatchPlans' => $suppressedDispatchPlans,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function disableSubscription(array $payload, ?string $modifiedBy = null, ?string $expectedRecipientKey = null): array
    {
        $subscription = $this->resolveSubscription($payload);
        if (!$subscription instanceof NotificationSubscriptionEntity) {
            return [
                'disabled' => false,
                'subscription' => null,
            ];
        }
        if (null !== $expectedRecipientKey && '' !== $expectedRecipientKey && $subscription->recipientKey() !== $expectedRecipientKey) {
            throw new \DomainException('Notification subscription ownership mismatch.');
        }

        if ($subscription->enabled()) {
            $subscription->disable($modifiedBy);
            $this->entityManager->flush();
        }

        return [
            'disabled' => true,
            'subscription' => self::subscriptionSummary($subscription),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function resolveSubscription(array $payload): ?NotificationSubscriptionEntity
    {
        $token = (string) ($payload['token'] ?? '');
        $tokenHash = (string) ($payload['tokenHash'] ?? '');
        if ('' !== $token) {
            $tokenHash = hash('sha256', $token);
        }
        if ('' !== $tokenHash) {
            return $this->subscriptionRepository->findByTokenHash($tokenHash);
        }

        $recipientType = RecipientType::tryFrom((string) ($payload['recipientType'] ?? 'user')) ?? RecipientType::User;
        $recipientKey = (string) ($payload['recipientKey'] ?? '');
        $platform = (string) ($payload['platform'] ?? '');
        $appKey = (string) ($payload['appKey'] ?? 'default');
        $deviceId = (string) ($payload['deviceId'] ?? '');

        if ('' === trim($recipientKey) || '' === trim($platform) || '' === trim($deviceId)) {
            return null;
        }

        return $this->subscriptionRepository->findForDevice($recipientType, $recipientKey, $appKey, $platform, $deviceId);
    }

    /** @return array<string, mixed> */
    private static function subscriptionSummary(NotificationSubscriptionEntity $subscription): array
    {
        return [
            'recipientType' => $subscription->recipientType()->value,
            'recipientKey' => $subscription->recipientKey(),
            'platform' => $subscription->platform(),
            'appKey' => $subscription->appKey(),
            'deviceId' => $subscription->deviceId(),
            'tokenHash' => $subscription->tokenHash(),
            'enabled' => $subscription->enabled(),
            'lastSeenAt' => $subscription->lastSeenAt()?->format(DATE_ATOM),
            'disabledAt' => $subscription->disabledAt()?->format(DATE_ATOM),
            'expiresAt' => $subscription->expiresAt()?->format(DATE_ATOM),
        ];
    }

    private static function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
