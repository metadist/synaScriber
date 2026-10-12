<?php

declare(strict_types=1);

// Runs inside a Synaplan backend container (tests/plugin/run.sh): Synaplan's
// vendor brings PHPUnit, Symfony and the App\ classes the plugin builds on.
$backend = getenv('SYNAPLAN_BACKEND') ?: '/var/www/backend';
require $backend.'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $roots = [
        'Plugin\\SynaScriber\\Tests\\' => __DIR__.'/Unit/',
        'Plugin\\SynaScriber\\' => dirname(__DIR__, 2).'/synascriber-plugin/backend/',
    ];
    foreach ($roots as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
