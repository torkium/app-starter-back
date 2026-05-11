<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Identity\Domain\Entity\UserActionToken;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class UserActionTokenCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserActionToken::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user');
        yield TextField::new('type');
        yield ArrayField::new('payload')->hideOnIndex();
        yield DateTimeField::new('expiresAt');
        yield DateTimeField::new('usedAt');
    }
}
