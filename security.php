<?php

require __DIR__ . '/autoload.php';

if ($argc > 2 || ($argc === 2 && !preg_match('/\A--step=([1-6])\z/', $argv[1], $matches))) {
    fwrite(STDERR, "Использование: php security.php [--step=1..6] (одно число от 1 до 6)\n");
    exit(2);
}

$steps = $argc === 2 ? [(int) $matches[1]] : range(1, 6);
$success = true;
try {
    foreach ($steps as $step) {
        $class = 'Security\\Step' . $step;
        $result = (new $class())->run();
        $success = $result && $success;
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Ошибка проверки: " . $error->getMessage() . "\n");
    exit(1);
}
exit($success ? 0 : 1);
