<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Identity\Domain\Entity\LegalDocument;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class LegalDocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LegalDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Legal document')
            ->setEntityLabelInPlural('Legal documents')
            ->setDefaultSort(['publishedAt' => 'DESC']);
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
        yield TextField::new('code');
        yield TextField::new('version');
        yield TextField::new('locale');
        yield TextField::new('title');
        yield TextareaField::new('content')->hideOnIndex();
        yield BooleanField::new('active');
        yield DateTimeField::new('publishedAt');
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function createEntity(string $entityFqcn): object
    {
        return new LegalDocument(
            Uuid::v7()->toRfc4122(),
            'terms',
            'v1',
            'fr',
            'Untitled document',
            '',
            true,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
    }
}
