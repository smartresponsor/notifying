<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationDispatchPlanEntity;
use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\NotificationDispatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationDispatchPlanEntity>
 */
final class NotificationDispatchPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDispatchPlanEntity::class);
    }

    public function findForRecipientEntryAndChannel(NotificationRecipientEntity $recipient, NotificationChannel $channel): ?NotificationDispatchPlanEntity
    {
        return $this->findOneBy([
            'recipientEntry' => $recipient,
            'channel' => $channel,
        ]);
    }

    /**
     * @return list<NotificationDispatchPlanEntity>
     */
    public function listPending(int $limit = 100): array
    {
        return $this->createQueryBuilder('dispatchPlan')
            ->andWhere('dispatchPlan.status IN (:statuses)')
            ->setParameter('statuses', [NotificationDispatchStatus::Planned, NotificationDispatchStatus::HandoffReady])
            ->orderBy('dispatchPlan.scheduledAt', 'ASC')
            ->addOrderBy('dispatchPlan.objectAudit.objectCreatedAt', 'ASC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<NotificationDispatchPlanEntity>
     */
    public function listForRecipientEntry(NotificationRecipientEntity $recipient): array
    {
        return $this->createQueryBuilder('dispatchPlan')
            ->andWhere('dispatchPlan.recipientEntry = :recipientEntry')
            ->setParameter('recipientEntry', $recipient)
            ->orderBy('dispatchPlan.channel', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
