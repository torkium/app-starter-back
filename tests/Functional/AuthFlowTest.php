<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

final class AuthFlowTest extends ApiTestCase
{
    public function testRegisterLoginAndMeFlow(): void
    {
        $register = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'alice@example.test',
            'password' => 'VeryStrongPassw0rd!',
            'firstName' => 'Alice',
            'lastName' => 'Martin',
        ]);

        self::assertSame(Response::HTTP_CREATED, $register->getStatusCode(), $register->getContent());

        $login = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'alice@example.test',
            'password' => 'VeryStrongPassw0rd!',
            'deviceName' => 'MacBook Pro',
        ]);

        self::assertSame(Response::HTTP_OK, $login->getStatusCode(), $login->getContent());
        $tokens = $this->decodeJson($login);

        self::assertArrayHasKey('access_token', $tokens);
        self::assertArrayHasKey('refresh_token', $tokens);
        self::assertArrayHasKey('session_id', $tokens);

        $me = $this->jsonRequest('GET', '/api/account/me', null, $this->authHeaders($tokens['access_token']));

        self::assertSame(Response::HTTP_OK, $me->getStatusCode(), $me->getContent());
        self::assertSame('alice@example.test', $this->decodeJson($me)['email']);

        $sessions = $this->jsonRequest('GET', '/api/account/sessions', null, $this->authHeaders($tokens['access_token']));
        self::assertSame(Response::HTTP_OK, $sessions->getStatusCode(), $sessions->getContent());
        self::assertCount(1, $this->decodeJson($sessions));
    }

    public function testRegisterExistingEmailDoesNotLeakAccountExistence(): void
    {
        $firstRegister = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'existing@example.test',
            'password' => 'VeryStrongPassw0rd!',
        ]);

        self::assertSame(Response::HTTP_CREATED, $firstRegister->getStatusCode(), $firstRegister->getContent());

        $secondRegister = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'existing@example.test',
            'password' => 'AnotherStrongPassw0rd!',
        ]);

        self::assertSame(Response::HTTP_CREATED, $secondRegister->getStatusCode(), $secondRegister->getContent());
        self::assertSame(['status' => 'registered'], $this->decodeJson($secondRegister));
    }

    public function testRegisterWithoutNameFields(): void
    {
        $register = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'no-name@example.test',
            'password' => 'VeryStrongPassw0rd!',
        ]);

        self::assertSame(Response::HTTP_CREATED, $register->getStatusCode(), $register->getContent());

        $login = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'no-name@example.test',
            'password' => 'VeryStrongPassw0rd!',
        ]);
        self::assertSame(Response::HTTP_OK, $login->getStatusCode(), $login->getContent());
        $tokens = $this->decodeJson($login);

        $me = $this->jsonRequest('GET', '/api/account/me', null, $this->authHeaders($tokens['access_token']));
        $user = $this->decodeJson($me);

        self::assertSame(Response::HTTP_OK, $me->getStatusCode(), $me->getContent());
        self::assertSame('no-name@example.test', $user['email']);
        self::assertSame('Utilisateur', $user['firstName']);
        self::assertSame('', $user['lastName']);
    }
}
