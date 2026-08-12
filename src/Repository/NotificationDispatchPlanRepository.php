<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationDispatchPlanEntity;
use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\NotificationDispatchStatus;
use App\Notifying\Enum\RecipientType;
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

    /**
     * @param list<string> $ids
     * @return list<NotificationDispatchPlanEntity>
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('dispatchPlan')
            ->andWhere('dispatchPlan.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('dispatchPlan.objectAudit.objectCreatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<NotificationDispatchPlanEntity>
     */
    public function listHandoffReadyPushForRecipientTarget(RecipientType $recipientType, string $recipientKey, string $target, int $limit = 100): array
    {
        if ('' === $recipientKey || '' === $target) {
            return [];
        }

        return $this->createQueryBuilder('dispatchPlan')
            ->andWhere('dispatchPlan.recipientType = :recipientType')
            ->andWhere('dispatchPlan.recipientKey = :recipientKey')
            ->andWhere('dispatchPlan.channel = :channel')
            ->andWhere('dispatchPlan.status = :status')
            ->andWhere('dispatchPlan.target = :target')
            ->setParameter('recipientType', $recipientType)
            ->setParameter('recipientKey', $recipientKey)
            ->setParameter('channel', NotificationChannel::Push)
            ->setParameter('status', NotificationDispatchStatus::HandoffReady)
            ->setParameter('target', $target)
            ->orderBy('dispatchPlan.scheduledAt', 'ASC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<NotificationDispatchPlanEntity>
     */
    public function listPolicySuppressedPushForRecipientTopic(RecipientType $recipientType, string $recipientKey, string $topic, int $limit = 100): array
    {
        if ('' === $recipientKey || '' === $topic) {
            return [];
        }

        return $this->createQueryBuilder('dispatchPlan')
            ->innerJoin('dispatchPlan.notification', 'notification')
            ->andWhere('dispatchPlan.recipientType = :recipientType')
            ->andWhere('dispatchPlan.recipientKey = :recipientKey')
            ->andWhere('notification.topic = :topic')
            ->andWhere('dispatchPlan.channel = :channel')
            ->andWhere('dispatchPlan.status = :status')
            ->andWhere('dispatchPlan.reason IN (:reasons)')
            ->setParameter('recipientType', $recipientType)
            ->setParameter('recipientKey', $recipientKey)
            ->setParameter('topic', $topic)
            ->setParameter('channel', NotificationChannel::Push)
            ->setParameter('status', NotificationDispatchStatus::Suppressed)
            ->setParameter('reasons', ['recipient-muted', 'channel-not-enabled', 'channel-disabled'])
            ->orderBy('dispatchPlan.scheduledAt', 'ASC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<NotificationDispatchPlanEntity>
     */
    public function listSuppressedPushForRecipient(RecipientType $recipientType, string $recipientKey, int $limit = 100): array
    {
        if ('' === $recipientKey) {
            return [];
        }

        return $this->createQueryBuilder('dispatchPlan')
            ->andWhere('dispatchPlan.recipientType = :recipientType')
            ->andWhere('dispatchPlan.recipientKey = :recipientKey')
            ->andWhere('dispatchPlan.channel = :channel')
            ->andWhere('dispatchPlan.status = :status')
            ->andWhere('dispatchPlan.reason = :reason')
            ->setParameter('recipientType', $recipientType)
            ->setParameter('recipientKey', $recipientKey)
            ->setParameter('channel', NotificationChannel::Push)
            ->setParameter('status', NotificationDispatchStatus::Suppressed)
            ->setParameter('reason', 'no-active-push-subscription')
            ->orderBy('dispatchPlan.scheduledAt', 'ASC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }
}
