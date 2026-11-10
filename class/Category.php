<?php

declare(strict_types=1);

/**
 * Class Category
 * Mengelola tabel `categories` (kategori peralatan laboratorium).
 */
final class Category extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'categories';
    }

    protected function getPrimaryKey(): string
    {
        return 'id';
    }

    /**
     * @return list<string>
     */
    protected function getAllowedColumns(): array
    {
        return [
            'category_code',
            'category_name',
            'lab_room',
            'description',
        ];
    }

    /**
     * @return list<string>
     */
    protected function getRequiredColumns(): array
    {
        return [
            'category_code',
            'category_name',
            'lab_room',
            'description',
        ];
    }

    /**
     * Mengambil seluruh kategori beserta jumlah jenis alat di dalamnya.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = 'SELECT c.*, COUNT(e.id) AS total_equipments
                FROM `categories` c
                LEFT JOIN `equipments` e ON e.category_id = c.id
                GROUP BY c.id
                ORDER BY c.id DESC';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql)->fetchAll();

        return $rows;
    }
}
