<?php

namespace Hash\Data;

class IpAddress
{
    // Генерирует набор IPv4-адресов для демонстрационных тестов.
    public static function get(): array
    {
        $ip_address_list = [];
        $seen = [];

        for ($i = 0; $i<10000; $i++) {
            $ip_address = mt_rand(1, 255) . '.' . mt_rand(1, 255) . '.' . mt_rand(1, 255) . '.' . mt_rand(1, 255);
            if (!isset($seen[$ip_address])) {
                $seen[$ip_address] = true;
                $ip_address_list[] = $ip_address;
            }
        }

        return $ip_address_list;
    }
}
