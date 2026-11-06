<?php

namespace Tests;

use InvalidArgumentException;
use SimpleHash\SimpleCryptoHash;
use Throwable;

class Runner
{
    private const LEVELS = [
        1 => ['Смоук', 'Формат 8/32 hex и совпадение с эталонными векторами.'],
        2 => ['Детерминизм', 'Повторные вызовы должны давать одинаковый хеш одного входа.'],
        3 => ['Лавина', 'Изменение выходных битов при перевороте одного входного бита.'],
        4 => ['Коллизии', 'Число коллизий рядом с ожиданием по парадоксу дней рождения.'],
        5 => ['Распределение', 'Равномерность по полному диапазону 32 бит с помощью χ².'],
        6 => ['Сравнение', 'Результаты рядом с доступными промышленными хешами.'],
    ];

    private int $seed = 1;
    private array $measurements = [];
    private const READINGS = [
        1 => 'Формат и эталоны должны совпасть, включая ведущие нули; это проверка работоспособности.',
        2 => 'Все сравнения должны совпасть; повтор, новый экземпляр и порядок не должны влиять на результат.',
        3 => 'Идеал: среднее 50%, каждый бит 0,50. Норма: среднее и все биты 0,45…0,55; иначе ПЛОХО. Последний символ здесь означает последний байт.',
        4 => 'Считаем повторные выходы на уникальных входах; ожидание n²/(2·2^bits). Норма: не больше 2·ожидание + 5. Малое ожидание ничего не доказывает.',
        5 => 'Меньше χ² — ближе к равномерности. Порог df + 6√(2df) + 18 — учебный ориентир, не точный p-value; превышение означает ПЛОХО.',
        6 => 'Все строки измерены на одинаковых данных. Слабые эталоны и антипример — наблюдения; код провала определяется результатами SimpleCryptoHash 32 бит.',
    ];

    // Разбираем опции и запускаем уровни по порядку, сохраняя итоговый код возврата.
    public function run(array $arguments): int
    {
        try {
            $options = $this->parseOptions($arguments);
        } catch (InvalidArgumentException $error) {
            fwrite(STDERR, 'Ошибка параметров: ' . $error->getMessage() . PHP_EOL
                . 'Запуск: php run.php [--level=1..6] [--seed=N]' . PHP_EOL);
            return 2;
        }

        $this->seed = $options['seed'];
        $this->measurements = [];
        echo 'Seed: ' . $this->seed . PHP_EOL;
        $levels = $options['level'] === null ? array_keys(self::LEVELS) : [$options['level']];
        $passed = 0;
        $failed = 0;

        foreach ($levels as $level) {
            [$name, $purpose] = self::LEVELS[$level];
            echo PHP_EOL . 'Уровень ' . $level . ': ' . $name . PHP_EOL;
            echo 'Что проверяем: ' . $purpose . PHP_EOL;

            // Один seed даёт те же данные и при отдельном, и при общем запуске уровня.
            mt_srand($options['seed']);
            try {
                $success = match ($level) {
                    1 => $this->smoke(),
                    2 => $this->determinism(),
                    3 => $this->avalanche(),
                    4 => $this->collisions(),
                    5 => $this->distribution(),
                    6 => $this->comparison(),
                };
            } catch (Throwable $error) {
                fwrite(STDERR, 'Ошибка уровня ' . $level . ': ' . $error->getMessage() . PHP_EOL);
                echo 'Результат: ПРОВАЛ — проверка завершилась исключением.' . PHP_EOL;
                $success = false;
            }

            echo 'Как читать: ' . self::READINGS[$level] . PHP_EOL;
            if ($success) {
                $passed++;
            } else {
                $failed++;
            }
        }

        echo PHP_EOL . 'Итог: пройдено ' . $passed . ', провалено ' . $failed . '.' . PHP_EOL;

        if ($failed > 0) {
            return 1;
        }

        return 0;
    }

    // Строгая проверка аргументов не даёт незаметно запустить другой уровень из-за опечатки.
    private function parseOptions(array $arguments): array
    {
        $options = ['level' => null, 'seed' => 1];
        $seen = [];
        foreach ($arguments as $argument) {
            if (preg_match('/\A--(level|seed)=(.*)\z/s', $argument, $matches) !== 1) {
                throw new InvalidArgumentException('Неизвестный аргумент: ' . $argument);
            }

            $name = $matches[1];
            if (isset($seen[$name])) {
                throw new InvalidArgumentException('Опция --' . $name . ' указана повторно.');
            }

            $value = filter_var($matches[2], FILTER_VALIDATE_INT);
            if ($value === false) {
                throw new InvalidArgumentException('--' . $name . ' должно быть целым числом в диапазоне PHP int.');
            }
            $options[$name] = $value;
            $seen[$name] = true;
        }

        if ($options['level'] !== null && !isset(self::LEVELS[$options['level']])) {
            throw new InvalidArgumentException('--level должно быть от 1 до 6.');
        }

        return $options;
    }

