<?php

declare(strict_types=1);

/**
 * Class Equipment
 * Mengelola tabel `equipments` (inventaris alat laboratorium).
 */
final class Equipment extends BaseRepository
{
    public const ALLOWED_CONDITIONS = [
        'Baik',
        'Perlu Kalibrasi',
        'Rusak Ringan',
    ];

    protected function getTableName(): string
    {
        return 'equipments';
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
            'category_id',
            'asset_code',
            'equipment_name',
            'brand',
            'stock',
            'item_condition',
        ];
    }

    /**
     * @return list<string>
     */
    protected function getRequiredColumns(): array
    {
        return [
            'category_id',
            'asset_code',
            'equipment_name',
            'brand',
            'stock',
            'item_condition',
        ];
    }

    /**
     * Validasi tambahan khusus untuk atribut peralatan laboratorium.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function filterPayload(array $input): array
    {
        $payload = parent::filterPayload($input);

        $categoryId = filter_var($payload['category_id'], FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId <= 0) {
            throw new InvalidArgumentException('Kategori alat yang dipilih tidak valid.');
        }

        $stock = filter_var($payload['stock'], FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0) {
            throw new InvalidArgumentException('Stok alat harus berupa angka bulat 0 atau lebih.');
        }

        $condition = (string) $payload['item_condition'];
        if (!in_array($condition, self::ALLOWED_CONDITIONS, true)) {
            throw new InvalidArgumentException('Kondisi alat yang dipilih tidak valid.');
        }

        $payload['category_id'] = $categoryId;
        $payload['stock'] = $stock;
        $payload['item_condition'] = $condition;

        return $payload;
    }

    /**
     * Mengambil semua peralatan dengan JOIN ke tabel categories agar menampilkan nama kategori & ruang lab.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = 'SELECT e.*,
                       c.category_code,
                       c.category_name,
                       c.lab_room
                FROM `equipments` e
                INNER JOIN `categories` c ON e.category_id = c.id
                ORDER BY e.id DESC';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql)->fetchAll();

        return $rows;
    }

    /**
     * Mengunci baris peralatan (SELECT ... FOR UPDATE) di dalam transaksi untuk mencegah race condition stok.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdForUpdate(int $id): ?array
    {
        $sql = 'SELECT * FROM `equipments` WHERE `id` = :id LIMIT 1 FOR UPDATE';

        /** @var array<string, mixed>|false $row */
        $row = $this->run($sql, ['id' => $id])->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Menyesuaikan jumlah stok (positif untuk tambah, negatif untuk kurang).
     */
    public function adjustStock(int $id, int $delta): bool
    {
        $sql = 'UPDATE `equipments`
                SET `stock` = `stock` + :delta
                WHERE `id` = :id AND (`stock` + :delta_check) >= 0';

        $stmt = $this->run($sql, [
            'delta' => $delta,
            'delta_check' => $delta,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }
}
