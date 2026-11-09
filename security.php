<?php

require __DIR__ . '/autoload.php';

if ($argc > 2 || ($argc === 2 && !preg_match('/\A--step=([1-6])\z/', $argv[1], $matches))) {
    fwrite(STDERR, "Использование: php security.php [--step=1..6] (одно число от 1 до 6)\n");
    exit(2);
}

$steps = $argc === 2 ? [(int) $matches[1]] : range(1, 6);
$success = true;
$step1 = new Security\Step1();
try {
    foreach ($steps as $step) {
        $class = 'Security\\Step' . $step;
        $instance = $step === 1 ? $step1 : (in_array($step, [2, 4], true) ? new $class($step1) : new $class());
        $result = $instance->run();
        $success = $result && $success;
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Ошибка проверки: " . $error->getMessage() . "\n");
    exit(1);
}
exit($success ? 0 : 1);
