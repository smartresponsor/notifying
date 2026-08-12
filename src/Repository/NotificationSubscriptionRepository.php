<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationSubscriptionEntity;
use App\Notifying\Enum\RecipientType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationSubscriptionEntity>
 */
final class NotificationSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationSubscriptionEntity::class);
    }

    public function findByTokenHash(string $tokenHash): ?NotificationSubscriptionEntity
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function findForDevice(RecipientType $recipientType, string $recipientKey, string $appKey, string $platform, string $deviceId): ?NotificationSubscriptionEntity
    {
        return $this->findOneBy([
            'recipientType' => $recipientType,
            'recipientKey' => $recipientKey,
            'appKey' => $appKey,
            'platform' => $platform,
            'deviceId' => $deviceId,
        ]);
    }

    /**
     * @return list<NotificationSubscriptionEntity>
     */
    public function listActiveForRecipient(RecipientType $recipientType, string $recipientKey, ?string $appKey = null): array
    {
        $builder = $this->createQueryBuilder('subscription')
            ->andWhere('subscription.recipientType = :recipientType')
            ->andWhere('subscription.recipientKey = :recipientKey')
            ->andWhere('subscription.enabled = true')
            ->andWhere('subscription.expiresAt IS NULL OR subscription.expiresAt > :now')
            ->setParameter('recipientType', $recipientType)
            ->setParameter('recipientKey', $recipientKey)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('subscription.lastSeenAt', 'DESC');

        if (null !== $appKey) {
            $builder
                ->andWhere('subscription.appKey = :appKey')
                ->setParameter('appKey', $appKey);
        }

        return $builder->getQuery()->getResult();
    }
}
