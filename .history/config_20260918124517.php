<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
// XAMPP/MariaDB stores this local database in lowercase.
const DB_NAME = 'freedeli_Toha';
const DB_USER = 'freedeli_freedelivery';
const DB_PASS = 'freedelivery12345@#';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

function normalizePhone(string $phone): string
{
    $phone = strtr(trim($phone), [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ]);

    return preg_replace('/[^0-9+]/', '', $phone) ?? '';
}