    // Результаты невелики; повторно используем их в уровне 6 без повторения миллионных прогонов.
    private function measure(string $name, string $kind, callable $hash, int $bits): array
    {
        $key = $name . ':' . $kind;
        if (!isset($this->measurements[$key])) {
            $this->measurements[$key] = match ($kind) {
                'avalanche' => Metrics::avalanche($hash, $bits, $this->seed),
                'collisions' => Metrics::collisions($hash, $bits, $this->seed),
                'distribution' => Metrics::distribution($hash, $bits, $this->seed),
            };
        }
        return $this->measurements[$key];
    }

    // Оба варианта используют тот же код измерений; отличаются только вызов и ширина выхода.
    private function ourHashes(): array
    {
        $hash = new SimpleCryptoHash();
        return [
            'SimpleCryptoHash32' => ['hash' => [$hash, 'get'], 'bits' => 32],
            'SimpleCryptoHash128' => ['hash' => [$hash, 'get128bit'], 'bits' => 128],
        ];
    }

    // Эталонные hash() существуют только здесь; остальные уровни не зависят от их наличия.
    private function comparison(): bool
    {
        $hash = new SimpleCryptoHash();
        $algorithms = [
            'SimpleCryptoHash32' => [$hash, 'get'],
            'getStringSum' => [$hash, 'getStringSum'],
        ];
        $available = array_fill_keys(hash_algos(), true);
        foreach (['crc32b', 'fnv1a32', 'murmur3a', 'xxh32', 'md5', 'sha256'] as $algorithm) {
            if (!isset($available[$algorithm])) {
                echo 'ПРОПУСК эталона ' . $algorithm . ': отсутствует в hash_algos().' . PHP_EOL;
                continue;
            }
            $algorithms[$algorithm] = static fn (string $input): string => substr(hash($algorithm, $input), 0, 8);
        }
        $rows = [];
        $weak = [];
        $ownPass = false;
        foreach ($algorithms as $name => $function) {
            echo 'Измеряем ' . $name . ' (32 бит), seed=' . $this->seed . '…' . PHP_EOL;
            $avalanche = $this->measure($name, 'avalanche', $function, 32);
            $collisions = $this->measure($name, 'collisions', $function, 32);
            $distribution = $this->measure($name, 'distribution', $function, 32);
            $pass = $avalanche['pass'] && $collisions['pass'] && $distribution['pass'];
            if (!$pass) {
                $weak[] = $name;
            }
            if ($name === 'SimpleCryptoHash32') {
                $ownPass = $pass;
            }
            $cells = [$name];
            foreach (['any', 'last'] as $scenario) {
                $a = $avalanche[$scenario];
                $cells[] = sprintf('%.2f%% [%.4f…%.4f]', 100 * $a['mean'], $a['min'], $a['max']);
            }
            foreach ($collisions['sets'] as $c) {
                $cells[] = sprintf('%d/%.5g%s', $c['collisions'], $c['expected'], $c['pass'] ? '' : ' !');
            }
            $cells[] = sprintf('%.2f/%.2f/%.2f/%.2f/%.2f', $distribution['hexChi'], $distribution['positionChi'],
                $distribution['bitChi'], $distribution['worstBitChi'], $distribution['binChi']);
            $cells[] = $pass ? 'НОРМА' : 'ПЛОХО';
            $rows[] = $cells;
        }
        echo 'Результат: общие измерения уровней 3–5, ' . Metrics::AVALANCHE_SAMPLES
            . ' переворотов на сценарий, ' . Metrics::DISTRIBUTION_SAMPLES . ' входов распределения.' . PHP_EOL;
        echo '| Алгоритм | Лавина: любой байт, среднее [min…max] | Последний байт | C/E: случайные | C/E: числа | C/E: повторы | C/E: перестановки | C/E: \0 | χ² hex/позиция/Σбит/максбит/бин | Оценка |' . PHP_EOL;
        echo '|---|---|---|---|---|---|---|---|---|---|' . PHP_EOL;
        foreach ($rows as $cells) {
            echo '| ' . implode(' | ', $cells) . ' |' . PHP_EOL;
        }
        echo 'C/E: коллизии / ожидание; ! — больше 2E+5. Норма лавины: среднее и все биты 0,45…0,55.' . PHP_EOL;
        printf("Пороги χ² по колонкам: %.2f/%.2f/%.2f/%.2f/%.2f.\n",
            Metrics::chiLimit(15), Metrics::chiLimit(15), Metrics::chiLimit(32), Metrics::chiLimit(1), Metrics::chiLimit(255));
        echo 'Вывод: SimpleCryptoHash 32 бит ' . ($ownPass ? 'прошёл выбранные ориентиры.' : 'не прошёл выбранные ориентиры.')
            . ' Слабости по выбранным критериям: ' . ($weak === [] ? 'не обнаружены' : implode(', ', $weak))
            . '. Криптостойкость из этих результатов не следует.' . PHP_EOL;
        return $ownPass;
    }

