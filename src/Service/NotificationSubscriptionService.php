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
        $subscription = $this->subscriptionRepository->findByTokenHash($tokenHash);
        $created = false;

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
            $subscription->rotateToken($token, $modifiedBy);
            $subscription->touchSeen($modifiedBy);
        }

        if (isset($payload['expiresAt']) && is_string($payload['expiresAt']) && '' !== $payload['expiresAt']) {
            $subscription->setExpiresAt(new \DateTimeImmutable($payload['expiresAt']), $modifiedBy);
        }

        $this->entityManager->flush();

        return [
            'recipientType' => $subscription->recipientType()->value,
            'recipientKey' => $subscription->recipientKey(),
            'platform' => $subscription->platform(),
            'appKey' => $subscription->appKey(),
            'deviceId' => $subscription->deviceId(),
            'tokenHash' => $subscription->tokenHash(),
            'enabled' => $subscription->enabled(),
            'created' => $created,
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
