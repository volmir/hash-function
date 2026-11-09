<?php

require __DIR__ . '/autoload.php';

if ($argc < 2 || $argc > 3 || ($argc === 3 && $argv[2] !== '32' && $argv[2] !== '128')) {
    fwrite(STDERR, "Использование: php hash.php \"<строка>\" [32|128]\n");
    exit(2);
}

$hash = new Hash\SimpleHash();
echo ($argc === 3 && $argv[2] === '32' ? $hash->get32bit($argv[1]) : $hash->get128bit($argv[1])) . "\n";
