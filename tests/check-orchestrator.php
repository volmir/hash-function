<?php

require __DIR__ . '/../orchestrator/check-quality.php';

$checks = 0;

function checkOrchestrator(bool $condition, string $description): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('Провал: ' . $description);
    }
}

try {
    $expected = 1000000 ** 2 / 2 / (2 ** 32);
    $accepted = [
        'seed' => 1,
        'levels' => [1 => true, 2 => true, 3 => true, 4 => false, 5 => true, 6 => false],
        'errors' => [],
        'measurements' => [],
    ];
    foreach (['SimpleHash32', 'SimpleHash128'] as $name) {
        foreach (['avalanche', 'collisions', 'distribution'] as $kind) {
            $accepted['measurements'][$name . ':' . $kind] = ['pass' => true];
        }
    }
    $accepted['measurements']['SimpleHash32:collisions'] = [
        'pass' => false,
        'sets' => [
            'random' => ['count' => 1000000, 'collisions' => 238, 'expected' => $expected,
                'limit' => 2 * $expected + 5, 'pass' => false],
            'numbers' => ['pass' => true],
            'repeats' => ['pass' => true],
            'permutations' => ['pass' => true],
            'nulls' => ['pass' => true],
        ],
    ];
    checkOrchestrator(Orchestrator\acceptsP8Failure(1, $accepted), 'известный провал P8 разрешён');
    foreach ([0, 2, 137] as $code) {
        checkOrchestrator(!Orchestrator\acceptsP8Failure($code, $accepted), 'другой код не считается провалом P8');
    }
    foreach (array_keys($accepted['levels']) as $level) {
        $changed = $accepted;
        $changed['levels'][$level] = !$changed['levels'][$level];
        checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'изменение результата уровня ' . $level);
    }
    foreach (array_keys($accepted['measurements']) as $key) {
        $changed = $accepted;
        $changed['measurements'][$key]['pass'] = !$changed['measurements'][$key]['pass'];
        checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'новый результат измерения ' . $key);
    }
    foreach (['numbers', 'repeats', 'permutations', 'nulls'] as $kind) {
        $changed = $accepted;
        $changed['measurements']['SimpleHash32:collisions']['sets'][$kind]['pass'] = false;
        checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'провал другого набора ' . $kind);
    }
    foreach (['count' => 999999, 'collisions' => 239, 'expected' => 120.0, 'limit' => 240.0, 'pass' => true] as $key => $value) {
        $changed = $accepted;
        $changed['measurements']['SimpleHash32:collisions']['sets']['random'][$key] = $value;
        checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'изменение параметра случайного набора ' . $key);
    }
    foreach (['seed', 'levels', 'errors', 'measurements'] as $key) {
        $changed = $accepted;
        unset($changed[$key]);
        checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'неполный отчёт без ' . $key);
    }
    $changed = $accepted;
    $changed['seed'] = 42;
    checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'другой seed');
    $changed = $accepted;
    $changed['errors'][6] = 'Ошибка эталона после измерений нашей функции';
    checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'исключение при тех же итогах уровней');
    $changed = $accepted;
    unset($changed['measurements']['SimpleHash128:distribution']);
    checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'незавершённое измерение');
    $changed = $accepted;
    unset($changed['measurements']['SimpleHash32:collisions']['sets']['nulls']);
    checkOrchestrator(!Orchestrator\acceptsP8Failure(1, $changed), 'пропущенный набор коллизий');

    // Запускаем настоящую функцию Bash в локали C, где cut раньше разрывал UTF-8.
    $script = file_get_contents(__DIR__ . '/../orchestrator/run.sh');
    if (preg_match('/^summary_line\(\) \{.*?^\}/ms', $script, $match) !== 1) {
        throw new RuntimeException('Не найдена функция summary_line');
    }
    $summaryFile = tempnam(sys_get_temp_dir(), 'hash-summary-');
    try {
        foreach ([
            ['ИТОГ: Короткий итог', 'ИТОГ: Короткий итог'],
            ['ИТОГ: ' . str_repeat('я', 400), 'ИТОГ: ' . str_repeat('я', 294)],
            ['ИТОГ: ' . str_repeat('😁', 400), 'ИТОГ: ' . str_repeat('😁', 294)],
            ['ИТОГ: ' . str_repeat('a', 400), 'ИТОГ: ' . str_repeat('a', 294)],
            ["\nЗапасной русский текст\n", 'Запасной русский текст'],
            ['', '(агент не оставил итога)'],
        ] as [$input, $expectedSummary]) {
            file_put_contents($summaryFile, $input);
            $process = proc_open(
                ['bash', '-c', $match[0] . "\n" . 'summary_line "$1"', 'summary-test', $summaryFile],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
                array_merge(getenv(), ['LC_ALL' => 'C'])
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Не удалось запустить Bash');
            }
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            checkOrchestrator($code === 0 && $error === '' && $output === $expectedSummary
                && preg_match('//u', $output) === 1, 'обрезание итога без повреждения UTF-8: ' . $error);
        }
    } finally {
        unlink($summaryFile);
    }
    $progress = file_get_contents(__DIR__ . '/../orchestrator/PROGRESS.md');
    checkOrchestrator(preg_match('//u', $progress) === 1 && strpos($progress, "\u{FFFD}") === false,
        'журнал восстановлен как UTF-8 без символов замены');
    echo "Результат: $checks проверок оркестратора пройдено.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
