# hash-function
Realization of simple cryptographic hash function (something like md5 or sha256)

## Installation (how to run)

1. Clone this repository
2. Go inside project folder
3. Run `php index.php` to start example

## Code example

```php
$str = 'Example text';
$simpleCryptoHash = new SimpleCryptoHash();
$hash = $simpleCryptoHash->get($str);
echo 'Hash of string "' . $str . '" is "' . $hash . '"' . PHP_EOL;
```
