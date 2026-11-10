<?php

declare(strict_types=1);

/**
 * Class Borrowing
 * Mengelola tabel `borrowings` (transaksi peminjaman & pengembalian alat laboratorium)
 * dan menyinkronkan stok pada tabel `equipments` menggunakan database transaction.
 */
final class Borrowing extends BaseRepository
{
    public const STATUS_BORROWED = 'Dipinjam';
    public const STATUS_RETURNED = 'Dikembalikan';

    private Equipment $equipmentRepository;

    public function __construct(PDO $db, Equipment $equipmentRepository)
    {
        parent::__construct($db);
        $this->equipmentRepository = $equipmentRepository;
    }

    protected function getTableName(): string
    {
        return 'borrowings';
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
            'equipment_id',
            'borrower_id',
            'quantity',
            'borrow_date',
            'return_date',
            'status',
            'purpose',
        ];
    }

    /**
     * @return list<string>
     */
    protected function getRequiredColumns(): array
    {
        return [
            'equipment_id',
            'borrower_id',
            'quantity',
            'borrow_date',
            'status',
            'purpose',
        ];
    }

    /**
     * Validasi dan normalisasi payload transaksi peminjaman.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function filterPayload(array $input): array
    {
        if (!isset($input['status']) || $input['status'] === '') {
            $input['status'] = self::STATUS_BORROWED;
        }

        $payload = parent::filterPayload($input);

        $equipmentId = filter_var($payload['equipment_id'], FILTER_VALIDATE_INT);
        $borrowerId = filter_var($payload['borrower_id'], FILTER_VALIDATE_INT);
        $quantity = filter_var($payload['quantity'], FILTER_VALIDATE_INT);

        if ($equipmentId === false || $equipmentId <= 0) {
            throw new InvalidArgumentException('Alat laboratorium yang dipilih tidak valid.');
        }

        if ($borrowerId === false || $borrowerId <= 0) {
            throw new InvalidArgumentException('Peminjam yang dipilih tidak valid.');
        }

        if ($quantity === false || $quantity <= 0) {
            throw new InvalidArgumentException('Jumlah pinjam minimal 1 unit.');
        }

        $status = (string) $payload['status'];
        if (!in_array($status, [self::STATUS_BORROWED, self::STATUS_RETURNED], true)) {
            throw new InvalidArgumentException('Status peminjaman tidak valid.');
        }

        $borrowDate = (string) $payload['borrow_date'];
        if (!$this->isValidDate($borrowDate)) {
            throw new InvalidArgumentException('Format tanggal pinjam tidak valid (gunakan YYYY-MM-DD).');
        }

        $returnDateRaw = isset($payload['return_date']) ? trim((string) $payload['return_date']) : '';
        if ($status === self::STATUS_BORROWED) {
            $returnDate = null;
        } else {
            $returnDate = $returnDateRaw !== '' ? $returnDateRaw : date('Y-m-d');
            if (!$this->isValidDate($returnDate)) {
                throw new InvalidArgumentException('Format tanggal kembali tidak valid (gunakan YYYY-MM-DD).');
            }
            if ($returnDate < $borrowDate) {
                throw new InvalidArgumentException('Tanggal kembali tidak boleh lebih awal dari tanggal pinjam.');
            }
        }

        $payload['equipment_id'] = $equipmentId;
        $payload['borrower_id'] = $borrowerId;
        $payload['quantity'] = $quantity;
        $payload['status'] = $status;
        $payload['borrow_date'] = $borrowDate;
        $payload['return_date'] = $returnDate;

        return $payload;
    }

    private function isValidDate(string $date): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $dt !== false && $dt->format('Y-m-d') === $date;
    }

    /**
     * Mengambil semua data peminjaman dengan JOIN ke tabel equipments, categories, dan borrowers.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = 'SELECT br.*,
                       e.asset_code,
                       e.equipment_name,
                       e.stock AS current_stock,
                       c.category_name,
                       bw.identity_number,
                       bw.full_name AS borrower_name,
                       bw.study_program
                FROM `borrowings` br
                INNER JOIN `equipments` e ON br.equipment_id = e.id
                INNER JOIN `categories` c ON e.category_id = c.id
                INNER JOIN `borrowers` bw ON br.borrower_id = bw.id
                ORDER BY br.id DESC';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql)->fetchAll();

        return $rows;
    }

    /**
     * Membuat data peminjaman baru sekaligus mengurangi stok alat bila berstatus Dipinjam.
     * Menggunakan transaksi database dan SELECT ... FOR UPDATE.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $payload = $this->filterPayload($data);

        $this->db->beginTransaction();
        try {
            $equipmentId = (int) $payload['equipment_id'];
            $quantity = (int) $payload['quantity'];
            $status = (string) $payload['status'];

            $equipment = $this->equipmentRepository->findByIdForUpdate($equipmentId);
            if ($equipment === null) {
                throw new RuntimeException('Alat laboratorium yang dipilih tidak ditemukan.');
            }

            if ($status === self::STATUS_BORROWED) {
                $availableStock = (int) $equipment['stock'];
                if ($availableStock < $quantity) {
                    throw new RuntimeException(
                        sprintf(
                            'Stok alat "%s" tidak mencukupi (tersedia: %d, diminta: %d).',
                            (string) $equipment['equipment_name'],
                            $availableStock,
                            $quantity
                        )
                    );
                }

                if (!$this->equipmentRepository->adjustStock($equipmentId, -$quantity)) {
                    throw new RuntimeException('Gagal memperbarui stok peralatan.');
                }
            }

            $newId = parent::create($payload);
            $this->db->commit();

            return $newId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Memperbarui data peminjaman dengan menjaga konsistensi stok jika alat, jumlah, atau status berubah.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $payload = $this->filterPayload($data);

        $this->db->beginTransaction();
        try {
            /** @var array<string, mixed>|false $existing */
            $existing = $this->run(
                'SELECT * FROM `borrowings` WHERE `id` = :id LIMIT 1 FOR UPDATE',
                ['id' => $id]
            )->fetch();

            if ($existing === false) {
                throw new RuntimeException('Data peminjaman yang ingin diubah tidak ditemukan.');
            }

            $oldEquipmentId = (int) $existing['equipment_id'];
            $oldQuantity = (int) $existing['quantity'];
            $oldStatus = (string) $existing['status'];

            $newEquipmentId = (int) $payload['equipment_id'];
            $newQuantity = (int) $payload['quantity'];
            $newStatus = (string) $payload['status'];

            // 1. Jika sebelumnya berstatus Dipinjam, kembalikan dulu stok lama secara virtual di dalam transaksi
            if ($oldStatus === self::STATUS_BORROWED) {
                $oldEquip = $this->equipmentRepository->findByIdForUpdate($oldEquipmentId);
                if ($oldEquip === null) {
                    throw new RuntimeException('Data peralatan lama tidak ditemukan.');
                }
                if (!$this->equipmentRepository->adjustStock($oldEquipmentId, $oldQuantity)) {
                    throw new RuntimeException('Gagal mengembalikan stok alat lama.');
                }
            }

            // 2. Jika status baru adalah Dipinjam, kunci dan kurangi stok alat baru
            if ($newStatus === self::STATUS_BORROWED) {
                $newEquip = $this->equipmentRepository->findByIdForUpdate($newEquipmentId);
                if ($newEquip === null) {
                    throw new RuntimeException('Data peralatan baru tidak ditemukan.');
                }

                $availableStock = (int) $newEquip['stock'];
                if ($availableStock < $newQuantity) {
                    throw new RuntimeException(
                        sprintf(
                            'Stok alat "%s" tidak mencukupi untuk perubahan ini (tersedia: %d, diminta: %d).',
                            (string) $newEquip['equipment_name'],
                            $availableStock,
                            $newQuantity
                        )
                    );
                }

                if (!$this->equipmentRepository->adjustStock($newEquipmentId, -$newQuantity)) {
                    throw new RuntimeException('Gagal mengurangi stok alat baru.');
                }
            }

            $result = parent::update($id, $payload);
            $this->db->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Menandai peminjaman aktif sebagai Dikembalikan dan mengembalikan stok alat secara otomatis.
     */
    public function returnEquipment(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            /** @var array<string, mixed>|false $existing */
            $existing = $this->run(
                'SELECT * FROM `borrowings` WHERE `id` = :id LIMIT 1 FOR UPDATE',
                ['id' => $id]
            )->fetch();

            if ($existing === false) {
                throw new RuntimeException('Data peminjaman tidak ditemukan.');
            }

            if ((string) $existing['status'] === self::STATUS_RETURNED) {
                throw new RuntimeException('Peminjaman ini sudah berstatus Dikembalikan.');
            }

            $equipmentId = (int) $existing['equipment_id'];
            $quantity = (int) $existing['quantity'];

            $equipment = $this->equipmentRepository->findByIdForUpdate($equipmentId);
            if ($equipment === null) {
                throw new RuntimeException('Data peralatan terkait tidak ditemukan.');
            }

            if (!$this->equipmentRepository->adjustStock($equipmentId, $quantity)) {
                throw new RuntimeException('Gagal mengembalikan stok peralatan.');
            }

            $this->run(
                'UPDATE `borrowings`
                 SET `status` = :status, `return_date` = :return_date
                 WHERE `id` = :id',
                [
                    'status' => self::STATUS_RETURNED,
                    'return_date' => date('Y-m-d'),
                    'id' => $id,
                ]
            );

            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Menghapus transaksi peminjaman; jika statusnya masih Dipinjam, stok alat otomatis dipulihkan.
     */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            /** @var array<string, mixed>|false $existing */
            $existing = $this->run(
                'SELECT * FROM `borrowings` WHERE `id` = :id LIMIT 1 FOR UPDATE',
                ['id' => $id]
            )->fetch();

            if ($existing === false) {
                throw new RuntimeException('Data peminjaman yang akan dihapus tidak ditemukan.');
            }

            if ((string) $existing['status'] === self::STATUS_BORROWED) {
                $equipmentId = (int) $existing['equipment_id'];
                $quantity = (int) $existing['quantity'];

                $equipment = $this->equipmentRepository->findByIdForUpdate($equipmentId);
                if ($equipment !== null) {
                    $this->equipmentRepository->adjustStock($equipmentId, $quantity);
                }
            }

            $deleted = parent::delete($id);
            $this->db->commit();

            return $deleted;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
