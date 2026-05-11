<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$privateKey = dirname(__DIR__).'/config/jwt/private.pem';
$publicKey = dirname(__DIR__).'/config/jwt/public.pem';

if (!is_file($privateKey) || !is_file($publicKey)) {
    if (!is_dir(dirname($privateKey))) {
        mkdir(dirname($privateKey), 0775, true);
    }

    $resource = openssl_pkey_new([
        'private_key_bits' => 4096,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if (false === $resource) {
        throw new RuntimeException('Unable to generate JWT keys for tests.');
    }

    openssl_pkey_export($resource, $privatePem);
    $publicDetails = openssl_pkey_get_details($resource);
    if (!is_array($publicDetails) || !isset($publicDetails['key'])) {
        throw new RuntimeException('Unable to extract public JWT key for tests.');
    }

    file_put_contents($privateKey, $privatePem);
    file_put_contents($publicKey, $publicDetails['key']);
    chmod($privateKey, 0600);
}
