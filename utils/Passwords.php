<?php

function getPasswords() {
    $passwords_list = [];

    $permitted_chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+|[]{}<>';

    for ($i = 0; $i<10000; $i++) {
        $password = substr(str_shuffle($permitted_chars), 0, mt_rand(10, 16));
        if (!in_array($password, $passwords_list)) {
            $passwords_list[] = $password;
        }
    }

    return $passwords_list;
}
