<?php

namespace Tests;

class DataSets
{
    public const COLLISION_SETS = [
        'random' => ['name' => 'Случайные строки', 'count' => 1000000],
        'numbers' => ['name' => 'Числа 0…1000000', 'count' => 1000001],
        'repeats' => ['name' => 'Повторы a, длины 1…2048', 'count' => 2048],
        'permutations' => ['name' => 'Перестановки abcdefghij', 'count' => 1000000],
        'nulls' => ['name' => 'Бинарные строки с \\0', 'count' => 1000000],
    ];

    // Используем только mt_rand: все случайные байты воспроизводятся через mt_srand.
    public static function randomBytes(int $length): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= chr(mt_rand(0, 255));
        }
        return $result;
    }

    // Генераторы не хранят входы; все наборы уникальны по построению.
    public static function inputs(string $kind, int $count, int $seed): \Generator
    {
        mt_srand($seed);
        $letters = str_split('abcdefghij');
        for ($i = 0; $i < $count; $i++) {
            switch ($kind) {
                case 'random':
                    // Восемь случайных байтов и уникальный 32-битный индекс исключают дубликаты входа.
                    yield self::randomBytes(8) . pack('N', $i);
                    break;
                case 'numbers':
                    yield (string) $i;
                    break;
                case 'repeats':
                    yield str_repeat('a', $i + 1);
                    break;
                case 'nulls':
                    yield pack('N', $i) . "\0";
                    break;
                case 'permutations':
                    yield implode('', $letters);
                    // Следующая лексикографическая перестановка, без случайных повторов.
                    if ($i + 1 < $count) {
                        $pivot = 8;
                        while ($pivot >= 0 && $letters[$pivot] >= $letters[$pivot + 1]) {
                            $pivot--;
                        }
                        if ($pivot < 0) {
                            throw new \RuntimeException('Закончились уникальные перестановки.');
                        }
                        $next = 9;
                        while ($letters[$next] <= $letters[$pivot]) {
                            $next--;
                        }
                        [$letters[$pivot], $letters[$next]] = [$letters[$next], $letters[$pivot]];
                        for ($left = $pivot + 1, $right = 9; $left < $right; $left++, $right--) {
                            [$letters[$left], $letters[$right]] = [$letters[$right], $letters[$left]];
                        }
                    }
                    break;
                default:
                    throw new \InvalidArgumentException('Неизвестный набор: ' . $kind);
            }
        }
    }
}
