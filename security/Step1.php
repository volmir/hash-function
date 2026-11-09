<?php

namespace Security;

use Hash\SimpleHash;

class Step1
{
    private array $rates = [];

    public function measureRate(int $bits): float
    {
        if (isset($this->rates[$bits])) {
            return $this->rates[$bits];
        }

        $hash = new SimpleHash();
        $method = 'get' . $bits . 'bit';
        $count = 0;
        $start = microtime(true);
        do {
            for ($i = 0; $i < 100; $i++) {
                $hash->$method('Example text');
                $count++;
            }
            $elapsed = microtime(true) - $start;
        } while ($elapsed < 1.0);
        $rate = $count / $elapsed;
        printf("%d бит: %s хешей/с (%d вызовов за %.3f с).\n", $bits, number_format($rate, 0, ',', ' '), $count, $elapsed);
        return $this->rates[$bits] = $rate;
    }

    public function run(): bool
    {
        echo "\nШаг 1. Термины и масштаб\n";
        echo "Идея: коллизия — два разных входа с одинаковым хешем.\n";
        echo "Прообраз — вход с заданным хешем; второй прообраз — другой вход с хешем заданного входа.\n";
        echo "Расчёт: для идеального n-битного хеша масштаб поиска любой коллизии — 2^(n/2), прообраза — 2^n.\n";
        echo "32 бита: коллизия 2^16 = 65 536; прообраз 2^32 ≈ 4,3·10^9.\n";
        echo "128 бит: коллизия 2^64 ≈ 1,8·10^19; прообраз 2^128 ≈ 3,4·10^38.\n";

        echo "Результат: измеряем на фиксированном входе длиной 12 байт около секунды для каждого метода.\n";
        foreach ([32, 128] as $bits) {
            $rate = $this->measureRate($bits);
            foreach (['коллизия' => 2 ** ($bits / 2), 'прообраз' => 2 ** $bits] as $name => $attempts) {
                $seconds = $attempts / $rate;
                printf("  %s: %.3g секунд = %.3g часов = %.3g лет при этой скорости.\n", $name, $seconds, $seconds / 3600, $seconds / (365.25 * 24 * 3600));
            }
        }
        echo "Как читать: это масштаб идеального перебора на одном измеренном устройстве, не доказательство стойкости SimpleHash.\n";
        echo "Скорость зависит от входа и окружения; реальная конструкция может иметь более дешёвые атаки.\n";
        return true;
    }
}
