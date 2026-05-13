<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\Port\TokenGeneratorInterface;
use App\Identity\Domain\Entity\LegalDocument;
use App\Identity\Domain\Entity\RefreshToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Entity\UserActionToken;
use App\Identity\Domain\Entity\UserConsent;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Port\ClockInterface;
use App\Shared\Application\Port\MailerPortInterface;
use App\Shared\Application\Port\RealtimePublisherInterface;
use App\Shared\Application\Port\TransactionManagerInterface;
use App\Shared\Application\Port\OutboxRecorderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final readonly class IdentityManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private TransactionManagerInterface $transactionManager,
        private TokenGeneratorInterface $tokenGenerator,
        private MailerPortInterface $mailer,
        private RealtimePublisherInterface $realtimePublisher,
        private OutboxRecorderInterface $outboxRecorder,
        private ClockInterface $clock,
        private string $frontBaseUrl,
    ) {
    }

    public function register(string $email, string $password, string $firstName, string $lastName): void
    {
        $normalizedEmail = strtolower(trim($email));
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $normalizedEmail]);
        if ($existing instanceof User) {
            throw ApiProblemException::unprocessable('Un compte existe déjà avec cet email.');
        }

        $this->transactionManager->run(function () use ($normalizedEmail, $password, $firstName, $lastName): void {
            $user = new User(Uuid::v7()->toRfc4122(), $normalizedEmail, '', $firstName, $lastName, $this->clock->now());
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
            $this->entityManager->persist($user);

            $tokenValue = $this->tokenGenerator->generate();
            $token = new UserActionToken(
                Uuid::v7()->toRfc4122(),
                $user,
                $this->hashActionToken($tokenValue),
                'email_confirmation',
                $this->clock->now()->modify('+2 days'),
            );
            $this->entityManager->persist($token);

            $firstName = trim($user->getFirstName());
            $intro = 'Votre espace est presque prêt.';
            if ('' !== $firstName && 'Utilisateur' !== $firstName) {
                $intro = sprintf('Bonjour %s, votre espace est presque prêt.', $firstName);
            }
            $frontBaseUrl = rtrim($this->frontBaseUrl, '/');

            $this->mailer->queue(
                $user->getEmail(),
                'Confirmez votre email',
                'Activez votre compte pour retrouver votre espace en toute sécurité.',
                [
                    'actionUrl' => sprintf('%s/verify-email?token=%s', $frontBaseUrl, $tokenValue),
                    'logoUrl' => sprintf('%s/icons/icon-192.png', $frontBaseUrl),
                    'brandName' => 'Starter',
                    'brandTagline' => 'Votre espace applicatif',
                    'ctaLabel' => 'Confirmer mon email',
                    'eyebrow' => 'Bienvenue',
                    'intro' => $intro,
                    'description' => 'Confirmez votre adresse email pour activer votre compte et accéder à votre espace.',
                    'details' => [
                        'Votre accès reste protégé tant que cette adresse n’est pas validée.',
                        'Le lien de confirmation reste valable pendant 48 heures.',
                    ],
                    'fallbackLabel' => 'Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :',
                    'signature' => 'À très vite',
                ],
            );

            $this->outboxRecorder->record('identity.user_registered', 'default', [
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
            ]);
        });
    }

    /**
     * @return array{access_token:string, refresh_token:string, expires_in:int}
     */
    public function login(string $email, string $password, ?string $deviceName = null, ?string $userAgent = null, ?string $ipAddress = null): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => strtolower(trim($email))]);
        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw ApiProblemException::unauthorized('Identifiants invalides.');
        }

        return $this->issueTokens($user, $deviceName, $userAgent, $ipAddress);
    }

    /**
     * @return array{access_token:string, refresh_token:string, expires_in:int}
     */
    public function refresh(string $refreshTokenValue, ?string $userAgent = null, ?string $ipAddress = null): array
    {
        $token = $this->findRefreshTokenByValue($refreshTokenValue);
        if (!$token instanceof RefreshToken || !$token->isActive($this->clock->now())) {
            throw ApiProblemException::unauthorized('Refresh token invalide.');
        }

        return $this->transactionManager->run(function () use ($token, $userAgent, $ipAddress): array {
            $token->touch($this->clock->now(), $userAgent, $ipAddress);
            $token->revoke($this->clock->now(), 'rotated');

            return $this->issueTokens(
                $token->getUser(),
                $token->getDeviceName(),
                $userAgent ?? $token->getLastUsedUserAgent(),
                $ipAddress ?? $token->getLastUsedIp(),
            );
        });
    }

    public function confirmEmail(string $tokenValue): void
    {
        $token = $this->loadToken($tokenValue, 'email_confirmation');

        $this->transactionManager->run(function () use ($token): void {
            $token->markUsed($this->clock->now());
            $token->getUser()->markEmailVerified();

            $this->realtimePublisher->publish('/users/'.$token->getUser()->getId(), [
                'type' => 'email_verified',
            ], true);
        });
    }

    public function forgotPassword(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => strtolower(trim($email))]);
        if (!$user instanceof User) {
            return;
        }

        $this->transactionManager->run(function () use ($user): void {
            $tokenValue = $this->tokenGenerator->generate();
            $token = new UserActionToken(
                Uuid::v7()->toRfc4122(),
                $user,
                $this->hashActionToken($tokenValue),
                'password_reset',
                $this->clock->now()->modify('+2 hours'),
            );
            $this->entityManager->persist($token);
            $frontBaseUrl = rtrim($this->frontBaseUrl, '/');

            $this->mailer->queue(
                $user->getEmail(),
                'Réinitialisation du mot de passe',
                'Réinitialisation du mot de passe',
                [
                    'actionUrl' => sprintf('%s/reset-password?token=%s', $frontBaseUrl, $tokenValue),
                    'logoUrl' => sprintf('%s/icons/icon-192.png', $frontBaseUrl),
                    'brandName' => 'Starter',
                    'brandTagline' => 'Votre espace applicatif',
                    'ctaLabel' => 'Réinitialiser mon mot de passe',
                    'eyebrow' => 'Sécurité du compte',
                    'intro' => 'Une demande de réinitialisation vient d’être faite pour votre compte.',
                    'description' => 'Choisissez un nouveau mot de passe pour retrouver l’accès à votre espace.',
                    'details' => [
                        'Ce lien est valable pendant 2 heures.',
                        'Ignorez cet email si vous n’êtes pas à l’origine de la demande.',
                    ],
                    'fallbackLabel' => 'Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :',
                ],
            );
        });
    }

    public function resetPassword(string $tokenValue, string $password): void
    {
        $token = $this->loadToken($tokenValue, 'password_reset');

        $this->transactionManager->run(function () use ($token, $password): void {
            $token->markUsed($this->clock->now());
            $user = $token->getUser();
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        });
    }

    public function requestEmailChange(User $user, string $newEmail): void
    {
        $normalizedEmail = strtolower(trim($newEmail));
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $normalizedEmail]);
        if ($existing instanceof User && $existing->getId() !== $user->getId()) {
            throw ApiProblemException::unprocessable('Cette adresse email est déjà utilisée.');
        }

        $this->transactionManager->run(function () use ($user, $newEmail): void {
            $tokenValue = $this->tokenGenerator->generate();
            $token = new UserActionToken(
                Uuid::v7()->toRfc4122(),
                $user,
                $this->hashActionToken($tokenValue),
                'email_change',
                $this->clock->now()->modify('+1 day'),
                ['email' => strtolower(trim($newEmail))],
            );
            $this->entityManager->persist($token);
            $frontBaseUrl = rtrim($this->frontBaseUrl, '/');

            $this->mailer->queue(
                $newEmail,
                'Confirmez votre nouvelle adresse email',
                'Changement d’email',
                [
                    'actionUrl' => sprintf('%s/confirm-email-change?token=%s', $frontBaseUrl, $tokenValue),
                    'logoUrl' => sprintf('%s/icons/icon-192.png', $frontBaseUrl),
                    'brandName' => 'Starter',
                    'brandTagline' => 'Votre espace applicatif',
                    'ctaLabel' => 'Valider cette adresse',
                    'eyebrow' => 'Paramètres du compte',
                    'intro' => 'Vous avez demandé à associer cette adresse email à votre compte.',
                    'description' => 'Validez ce changement pour continuer à recevoir les notifications importantes au bon endroit.',
                    'details' => [
                        'Ce lien est valable pendant 24 heures.',
                        'Votre adresse actuelle reste active tant que cette confirmation n’est pas terminée.',
                    ],
                    'fallbackLabel' => 'Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :',
                ],
            );
        });
    }

    /**
     * @return list<array{id:string,deviceName:?string,createdAt:string,lastUsedAt:?string,lastUsedIp:?string,lastUsedUserAgent:?string,expiresAt:string,revokedAt:?string,revokedReason:?string,isCurrent:bool}>
     */
    public function listSessions(User $user, ?string $currentSessionId = null): array
    {
        $sessions = $this->entityManager->getRepository(RefreshToken::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return array_map(
            fn (RefreshToken $session): array => [
                'id' => $session->getId(),
                'deviceName' => $session->getDeviceName(),
                'createdAt' => $session->getCreatedAt()->format(\DATE_ATOM),
                'lastUsedAt' => $session->getLastUsedAt()?->format(\DATE_ATOM),
                'lastUsedIp' => $session->getLastUsedIp(),
                'lastUsedUserAgent' => $session->getLastUsedUserAgent(),
                'expiresAt' => $session->getExpiresAt()->format(\DATE_ATOM),
                'revokedAt' => $session->getRevokedAt()?->format(\DATE_ATOM),
                'revokedReason' => $session->getRevokedReason(),
                'isCurrent' => null !== $currentSessionId && hash_equals($session->getId(), $currentSessionId),
            ],
            array_values(array_filter($sessions, static fn (mixed $session): bool => $session instanceof RefreshToken)),
        );
    }

    public function revokeSession(User $user, string $sessionId): void
    {
        $session = $this->entityManager->getRepository(RefreshToken::class)->findOneBy(['id' => $sessionId, 'user' => $user]);
        if (!$session instanceof RefreshToken) {
            throw ApiProblemException::notFound('Session introuvable.');
        }

        $this->transactionManager->run(function () use ($session): void {
            $session->revoke($this->clock->now(), 'manual_revoke');
            $this->outboxRecorder->record('identity.session_revoked', 'default', [
                'userId' => $session->getUser()->getId(),
                'sessionId' => $session->getId(),
            ]);
        });
    }

    public function revokeOtherSessions(User $user, string $currentSessionId): void
    {
        $currentSession = $this->entityManager->getRepository(RefreshToken::class)->findOneBy(['id' => $currentSessionId, 'user' => $user]);
        if (!$currentSession instanceof RefreshToken) {
            throw ApiProblemException::unprocessable('La session courante est requise pour révoquer les autres sessions.');
        }

        $sessions = $this->entityManager->getRepository(RefreshToken::class)->findBy(['user' => $user]);

        $this->transactionManager->run(function () use ($sessions, $currentSession, $user): void {
            foreach ($sessions as $session) {
                if (!$session instanceof RefreshToken || $session->getId() === $currentSession->getId() || null !== $session->getRevokedAt()) {
                    continue;
                }

                $session->revoke($this->clock->now(), 'revoke_others');
            }

            $this->outboxRecorder->record('identity.other_sessions_revoked', 'default', [
                'userId' => $user->getId(),
                'currentSessionId' => $currentSession->getId(),
            ]);
        });
    }

    /**
     * @return list<array{id:string,code:string,version:string,locale:string,title:string,content:string,publishedAt:string}>
     */
    public function listActiveLegalDocuments(?string $locale = null): array
    {
        $criteria = ['active' => true];
        if (null !== $locale && '' !== trim($locale)) {
            $criteria['locale'] = strtolower(trim($locale));
        }

        $documents = $this->entityManager->getRepository(LegalDocument::class)->findBy($criteria, ['publishedAt' => 'DESC']);

        return array_map(
            static fn (LegalDocument $document): array => [
                'id' => $document->getId(),
                'code' => $document->getCode(),
                'version' => $document->getVersion(),
                'locale' => $document->getLocale(),
                'title' => $document->getTitle(),
                'content' => $document->getContent(),
                'publishedAt' => $document->getPublishedAt()->format(\DATE_ATOM),
            ],
            array_values(array_filter($documents, static fn (mixed $document): bool => $document instanceof LegalDocument)),
        );
    }

    /**
     * @param list<string> $documentIds
     */
    public function recordConsents(User $user, array $documentIds, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        if ([] === $documentIds) {
            throw ApiProblemException::unprocessable('Au moins un document légal doit être accepté.');
        }

        $documents = $this->entityManager->getRepository(LegalDocument::class)->findBy(['id' => $documentIds, 'active' => true]);
        if (count($documents) !== count(array_unique($documentIds))) {
            throw ApiProblemException::unprocessable('Un ou plusieurs documents légaux sont invalides ou inactifs.');
        }

        $this->transactionManager->run(function () use ($user, $documents, $ipAddress, $userAgent): void {
            foreach ($documents as $document) {
                if (!$document instanceof LegalDocument) {
                    continue;
                }

                $existing = $this->entityManager->getRepository(UserConsent::class)->findOneBy([
                    'user' => $user,
                    'document' => $document,
                ]);

                if ($existing instanceof UserConsent) {
                    continue;
                }

                $consent = new UserConsent(
                    Uuid::v7()->toRfc4122(),
                    $user,
                    $document,
                    $this->clock->now(),
                    $ipAddress,
                    $userAgent,
                );
                $this->entityManager->persist($consent);

                $this->outboxRecorder->record('identity.consent_recorded', 'default', [
                    'userId' => $user->getId(),
                    'documentId' => $document->getId(),
                    'code' => $document->getCode(),
                    'version' => $document->getVersion(),
                ]);
            }
        });
    }

    /**
     * @return list<array<code:string,version:string,title:string,acceptedAt:string>>
     */
    public function listConsents(User $user): array
    {
        $consents = $this->entityManager->getRepository(UserConsent::class)->findBy(['user' => $user], ['acceptedAt' => 'DESC']);

        return array_map(
            static fn (UserConsent $consent): array => [
                'code' => $consent->getDocument()->getCode(),
                'version' => $consent->getDocument()->getVersion(),
                'title' => $consent->getDocument()->getTitle(),
                'acceptedAt' => $consent->getAcceptedAt()->format(\DATE_ATOM),
            ],
            array_values(array_filter($consents, static fn (mixed $consent): bool => $consent instanceof UserConsent)),
        );
    }

    public function confirmEmailChange(string $tokenValue): void
    {
        $token = $this->loadToken($tokenValue, 'email_change');
        $payload = $token->getPayload();
        $newEmail = is_array($payload) && isset($payload['email']) && is_string($payload['email']) ? $payload['email'] : null;
        if (null === $newEmail) {
            throw new \RuntimeException('Token invalide.');
        }

        $this->transactionManager->run(function () use ($token, $newEmail): void {
            $token->markUsed($this->clock->now());
            $token->getUser()->changeEmail($newEmail);
        });
    }

    /**
     * @return array{id:string,email:string,firstName:string,lastName:string,emailVerified:bool,roles:list<string>}
     */
    public function viewUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'emailVerified' => $user->isEmailVerified(),
            'roles' => $user->getRoles(),
        ];
    }

    /**
     * @return array{access_token:string, refresh_token:string, expires_in:int, refresh_token_expires_at:string, session_id:string}
     */
    private function issueTokens(User $user, ?string $deviceName = null, ?string $userAgent = null, ?string $ipAddress = null): array
    {
        return $this->transactionManager->run(function () use ($user, $deviceName, $userAgent, $ipAddress): array {
            $refreshTokenValue = $this->tokenGenerator->generate();
            $sessionId = Uuid::v7()->toRfc4122();
            $expiresAt = $this->clock->now()->modify('+7 days');

            $this->entityManager->persist(new RefreshToken(
                $sessionId,
                $user,
                $this->hashRefreshToken($refreshTokenValue),
                $expiresAt,
                $this->clock->now(),
                $deviceName,
                $userAgent,
                $ipAddress,
            ));

            return [
                'access_token' => $this->jwtManager->createFromPayload($user, ['sid' => $sessionId]),
                'refresh_token' => $refreshTokenValue,
                'expires_in' => 3600,
                'refresh_token_expires_at' => $expiresAt->format(\DATE_ATOM),
                'session_id' => $sessionId,
            ];
        });
    }

    private function loadToken(string $tokenValue, string $type): UserActionToken
    {
        $token = $this->entityManager->getRepository(UserActionToken::class)->findOneBy([
            'tokenHash' => $this->hashActionToken($tokenValue),
            'type' => $type,
        ]);

        if (!$token instanceof UserActionToken || !$token->isConsumable($this->clock->now())) {
            throw ApiProblemException::unprocessable('Token invalide ou expiré.');
        }

        return $token;
    }

    private function hashRefreshToken(string $refreshTokenValue): string
    {
        return hash('sha256', $refreshTokenValue);
    }

    private function hashActionToken(string $tokenValue): string
    {
        return hash('sha256', $tokenValue);
    }

    private function findRefreshTokenByValue(string $refreshTokenValue): ?RefreshToken
    {
        $tokenHash = $this->hashRefreshToken($refreshTokenValue);
        $token = $this->entityManager->getRepository(RefreshToken::class)->findOneBy(['tokenHash' => $tokenHash]);

        return $token instanceof RefreshToken ? $token : null;
    }

    private function findUserRefreshTokenByValue(User $user, string $refreshTokenValue): ?RefreshToken
    {
        $tokenHash = $this->hashRefreshToken($refreshTokenValue);
        $token = $this->entityManager->getRepository(RefreshToken::class)->findOneBy([
            'tokenHash' => $tokenHash,
            'user' => $user,
        ]);

        return $token instanceof RefreshToken ? $token : null;
    }
}
