<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Notification\Domain\Entity\PushSubscription;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class PushSubscriptionCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return PushSubscription::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user');
        yield TextField::new('endpoint');
        yield TextField::new('p256dh')->hideOnIndex();
        yield TextField::new('auth')->hideOnIndex();
        yield IntegerField::new('expirationTime');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('updatedAt');
    }
}
