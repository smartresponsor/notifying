<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationRecipientEntity;
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
}
