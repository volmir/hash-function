<?php

set_time_limit(0);

include 'src/Hash.php';

$hash = new Hash();

echo $hash->getHash('') . PHP_EOL;
echo $hash->getHash('6') . PHP_EOL;
echo $hash->getHash('M') . PHP_EOL;
echo $hash->getHash('m') . PHP_EOL;
echo $hash->getHash('md') . PHP_EOL;
echo $hash->getHash('KfdKF54KFvs') . PHP_EOL;
echo $hash->getHash('KfdKF54KFvs') . PHP_EOL;
echo $hash->getHash('sfgj asd hs324q324fqdgfyh6fqad') . PHP_EOL;
echo $hash->getHash('sRM KMFfg dsf565g sdfg dsfg sdjhdsfgj asd hs384q32') . PHP_EOL;
echo $hash->getHash('dfg42576thdf 673 wtrhe675 edfg dsfg sdfg dsfg sdjhdsfgj asd hs324q32456') . PHP_EOL;


