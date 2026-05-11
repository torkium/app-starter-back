<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'app_admin_dashboard')]
final class AdminDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Starter Back Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::section('Administration');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\AdminUserCrudController::class, 'Admins', 'fa fa-user-shield');
        }

        yield MenuItem::section('Identity');
        yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\UserCrudController::class, 'Users', 'fa fa-users');
        yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\LegalDocumentCrudController::class, 'Legal documents', 'fa fa-file-contract');

        yield MenuItem::section('Billing');
        yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\BillingPlanCrudController::class, 'Plans', 'fa fa-tags');

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::section('Support & Audit');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\UserConsentCrudController::class, 'Consents', 'fa fa-check-double');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\RefreshTokenCrudController::class, 'Refresh tokens', 'fa fa-key');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\UserActionTokenCrudController::class, 'Action tokens', 'fa fa-link');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\UserSubscriptionCrudController::class, 'Subscriptions', 'fa fa-credit-card');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\BillingEventCrudController::class, 'Billing events', 'fa fa-receipt');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\MediaAssetCrudController::class, 'Media assets', 'fa fa-photo-film');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\PushSubscriptionCrudController::class, 'Push subscriptions', 'fa fa-bell');

            yield MenuItem::section('Technique');
            yield MenuItem::linkTo(\App\Admin\Presentation\EasyAdmin\OutboxMessageCrudController::class, 'Outbox', 'fa fa-envelope-open-text');
        }

        yield MenuItem::linkToLogout('Logout', 'fa fa-sign-out-alt');
    }
}
