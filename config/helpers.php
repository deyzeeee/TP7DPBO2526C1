<?php

declare(strict_types=1);

/**
 * Escape string untuk mencegah XSS saat render ke HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Menyimpan flash message ke dalam session untuk pola Post/Redirect/Get (PRG).
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Mengambil sekaligus menghapus flash message dari session.
 *
 * @return array{type: string, message: string}|null
 */
function pullFlash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = [
        'type' => (string) ($_SESSION['flash']['type'] ?? 'info'),
        'message' => (string) ($_SESSION['flash']['message'] ?? ''),
    ];
    unset($_SESSION['flash']);

    return $flash;
}

/**
 * Melakukan redirect HTTP ke halaman tertentu di dalam aplikasi dan menghentikan script.
 *
 * @param array<string, scalar> $params
 */
function redirectToPage(string $page, array $params = []): never
{
    $query = array_merge(['page' => $page], $params);
    header('Location: index.php?' . http_build_query($query));
    exit;
}

/**
 * Menerjemahkan exception database (PDOException) atau validasi (InvalidArgumentException, RuntimeException)
 * menjadi pesan bahasa Indonesia yang ramah dan tidak membocorkan stack trace SQL.
 */
function translateException(Throwable $exception): string
{
    if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
        return $exception->getMessage();
    }

    if ($exception instanceof PDOException) {
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

        return match ($driverCode) {
            1451 => 'Data ini tidak dapat dihapus karena masih digunakan sebagai referensi pada tabel lain.',
            1452 => 'Data referensi (pilihan relasi) tidak ditemukan atau tidak valid.',
            1062 => 'Data dengan kode, nomor identitas, atau email tersebut sudah terdaftar (duplikat).',
            default => 'Terjadi kesalahan saat memproses data di database. Silakan periksa kembali input Anda.',
        };
    }

    return 'Terjadi kesalahan sistem yang tidak terduga.';
}
