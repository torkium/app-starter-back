<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Billing\Domain\Entity\UserSubscription;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class UserSubscriptionCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserSubscription::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user');
        yield AssociationField::new('plan');
        yield TextField::new('status');
        yield TextField::new('stripeCustomerId');
        yield TextField::new('stripeSubscriptionId');
        yield DateTimeField::new('currentPeriodStart');
        yield DateTimeField::new('currentPeriodEnd');
        yield BooleanField::new('cancelAtPeriodEnd');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('updatedAt');
    }
}
