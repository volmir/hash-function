<?php

$marker_start = microtime(true);

set_time_limit(300);
ini_set('max_execution_time', 300);

include './words.php';
include './SimpleHash.php';


$simpleHash = new SimpleHash();



echo <<<EOH
---------------------------------
 Test 1
---------------------------------
EOH;
$str = 'Random generated string'; 
$hash = $simpleHash->get($str);
echo PHP_EOL . $str . ' = ' . $hash . PHP_EOL;
echo PHP_EOL . PHP_EOL;



echo <<<EOH
---------------------------------
 Test 2
---------------------------------
EOH;
$str1 = 'a'; 
$hash1 = $simpleHash->get($str1);
echo PHP_EOL . $str1 . ' = ' . $hash1 . PHP_EOL;

$str2 = 'b'; 
$hash2 = $simpleHash->get($str2);
echo $str2 . ' = ' . $hash2 . PHP_EOL;

$str3 = '111';
$hash3 = $simpleHash->get($str3);
echo $str3 . ' = ' . $hash3 . PHP_EOL;

$str4 = '1111';
$hash4 = $simpleHash->get($str4);
echo $str4 . ' = ' . $hash4 . PHP_EOL;

$avalanche_effect_1 = 0;
$avalanche_effect_2 = 0;
for ($i = 0; $i<=31; $i++) {
    if ($hash1[$i] == $hash2[$i]) {
        $avalanche_effect_1++;
    }
    if ($hash3[$i] == $hash4[$i]) {
        $avalanche_effect_2++;
    }
}
echo 'Avalanche effect ("' . $str1 . '" to "' . $str2 . '")  = ' . ($avalanche_effect_1 / 32 * 100) . "%" . PHP_EOL;
echo 'Avalanche effect ("' . $str3 . '" to "' . $str4 . '")  = ' . ($avalanche_effect_2 / 32 * 100) . "%" . PHP_EOL;
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
    if (strlen($hash) != 32) {
        var_dump($hash);
    }
}


echo PHP_EOL . 'Count common used English words: ' . count($words_list_unique) . PHP_EOL;
echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;
echo 'Percent of collisions: ' . number_format(($collision_count / count($hashes) * 100), 2) . "%" . PHP_EOL . PHP_EOL;

echo 'Elapsed time: ' . number_format(microtime(true) - $marker_start, 4) . ' seconds' . PHP_EOL . PHP_EOL;


