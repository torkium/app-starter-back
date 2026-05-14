<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Shared\Domain\Entity\OutboxMessage;
use App\Shared\Application\Message\DispatchOutboxMessage;
use App\Shared\Infrastructure\Http\SensitiveValueSanitizer;
use App\Shared\Infrastructure\Outbox\DispatchOutboxMessageHandler;
use App\Shared\Infrastructure\Outbox\OutboxRecorder;
use App\Shared\Infrastructure\Outbox\OutboxPayloadProtector;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Uid\Uuid;

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

    public function testRealtimeOutboxMessageIsPublishedByHandler(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'shared.realtime.update', 'realtime', [
            'topic' => '/events/test',
            'payload' => ['hello' => 'world'],
        ], new \DateTimeImmutable());
        $message->claim($claimToken, new \DateTimeImmutable());

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        $handler = new DispatchOutboxMessageHandler(
            $this->entityManager(),
            self::getContainer()->get(\App\Shared\Application\Port\ClockInterface::class),
            self::getContainer()->get(MailerInterface::class),
            new class implements HubInterface {
                public function getPublicUrl(): string
                {
                    return 'https://mercure.example.test/.well-known/mercure';
                }

                public function getFactory(): ?TokenFactoryInterface
                {
                    return null;
                }

                public function publish(Update $update): string
                {
                    return 'published';
                }
            },
            self::getContainer()->get(OutboxPayloadProtector::class),
        );
        $handler(new DispatchOutboxMessage($id, $claimToken));
        $this->entityManager()->clear();

        /** @var OutboxMessage $stored */
        $stored = $this->entityManager()->find(OutboxMessage::class, $id);
        self::assertInstanceOf(OutboxMessage::class, $stored);
        self::assertTrue($stored->isPublished());
        self::assertNull($stored->getFailedAt());
    }

    public function testDefaultOutboxMessageIsInternalOnly(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'outbox.example.completed', 'default', [
            'sourceUrl' => 'https://example.test/source.csv',
        ], new \DateTimeImmutable());
        $message->claim($claimToken, new \DateTimeImmutable());

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        $handler = new DispatchOutboxMessageHandler(
            $this->entityManager(),
            self::getContainer()->get(\App\Shared\Application\Port\ClockInterface::class),
            self::getContainer()->get(MailerInterface::class),
            new class implements HubInterface {
                public function getPublicUrl(): string
                {
                    return 'https://mercure.example.test/.well-known/mercure';
                }

                public function getFactory(): ?TokenFactoryInterface
                {
                    return null;
                }

                public function publish(Update $update): string
                {
                    throw new \RuntimeException('Default outbox messages must not be published to Mercure.');
                }
            },
            self::getContainer()->get(OutboxPayloadProtector::class),
        );

        $handler(new DispatchOutboxMessage($id, $claimToken));
        $this->entityManager()->clear();

        /** @var OutboxMessage $stored */
        $stored = $this->entityManager()->find(OutboxMessage::class, $id);
        self::assertInstanceOf(OutboxMessage::class, $stored);
        self::assertTrue($stored->isPublished());
        self::assertNull($stored->getFailedAt());
    }

    public function testInvalidRealtimeOutboxMessageIsMarkedFailed(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'shared.realtime.update', 'realtime', [
            'payload' => ['hello' => 'world'],
        ], new \DateTimeImmutable());
        $message->claim($claimToken, new \DateTimeImmutable());

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        self::getContainer()->get(DispatchOutboxMessageHandler::class)(new DispatchOutboxMessage($id, $claimToken));

        $this->entityManager()->clear();
        /** @var OutboxMessage $stored */
        $stored = $this->entityManager()->find(OutboxMessage::class, $id);
        self::assertInstanceOf(OutboxMessage::class, $stored);
        self::assertFalse($stored->isPublished());
        self::assertNotNull($stored->getFailedAt());
        self::assertStringContainsString('topic', (string) $stored->getFailureReason());
    }

    public function testTransientRealtimeFailureIsReleasedForRetry(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'shared.realtime.update', 'realtime', [
            'topic' => '/events/test',
            'payload' => ['hello' => 'world'],
        ], new \DateTimeImmutable());
        $message->claim($claimToken, new \DateTimeImmutable());

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        $handler = new DispatchOutboxMessageHandler(
            $this->entityManager(),
            self::getContainer()->get(\App\Shared\Application\Port\ClockInterface::class),
            self::getContainer()->get(MailerInterface::class),
            new class implements HubInterface {
                public function getPublicUrl(): string
                {
                    return 'https://mercure.example.test/.well-known/mercure';
                }

                public function getFactory(): ?TokenFactoryInterface
                {
                    return null;
                }

                public function publish(Update $update): string
                {
                    throw new \RuntimeException('Mercure unavailable');
                }
            },
            self::getContainer()->get(OutboxPayloadProtector::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mercure unavailable');

        try {
            $handler(new DispatchOutboxMessage($id, $claimToken));
        } finally {
            $this->entityManager()->clear();
            /** @var OutboxMessage $stored */
            $stored = $this->entityManager()->find(OutboxMessage::class, $id);
            self::assertInstanceOf(OutboxMessage::class, $stored);
            self::assertFalse($stored->isPublished());
            self::assertFalse($stored->isFailed());
            self::assertNull($stored->getClaimToken());
            self::assertNull($stored->getDeliveryStartedAt());
            self::assertSame(1, $stored->getAttemptCount());
            self::assertNotNull($stored->getNextAttemptAt());
        }
    }

    public function testMailPayloadIsRedactedAfterSuccessfulDelivery(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'shared.mail.transactional', 'mail', [
            'to' => 'user@example.test',
            'subject' => 'Reset',
            'template' => 'Reset password',
            'context' => [
                'from' => 'noreply@example.test',
                'actionUrl' => 'https://front.example.test/reset?token=very-secret-token',
                'nested' => ['refreshToken' => 'also-secret'],
            ],
        ], new \DateTimeImmutable());
        $message->claim($claimToken, new \DateTimeImmutable());

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        self::getContainer()->get(DispatchOutboxMessageHandler::class)(new DispatchOutboxMessage($id, $claimToken));

        $this->entityManager()->clear();
        /** @var OutboxMessage $stored */
        $stored = $this->entityManager()->find(OutboxMessage::class, $id);
        self::assertInstanceOf(OutboxMessage::class, $stored);
        self::assertTrue($stored->isPublished());
        self::assertTrue($stored->getPayload()['redacted']);
        self::assertSame('mail_payload_protected', $stored->getPayload()['reason']);
        self::assertStringNotContainsString('user@example.test', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('very-secret-token', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
    }

    public function testQueuedMailPayloadIsEncryptedAtRestBeforeDelivery(): void
    {
        $recorder = self::getContainer()->get(OutboxRecorder::class);
        $recorder->record('shared.mail.transactional', 'mail', [
            'to' => 'user@example.test',
            'subject' => 'Reset',
            'template' => 'Reset password',
            'context' => [
                'actionUrl' => 'https://front.example.test/reset?token=very-secret-token',
            ],
        ]);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        /** @var OutboxMessage $message */
        $message = $this->entityManager()->getRepository(OutboxMessage::class)->findOneBy(['topic' => 'shared.mail.transactional']);
        self::assertInstanceOf(OutboxMessage::class, $message);
        self::assertTrue($message->getPayload()['_encrypted'] ?? false);
        self::assertStringNotContainsString('very-secret-token', json_encode($message->getPayload(), JSON_THROW_ON_ERROR));
    }

    public function testLegacyPlainMailPayloadIsEncryptedAgainAfterTransientFailure(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'shared.mail.transactional', 'mail', [
            'to' => 'user@example.test',
            'subject' => 'Reset',
            'template' => 'Reset password',
            'context' => [
                'actionUrl' => 'https://front.example.test/reset?token=very-secret-token',
            ],
        ], new \DateTimeImmutable());
        $message->claim($claimToken, new \DateTimeImmutable());

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        $handler = new DispatchOutboxMessageHandler(
            $this->entityManager(),
            self::getContainer()->get(\App\Shared\Application\Port\ClockInterface::class),
            new class implements MailerInterface {
                public function send(RawMessage $message, ?Envelope $envelope = null): void
                {
                    throw new \RuntimeException('SMTP unavailable');
                }
            },
            self::getContainer()->get(HubInterface::class),
            self::getContainer()->get(OutboxPayloadProtector::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP unavailable');

        try {
            $handler(new DispatchOutboxMessage($id, $claimToken));
        } finally {
            $this->entityManager()->clear();
            /** @var OutboxMessage $stored */
            $stored = $this->entityManager()->find(OutboxMessage::class, $id);
            self::assertInstanceOf(OutboxMessage::class, $stored);
            self::assertFalse($stored->isPublished());
            self::assertFalse($stored->isFailed());
            self::assertTrue($stored->getPayload()['_encrypted'] ?? false);
            self::assertStringNotContainsString('user@example.test', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('very-secret-token', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
        }
    }

    public function testInvalidMailPayloadIsRedactedBeforePermanentFailure(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'shared.mail.transactional', 'mail', [
            'to' => 'user@example.test',
            'subject' => 'Reset',
            'context' => [
                'actionUrl' => 'https://front.example.test/reset?token=very-secret-token&signature=signed',
            ],
        ], new \DateTimeImmutable());
        $message->claim($claimToken, new \DateTimeImmutable());

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        self::getContainer()->get(DispatchOutboxMessageHandler::class)(new DispatchOutboxMessage($id, $claimToken));

        $this->entityManager()->clear();
        /** @var OutboxMessage $stored */
        $stored = $this->entityManager()->find(OutboxMessage::class, $id);
        self::assertInstanceOf(OutboxMessage::class, $stored);
        self::assertFalse($stored->isPublished());
        self::assertTrue($stored->isFailed());
        self::assertTrue($stored->getPayload()['redacted']);
        self::assertSame('mail_payload_protected', $stored->getPayload()['reason']);
        self::assertStringNotContainsString('user@example.test', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('very-secret-token', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
    }

    public function testTimedOutMailPayloadIsReleasedForRetryByConsumeCommand(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $protectedPayload = self::getContainer()->get(OutboxPayloadProtector::class)->protect([
            'to' => 'user@example.test',
            'subject' => 'Reset',
            'template' => 'Reset password',
            'context' => [
                'actionUrl' => 'https://front.example.test/reset?token=very-secret-token',
            ],
        ]);
        $message = new OutboxMessage($id, 'shared.mail.transactional', 'mail', $protectedPayload, new \DateTimeImmutable('2026-05-12 10:00:00'));
        $message->claim($claimToken, new \DateTimeImmutable('2026-05-12 10:00:00'));
        $message->startDelivery(new \DateTimeImmutable('2020-01-01 10:00:00'));

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:outbox:consume'));
        self::assertSame(0, $tester->execute(['--batch' => 10]), $tester->getDisplay());

        $this->entityManager()->clear();
        /** @var OutboxMessage $stored */
        $stored = $this->entityManager()->find(OutboxMessage::class, $id);
        self::assertInstanceOf(OutboxMessage::class, $stored);
        self::assertFalse($stored->isFailed());
        self::assertNull($stored->getDeliveryStartedAt());
        self::assertNull($stored->getClaimToken());
        self::assertNotNull($stored->getNextAttemptAt());
        self::assertTrue($stored->getPayload()['_encrypted'] ?? false);
        self::assertStringNotContainsString('very-secret-token', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
    }

    public function testTimedOutLegacyPlainMailPayloadIsRedactedAndFailedByConsumeCommand(): void
    {
        $id = Uuid::v7()->toRfc4122();
        $claimToken = Uuid::v7()->toRfc4122();
        $message = new OutboxMessage($id, 'shared.mail.transactional', 'mail', [
            'to' => 'user@example.test',
            'subject' => 'Reset',
            'template' => 'Reset password',
            'context' => [
                'actionUrl' => 'https://front.example.test/reset?token=very-secret-token',
            ],
        ], new \DateTimeImmutable('2026-05-12 10:00:00'));
        $message->claim($claimToken, new \DateTimeImmutable('2026-05-12 10:00:00'));
        $message->startDelivery(new \DateTimeImmutable('2020-01-01 10:00:00'));

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:outbox:consume'));
        self::assertSame(0, $tester->execute(['--batch' => 10]), $tester->getDisplay());

        $this->entityManager()->clear();
        /** @var OutboxMessage $stored */
        $stored = $this->entityManager()->find(OutboxMessage::class, $id);
        self::assertInstanceOf(OutboxMessage::class, $stored);
        self::assertTrue($stored->isFailed());
        self::assertNull($stored->getDeliveryStartedAt());
        self::assertNull($stored->getClaimToken());
        self::assertNull($stored->getNextAttemptAt());
        self::assertTrue($stored->getPayload()['redacted']);
        self::assertSame('mail_payload_protected', $stored->getPayload()['reason']);
        self::assertStringNotContainsString('user@example.test', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('very-secret-token', json_encode($stored->getPayload(), JSON_THROW_ON_ERROR));
    }

    public function testSensitiveValueSanitizerScrubsSecretsEmbeddedInUrlValues(): void
    {
        $sanitized = (new SensitiveValueSanitizer())->sanitizeArray([
            'successUrl' => 'https://front.example.test/callback?code=abc&signature=secret&ok=1',
            'sourceUrl' => 'https://provider.example.test/export?api_key=secret&key=another&apikey=third&ok=1',
            'redirectUrl' => 'https://bucket.example.test/source?X-Amz-Credential=credential&X-Amz-Signature=signature&ok=1',
        ]);

        self::assertSame('https://front.example.test/callback?code=***&signature=***&ok=1', $sanitized['successUrl']);
        self::assertSame('https://provider.example.test/export?api_key=***&key=***&apikey=***&ok=1', $sanitized['sourceUrl']);
        self::assertSame('https://bucket.example.test/source?X-Amz-Credential=***&X-Amz-Signature=***&ok=1', $sanitized['redirectUrl']);
    }
}
