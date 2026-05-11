<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Shared\Domain\Entity\OutboxMessage;
use App\Shared\Infrastructure\Outbox\OutboxRecorder;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class OutboxConsumeCommandTest extends ApiTestCase
{
    public function testOutboxConsumeCommandClaimsAndDispatchesPendingMessages(): void
    {
        $recorder = self::getContainer()->get(OutboxRecorder::class);
        $recorder->record('test.topic', 'default', ['hello' => 'world']);
        $this->entityManager()->flush();

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:outbox:consume'));
        $exitCode = $tester->execute(['--batch' => 10]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Dispatched 1 outbox message(s).', $tester->getDisplay());

        $this->entityManager()->clear();

        /** @var OutboxMessage $message */
        $message = $this->entityManager()->getRepository(OutboxMessage::class)->findOneBy(['topic' => 'test.topic']);
        self::assertInstanceOf(OutboxMessage::class, $message);
        self::assertFalse($message->isPublished());
        self::assertNull($message->getPublishedAt());
        self::assertNotNull($message->getClaimToken());
    }
}
