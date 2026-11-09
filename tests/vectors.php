<?php

// При любом изменении алгоритма векторы обновляются осознанно,
// а не «чтобы тест позеленел». Ожидаемые значения хранятся буквально.
// Пересобраны после добавления одностороннего финала (feedForward).
return [
    [
        'name' => 'Пустая строка',
        'input' => '',
        'hash32' => 'd8ab6116',
        'hash128' => '43067ab07afa57a7056d2aec0fae3ca4',
    ],
    [
        'name' => 'Один байт',
        'input' => 'a',
        'hash32' => '49348c7e',
        'hash128' => 'a418e14fa42e22a84aa44fdfee590e82',
    ],
    [
        'name' => 'Пример из README',
        'input' => 'Example text',
        'hash32' => '0152259d',
        'hash128' => '6681c9e050e6fec0a47743dda44aba10',
    ],
    [
        'name' => 'Короткая ASCII-строка',
        'input' => 'abc',
        'hash32' => 'b1091e48',
        'hash128' => '86310aba62a7df96dd9386c97e67cfc7',
    ],
    [
        'name' => 'Кириллица UTF-8',
        'input' => 'Привет',
        'hash32' => 'b1d09ea6',
        'hash128' => '062bbaa999ebecff65fb5ccf7937e4ff',
    ],
    [
        'name' => 'Иероглифы и эмодзи UTF-8',
        'input' => '讲讲😁',
        'hash32' => 'dabdea5b',
        'hash128' => '0a86cc1aa413345c64d8803ccd49d909',
    ],
    [
        'name' => 'Нулевой байт',
        'input' => "\0",
        'hash32' => '1d4d0801',
        'hash128' => 'abe5c3fe0f2f1da98a0bee98882c65ff',
    ],
    [
        'name' => 'Нулевой байт внутри строки',
        'input' => "a\0b",
        'hash32' => '7c970d40',
        'hash128' => '93b4f64d0d9c5fb8929b27aa8055069d',
    ],
    [
        'name' => 'Нулевой байт в начале',
        'input' => "\0a",
        'hash32' => '49001de1',
        'hash128' => '4e702757554b3882fc6ed302f8b0c5c9',
    ],
    [
        'name' => 'Нулевой байт в конце',
        'input' => "a\0",
        'hash32' => '9dce8266',
        'hash128' => '6c7cdb581fc387dcce5f72ccec3bce2e',
    ],
    [
        'name' => '64 нулевых байта',
        'input' => str_repeat("\0", 64),
        'hash32' => '1fc347a7',
        'hash128' => '7845e5d2e8f7caab6a0458bc468d11f3',
    ],
    [
        'name' => 'Длинная строка, 4096 байт',
        'input' => str_repeat('a', 4096),
        'hash32' => '9ba169e8',
        'hash128' => '80769a0e50850eac2f5549bc61bea543',
    ],
    [
        'name' => 'Байты за пределами ASCII',
        'input' => "\x00\x7f\x80\xff",
        'hash32' => '542edc75',
        'hash128' => '355eccde068032d4591b547ca531b51e',
    ],
    [
        'name' => 'Строка zero-115',
        'input' => 'zero-115',
        'hash32' => '7f3d8922',
        'hash128' => 'd6f500952e9456543287c47654010178',
    ],
    [
        'name' => 'Строка zero-146',
        'input' => 'zero-146',
        'hash32' => 'e7338188',
        'hash128' => 'e8212617702c2a10cdc241af8160417e',
    ],
];
