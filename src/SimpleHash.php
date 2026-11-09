<?php

namespace Hash;

// Учебный хеш по байтам; криптостойкость не предполагается.
class SimpleHash
{
    private const MASK32 = 0xFFFFFFFF;
    private const ODD_PRIME = 0xA7D46BAD;
    private const ROTATION_BITS = 13;
    private const XOR_SHIFT_BITS = 15;
    // Один раунд финализатора содержит две пары «XOR со сдвигом + умножение».
    public const AVALANCHE_ROUNDS = 2;
    private const AVALANCHE_SHIFTS = [16, 13];
    private const AVALANCHE_MULTIPLIERS = [0xD59B4E93, 0xA3C79B5D];
    private const INITIAL_STATES = [
        0x73A4D1B7,
        0xC5E92F83,
        0x1D8B6A59,
        0xE2F47C31,
    ];

    // Обрабатываем каждый байт входа и возвращаем ровно восемь hex-цифр.
    public function get32bit(string $str): string
    {
        $state = $this->initState();
        $length = strlen($str);

        for ($i = 0; $i < $length; $i++) {
            $state = $this->absorbByte($state, ord($str[$i]));
        }

        $y = $state ^ ($length & self::MASK32);

        return $this->toHex($this->feedForward($this->avalanche($y), $y));
    }

    // Смешиваем четыре состояния с разными началами перед их финализацией.
    public function get128bit(string $str): string
    {
        $state1 = $this->initState(0);
        $state2 = $this->initState(1);
        $state3 = $this->initState(2);
        $state4 = $this->initState(3);
        $length = strlen($str);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($str[$i]);
            $state1 = $this->absorbByte($state1, $byte);
            $state2 = $this->absorbByte($state2, $byte);
            $state3 = $this->absorbByte($state3, $byte);
            $state4 = $this->absorbByte($state4, $byte);
        }

        // Для обратной связи сохраняем состояния до перекрёстного смешивания.
        // XOR с состояниями после смешивания позволил бы решать четыре дорожки
        // независимо (около 4 * 2^32 переборов); исходные состояния связывают уравнения.
        // Эта связь сама по себе не доказывает стойкость порядка 2^128.
        $original1 = $state1;
        $original2 = $state2;
        $original3 = $state3;
        $original4 = $state4;

        // Сложение связывает соседей через переносы; маска сохраняет арифметику mod 2^32.
        $state1 = ($state1 + $this->rotl32($state2, 7)) & self::MASK32;
        $state2 = ($state2 + $this->rotl32($state3, 11)) & self::MASK32;
        $state3 = ($state3 + $this->rotl32($state4, 17)) & self::MASK32;
        $state4 = ($state4 + $this->rotl32($state1, 23)) & self::MASK32;

        // XOR с поворотом распространяет влияние всех четырёх состояний на каждую часть.
        $state1 ^= $this->rotl32($state3, 13);
        $state2 ^= $this->rotl32($state4, 19);
        $state3 ^= $this->rotl32($state1, 7);
        $state4 ^= $this->rotl32($state2, 11);

        return $this->toHex($this->feedForward($this->mixFinal($state1, $length), $original1))
            . $this->toHex($this->feedForward($this->mixFinal($state2, $length), $original2))
            . $this->toHex($this->feedForward($this->mixFinal($state3, $length), $original3))
            . $this->toHex($this->feedForward($this->mixFinal($state4, $length), $original4));
    }

    // Задаём своё начальное значение для каждой 32-битной части.
    private function initState(int $lane = 0): int
    {
        return self::INITIAL_STATES[$lane];
    }

    // Переносим старшие биты в младшие, сохраняя все 32 бита состояния.
    private function rotl32(int $state, int $bits): int
    {
        return (($state << $bits) & self::MASK32) | ($state >> (32 - $bits));
    }

    // Разбиваем первый множитель на половины: произведения меньше 2^48 и остаются int.
    private function mul32(int $a, int $b): int
    {
        $low = (($a & 0xFFFF) * $b) & self::MASK32;
        $high = (((($a >> 16) * $b) & 0xFFFF) << 16) & self::MASK32;

        return ($low + $high) & self::MASK32;
    }

    // Подмешиваем байт через обратимый XOR перед перемешиванием состояния.
    private function absorbByte(int $state, int $byte): int
    {
        return $this->round($state ^ $byte);
    }

    // Нечётный множитель, вращение и XOR со сдвигом дают биективный раунд.
    private function round(int $state): int
    {
        $state = $this->rotl32($this->mul32($state, self::ODD_PRIME), self::ROTATION_BITS);
        $state ^= $state >> self::XOR_SHIFT_BITS;

        return $state;
    }

    // Учитываем длину в байтах и перемешиваем состояние без обратной связи.
    private function mixFinal(int $state, int $length): int
    {
        return $this->avalanche($state ^ ($length & self::MASK32));
    }

    // Единственное место, где информация теряется намеренно: XOR возвращает
    // в результат исходное состояние, поэтому «выход = f(x) ^ x» нельзя решить
    // последовательным применением обратных операций к f.
    // Делаем это один раз в конце: в каждом раунде обратная связь лишила бы его биективности.
    private function feedForward(int $mixed, int $original): int
    {
        return $mixed ^ $original;
    }

    // Рассеиваем влияние каждого входного бита по всему 32-битному состоянию.
    private function avalanche(int $state): int
    {
        for ($round = 0; $round < self::AVALANCHE_ROUNDS; $round++) {
            foreach (self::AVALANCHE_SHIFTS as $step => $shift) {
                // Сдвиг с XOR переносит влияние старших битов вниз, не теряя их.
                $state ^= $state >> $shift;
                // Умножение переносит влияние младших битов вверх через переносы.
                $state = $this->mul32($state, self::AVALANCHE_MULTIPLIERS[$step]);
            }
        }

        return $state;
    }

    // Сохраняем ведущие нули и единый формат нижнего регистра.
    private function toHex(int $state): string
    {
        return sprintf('%08x', $state);
    }

}
