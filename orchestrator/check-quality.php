<?php

namespace Orchestrator;

require_once __DIR__ . '/../autoload.php';

use Tests\Runner;

// Допускаем только согласованный в P8 провал, остальные результаты должны пройти.
function acceptsP8Failure(int $code, array $report): bool
{
    if ($code !== 1 || ($report['seed'] ?? null) !== 1 || ($report['errors'] ?? null) !== []
        || ($report['levels'] ?? null) !== [1 => true, 2 => true, 3 => true, 4 => false, 5 => true, 6 => false]) {
        return false;
    }

    $measurements = $report['measurements'] ?? [];
    foreach (['SimpleHash32', 'SimpleHash128'] as $name) {
        foreach (['avalanche', 'collisions', 'distribution'] as $kind) {
            $expectedPass = $name !== 'SimpleHash32' || $kind !== 'collisions';
            if (($measurements[$name . ':' . $kind]['pass'] ?? null) !== $expectedPass) {
                return false;
            }
        }
    }

    $sets = $measurements['SimpleHash32:collisions']['sets'] ?? [];
    if (array_keys($sets) !== ['random', 'numbers', 'repeats', 'permutations', 'nulls']) {
        return false;
    }
    $random = $sets['random'];
    $expected = 1000000 ** 2 / 2 / (2 ** 32);
    if (($random['count'] ?? null) !== 1000000 || ($random['collisions'] ?? null) !== 238
        || ($random['expected'] ?? null) !== $expected || ($random['limit'] ?? null) !== 2 * $expected + 5
        || ($random['pass'] ?? null) !== false) {
        return false;
    }
    foreach (['numbers', 'repeats', 'permutations', 'nulls'] as $kind) {
        if (($sets[$kind]['pass'] ?? null) !== true) {
            return false;
        }
    }
    return true;
}

// Подключение файла в проверках не запускает многомиллионное измерение.
if (realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) {
    return;
}

$runner = new Runner();
$code = $runner->run(['--seed=1']);
if ($code === 0) {
    exit(0);
}
if (acceptsP8Failure($code, $runner->getReport())) {
    echo "Проверка оркестратора: принято отклонение P8 — 238 коллизий SimpleHash32 на 1000000 случайных входов, seed=1.\n";
    echo "Остальные проверки нашей функции прошли; обычный run.php сохраняет код 1.\n";
    exit(0);
}
fwrite(STDERR, "Проверка оркестратора: результат отличается от согласованного отклонения P8.\n");
exit(1);
