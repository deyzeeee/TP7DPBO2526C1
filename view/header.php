<?php

declare(strict_types=1);

/** @var string $currentPage */
/** @var string|null $dbError */
$flash = pullFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LabGear - Sistem Peminjaman Alat Laboratorium Komputer</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <div>
                <h1 class="brand-title">LabGear Management System</h1>
                <p class="brand-subtitle">Sistem Inventaris &amp; Peminjaman Alat Laboratorium Komputer</p>
            </div>
            <nav class="nav-links">
                <a href="index.php?page=borrowings" class="<?= $currentPage === 'borrowings' ? 'active' : '' ?>">Peminjaman</a>
                <a href="index.php?page=equipments" class="<?= $currentPage === 'equipments' ? 'active' : '' ?>">Alat Lab</a>
                <a href="index.php?page=categories" class="<?= $currentPage === 'categories' ? 'active' : '' ?>">Kategori Alat</a>
                <a href="index.php?page=borrowers" class="<?= $currentPage === 'borrowers' ? 'active' : '' ?>">Data Peminjam</a>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <?php if ($dbError !== null): ?>
            <div class="alert alert-error">
                <strong>Koneksi Bermasalah:</strong> <?= e($dbError) ?>
            </div>
        <?php endif; ?>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= e($flash['type']) ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>
