<?php

declare(strict_types=1);

if (! extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "pdo_sqlite is required for PHPUnit (sqlite :memory:). See references/local-php-sqlite-testing.md\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';
