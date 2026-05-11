<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Shared\Domain\Entity\OutboxMessage;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class OutboxMessageCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return OutboxMessage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('topic');
        yield TextField::new('channel');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('claimedAt');
        yield DateTimeField::new('deliveryStartedAt');
        yield DateTimeField::new('publishedAt');
        yield DateTimeField::new('failedAt');
        yield TextField::new('failureReason');
        yield ArrayField::new('payload')->hideOnIndex();
    }
}
