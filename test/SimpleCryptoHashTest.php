<?php

namespace test;

use utils\AvalancheEffect;

class SimpleCryptoHashTest 
{
    public $class;

    public function __construct($class)
    {
        $this->class = $class;
    }

    public function test1()
    {
        $this->echoTestHead(1);
        $str = 'Random generated string';
        $hash = $this->class->get($str);
        echo PHP_EOL . '32bit hash: "' . $str . '" = ' . $hash . PHP_EOL;
        $hash = $this->class->get128bit($str);
        echo '128bit hash: "' . $str . '" = ' . $hash . PHP_EOL;        
        $this->echoTestFooter();
    }

    public function test2($function = 'get')
    {
        $this->echoTestHead(2 . ', ' . $function . '()');
        (new AvalancheEffect())->compare($this->class, $function, 'a', 'b');
        (new AvalancheEffect())->compare($this->class, $function, '111', '1111');
        (new AvalancheEffect())->compare($this->class, $function, '999999999999999', '9999999999999999');
        (new AvalancheEffect())->compare($this->class, $function, 'а', 'б');
        (new AvalancheEffect())->compare($this->class, $function, '讲讲', '讲讲讲');
        (new AvalancheEffect())->compare($this->class, $function, '😁', '😁😁');
        (new AvalancheEffect())->compare($this->class, $function, '😁😂😃😄😅😆😇😈', '😁😂😃😄😅😆😇😈😉');
        (new AvalancheEffect())->compare($this->class, $function, "5Zn1Uwkrb8@2G&|6", "a_5ZLJ0)X}9Njt<A");
        $this->echoTestFooter();
    }

    public function test3($words_list_unique = [], $function = 'get')
    {
        $this->echoTestHead(3 . ', SimpleCryptoHash->' . $function . '()');
        $hashes = [];
        foreach ($words_list_unique as $word) {
            $hash = $this->class->$function($word);
            $hashes[$hash][] = $word; 
        }

        $collision_count = 0;
        foreach ($hashes as $hash => $words) {
            if (count($words) > 1) {
                $collision_count += (count($words) - 1);
                //var_dump($words);
            }
            if (strlen($hash) != 8) {
                //var_dump($hash);
            }
        }
        echo PHP_EOL . 'Count common used English words: ' . count($words_list_unique) . PHP_EOL;
        echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;
        echo 'Percent of collisions: ' . number_format(($collision_count / count($words_list_unique) * 100), 3) . "%" . PHP_EOL;
        $this->echoTestFooter();
    }

    public function test4($ip_address_list = [], $function = 'get')
    {
        $this->echoTestHead(4 . ', SimpleCryptoHash->' . $function . '()');
        $hashes = [];
        foreach ($ip_address_list as $ip_address) {
            $hash = $this->class->$function($ip_address);
            $hashes[$hash][] = $ip_address; 
        }

        $collision_count = 0;
        foreach ($hashes as $hash => $ip_addresses) {
            if (count($ip_addresses) > 1) {
                $collision_count += (count($ip_addresses) - 1);
                //var_dump($ip_addresses);
            }
            if (strlen($hash) != 8) {
                //var_dump($hash);
            }
        }
        echo PHP_EOL . 'Count IP address: ' . count($ip_address_list) . PHP_EOL;
        echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;
        echo 'Percent of collisions: ' . number_format(($collision_count / count($ip_address_list) * 100), 3) . "%" . PHP_EOL;
        $this->echoTestFooter();
    }

    public function test5($passwords_list = [], $function = 'get')
    {
        $this->echoTestHead(5 . ', SimpleCryptoHash->' . $function . '()');
        $hashes = [];
        foreach ($passwords_list as $password) {
            $hash = $this->class->$function($password);
            $hashes[$hash][] = $password; 
        }

        $collision_count = 0;
        foreach ($hashes as $hash => $passwords) {
            if (count($passwords) > 1) {
                $collision_count += (count($passwords) - 1);
                //var_dump($passwords);
            }
            if (strlen($hash) != 8) {
                //var_dump($hash);
            }
        }
        echo PHP_EOL . 'Count passwords: ' . count($passwords_list) . PHP_EOL;
        echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;
        echo 'Percent of collisions: ' . number_format(($collision_count / count($passwords_list) * 100), 3) . "%" . PHP_EOL;
        $this->echoTestFooter();
    }

