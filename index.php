<?php

$marker_start = microtime(true);

set_time_limit(300);
ini_set('max_execution_time', 300);

include './avalanche_effect.php';
include './english_words.php';
include './passwords.php';
include './ip_address.php';
include './SimpleCryptoHash.php';


$simpleHash = new SimpleCryptoHash();



echo <<<EOH
---------------------------------
 Test 1
---------------------------------
EOH;
$str = 'Random generated string'; 
$hash = $simpleHash->get($str);
echo PHP_EOL . '32bit hash: "' . $str . '" = ' . $hash . PHP_EOL;
$hash = $simpleHash->get128bit($str);
echo '128bit hash: "' . $str . '" = ' . $hash . PHP_EOL;
echo PHP_EOL . PHP_EOL;



echo <<<EOH
---------------------------------
 Test 2
---------------------------------
EOH;
avalanche_effect($simpleHash, 'a', 'b');
avalanche_effect($simpleHash, '111', '1111');
avalanche_effect($simpleHash, '999999999999999', '9999999999999999');
avalanche_effect($simpleHash, 'а', 'б');
avalanche_effect($simpleHash, '讲讲', '讲讲讲');
avalanche_effect($simpleHash, '😁', '😁😁');
avalanche_effect($simpleHash, '😁😂😃😄😅😆😇😈', '😁😂😃😄😅😆😇😈😉');
avalanche_effect($simpleHash, "5Zn1Uwkrb8@2G&|6", "a_5ZLJ0)X}9Njt<A");
echo PHP_EOL . PHP_EOL;


echo <<<EOH
---------------------------------
 Test 3
---------------------------------
EOH;
$hashes = [];
foreach ($words_list_unique as $word) {
    $hash = $simpleHash->get($word);
    $hashes[$hash][] = $word; 
}

/**
 * Check for hash collisions
 */
$collision_count = 0;
foreach ($hashes as $hash => $words) {
    if (count($words) > 1) {
        $collision_count += (count($words) - 1);
        var_dump($words);
    }
    if (strlen($hash) != 8) {
        var_dump($hash);
    }
}
echo PHP_EOL . 'Count common used English words: ' . count($words_list_unique) . PHP_EOL;
echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;
echo 'Percent of collisions: ' . number_format(($collision_count / count($words_list_unique) * 100), 3) . "%" . PHP_EOL;
echo PHP_EOL . PHP_EOL;



echo <<<EOH
---------------------------------
 Test 4
---------------------------------
EOH;
$hashes = [];
foreach ($ip_address_list as $ip_address) {
    $hash = $simpleHash->get($ip_address);
    $hashes[$hash][] = $ip_address; 
}

/**
 * Check for hash collisions
 */
$collision_count = 0;
foreach ($hashes as $hash => $ip_addresses) {
    if (count($ip_addresses) > 1) {
        $collision_count += (count($ip_addresses) - 1);
        var_dump($ip_addresses);
    }
    if (strlen($hash) != 8) {
        var_dump($hash);
    }
}
echo PHP_EOL . 'Count IP address: ' . count($ip_address_list) . PHP_EOL;
echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;
echo 'Percent of collisions: ' . number_format(($collision_count / count($ip_address_list) * 100), 3) . "%" . PHP_EOL;
echo PHP_EOL . PHP_EOL;



echo <<<EOH
---------------------------------
 Test 5
---------------------------------
EOH;
$hashes = [];
foreach ($passwords_list as $password) {
    $hash = $simpleHash->get($password);
    $hashes[$hash][] = $password; 
}

/**
 * Check for hash collisions
 */
$collision_count = 0;
foreach ($hashes as $hash => $passwords) {
    if (count($passwords) > 1) {
        $collision_count += (count($passwords) - 1);
        var_dump($passwords);
    }
    if (strlen($hash) != 8) {
        var_dump($hash);
    }
}
echo PHP_EOL . 'Count passwords: ' . count($passwords_list) . PHP_EOL;
echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;
echo 'Percent of collisions: ' . number_format(($collision_count / count($passwords_list) * 100), 3) . "%" . PHP_EOL;
echo PHP_EOL . PHP_EOL;



echo <<<EOH
---------------------------------
 Test 6
---------------------------------
EOH;
$character_distribution = [];
foreach ($passwords_list as $password) {
    $hash = $simpleHash->get($password);
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
echo PHP_EOL . 'Character distribution (' . count($passwords_list) . ' hashes): ' . PHP_EOL;
$character_sum = 0;
foreach ($character_distribution as $symbol => $items) {
    $character_sum += $items;
}
foreach ($character_distribution as $symbol => $items) {
    echo $symbol . ': ' . number_format(($items / $character_sum * 100), 2) . "%"  . PHP_EOL;
}
echo PHP_EOL . PHP_EOL;




echo <<<EOH
---------------------------------
 Test 7
---------------------------------
EOH;
$min_value = 4294967295;
$max_value = 0;
foreach ($passwords_list as $password) {
    $hash = $simpleHash->get($password);
    $hash = base_convert($hash, 16, 10);
    if ($hash < $min_value) {
        $min_value = $hash;
    }
    if ($hash > $max_value) {
        $max_value = $hash;
    }
}
echo PHP_EOL . 'Min/max hash value (' . count($passwords_list) . ' hashes): ' . PHP_EOL;
echo 'Min: ' . $min_value . ' (decimal) - ' . base_convert($min_value, 10, 16) . ' (hex)' . PHP_EOL;
echo 'Max: ' . $max_value . ' (decimal) - ' . base_convert($max_value, 10, 16) . ' (hex)' . PHP_EOL;
echo PHP_EOL . PHP_EOL;



echo <<<EOH
---------------------------------
 Test 8
---------------------------------
EOH;
$min_value = 268435456;
$max_value = 4294967295;
$steps = 10;
$step_value = round(($max_value - $min_value) / $steps);

$qauntity_distribution = [];
for ($i = 0; $i < $steps; $i++) {
    $qauntity_distribution[($min_value + ($i * $step_value))] = 0;
}
foreach ($passwords_list as $password) {
    $hash = $simpleHash->get($password);
    $hash_decim = base_convert($hash, 16, 10);
    foreach ($qauntity_distribution as $key => $value) {
        $next_key = $key + $step_value;
        if ($hash_decim > $key && $hash_decim < $next_key) {
            $qauntity_distribution[$key] += 1;
            continue;
        }        
    }
}

echo PHP_EOL . 'Qauntity distribution (' . $steps . ' parts): ' . PHP_EOL;
$qauntity_sum = 0;
foreach ($qauntity_distribution as $key => $items) {
    $qauntity_sum += $items;
}
foreach ($qauntity_distribution as $key => $items) {
    echo ' > ' . $key . ': ' . number_format(($items / $qauntity_sum * 100), 2) . "%"  . PHP_EOL;
}
echo PHP_EOL . PHP_EOL;




echo 'Elapsed time: ' . number_format(microtime(true) - $marker_start, 4) . ' seconds' . PHP_EOL . PHP_EOL;