    private function distribution(): bool
    {
        $pass = true;
        echo 'Результат: ' . Metrics::DISTRIBUTION_SAMPLES . ' уникальных случайных входов;'
            . ' 256 бинов покрывают 0x00000000…0xFFFFFFFF каждой 32-битной части.' . PHP_EOL;
        foreach ($this->ourHashes() as $name => $algorithm) {
            $row = $this->measure($name, 'distribution', $algorithm['hash'], $algorithm['bits']);
            echo $name . PHP_EOL . 'Hex-цифры (идеал 6,25%): ';
            foreach ($row['digits'] as $digit => $count) {
                printf('%x=%.3f%% ', $digit, 100 * $count / array_sum($row['digits']));
            }
            echo PHP_EOL . 'P(1) по позициям: байты слева направо, внутри байта от младшего бита к старшему.' . PHP_EOL;
            foreach (array_chunk($row['probabilities'], 16, true) as $chunk) {
                foreach ($chunk as $bit => $probability) {
                    printf('%d=%.4f ', $bit, $probability);
                }
                echo PHP_EOL;
            }
            printf("χ² hex(df=15)=%.2f / %.2f; худшая hex-позиция=%.2f / %.2f\n",
                $row['hexChi'], Metrics::chiLimit(15), $row['positionChi'], Metrics::chiLimit(15));
            printf("χ² биты Σ(df=%d)=%.2f / %.2f; худший бит(df=1)=%.2f / %.2f\n",
                $algorithm['bits'], $row['bitChi'], Metrics::chiLimit($algorithm['bits']), $row['worstBitChi'], Metrics::chiLimit(1));
            printf("χ² бины(df=255)=%.2f / %.2f; худшая 32-битная часть=%.2f / %.2f\n",
                $row['binChi'], Metrics::chiLimit(255), $row['laneChi'], Metrics::chiLimit(255));
            echo 'Интерпретация: ' . ($row['pass'] ? 'НОРМА — явной неравномерности на этом наборе не обнаружено.'
                : 'ПЛОХО — хотя бы одна статистика превысила учебный порог.') . PHP_EOL;
            $pass = $pass && $row['pass'];
        }
        return $pass;
    }

    private function collisions(): bool
    {
        $pass = true;
        echo 'Результат: случайные строки = 8 случайных байтов + 4 байта уникального индекса.' . PHP_EOL;
        foreach ($this->ourHashes() as $name => $algorithm) {
            $result = $this->measure($name, 'collisions', $algorithm['hash'], $algorithm['bits']);
            foreach ($result['sets'] as $row) {
                printf("%s, %s: n=%d, коллизий %d, ожидается %.6g, порог %.2f — %s\n",
                    $name, $row['name'], $row['count'], $row['collisions'], $row['expected'], $row['limit'],
                    $row['pass'] ? 'НОРМА' : 'ПЛОХО');
            }
            $pass = $pass && $result['pass'];
        }
        echo 'Повторы ограничены 2048 длинами: миллион длин потребовал бы около 500 млрд байт обработки.' . PHP_EOL;
        return $pass;
    }

    private function avalanche(): bool
    {
        $pass = true;
        echo 'Результат: ' . Metrics::AVALANCHE_SAMPLES . ' выборок на сценарий, раундов финализатора '
            . SimpleCryptoHash::AVALANCHE_ROUNDS . '.' . PHP_EOL;
        foreach ($this->ourHashes() as $name => $algorithm) {
            $result = $this->measure($name, 'avalanche', $algorithm['hash'], $algorithm['bits']);
            foreach (['any' => 'любой байт', 'last' => 'последний байт'] as $scenario => $label) {
                $row = $result[$scenario];
                printf("%s, %s: среднее %.2f%%, min/max P(изменения) %.4f…%.4f — %s\n",
                    $name, $label, 100 * $row['mean'], $row['min'], $row['max'], $row['pass'] ? 'НОРМА' : 'ПЛОХО');
            }
            $pass = $pass && $result['pass'];
        }
        return $pass;
    }

