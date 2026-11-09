<?php

namespace Security;

use InvalidArgumentException;
use RuntimeException;

class Inverse
{
    private int $roundMultiplier;
    private array $avalancheMultipliers = [];

    // Получаем уже сверенное зеркало и вычисляем обратные множители без хардкода.
    public function __construct(private Mirror $mirror)
    {
        $this->roundMultiplier = $this->inverseOdd(Mirror::ODD_PRIME);
        foreach (Mirror::AVALANCHE_MULTIPLIERS as $multiplier) {
            $this->avalancheMultipliers[] = $this->inverseOdd($multiplier);
        }
    }

    // Прямо: mul32(x, a). Обратно: mul32(y, b), где a*b = 1 mod 2^32.
    public function inverseOdd(int $multiplier): int
    {
        if ($multiplier < 1 || $multiplier > Mirror::MASK32 || ($multiplier & 1) === 0) {
            throw new InvalidArgumentException('Множитель должен быть нечётным 32-битным числом.');
        }

        // Для нечётного a число b=1 верно mod 2. Поправка b*(2-a*b)
        // удваивает число верных битов: 1 → 2 → 4 → 8 → 16 → 32.
        $inverse = 1;
        for ($step = 0; $step < 5; $step++) {
            $correction = (2 - $this->mirror->mul32($multiplier, $inverse)) & Mirror::MASK32;
            $inverse = $this->mirror->mul32($inverse, $correction);
        }
        if ($this->mirror->mul32($multiplier, $inverse) !== 1) {
            throw new RuntimeException('Ошибка вычисления обратного множителя.');
        }
        return $inverse;
    }

    // Прямо: y = x ^ (x >> k). Обратно: x = y ^ (y >> k) ^ (y >> 2k) ^ …
    public function undoXorShift(int $value, int $bits): int
    {
        if ($bits < 1 || $bits > 31) {
            throw new InvalidArgumentException('Сдвиг должен быть от 1 до 31.');
        }
        $state = $value;
        for ($shift = $bits; $shift < 32; $shift += $bits) {
            $state ^= $value >> $shift;
        }
        return $state;
    }

    // Прямо: rotl32(x, k). Обратно: вращаем вправо, сохраняя все биты.
    public function rotr32(int $value, int $bits): int
    {
        if ($bits < 1 || $bits > 31) {
            throw new InvalidArgumentException('Поворот должен быть от 1 до 31.');
        }
        return ($value >> $bits) | (($value << (32 - $bits)) & Mirror::MASK32);
    }

    // Прямо: умножение → поворот → XOR со сдвигом. Обращаем в обратном порядке.
    public function round(int $state): int
    {
        $state = $this->undoXorShift($state, Mirror::XOR_SHIFT_BITS);
        $state = $this->rotr32($state, Mirror::ROTATION_BITS);
        return $this->mirror->mul32($state, $this->roundMultiplier);
    }

    // Прямо: round(state ^ byte). Обратно: обратный раунд и повторный XOR байта.
    public function absorbByte(int $state, int $byte): int
    {
        return $this->round($state) ^ $byte;
    }

    // Прямо: пары «XOR со сдвигом → умножение». Обращаем пары и раунды с конца.
    public function avalanche(int $state): int
    {
        for ($round = Mirror::AVALANCHE_ROUNDS - 1; $round >= 0; $round--) {
            for ($step = count(Mirror::AVALANCHE_SHIFTS) - 1; $step >= 0; $step--) {
                $state = $this->mirror->mul32($state, $this->avalancheMultipliers[$step]);
                $state = $this->undoXorShift($state, Mirror::AVALANCHE_SHIFTS[$step]);
            }
        }
        return $state;
    }
}
