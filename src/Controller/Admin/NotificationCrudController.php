<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use App\Notifying\Entity\NotificationEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<NotificationEntity> */
final class NotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationEntity::class;
    }
}
