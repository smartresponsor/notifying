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
     * Atomically claims handoff-ready plans. The conditional UPDATE is the concurrency gate:
     * only one worker can change a given row from handoff_ready to claimed.
     *
     * @return list<NotificationDispatchPlanEntity>
     */
    public function claimHandoffReady(string $claimedBy, \DateTimeImmutable $claimedAt, \DateTimeImmutable $claimExpiresAt, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement(
            'UPDATE notifying_notification_dispatch_plan SET status = :ready, claimed_by = NULL, claimed_at = NULL, claim_expires_at = NULL, object_modified_at = :modifiedAt, object_modified_by = :modifiedBy WHERE status = :claimed AND claim_expires_at IS NOT NULL AND claim_expires_at <= :now',
            [
                'ready' => NotificationDispatchStatus::HandoffReady->value,
                'modifiedAt' => $claimedAt->format('Y-m-d H:i:s'),
                'modifiedBy' => 'claim-expiry-recovery',
                'claimed' => NotificationDispatchStatus::Claimed->value,
                'now' => $claimedAt->format('Y-m-d H:i:s'),
            ],
        );

        $rows = $this->createQueryBuilder('dispatchPlan')
            ->select('dispatchPlan.id')
            ->andWhere('dispatchPlan.status = :status')
            ->andWhere('(dispatchPlan.scheduledAt IS NULL OR dispatchPlan.scheduledAt <= :claimedAt)')
            ->setParameter('status', NotificationDispatchStatus::HandoffReady)
            ->setParameter('claimedAt', $claimedAt)
            ->orderBy('dispatchPlan.scheduledAt', 'ASC')
            ->addOrderBy('dispatchPlan.objectAudit.objectCreatedAt', 'ASC')
            ->setMaxResults($limit * 2)
            ->getQuery()
            ->getArrayResult();

        $claimedIds = [];
        $connection = $this->getEntityManager()->getConnection();
        foreach ($rows as $row) {
            if (count($claimedIds) >= $limit) {
                break;
            }

            $id = (string) ($row['id'] ?? '');
            if ('' === $id) {
                continue;
            }

            $updated = $connection->executeStatement(
                'UPDATE notifying_notification_dispatch_plan SET status = :claimed, claimed_by = :claimedBy, claimed_at = :claimedAt, claim_expires_at = :claimExpiresAt, object_modified_at = :modifiedAt, object_modified_by = :modifiedBy WHERE id = :id AND status = :ready AND (scheduled_at IS NULL OR scheduled_at <= :claimedAt)',
                [
                    'claimed' => NotificationDispatchStatus::Claimed->value,
                    'claimedBy' => $claimedBy,
                    'claimedAt' => $claimedAt->format('Y-m-d H:i:s'),
                    'claimExpiresAt' => $claimExpiresAt->format('Y-m-d H:i:s'),
                    'modifiedAt' => $claimedAt->format('Y-m-d H:i:s'),
                    'modifiedBy' => $claimedBy,
                    'id' => $id,
                    'ready' => NotificationDispatchStatus::HandoffReady->value,
                ],
            );
            if (1 === $updated) {
                $claimedIds[] = $id;
            }
        }

        return $this->findByIds($claimedIds);
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
    public function listHandoffReadyPushForRecipientTopic(RecipientType $recipientType, string $recipientKey, string $topic, int $limit = 100): array
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
            ->setParameter('recipientType', $recipientType)
            ->setParameter('recipientKey', $recipientKey)
            ->setParameter('topic', $topic)
            ->setParameter('channel', NotificationChannel::Push)
            ->setParameter('status', NotificationDispatchStatus::HandoffReady)
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
