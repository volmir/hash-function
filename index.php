<?php

$marker_start = microtime(true);

set_time_limit(300);
ini_set('max_execution_time', 300);

include './utils/AvalancheEffect.php';
include './utils/EnglishWords.php';
include './utils/Passwords.php';
include './utils/IpAddress.php';
include './model/SimpleCryptoHash.php';
include './test/SimpleCryptoHashTest.php';

use model\SimpleCryptoHash;
use test\SimpleCryptoHashTest;


$simpleHash = new SimpleCryptoHash();

$common_english_words = getCommonEnglishWords();
$ip_address_list = getIpAddesses();
$passwords_list = getPasswords();

$simpleHashTest = new SimpleCryptoHashTest($simpleHash);

$simpleHashTest->test1();
$simpleHashTest->test2();
$simpleHashTest->test3($common_english_words);
$simpleHashTest->test4($ip_address_list);
$simpleHashTest->test5($passwords_list);
$simpleHashTest->test6($passwords_list);
$simpleHashTest->test7($passwords_list);

$simpleHashTest->test3($common_english_words, 'getStringSum');
$simpleHashTest->test4($ip_address_list, 'getStringSum');
$simpleHashTest->test5($passwords_list, 'getStringSum');

$simpleHashTest->test8($passwords_list, 'simpleCryptoHash');
$simpleHashTest->test8($passwords_list, 'md5');
$simpleHashTest->test8($passwords_list, 'sha256');


echo 'Elapsed time: ' . number_format(microtime(true) - $marker_start, 4) . ' seconds' . PHP_EOL . PHP_EOL;


