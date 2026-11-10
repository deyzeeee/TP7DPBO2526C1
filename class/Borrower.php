<?php

declare(strict_types=1);

/**
 * Class Borrower
 * Mengelola tabel `borrowers` (data peminjam: mahasiswa / dosen / asisten).
 */
final class Borrower extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'borrowers';
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
            'identity_number',
            'full_name',
            'study_program',
            'email',
            'phone',
        ];
    }

    /**
     * @return list<string>
     */
    protected function getRequiredColumns(): array
    {
        return [
            'identity_number',
            'full_name',
            'study_program',
            'email',
            'phone',
        ];
    }

    /**
     * Validasi format email pada input peminjam.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function filterPayload(array $input): array
    {
        $payload = parent::filterPayload($input);

        $email = (string) $payload['email'];
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Format alamat email peminjam tidak valid.');
        }

        return $payload;
    }

    /**
     * Mengambil semua peminjam beserta jumlah peminjaman aktif mereka.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = "SELECT b.*,
                       COUNT(CASE WHEN br.status = 'Dipinjam' THEN 1 END) AS active_borrowings
                FROM `borrowers` b
                LEFT JOIN `borrowings` br ON br.borrower_id = b.id
                GROUP BY b.id
                ORDER BY b.id DESC";

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql)->fetchAll();

        return $rows;
    }
}
