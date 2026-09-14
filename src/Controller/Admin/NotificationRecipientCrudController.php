<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use App\Notifying\Entity\NotificationRecipientEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/** @extends AbstractCrudController<NotificationRecipientEntity> */
final class NotificationRecipientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationRecipientEntity::class;
    }
}
