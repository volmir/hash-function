<?php

$marker_start = microtime(true);

set_time_limit(300);
ini_set('max_execution_time', 300);

include './words.php';
include './SimpleHash.php';


$simpleHash = new SimpleHash();


// $str = '1M5dsk34as3M352wKf'; 
// $hash = $simpleHash->get($str);
// echo $hash . PHP_EOL;
// exit();


$hashes = [];
foreach ($words_list_unique as $word) {
    $hash = $simpleHash->get($word);
    $hashes[$hash][] = $word; 
}

/**
 * Check for collisions
 */
foreach ($hashes as $hash => $words) {
    if (count($words) > 1) {
        var_dump($hash, $words);
    }
    if (strlen($hash) != 32) {
        var_dump($hash);
    }
}


echo 'Count words: ' . count($words_list_unique) . PHP_EOL;
echo 'Count unique hashes: ' . count($hashes) . PHP_EOL;

echo 'Elapsed time: ' . number_format(microtime(true) - $marker_start, 4) . ' seconds' . PHP_EOL;


