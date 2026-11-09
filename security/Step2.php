<?php

namespace Security;

use Hash\SimpleHash;

class Step2
{
    public function __construct(private Step1 $step1)
    {
    }

    public function run(): bool
    {
        echo "\nШаг 2. Поиск коллизии 32 бит\n";
        $hash = new SimpleHash();
        $seen = [];
        $start = microtime(true);
        for ($i = 0; ; $i++) {
            $input = 'x' . $i;
            $value = $hash->get32bit($input);
            if (isset($seen[$value])) {
                $first = $seen[$value];
                break;
            }
            $seen[$value] = $input;
        }
        $elapsed = microtime(true) - $start;
        $attempts = $i + 1;
        printf("Первый вход: %s (hex: %s)\n", $first, bin2hex($first));
        printf("Второй вход: %s (hex: %s)\n", $input, bin2hex($input));
        printf("Общий хеш: %s; попыток: %d; время: %.3f с.\n", $value, $attempts, $elapsed);
        $firstHash = $hash->get32bit($first);
        $secondHash = $hash->get32bit($input);
        $valid = $first !== $input && $firstHash === $value && $secondHash === $value;
        printf("Проверка публичным get32bit(): %s и %s — %s.\n", $firstHash, $secondHash, $valid ? 'совпадают' : 'ошибка');

        $expected = sqrt(M_PI / 2 * 2 ** 32);
        printf("Для идеального равномерного хеша ожидание: √(π/2 · 2³²) = √(π/2 · 4 294 967 296) ≈ %.0f попыток; фактически %d (%.2f от ожидания).\n", $expected, $attempts, $attempts / $expected);
        echo "Это примерно в 52 тысячи раз меньше 2³²: каждый новый хеш сравнивается со всеми предыдущими, поэтому число пар растёт примерно как n²/2.\n";
        echo "Односторонний финал от коллизий не защищает. Для идеального хеша общий масштаб поиска определяется длиной выхода; недостатки конструкции могут снизить цену.\n";
        echo "128 бит: коллизию не ищем; идеальный масштаб 2⁶⁴ ≈ 1,845·10¹⁹ попыток. Скорость измеряется методом шага 1 на входе длиной 12 байт.\n";
        $rate = $this->step1->measureRate(128);
        $seconds = 2 ** 64 / $rate;
        printf("При скорости %s хешей/с это ≈ %.3g секунд, или %.3g лет на одном устройстве.\n", number_format($rate, 0, ',', ' '), $seconds, $seconds / (365.25 * 24 * 3600));
        echo "Это оценка для идеального хеша, а не доказательство стойкости SimpleHash; скорость зависит от входа и окружения.\n";
        return $valid;
    }
}
