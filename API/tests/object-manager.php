<?php

declare(strict_types=1);

// Entity manager for phpstan-doctrine. Test env, to match the container XML phpstan-symfony reads.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
