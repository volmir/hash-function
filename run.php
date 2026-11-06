<?php

require __DIR__ . '/autoload.php';

exit((new Tests\Runner())->run(array_slice($argv, 1)));
