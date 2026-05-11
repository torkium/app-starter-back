<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Identity\Domain\Entity\RefreshToken;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class RefreshTokenCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return RefreshToken::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user');
        yield TextField::new('deviceName');
        yield TextField::new('lastUsedIp');
        yield TextField::new('lastUsedUserAgent')->hideOnIndex();
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('lastUsedAt');
        yield DateTimeField::new('expiresAt');
        yield DateTimeField::new('revokedAt');
        yield TextField::new('revokedReason');
    }
}
