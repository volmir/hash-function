<?php

require __DIR__ . '/../autoload.php';

use Tests\DataSets;
use Tests\Metrics;

// Известные синтетические результаты проверяют измеритель, а не конкретный хеш.
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    echo 'Seed: 1' . PHP_EOL;
    foreach (DataSets::COLLISION_SETS as $kind => $set) {
        $seen = [];
        $first = iterator_to_array(DataSets::inputs($kind, 1000, 1));
        $check($first === iterator_to_array(DataSets::inputs($kind, 1000, 1)), 'Набор невоспроизводим: ' . $kind);
        foreach ($first as $input) {
            $check(!isset($seen[$input]), 'Повтор входа: ' . $kind);
            $seen[$input] = true;
        }
    }
    $check(iterator_to_array(DataSets::inputs('random', 10, 1)) !== iterator_to_array(DataSets::inputs('random', 10, 2)), 'Seed не меняет случайные данные.');
    $check(Metrics::chiSquare([25, 25, 25, 25], 25) === 0.0, 'Неверный χ² равномерного набора.');
    $check(Metrics::chiSquare([100, 0, 0, 0], 25) === 300.0, 'Неверный χ² неравномерного набора.');

    foreach ([32, 128] as $bits) {
        // Изменение любого бита входа меняет ровно половину выходных битов.
        // Среднее идеально, но половина битов никогда не меняется: тест обязан провалиться.
        $half = static function (string $input) use ($bits): string {
            $parity = 0;
            for ($i = 0; $i < strlen($input); $i++) {
                for ($bit = 0; $bit < 8; $bit++) {
                    $parity ^= (ord($input[$i]) >> $bit) & 1;
                }
            }
            return str_repeat('0', intdiv($bits, 8)) . str_repeat($parity ? 'f' : '0', intdiv($bits, 8));
        };
        $a = Metrics::avalanche($half, $bits, 1);
        foreach (['any', 'last'] as $scenario) {
            $check($a[$scenario]['mean'] === 0.5 && $a[$scenario]['min'] === 0.0
                && $a[$scenario]['max'] === 1.0 && !$a[$scenario]['pass'], 'Среднее скрыло слабые биты.');
        }

        $constant = static fn (string $input): string => str_repeat('0', intdiv($bits, 4));
        $c = Metrics::collisions($constant, $bits, 1, ['numbers' => ['name' => 'Восемь уникальных входов', 'count' => 8]]);
        $check($c['sets']['numbers']['collisions'] === 7 && !$c['pass'], 'Неверный подсчёт коллизий.');
        $check($c['sets']['numbers']['expected'] === 64 / 2 / (2 ** $bits), 'Неверное ожидание коллизий.');

        $zero = Metrics::distribution($constant, $bits, 1, 256);
        $check($zero['bins'][0] === 256 * intdiv($bits, 32) && !$zero['pass'], 'Потерян нулевой бин.');
        $ones = static fn (string $input): string => str_repeat('f', intdiv($bits, 4));
        $max = Metrics::distribution($ones, $bits, 1, 256);
        $check($max['bins'][255] === 256 * intdiv($bits, 32) && !$max['pass'], 'Потерян верхний конец диапазона.');

        // У синтетического выхода равномерны все проверяемые маргинальные частоты.
        // Корреляции между битами эти метрики не исследуют.
        $balanced = static function (string $input) use ($bits): string {
            $index = unpack('Nvalue', substr($input, -4))['value'];
            return str_repeat(sprintf('%02x', $index & 255), intdiv($bits, 8));
        };
        $d = Metrics::distribution($balanced, $bits, 1, 256);
        $check($d['hexChi'] === 0.0 && $d['bitChi'] === 0.0 && $d['binChi'] === 0.0
            && min($d['probabilities']) === 0.5 && max($d['probabilities']) === 0.5 && $d['pass'], 'Неверные частоты на равномерной калибровке.');
    }
    try {
        Metrics::distribution(static fn (string $input): string => 'INVALID', 32, 1, 1);
        $check(false, 'Неверный формат не отклонён.');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), 'hex-цифр'), 'Неожиданная ошибка формата.');
    }
    echo 'Результат: ' . $checks . ' проверок измерителя пройдено.' . PHP_EOL;
    echo 'Как читать: проверены формулы, крайние бины, уникальность входов и случай, когда среднее скрывает слабые биты.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'ПРОВАЛ измерителя: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
