<?php

namespace Hash\Data;

class Passwords
{
    // Генерирует набор паролей для демонстрационных тестов.
    public static function get(): array
    {
        $passwords_list = [];
        $seen = [];

        $permitted_chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+|[]{}<>';

        for ($i = 0; $i<10000; $i++) {
            $password = substr(str_shuffle($permitted_chars), 0, mt_rand(10, 16));
            if (!isset($seen[$password])) {
                $seen[$password] = true;
                $passwords_list[] = $password;
            }
        }

        return $passwords_list;
    }
}
