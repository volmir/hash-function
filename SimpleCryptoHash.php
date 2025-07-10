<?php

/**
 * Realization of simple cryptographic hash function 
 * (something like md5())
 */
class SimpleCryptoHash 
{
    /**
     * 10000000 (hex) = 268435456 (decimal)
     */
    public const MIN_8_SYMBOL_HEX = 268435456;

    /**
     * Input: string
     * Output: hash (32bit, string 8 symbols lenght [0-9][a-f])
     */
    public function get(string $str):string
    {
        $hash = 0;
        
        for ($i = 0; $i < strlen($str); $i++) {
            $char = ord($str[$i]);
            
            // Bitwise transformations
            $hash_cycle = (int)strrev(($char + 0) + (($char + 1) << 8) + (($char + 2) << 16) + (($char + 3) << 24));
            $hash ^= $hash_cycle;
            $hash = strrev($hash >> 1);

            // Convert to 32bit integer
            $hash &= 0xFFFFFFFF;
        }

        if ($hash < self::MIN_8_SYMBOL_HEX) {
            $hash = sprintf("%'" . $this->getDigit($hash) . "*s", 10, $hash);
        }
        $hash = base_convert($hash, 10, 16);
          
        return $hash;
    }

    /**
     * Input: string
     * Output: hash (128bit (4 x 32bit), string 32 symbols lenght [0-9][a-f])
     */
    public function get128bit(string $str):string
    {
        $hash1 = 0;
        $hash2 = 0;
        $hash3 = 0;
        $hash4 = 0;
        
        for ($i = 0; $i < strlen($str); $i++) {
            $char = ord($str[$i]);
            
            // Bitwise transformations
            $hash = (int)strrev(($char + 0) + (($char + 1) << 8) + (($char + 2) << 16) + (($char + 3) << 24));

            $hash1_cycle = ($hash * ($char + 0) / ($char + 1));
            $hash2_cycle = ($hash * ($char + 2) / ($char + 3));
            $hash3_cycle = ($hash * ($char + 4) / ($char + 5));
            $hash4_cycle = ($hash * ($char + 6) / ($char + 7));

            $hash1 ^= $hash1_cycle;
            $hash2 ^= $hash2_cycle;
            $hash3 ^= $hash3_cycle;
            $hash4 ^= $hash4_cycle;

            $hash1 = strrev($hash1 >> 1);
            $hash2 = strrev($hash2 >> 1);
            $hash3 = strrev($hash3 >> 1);
            $hash4 = strrev($hash4 >> 1);

            // Convert to 32bit integer
            $hash1 &= 0xFFFFFFFF;
            $hash2 &= 0xFFFFFFFF;
            $hash3 &= 0xFFFFFFFF;
            $hash4 &= 0xFFFFFFFF;
        }

        if ($hash1 < self::MIN_8_SYMBOL_HEX) {
            $hash1 = sprintf("%'" . $this->getDigit($hash1) . "*s", 10, $hash1);
        }
        if ($hash2 < self::MIN_8_SYMBOL_HEX) {
            $hash2 = sprintf("%'" . $this->getDigit($hash2) . "*s", 10, $hash2);
        }
        if ($hash3 < self::MIN_8_SYMBOL_HEX) {
            $hash3 = sprintf("%'" . $this->getDigit($hash3) . "*s", 10, $hash3);
        }
        if ($hash4 < self::MIN_8_SYMBOL_HEX) {
            $hash4 = sprintf("%'" . $this->getDigit($hash4) . "*s", 10, $hash4);
        }

        $hash = base_convert($hash1, 10, 16)
          . base_convert($hash2, 10, 16)
          . base_convert($hash3, 10, 16)
          . base_convert($hash4, 10, 16);
          
        return $hash;
    }

    /**
     * Negative example: poor, simple, primitive function
     * 
     * Input: string
     * Output: hash (32bit, string 8 symbols lenght [0-9][a-f])
     */
    public function getStringSum(string $str):string
    {
        $hash = 0;
        
        for ($i = 0; $i < strlen($str); $i++) {
            $char = ord($str[$i]);
            
            // Hash transformations
            $hash += $char;

            // Convert to 32bit integer
            $hash &= 0xFFFFFFFF;
        }

        if ($hash < self::MIN_8_SYMBOL_HEX) {
            $hash = sprintf("%'" . $this->getDigit($hash) . "*s", 10, $hash);
        }
        $hash = base_convert($hash, 10, 16);
          
        return $hash;
    }

    protected function getDigit(int $hash): int
    {
        $digit = 1;
        if ($hash % 3 == 0) {
            $digit = 3;
        } elseif ($hash % 2 == 0) {
            $digit = 2;
        }
        return $digit;
    }
}
