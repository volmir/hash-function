# hash-function
Realization of simple cryptographic hash function (something like md5 or sha256)

## Installation (how to run)

1. Clone this repository
2. Go inside project folder
3. Run `php index.php` to start example

## Code examples

32bit hash
```php
$str = 'Example text';
$simpleCryptoHash = new SimpleCryptoHash();
$hash = $simpleCryptoHash->get($str);
echo '32bit hash of string "' . $str . '" is "' . $hash . '"' . PHP_EOL;
# Output: 32bit hash of string "Example text" is "2a0082b4"
```

128bit hash
```php
$str = 'Example text';
$simpleCryptoHash = new SimpleCryptoHash();
$hash = $simpleCryptoHash->get128bit($str);
echo '128bit hash of string "' . $str . '" is "' . $hash . '"' . PHP_EOL;
# Output: 128bit hash of string "Example text" is "16e9fa87454f917e2d2ab4bd85090ef5"
```