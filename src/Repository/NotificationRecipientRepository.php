<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Enum\NotificationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationRecipientEntity>
 */
final class NotificationRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRecipientEntity::class);
    }

    /**
     * @return list<NotificationRecipientEntity>
     */
    public function listInbox(string $recipientKey, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('recipient')
            ->addSelect('notification')
            ->innerJoin('recipient.notification', 'notification')
            ->andWhere('recipient.recipientKey = :recipientKey')
            ->andWhere('recipient.status <> :archived')
            ->setParameter('recipientKey', $recipientKey)
            ->setParameter('archived', NotificationStatus::Archived)
            ->orderBy('recipient.objectAudit.objectCreatedAt', 'DESC')
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, min(100, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function countUnread(string $recipientKey): int
    {
        return (int) $this->createQueryBuilder('recipient')
            ->select('COUNT(recipient.id)')
            ->andWhere('recipient.recipientKey = :recipientKey')
            ->andWhere('recipient.status = :status')
            ->setParameter('recipientKey', $recipientKey)
            ->setParameter('status', NotificationStatus::New)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<string> $ids
     * @return list<NotificationRecipientEntity>
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('recipient')
            ->andWhere('recipient.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
