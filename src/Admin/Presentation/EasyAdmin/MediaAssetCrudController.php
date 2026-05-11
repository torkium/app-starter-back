<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Media\Domain\Entity\MediaAsset;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class MediaAssetCrudController extends AbstractReadOnlyCrudController
{
    public static function getEntityFqcn(): string
    {
        return MediaAsset::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user');
        yield TextField::new('filename');
        yield TextField::new('mimeType');
        yield IntegerField::new('size');
        yield TextField::new('status');
        yield TextField::new('purpose');
        yield TextField::new('objectKey')->hideOnIndex();
        yield TextField::new('previewUrl')->hideOnIndex();
        yield TextField::new('checksumSha256')->hideOnIndex();
        yield DateTimeField::new('uploadTokenExpiresAt')->hideOnIndex();
        yield DateTimeField::new('uploadedAt');
        yield DateTimeField::new('completedAt');
        yield DateTimeField::new('createdAt');
    }
}
