<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

abstract class ApiTestCase extends WebTestCase
{
    use Factories;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        static::bootKernel();
        $this->resetDatabase();
        $this->resetUploadedMedia();
        self::ensureKernelShutdown();
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    protected function jsonRequest(string $method, string $uri, ?array $payload = null, array $headers = []): Response
    {
        $server = array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $this->formatHeaders($headers));

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $server,
            null !== $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        );

        return $this->client->getResponse();
    }

    protected function decodeJson(Response $response): array
    {
        $content = $response->getContent();

        return json_decode(false !== $content ? $content : 'null', true, 512, JSON_THROW_ON_ERROR);
    }

    protected function login(User $user, string $password = 'Passw0rd!Passw0rd!', ?string $deviceName = null): array
    {
        $payload = [
            'email' => $user->getEmail(),
            'password' => $password,
        ];

        if (null !== $deviceName) {
            $payload['deviceName'] = $deviceName;
        }

        $response = $this->jsonRequest('POST', '/api/auth/login', $payload);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        return $this->decodeJson($response);
    }

    protected function authHeaders(string $accessToken): array
    {
        return [
            'Authorization' => 'Bearer '.$accessToken,
        ];
    }

    protected function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function resetDatabase(): void
    {
        $connection = $this->entityManager()->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        $this->dropDatabaseTables($connection, $tables);
        $this->initializeDatabaseSchema();
    }

    /**
     * @param list<string> $tables
     */
    private function dropDatabaseTables(Connection $connection, array $tables): void
    {
        if ([] === $tables) {
            return;
        }

        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach ($tables as $table) {
                $connection->executeStatement('DROP TABLE '.$platform->quoteIdentifier($table));
            }
        } finally {
            if ($platform instanceof AbstractMySQLPlatform) {
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    private function resetUploadedMedia(): void
    {
        $filesystem = new Filesystem();
        $uploadDirectory = self::getContainer()->getParameter('app.media_upload_directory');

        if (!is_string($uploadDirectory) || '' === $uploadDirectory) {
            return;
        }

        if (is_dir($uploadDirectory)) {
            $filesystem->remove($uploadDirectory);
        }

        $filesystem->mkdir($uploadDirectory, 0775);
    }

    private function initializeDatabaseSchema(): void
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $migrate = new CommandTester($application->find('doctrine:migrations:migrate'));
        $migrate->execute([
            '--no-interaction' => true,
            '--allow-no-migration' => true,
        ]);

        self::assertSame(0, $migrate->getStatusCode(), $migrate->getDisplay());

        $messenger = new CommandTester($application->find('messenger:setup-transports'));
        $messenger->execute([
            '--no-interaction' => true,
        ]);

        self::assertSame(0, $messenger->getStatusCode(), $messenger->getDisplay());
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function formatHeaders(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $normalized = strtoupper(str_replace('-', '_', $name));

            if ('CONTENT_TYPE' !== $normalized && !str_starts_with($normalized, 'HTTP_')) {
                $normalized = 'HTTP_'.$normalized;
            }

            $server[$normalized] = $value;
        }

        return $server;
    }
}
