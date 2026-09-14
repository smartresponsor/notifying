<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use App\Notifying\Entity\NotificationPreferenceEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<NotificationPreferenceEntity> */
final class NotificationPreferenceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationPreferenceEntity::class;
    }
}