    public function test6($passwords_list = [])
    {
        $this->echoTestHead(6);
        $character_distribution = [];
        foreach ($passwords_list as $password) {
            $hash = $this->class->get($password);
            $hash_arr = str_split($hash);
            foreach ($hash_arr as $symbol) {
                if (!array_key_exists($symbol, $character_distribution)) {
                    $character_distribution[$symbol] = 1;
                } else {
                    $character_distribution[$symbol] += 1;
                }
            }
        }
        ksort($character_distribution);
        echo PHP_EOL . 'Character distribution (' . count($passwords_list) . ' items): ' . PHP_EOL;
        $character_sum = 0;
        foreach ($character_distribution as $symbol => $items) {
            $character_sum += $items;
        }
        foreach ($character_distribution as $symbol => $items) {
            echo $symbol . ':	' . number_format(($items / $character_sum * 100), 2) . "%"  . PHP_EOL;
        }
        $this->echoTestFooter();
    }


    public function test7($passwords_list = [])
    {
        $this->echoTestHead(7);

        $min_value = 4294967295;
        $max_value = 0;
        foreach ($passwords_list as $password) {
            $hash = $this->class->get($password);
            $hash = base_convert($hash, 16, 10);
            if ($hash < $min_value) {
                $min_value = $hash;
            }
            if ($hash > $max_value) {
                $max_value = $hash;
            }
        }
        echo PHP_EOL . 'Min/max hash value (' . count($passwords_list) . ' items): ' . PHP_EOL;
        echo 'Min: ' . $min_value . ' (decimal) - ' . base_convert($min_value, 10, 16) . ' (hex)' . PHP_EOL;
        echo 'Max: ' . $max_value . ' (decimal) - ' . base_convert($max_value, 10, 16) . ' (hex)' . PHP_EOL;
        $this->echoTestFooter();
    }

    public function test8($passwords_list = [], $function = 'get')
    {
        $this->echoTestHead(8 . ', ' . $function);

        $min_value = 268435456;
        $max_value = 4294967295;
        $steps = 10;
        $step_value = round(($max_value - $min_value) / $steps);

        $qauntity_distribution = [];
        for ($i = 0; $i < $steps; $i++) {
            $qauntity_distribution[($min_value + ($i * $step_value))] = 0;
        }
        foreach ($passwords_list as $password) {
            if ($function == 'sha256') {
                $hash = substr(hash('sha256', $password), 0, 8);
            } elseif ($function == 'md5') {
                $hash = substr(md5($password), 0, 8);
            } else {
                $hash = $this->class->get($password);
            }
            
            $hash_decim = base_convert($hash, 16, 10);
            foreach ($qauntity_distribution as $key => $value) {
                $next_key = $key + $step_value;
                if ($hash_decim > $key && $hash_decim < $next_key) {
                    $qauntity_distribution[$key] += 1;
                    continue;
                }        
            }
        }

        echo PHP_EOL . 'Qauntity distribution (' . $steps . ' parts, ' . count($passwords_list) . ' items): ' . PHP_EOL;
        $qauntity_sum = 0;
        foreach ($qauntity_distribution as $key => $items) {
            $qauntity_sum += $items;
        }
        foreach ($qauntity_distribution as $key => $items) {
            echo '{decimal_digit} < ' . $key . ':	' . number_format(($items / $qauntity_sum * 100), 2) . "%"  . PHP_EOL;
        }
        $this->echoTestFooter();
    }

    public function echoTestHead($number)
    {
        echo <<<EOH
---------------------------------
 Test {$number}
---------------------------------
EOH;
    }

    public function echoTestFooter()
    {
        echo PHP_EOL . PHP_EOL;
    }
}
