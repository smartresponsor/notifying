<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationPreferenceEntity;
use App\Notifying\Enum\RecipientType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreferenceEntity>
 */
final class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreferenceEntity::class);
    }

    public function findForTopic(RecipientType $recipientType, string $recipientKey, string $topic): ?NotificationPreferenceEntity
    {
        return $this->findOneBy([
            'recipientType' => $recipientType,
            'recipientKey' => $recipientKey,
            'topic' => $topic,
        ]);
    }

    /**
     * @return list<NotificationPreferenceEntity>
     */
    public function listForRecipient(RecipientType $recipientType, string $recipientKey): array
    {
        return $this->createQueryBuilder('preference')
            ->andWhere('preference.recipientType = :recipientType')
            ->andWhere('preference.recipientKey = :recipientKey')
            ->setParameter('recipientType', $recipientType)
            ->setParameter('recipientKey', $recipientKey)
            ->orderBy('preference.topic', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
