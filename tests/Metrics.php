<?php

namespace Tests;

use RuntimeException;

// Общие измерения для нашей функции, антипримера и эталонов; готовых хешей здесь нет.
class Metrics
{
    public const AVALANCHE_SAMPLES = 5000;
    public const DISTRIBUTION_SAMPLES = 100000;
    public const PROBABILITY_MIN = 0.45;
    public const PROBABILITY_MAX = 0.55;

    // Переворачиваем ровно один бит, отдельно в произвольном и последнем байте.
    public static function avalanche(callable $hash, int $bits, int $seed, int $samples = self::AVALANCHE_SAMPLES): array
    {
        $result = [];
        foreach (['any', 'last'] as $scenario) {
            mt_srand($seed);
            $changes = array_fill(0, $bits, 0);
            for ($sample = 0; $sample < $samples; $sample++) {
                $input = DataSets::randomBytes(mt_rand(1, 64));
                $position = mt_rand(0, strlen($input) - 1);
                if ($scenario === 'last') {
                    $position = strlen($input) - 1;
                }
                $changed = $input;
                $changed[$position] = chr(ord($changed[$position]) ^ (1 << mt_rand(0, 7)));
                $before = self::digest($hash, $input, $bits);
                $after = self::digest($hash, $changed, $bits);
                // XOR строк здесь намеренно побайтовый: это разность двоичных выходов.
                $difference = hex2bin($before) ^ hex2bin($after);
                for ($byte = 0; $byte < strlen($difference); $byte++) {
                    $value = ord($difference[$byte]);
                    for ($bit = 0; $bit < 8; $bit++) {
                        $changes[$byte * 8 + $bit] += ($value >> $bit) & 1;
                    }
                }
            }
            $probabilities = array_map(static fn (int $n): float => $n / $samples, $changes);
            $mean = array_sum($changes) / ($samples * $bits);
            $min = min($probabilities);
            $max = max($probabilities);
            $result[$scenario] = [
                'samples' => $samples, 'mean' => $mean, 'min' => $min, 'max' => $max,
                'probabilities' => $probabilities,
                'pass' => $samples >= self::AVALANCHE_SAMPLES
                    && $mean >= self::PROBABILITY_MIN && $mean <= self::PROBABILITY_MAX
                    && $min >= self::PROBABILITY_MIN && $max <= self::PROBABILITY_MAX,
            ];
        }
        $result['pass'] = $result['any']['pass'] && $result['last']['pass'];
        return $result;
    }

    // Считаем повторные выходы на уникальных входах, храним только множество хешей.
    public static function collisions(callable $hash, int $bits, int $seed, ?array $sets = null): array
    {
        $results = [];
        $pass = true;
        foreach ($sets ?? DataSets::COLLISION_SETS as $kind => $set) {
            $seen = [];
            $collisions = 0;
            $count = 0;
            foreach (DataSets::inputs($kind, $set['count'], $seed) as $input) {
                $value = self::digest($hash, $input, $bits);
                // Целые ключи для 32 бит и двоичные для 128 бит уменьшают расход памяти.
                $key = $bits === 32 ? (int) hexdec($value) : hex2bin($value);
                if (isset($seen[$key])) {
                    $collisions++;
                } else {
                    $seen[$key] = true;
                }
                $count++;
            }
            $expected = $count * $count / 2 / (2 ** $bits);
            $limit = 2 * $expected + 5;
            $results[$kind] = [
                'name' => $set['name'], 'count' => $count, 'collisions' => $collisions,
                'expected' => $expected, 'limit' => $limit, 'pass' => $collisions <= $limit,
            ];
            $pass = $pass && $results[$kind]['pass'];
            unset($seen);
        }
        return ['sets' => $results, 'pass' => $pass];
    }

    // Считаем частоты цифр, каждой битовой позиции и бинов полного 32-битного диапазона.
    public static function distribution(callable $hash, int $bits, int $seed, int $samples = self::DISTRIBUTION_SAMPLES): array
    {
        $digits = array_fill(0, 16, 0);
        $positions = array_fill(0, intdiv($bits, 4), array_fill(0, 16, 0));
        $ones = array_fill(0, $bits, 0);
        $lanes = intdiv($bits, 32);
        $bins = array_fill(0, 256, 0);
        $laneBins = array_fill(0, $lanes, array_fill(0, 256, 0));
        foreach (DataSets::inputs('random', $samples, $seed) as $input) {
            $binary = hex2bin(self::digest($hash, $input, $bits));
            for ($byte = 0; $byte < strlen($binary); $byte++) {
                $value = ord($binary[$byte]);
                $high = $value >> 4;
                $low = $value & 15;
                $digits[$high]++;
                $digits[$low]++;
                $positions[$byte * 2][$high]++;
                $positions[$byte * 2 + 1][$low]++;
                for ($bit = 0; $bit < 8; $bit++) {
                    $ones[$byte * 8 + $bit] += ($value >> $bit) & 1;
                }
                if ($byte % 4 === 0) {
                    // Старший байт задаёт бин: 0 включает ноль, 255 включает 0xFFFFFFFF.
                    $bins[$value]++;
                    $laneBins[intdiv($byte, 4)][$value]++;
                }
            }
        }
        $hexChi = self::chiSquare($digits, $samples * intdiv($bits, 4) / 16);
        $positionChi = array_map(static fn (array $counts): float => self::chiSquare($counts, $samples / 16), $positions);
        $bitChi = array_map(static fn (int $n): float => self::chiSquare([$n, $samples - $n], $samples / 2), $ones);
        $binChi = self::chiSquare($bins, $samples * $lanes / 256);
        $laneChi = array_map(static fn (array $counts): float => self::chiSquare($counts, $samples / 256), $laneBins);
        $probabilities = array_map(static fn (int $n): float => $n / $samples, $ones);
        $pass = $hexChi <= self::chiLimit(15) && max($positionChi) <= self::chiLimit(15)
            && array_sum($bitChi) <= self::chiLimit($bits) && max($bitChi) <= self::chiLimit(1)
            && $binChi <= self::chiLimit(255) && max($laneChi) <= self::chiLimit(255);
        return [
            'samples' => $samples, 'digits' => $digits, 'probabilities' => $probabilities,
            'hexChi' => $hexChi, 'positionChi' => max($positionChi),
            'bitChi' => array_sum($bitChi), 'worstBitChi' => max($bitChi),
            'binChi' => $binChi, 'laneChi' => max($laneChi), 'bins' => $bins, 'pass' => $pass,
        ];
    }

    // χ² = сумма (наблюдаемое − ожидаемое)^2 / ожидаемое, без библиотек статистики.
    public static function chiSquare(array $counts, float $expected): float
    {
        $sum = 0.0;
        foreach ($counts as $count) {
            $sum += ($count - $expected) ** 2 / $expected;
        }
        return $sum;
    }

    // Консервативный учебный порог, а не точный квантиль или вычисление p-value.
    public static function chiLimit(int $degrees): float
    {
        return $degrees + 6 * sqrt(2 * $degrees) + 18;
    }

    // Неверный формат нельзя принять за хорошую статистику: сразу останавливаем измерение.
    private static function digest(callable $hash, string $input, int $bits): string
    {
        $value = $hash($input);
        if (!is_string($value) || preg_match('/\A[0-9a-f]{' . intdiv($bits, 4) . '}\z/', $value) !== 1) {
            throw new RuntimeException('Хеш должен возвращать ' . intdiv($bits, 4) . ' hex-цифр нижнего регистра.');
        }
        return $value;
    }
}
