<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Billing\Domain\Entity\BillingEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class BillingEventCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return BillingEvent::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user');
        yield TextField::new('externalId');
        yield TextField::new('type');
        yield DateTimeField::new('occurredAt');
        yield DateTimeField::new('createdAt');
        yield ArrayField::new('payload')->hideOnIndex();
    }
}
