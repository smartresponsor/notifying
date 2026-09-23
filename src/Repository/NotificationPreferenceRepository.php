<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationPreferenceEntity;
use App\Notifying\Enum\NotificationRecipientType;
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

    public function persist(NotificationPreferenceEntity $preference): void
    {
        $this->getEntityManager()->persist($preference);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function findForTopic(NotificationRecipientType $recipientType, string $recipientKey, string $topic): ?NotificationPreferenceEntity
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
    public function listForRecipient(NotificationRecipientType $recipientType, string $recipientKey): array
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
