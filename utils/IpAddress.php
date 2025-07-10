<?php

function getIpAddesses() {
    $ip_address_list = [];

    for ($i = 0; $i<10000; $i++) {
        $ip_address = mt_rand(1, 255) . '.' . mt_rand(1, 255) . '.' . mt_rand(1, 255) . '.' . mt_rand(1, 255);
        if (!in_array($ip_address, $ip_address_list)) {
            $ip_address_list[] = $ip_address;
        }
    }

    return $ip_address_list;
}
