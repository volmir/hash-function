<?php

namespace Security;

use Hash\SimpleHash;

class Step5
{
    public function run(): bool
    {
        echo "\nШаг 5. Попытка продолжить хеш и подделать токен\n";
        $mirror = new Mirror();
        $hash = new SimpleHash();
        mt_srand(1);
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= chr(mt_rand(0, 255));
        }
        $message = 'action=read';
        $suffix = '&admin=1';
        $length = strlen($secret . $message);
        printf("Сервер выдаёт H(секрет || %s); секрет сгенерирован с seed=1, известная общая длина — %d байт.\n", $message, $length);
        echo "Злоумышленник знает токен и длину и хочет добавить &admin=1, не зная секрета.\n";
        echo "Идея классической атаки: если хеш равен состоянию автомата после последнего байта, можно взять токен как состояние и продолжить обработку.\n";

        $success = true;
        foreach ([32, 128] as $bits) {
            $method = 'get' . $bits . 'bit';
            $token = $hash->$method($secret . $message);
            $forged = $this->continueToken($mirror, $token, $length, $suffix);
            $actual = $hash->$method($secret . $message . $suffix);
            $matches = $forged === $actual;
            printf("%d бит: исходный токен=%s; наивная подделка=%s; настоящий токен=%s; %s.\n", $bits, $token, $forged, $actual, $matches ? 'СОВПАЛИ' : 'НЕ совпали');
            $success = !$matches && $success;
        }
        echo "Выход не равен состоянию: его преобразуют финализатор и обратная связь; для 128 бит также смешиваются дорожки. Поэтому эта наивная попытка не работает на данном примере.\n";
        echo "Для 32 бит можно перебирать около 2^32 состояний (только оценка, перебор не выполнялся). Для 128 бит способ восстановления состояния неизвестен; его отсутствие не доказано.\n";
        echo "Для проверки подлинности на практике применяют HMAC с криптографической хеш-функцией, а не H(секрет || сообщение): https://www.rfc-editor.org/rfc/rfc2104. Безопасность HMAC с SimpleHash не утверждается.\n";
        return $success;
    }

    // Подделывателю доступны только токен, длина и добавляемый суффикс.
    private function continueToken(Mirror $mirror, string $token, int $length, string $suffix): string
    {
        $states = [];
        foreach (str_split($token, 8) as $part) {
            $states[] = (int) hexdec($part);
        }
        for ($i = 0; $i < strlen($suffix); $i++) {
            foreach ($states as $lane => $state) {
                $states[$lane] = $mirror->absorbByte($state, ord($suffix[$i]));
            }
        }
        $length += strlen($suffix);
        if (count($states) === 1) {
            $y = $states[0] ^ ($length & Mirror::MASK32);
            return $mirror->toHex($mirror->feedForward($mirror->avalanche($y), $y));
        }

        // Порядок обновлений совпадает с финализацией четырёх дорожек Mirror.
        $original = $states;
        $states[0] = ($states[0] + $mirror->rotl32($states[1], 7)) & Mirror::MASK32;
        $states[1] = ($states[1] + $mirror->rotl32($states[2], 11)) & Mirror::MASK32;
        $states[2] = ($states[2] + $mirror->rotl32($states[3], 17)) & Mirror::MASK32;
        $states[3] = ($states[3] + $mirror->rotl32($states[0], 23)) & Mirror::MASK32;
        $states[0] ^= $mirror->rotl32($states[2], 13);
        $states[1] ^= $mirror->rotl32($states[3], 19);
        $states[2] ^= $mirror->rotl32($states[0], 7);
        $states[3] ^= $mirror->rotl32($states[1], 11);
        $result = '';
        foreach ($states as $lane => $state) {
            $result .= $mirror->toHex($mirror->feedForward($mirror->mixFinal($state, $length), $original[$lane]));
        }
        return $result;
    }
}
