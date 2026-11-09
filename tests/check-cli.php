<?php

$vectors = require __DIR__ . '/vectors.php';
$checks = 0;
$skipped = 0;

function checkCommand(array $arguments, int $expectedCode, string $expectedOutput, bool $expectError): void
{
    global $checks;
    $command = array_merge([PHP_BINARY, __DIR__ . '/../hash.php'], $arguments);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Не удалось запустить hash.php');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $checks++;
    if ($code !== $expectedCode || $output !== $expectedOutput ||
        ($expectError ? strpos($error, 'Использование:') === false : $error !== '')) {
        throw new RuntimeException('Ошибка проверки CLI №' . $checks . ': код ' . $code . ', stdout ' . var_export($output, true) . ', stderr ' . var_export($error, true));
    }
}

try {
    foreach ($vectors as $vector) {
        if (strpos($vector['input'], "\0") !== false) {
            echo 'Пропуск вектора «' . $vector['name'] . "»: нулевой байт нельзя передать аргументом процесса.\n";
            $skipped++;
            continue;
        }
        checkCommand([$vector['input'], '32'], 0, $vector['hash32'] . "\n", false);
        checkCommand([$vector['input'], '128'], 0, $vector['hash128'] . "\n", false);
        checkCommand([$vector['input']], 0, $vector['hash128'] . "\n", false);
    }
    foreach ([[], ['abc', '64'], ['abc', ''], ['abc', '32', 'extra']] as $arguments) {
        checkCommand($arguments, 2, '', true);
    }
    echo "Проверено вызовов: $checks; пропущено векторов: $skipped.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
