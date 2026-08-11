<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationEntity>
 */
final class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationEntity::class);
    }

    public function findByCorrelationId(string $correlationId): ?NotificationEntity
    {
        return $this->findOneBy(['correlationId' => $correlationId]);
    }

    /**
     * @return list<NotificationEntity>
     */
    public function listRecentBySource(string $sourceComponent, string $eventName, int $limit = 50): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.sourceComponent = :sourceComponent')
            ->andWhere('notification.eventName = :eventName')
            ->setParameter('sourceComponent', $sourceComponent)
            ->setParameter('eventName', $eventName)
            ->orderBy('notification.objectAudit.objectCreatedAt', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)))
            ->getQuery()
            ->getResult();
    }
}
