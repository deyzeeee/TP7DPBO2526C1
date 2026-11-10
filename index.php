<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

/**
 * Daftar halaman yang diizinkan (whitelist router).
 * Mencegah Local File Inclusion (LFI) dari $_GET['page'].
 *
 * @var array<string, string> $allowedPages
 */
$allowedPages = [
    'borrowings' => __DIR__ . '/view/borrowings.php',
    'equipments' => __DIR__ . '/view/equipments.php',
    'categories' => __DIR__ . '/view/categories.php',
    'borrowers'  => __DIR__ . '/view/borrowers.php',
];

$requestedPage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'borrowings';
$currentPage = array_key_exists($requestedPage, $allowedPages) ? $requestedPage : 'borrowings';

$dbError = null;
$pdo = null;
$categoryRepo = null;
$equipmentRepo = null;
$borrowerRepo = null;
$borrowingRepo = null;

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Dependency Injection lewat constructor (SOLID - Dependency Inversion Principle)
    $categoryRepo = new Category($pdo);
    $equipmentRepo = new Equipment($pdo);
    $borrowerRepo = new Borrower($pdo);
    $borrowingRepo = new Borrowing($pdo, $equipmentRepo);
} catch (Throwable $e) {
    $dbError = 'Koneksi ke database gagal. Pastikan MySQL aktif dan file database/db_lab_gear.sql sudah di-import.';
}

require $allowedPages[$currentPage];