    // Сравниваем повторы, новые экземпляры и два порядка с исходными результатами и векторами.
    private function determinism(): bool
    {
        $vectors = require __DIR__ . '/vectors.php';
        $inputs = array_column($vectors, 'input');
        for ($i = 0; $i < 128; $i++) {
            $inputs[] = DataSets::randomBytes(mt_rand(1, 64));
        }
        $hash = new SimpleCryptoHash();
        $expected = [];
        $checks = 0;
        $failed = 0;
        $check = static function (string $actual, string $wanted, string $context) use (&$checks, &$failed): void {
            $checks++;
            if ($actual !== $wanted) {
                $failed++;
                if ($failed <= 5) {
                    fwrite(STDERR, $context . ': ожидалось ' . $wanted . ', получено ' . $actual . PHP_EOL);
                }
            }
        };
        foreach ($inputs as $index => $input) {
            foreach (['get' => 'hash32', 'get128bit' => 'hash128'] as $method => $key) {
                $expected[$index][$method] = $hash->$method($input);
                if (isset($vectors[$index])) {
                    $check($expected[$index][$method], $vectors[$index][$key], 'Вектор ' . $index . ', ' . $method);
                }
                for ($repeat = 0; $repeat < 5; $repeat++) {
                    $check($hash->$method($input), $expected[$index][$method], 'Повтор ' . $index . ', ' . $method);
                    $check((new SimpleCryptoHash())->$method($input), $expected[$index][$method], 'Новый экземпляр ' . $index . ', ' . $method);
                }
            }
        }
        $order = array_keys($inputs);
        for ($i = count($order) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        }
        foreach ([array_reverse(array_keys($inputs)), $order] as $indices) {
            foreach ($indices as $index) {
                foreach (['get128bit', 'get'] as $method) {
                    $check($hash->$method($inputs[$index]), $expected[$index][$method], 'Порядок ' . $index . ', ' . $method);
                }
            }
        }
        echo 'Результат: входов ' . count($inputs) . ', совпало ' . ($checks - $failed) . '/' . $checks
            . ', ошибок ' . $failed . '.' . PHP_EOL;
        return $failed === 0;
    }

    // Проверяем оба публичных метода на пустом, текстовом, бинарном и длинном входах из векторов.
    private function smoke(): bool
    {
        if (PHP_INT_SIZE < 8) {
            echo 'Результат: ПРОВАЛ — для арифметики алгоритма нужен 64-битный PHP.' . PHP_EOL;
            return false;
        }

        $hash = new SimpleCryptoHash();
        $vectors = require __DIR__ . '/vectors.php';
        $checks = 0;
        $formats = 0;
        $matches = 0;

        foreach ($vectors as $vector) {
            foreach (['get' => ['hash32', 8], 'get128bit' => ['hash128', 32]] as $method => [$key, $length]) {
                $checks++;
                try {
                    $actual = $hash->$method($vector['input']);
                } catch (Throwable $error) {
                    fwrite(STDERR, $vector['name'] . ', ' . $method . ': ' . $error->getMessage() . PHP_EOL);
                    continue;
                }

                if (is_string($actual) && preg_match('/\A[0-9a-f]{' . $length . '}\z/', $actual) === 1) {
                    $formats++;
                } else {
                    fwrite(STDERR, $vector['name'] . ', ' . $method . ': неверный формат, получено '
                        . var_export($actual, true) . PHP_EOL);
                }

                if ($actual === $vector[$key]) {
                    $matches++;
                } else {
                    fwrite(STDERR, $vector['name'] . ', ' . $method . ': ожидалось '
                        . $vector[$key] . ', получено ' . var_export($actual, true) . PHP_EOL);
                }
            }
        }

        $success = $checks > 0 && $formats === $checks && $matches === $checks;
        echo 'Результат: ' . ($success ? 'ПРОЙДЕН' : 'ПРОВАЛ') . '; входов ' . count($vectors)
            . ', формат ' . $formats . '/' . $checks . ', эталоны ' . $matches . '/' . $checks . '.' . PHP_EOL;

        return $success;
    }
}
