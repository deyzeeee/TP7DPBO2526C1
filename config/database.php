<?php

declare(strict_types=1);

/**
 * Class Database
 * Bertanggung jawab tunggal (Single Responsibility) untuk membuka koneksi PDO ke MySQL.
 */
final class Database
{
    private string $host;
    private string $dbName;
    private string $username;
    private string $password;
    private string $charset;
    private ?PDO $connection = null;

    public function __construct(
        string $host = '127.0.0.1',
        string $dbName = 'db_lab_gear',
        string $username = 'root',
        string $password = '',
        string $charset = 'utf8mb4'
    ) {
        $this->host = $host;
        $this->dbName = $dbName;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
    }

    /**
     * Mengembalikan instance PDO tunggal dengan konfigurasi aman.
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->host,
                $this->dbName,
                $this->charset
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        }

        return $this->connection;
    }
}
