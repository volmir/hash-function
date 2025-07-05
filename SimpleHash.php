<?php

/**
 * Realization of simple cryptographic hash function 
 * (something like md5())
 */
class SimpleHash 
{
    /**
     * Input: string
     * Output: hash (string 32 symbols lenght [0-9][a-f])
     */
    public function get(string $str):string
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

        $hash1 = sprintf("%'1*s", 10, $hash1);
        $hash2 = sprintf("%'1*s", 10, $hash2);
        $hash3 = sprintf("%'1*s", 10, $hash3);
        $hash4 = sprintf("%'1*s", 10, $hash4);

        $hash = base_convert($hash1, 10, 16)
          . base_convert($hash2, 10, 16)
          . base_convert($hash3, 10, 16)
          . base_convert($hash4, 10, 16);
          
        return $hash;
    }
}
