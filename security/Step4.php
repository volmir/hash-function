<?php

namespace Security;

use Hash\SimpleHash;
use RuntimeException;

class Step4
{
    public function __construct(private Step1 $step1)
    {
    }

    public function run(): bool
    {
        echo "\nШаг 4. Поиск прообраза заданного хеша\n";
        $mirror = new Mirror();
        $inverse = new Inverse($mirror);
        $y = 0x12345678;
        $output = $mirror->feedForward($mirror->avalanche($y), $y);
        $restored = $inverse->avalanche($output);
        printf("Состояние y=0x%08x; выход F(y)=0x%08x; обратный avalanche от выхода=0x%08x.\n", $y, $output, $restored);
        if ($restored === $y) {
            throw new RuntimeException('Пример не показал границу обратимости.');
        }
        echo "Несовпадение: для обратного avalanche нужно avalanche(y), но выход содержит avalanche(y) ^ y, а y неизвестно. Прежняя встреча посередине с движением назад от хеша так не работает; другие атаки этим не исключены.\n";

        $hash = new SimpleHash();
        echo "Перебор первых k бит get32bit: 10 целей, seed=1…10; кандидаты — уникальные пары seed и счётчика.\n";
        foreach ([12, 16, 20] as $bits) {
            $total = 0;
            for ($seed = 1; $seed <= 10; $seed++) {
                mt_srand($seed);
                $targetInput = pack('N2', mt_rand(0, 0xffffffff), mt_rand(0, 0xffffffff));
                $target = substr($hash->get32bit($targetInput), 0, intdiv($bits, 4));
                for ($counter = 0; $counter <= 0xffffffff; $counter++) {
                    $input = pack('N2', $seed, $counter);
                    if (substr($hash->get32bit($input), 0, intdiv($bits, 4)) === $target) {
                        break;
                    }
                }
                if ($counter > 0xffffffff) {
                    throw new RuntimeException('Прообраз не найден среди кандидатов.');
                }
                $attempts = $counter + 1;
                $total += $attempts;
                printf("  k=%d, seed=%d, цель=%s, вход=%s, попыток=%d.\n", $bits, $seed, $target, bin2hex($input), $attempts);
            }
            printf("  k=%d: среднее %.1f попыток; ориентир 2^%d = %s.\n", $bits, $total / 10, $bits, number_format(2 ** $bits, 0, ',', ' '));
        }
        echo "Для равномерного независимого выхода каждый лишний бит удваивает ожидаемую цену; 10 результатов могут заметно отклоняться от среднего.\n";
        echo "32 бита: ожидается около 2^32 ≈ 4,3·10^9 попыток. Односторонность не может быть сильнее длины результата: такой перебор остаётся посильным.\n";
        $rate = $this->step1->measureRate(32);
        $seconds = 2 ** 32 / $rate;
        printf("При измеренной скорости PHP это ≈ %.3g секунд, или %.3g часов; скорость измерена на 12-байтном входе.\n", $seconds, $seconds / 3600);
        echo "Более быстрые реализации и параллельный перебор сокращают время; скорость зависит от реализации и оборудования.\n";
        echo "Для второго прообраза идеальный ориентир тот же, 2^32, при поиске другого входа. Для 128 бит идеальный ориентир — 2^128 ≈ 3,4·10^38 попыток.\n";
        echo "Это ожидания для идеального хеша, а не доказательство стойкости данной конструкции: более дешёвые атаки возможны. Полные 32- и 128-битные прообразы здесь не ищутся.\n";
        return true;
    }
}
