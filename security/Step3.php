<?php

namespace Security;

use RuntimeException;

class Step3
{
    // Сравниваем обратимые внутренние операции и финал с потерей однозначности.
    public function run(): bool
    {
        echo "\nШаг 3. Обратные операции и граница обратимости\n";
        echo "Идея: обратное число отменяет умножение по модулю.\n";
        echo "Маленький пример: 3 · 171 = 513 = 2·256 + 1, значит 171 — обратное к 3 по модулю 256.\n";
        echo "В проекте модуль 2³²: обратное существует для каждого нечётного множителя.\n";
        $mirror = new Mirror();
        $inverse = new Inverse($mirror);
        echo "Расчёт: b ← b·(2−a·b) mod 2³²; пять уточнений от b=1 дают 32 верных бита.\n";
        foreach (array_merge([Mirror::ODD_PRIME], Mirror::AVALANCHE_MULTIPLIERS) as $multiplier) {
            $back = $inverse->inverseOdd($multiplier);
            printf("  0x%08x · 0x%08x mod 2³² = %d\n", $multiplier, $back, $mirror->mul32($multiplier, $back));
        }
        echo "XOR со сдвигом: y = x ^ (x >> k); обратно x = y ^ (y >> k) ^ (y >> 2k) ^ … до сдвига 32.\n";
        echo "Поворот влево обращается поворотом вправо; операции раунда и avalanche отменяются с конца.\n";

        mt_srand(1);
        $count = 10000;
        for ($i = 0; $i < $count; $i++) {
            $state = mt_rand(0, Mirror::MASK32);
            if ($inverse->round($mirror->round($state)) !== $state) {
                throw new RuntimeException(sprintf('Обратный раунд не восстановил 0x%08x.', $state));
            }
            if ($inverse->avalanche($mirror->avalanche($state)) !== $state) {
                throw new RuntimeException(sprintf('Обратный avalanche не восстановил 0x%08x.', $state));
            }
        }
        printf("Результат, seed=1: %d случайных состояний; раунд %d/%d, avalanche %d/%d; всего %d проверок.\n", $count, $count, $count, $count, $count, 2 * $count);
        echo "Внутренние части по-прежнему обратимы. При фиксированном байте раунд намеренно биективен: не теряет информацию и не сливает разные состояния в одно.\n";

        $y = 0x12345678;
        $output = $mirror->feedForward($mirror->avalanche($y), $y);
        $restored = $inverse->avalanche($output);
        printf("Граница обратимости: y=0x%08x, выход=avalanche(y)^y=0x%08x; Inverse::avalanche(выход)=0x%08x.\n", $y, $output, $restored);
        if ($restored === $y) {
            throw new RuntimeException('Пример не показал разницу после обратной связи.');
        }
        echo "Значения различаются: обратный avalanche больше не восстанавливает y из выхода хеша.\n";
        $this->showFinalCollision($mirror);
        echo "Как читать: в 32-битном финале информация теряется один раз, в конце. Два разных y дают один выход, поэтому по нему нельзя однозначно выбрать исходное состояние и «размотать» хеш цепочкой обратных операций.\n";
        echo "Это не запрещает перебор кандидатов и не доказывает криптографическую односторонность. Найдена коллизия внутренних состояний финала, а не пара сообщений полного хеша.\n";
        echo "128 бит: S_k сохраняется до смешивания, M_k — после; выход_k = avalanche(M_k ^ длина) ^ S_k.\n";
        echo "Если брать обратную связь по M_k, четыре уравнения можно решать независимо примерно за 4 × 2³² переборов, затем обратить смешивание.\n";
        echo "Обратная связь по S_k связывает уравнения дорожек. Это не доказательство стойкости 2¹²⁸; коллизии и прообразы полного 128-битного выхода здесь не ищутся.\n";
        return true;
    }

    // Уникальные состояния-счётчики сравниваем со всеми предыдущими выходами финала.
    private function showFinalCollision(Mirror $mirror): void
    {
        echo "Поиск коллизии F(y)=avalanche(y)^y по парадоксу дней рождения: y=0,1,2,…\n";
        echo "У n состояний примерно n²/2 пар; идеальный ориентир первой коллизии — √(π/2 · 2³²) ≈ 82 137 попыток.\n";
        $seen = [];
        // Ограничиваем память и время; отсутствие пары означает невыполненную демонстрацию.
        $limit = 1000000;
        for ($y = 0; $y < $limit; $y++) {
            $value = $mirror->feedForward($mirror->avalanche($y), $y);
            if (isset($seen[$value])) {
                $first = $seen[$value];
                $firstOutput = $mirror->feedForward($mirror->avalanche($first), $first);
                $secondOutput = $mirror->feedForward($mirror->avalanche($y), $y);
                if ($first === $y || $firstOutput !== $value || $secondOutput !== $value) {
                    throw new RuntimeException('Повторная проверка коллизии финала не прошла.');
                }
                printf("y1=0x%08x, y2=0x%08x; общий результат=0x%08x; попыток: %d.\n", $first, $y, $value, $y + 1);
                printf("Повторная проверка через Mirror: y1 ≠ y2, F(y1)=0x%08x, F(y2)=0x%08x — совпадают.\n", $firstOutput, $secondOutput);
                return;
            }
            $seen[$value] = $y;
        }
        throw new RuntimeException("За $limit попыток коллизия финала не найдена; демонстрация не выполнена.");
    }
}
