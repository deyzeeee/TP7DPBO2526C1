<?php

declare(strict_types=1);

/**
 * Abstract Class BaseRepository
 * Menyediakan implementasi generik CRUD dengan PDO Prepared Statement.
 * Menerapkan Open/Closed Principle (OCP) dan Dependency Inversion Principle (DIP).
 */
abstract class BaseRepository implements CrudRepository
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    abstract protected function getTableName(): string;

    abstract protected function getPrimaryKey(): string;

    /**
     * Daftar kolom tabel yang diizinkan untuk operasi INSERT/UPDATE (whitelist).
     *
     * @return list<string>
     */
    abstract protected function getAllowedColumns(): array;

    /**
     * Daftar kolom yang wajib diisi (tidak boleh kosong).
     *
     * @return list<string>
     */
    abstract protected function getRequiredColumns(): array;

    /**
     * Seluruh eksekusi query database WAJIB melewati method ini.
     * Selalu menggunakan PDO::prepare() dan PDOStatement::execute().
     *
     * @param array<int|string, mixed> $params
     */
    protected function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Memfilter dan memvalidasi input berdasarkan whitelist kolom dan kolom wajib.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function filterPayload(array $input): array
    {
        $allowed = $this->getAllowedColumns();
        $required = $this->getRequiredColumns();
        $filtered = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $input)) {
                $value = $input[$column];
                $filtered[$column] = is_string($value) ? trim($value) : $value;
            }
        }

        foreach ($required as $reqColumn) {
            if (!isset($filtered[$reqColumn]) || $filtered[$reqColumn] === '') {
                throw new InvalidArgumentException(
                    sprintf('Kolom "%s" wajib diisi dan tidak boleh kosong.', $reqColumn)
                );
            }
        }

        return $filtered;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` ORDER BY `%s` DESC',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql)->fetchAll();

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `%s` = :id LIMIT 1',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        /** @var array<string, mixed>|false $row */
        $row = $this->run($sql, ['id' => $id])->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $payload = $this->filterPayload($data);
        $columns = array_keys($payload);

        $columnList = implode(', ', array_map(static fn(string $col): string => "`{$col}`", $columns));
        $placeholderList = implode(', ', array_map(static fn(string $col): string => ":{$col}", $columns));

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->getTableName(),
            $columnList,
            $placeholderList
        );

        $this->run($sql, $payload);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $payload = $this->filterPayload($data);
        $columns = array_keys($payload);

        $assignments = implode(
            ', ',
            array_map(static fn(string $col): string => "`{$col}` = :{$col}", $columns)
        );

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :primary_id',
            $this->getTableName(),
            $assignments,
            $this->getPrimaryKey()
        );

        $payload['primary_id'] = $id;
        $this->run($sql, $payload);

        return true;
    }

    public function delete(int $id): bool
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `%s` = :id',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        $stmt = $this->run($sql, ['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
