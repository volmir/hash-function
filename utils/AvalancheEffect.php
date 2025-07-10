<?php

namespace utils;

class AvalancheEffect 
{
    public function compare($simpleHash, $function, $str1 = '', $str2 = '') {
        $hash1 = $simpleHash->$function($str1);
        echo PHP_EOL . $str1 . ' = ' . $hash1 . PHP_EOL;

        $hash2 = $simpleHash->$function($str2);
        echo $str2 . ' = ' . $hash2 . PHP_EOL;

        $avalanche_effect = 0;
        for ($i = 0; $i<=7; $i++) {
            if ($hash1[$i] == $hash2[$i]) {
                $avalanche_effect++;
            }
        }
        echo 'Avalanche effect ("' . $str1 . '" and "' . $str2 . '")  = ' . ($avalanche_effect / 8 * 100) . "%" . PHP_EOL;
    }
}