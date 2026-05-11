<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Identity\Domain\Entity\UserConsent;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class UserConsentCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserConsent::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user');
        yield AssociationField::new('document');
        yield TextField::new('ipAddress');
        yield TextField::new('userAgent')->hideOnIndex();
        yield DateTimeField::new('acceptedAt');
    }
}
