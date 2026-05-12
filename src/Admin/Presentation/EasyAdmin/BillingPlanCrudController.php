<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Billing\Domain\Entity\BillingPlan;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class BillingPlanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BillingPlan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Billing plan')
            ->setEntityLabelInPlural('Billing plans')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::EDIT, Action::DELETE)
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code')->hideWhenUpdating();
        yield TextField::new('name');
        yield TextareaField::new('description')->hideOnIndex();
        yield IntegerField::new('amountMinor', 'Amount (minor units)');
        yield TextField::new('currency');
        yield TextField::new('intervalUnit');
        yield TextField::new('stripePriceId');
        yield BooleanField::new('active');
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function createEntity(string $entityFqcn): object
    {
        return new BillingPlan(
            Uuid::v7()->toRfc4122(),
            'starter_monthly',
            'Starter Monthly',
            null,
            1990,
            'eur',
            'month',
            true,
            new \DateTimeImmutable(),
        );
    }
}
