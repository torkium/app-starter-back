<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Admin\Domain\Entity\AdminUser;
use App\Tests\Factory\AdminUserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminAccessTest extends ApiTestCase
{
    public function testAdminLoginFlowAndDashboardAccess(): void
    {
        $this->loginAdmin();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Starter Back Admin', (string) $this->client->getResponse()->getContent());
    }

    public function testRoleAdminCannotAccessSuperAdminAreas(): void
    {
        $this->loginAdmin();

        $this->client->request('GET', '/admin/admin-user');
        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/admin/outbox-message');
        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/admin/user/new');
        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdminCanOpenProtectedCrudScreens(): void
    {
        $this->loginAdmin(['email' => 'root@example.test'], ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);

        $this->client->request('GET', '/admin/admin-user/new');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/user/new');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/outbox-message');
        self::assertResponseIsSuccessful();
    }

    public function testCreateAdminUserCommandCreatesAnIsolatedAdminAccount(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:admin-user:create'));
        putenv('STARTER_ADMIN_PASSWORD=Sup3rStrongAdminPass!');
        $exitCode = $tester->execute([
            'email' => 'ops@example.test',
            'display-name' => 'Ops Admin',
            '--password-env' => 'STARTER_ADMIN_PASSWORD',
            '--super-admin' => true,
        ]);
        putenv('STARTER_ADMIN_PASSWORD');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Admin user "ops@example.test" created.', $tester->getDisplay());

        $admin = $this->entityManager()->getRepository(AdminUser::class)->findOneBy(['email' => 'ops@example.test']);
        self::assertInstanceOf(AdminUser::class, $admin);
        self::assertContains('ROLE_SUPER_ADMIN', $admin->getRoles());

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($admin, 'Sup3rStrongAdminPass!'));
    }

    /**
     * @param list<string> $roles
     * @param array<string, mixed> $attributes
     */
    private function loginAdmin(array $attributes = [], array $roles = ['ROLE_ADMIN']): void
    {
        $email = (string) ($attributes['email'] ?? 'admin@example.test');

        AdminUserFactory::createOne(array_merge([
            'email' => $email,
            'displayName' => 'Starter Admin',
            'roles' => $roles,
        ], $attributes));

        $this->client->request('GET', '/admin');
        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertStringEndsWith('/admin/login', (string) $this->client->getResponse()->headers->get('Location'));

        $crawler = $this->client->request('GET', '/admin/login');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => 'AdminPassw0rd!AdminPassw0rd!',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin');

        $this->client->followRedirect();
    }
}
