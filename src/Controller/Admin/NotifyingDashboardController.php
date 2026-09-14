<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin/notifying', routeName: 'notifying_admin_dashboard')]
final class NotifyingDashboardController extends AbstractDashboardController
{
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
        yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');
        yield MenuItem::linkTo(NotificationRecipientCrudController::class, 'Recipients', 'fa fa-users');
        yield MenuItem::linkTo(NotificationDispatchPlanCrudController::class, 'Dispatch Plans', 'fa fa-route');
        yield MenuItem::linkTo(NotificationPreferenceCrudController::class, 'Preferences', 'fa fa-sliders');
        yield MenuItem::linkTo(NotificationSubscriptionCrudController::class, 'Subscriptions', 'fa fa-mobile-screen');
    }
}
