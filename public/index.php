<?php

use App\Kernel;

$_SERVER['APP_RUNTIME_OPTIONS'] ??= $_ENV['APP_RUNTIME_OPTIONS'] ?? [];
$_SERVER['APP_RUNTIME_OPTIONS']['dotenv_path'] ??= is_file(dirname(__DIR__).'/.env') ? '.env' : '.env.example';

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
