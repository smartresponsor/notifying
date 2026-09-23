<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin/notifying', routeName: 'notifying_admin_dashboard')]
final class NotificationDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return new Response('Notifying back-office is connected. Generic entity CRUD is provided by Cruding.');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Notifying');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Notification center');
    }
}
