<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use App\Notifying\Entity\NotificationSubscriptionEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<NotificationSubscriptionEntity> */
final class NotificationSubscriptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationSubscriptionEntity::class;
    }
}
