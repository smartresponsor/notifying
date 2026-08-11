<?php

declare(strict_types=1);

namespace App\Notifying\Repository;

use App\Notifying\Entity\NotificationPreferenceEntity;
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
}
