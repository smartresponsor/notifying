<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use App\Notifying\Entity\NotificationEntity;
use App\Notifying\Entity\NotificationPreferenceEntity;
use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Entity\NotificationSubscriptionEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotifyingDashboardController extends AbstractDashboardController
{
    #[Route('/admin/notifying', name: 'notifying_admin_dashboard')]
    public function index(): Response
    {
        return new Response('Notifying back-office is connected. CRUD screens are exposed through the menu.');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Notifying');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Notification center');
        yield MenuItem::linkToCrud('Notifications', 'fa fa-bell', NotificationEntity::class);
        yield MenuItem::linkToCrud('Recipients', 'fa fa-users', NotificationRecipientEntity::class);
        yield MenuItem::linkToCrud('Preferences', 'fa fa-sliders', NotificationPreferenceEntity::class);
        yield MenuItem::linkToCrud('Subscriptions', 'fa fa-mobile-screen', NotificationSubscriptionEntity::class);
    }
}
