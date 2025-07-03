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
        $str = sprintf("%'1*s", 32, $str);

        $hash1 = 0;
        $hash2 = 0;
        $hash3 = 0;
        $hash4 = 0;
        
        for ($i = 0; $i < strlen($str); $i++) {
            $char = ord($str[$i]);
            
            // Bitwise transformations
            $hash1 = ($hash3 << 7) - $hash1 + $char;
            $hash2 = ($hash1 << 3) - $hash2 + $char;
            $hash3 = ($hash4 << 1) - $hash3 + $char;
            $hash4 = ($hash2 << 5) - $hash4 + $char;
            
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
