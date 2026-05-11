<?php

declare(strict_types=1);

namespace App\Admin\Presentation\EasyAdmin;

use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email')->setFormTypeOption('mapped', false);
        yield TextField::new('firstName')->setFormTypeOption('mapped', false);
        yield TextField::new('lastName')->setFormTypeOption('mapped', false);
        yield BooleanField::new('emailVerified')->setFormTypeOption('mapped', false);
        yield Field::new('plainPassword', 'New password')
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('mapped', false)
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->onlyOnForms();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
    }

    public function createEntity(string $entityFqcn): object
    {
        return new User(
            Uuid::v7()->toRfc4122(),
            'new-user@example.test',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'New',
            'User',
            new \DateTimeImmutable(),
        );
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applySubmittedChanges($entityInstance);
            $this->hashPlainPassword($entityInstance, $this->getContext(), true);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applySubmittedChanges($entityInstance);
            $this->hashPlainPassword($entityInstance, $this->getContext(), false);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPlainPassword(User $user, ?AdminContext $context, bool $required): void
    {
        if (null === $context) {
            return;
        }

        $plainPassword = $context->getRequest()->request->all('User')['plainPassword'] ?? null;
        if (!is_string($plainPassword) || '' === trim($plainPassword)) {
            if ($required) {
                throw new \InvalidArgumentException('A password is required when creating a user from the admin.');
            }

            return;
        }

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
    }

    private function applySubmittedChanges(User $user): void
    {
        $requestData = $this->getContext()?->getRequest()->request->all('User') ?? [];
        $submittedEmail = strtolower(trim((string) ($requestData['email'] ?? $user->getEmail())));

        $user->rename(
            (string) ($requestData['firstName'] ?? $user->getFirstName()),
            (string) ($requestData['lastName'] ?? $user->getLastName()),
        );

        if ($submittedEmail !== $user->getEmail()) {
            $user->changeEmail($submittedEmail);

            return;
        }

        $user->setEmailVerified((bool) ($requestData['emailVerified'] ?? $user->isEmailVerified()));
    }
}
