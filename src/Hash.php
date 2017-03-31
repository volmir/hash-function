<?php

/**
 * Hash function
 * @url http://www.pvsm.ru/programmirovanie/62151
 */
class Hash 
{
    /**
     *
     * @var int
     */
    public $hash_len = 32;
    /**
     *
     * @var int
     */    
    public $min_block_len = 64;
    
    /**
     * 
     * @param string $str
     * @return string
     */
    public function getHash(string $str)
    {
        $min_len = $this->min_block_len;
        $str_len = strlen($str);
        while ($min_len < $str_len) {
            $min_len = $min_len * 2;
        }
        $add_len = $min_len - $str_len;
        
        $salt = 1;
        if (strlen($str)) {
            $str_arr = str_split($str);
            foreach ($str_arr as $value) {
                $salt += ord($value);
            }
        }
        
        for ($i = 0; $i < $add_len; $i++) {
            $str .= chr($this->whitening($i * $salt));
        }
        
        foreach (str_split($str) as $value) {
            $digits_arr[] = ord($value);
        }
        
        $digits_len = count($digits_arr);
        while ($digits_len != $this->hash_len) {
            $temp_arr = $digits_arr;
            $digits_arr = [];
            for ($j = 0; $j < $digits_len; $j += 2) {
                $digits_arr[] = $temp_arr[$j] + $temp_arr[$j + 1];
            }
            $digits_len = count($digits_arr);
        }
        
        $hash = '';
        foreach ($digits_arr as $value) {
            $hash .= chr($this->whitening($value));
        }
        
        return $hash;
    }

    /**
     * 
     * @param int $x
     * @return int
     */
    public function whitening(int $x) 
    {
        $x += 256;
        while (!(($x >= 48 &&$x <= 57) || 
                ($x >= 65 && $x <= 90) || 
                ($x >= 97 && $x <= 122))) {
            if ($x < 48) {
                $x += 24;
            } else {
                $x -= 47;
            }
        }
        
        return $x;
    }

}
