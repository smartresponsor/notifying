<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use App\Notifying\Entity\NotificationDispatchPlanEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<NotificationDispatchPlanEntity> */
final class NotificationDispatchPlanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationDispatchPlanEntity::class;
    }
}
