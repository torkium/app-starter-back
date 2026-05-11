<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Admin\Domain\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AdminUserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return AdminUser::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityPermission('ROLE_SUPER_ADMIN')
            ->setEntityLabelInSingular('Admin')
            ->setEntityLabelInPlural('Admins')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield TextField::new('displayName', 'Display name');
        yield ChoiceField::new('roles')
            ->setChoices([
                'ROLE_ADMIN' => 'ROLE_ADMIN',
                'ROLE_SUPER_ADMIN' => 'ROLE_SUPER_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->hideOnForm();
        yield BooleanField::new('active')->hideOnForm();
        yield Field::new('plainPassword', 'Password')
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('mapped', false)
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->onlyOnForms();
        yield ArrayField::new('roles')->onlyOnIndex();
        yield DateTimeField::new('lastLoginAt')->hideOnForm();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
    }

    public function createEntity(string $entityFqcn): object
    {
        return new AdminUser(
            Uuid::v7()->toRfc4122(),
            'new-admin@example.test',
            '',
            'New Admin',
            ['ROLE_ADMIN'],
            new \DateTimeImmutable(),
        );
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPlainPassword($entityInstance, $this->getContext(), true);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPlainPassword($entityInstance, $this->getContext(), false);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPlainPassword(object $entityInstance, ?AdminContext $context, bool $required): void
    {
        if (!$entityInstance instanceof AdminUser || null === $context) {
            return;
        }

        $plainPassword = $context->getRequest()->request->all('AdminUser')['plainPassword'] ?? null;
        if (!is_string($plainPassword) || '' === trim($plainPassword)) {
            if ($required) {
                throw new \InvalidArgumentException('A password is required when creating an admin user from the back office.');
            }

            return;
        }

        $entityInstance->setPasswordHash($this->passwordHasher->hashPassword($entityInstance, $plainPassword));
    }
}
