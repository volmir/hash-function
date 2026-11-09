<?php

// Сопоставляем пространства имён каталогам без Composer и ручных подключений классов.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Hash\\' => __DIR__ . '/src/',
        'Tests\\' => __DIR__ . '/tests/',
        'Security\\' => __DIR__ . '/security/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
});
