<?php

declare(strict_types=1);

/** @var Borrowing|null $borrowingRepo */
/** @var Equipment|null $equipmentRepo */
/** @var Borrower|null $borrowerRepo */
/** @var string|null $dbError */

if ($borrowingRepo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($action === 'create') {
            $borrowingRepo->create($_POST);
            setFlash('success', 'Transaksi peminjaman alat berhasil dicatat dan stok telah diperbarui.');
        } elseif ($action === 'update') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $borrowingRepo->update($id, $_POST);
            setFlash('success', 'Data transaksi peminjaman dan stok berhasil diperbarui.');
        } elseif ($action === 'return') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $borrowingRepo->returnEquipment($id);
            setFlash('success', 'Alat laboratorium telah dikembalikan dan stok berhasil ditambahkan kembali.');
        } elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $borrowingRepo->delete($id);
            setFlash('success', 'Data transaksi peminjaman berhasil dihapus.');
        }
    } catch (Throwable $e) {
        setFlash('error', translateException($e));
    }

    redirectToPage('borrowings');
}

$borrowings = [];
$equipments = [];
$borrowers = [];
$editingBorrowing = null;

if ($borrowingRepo !== null && $equipmentRepo !== null && $borrowerRepo !== null) {
    try {
        $borrowings = $borrowingRepo->findAll();
        $equipments = $equipmentRepo->findAll();
        $borrowers = $borrowerRepo->findAll();

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editingBorrowing = $borrowingRepo->findById((int) $_GET['edit']);
            if ($editingBorrowing === null) {
                setFlash('error', 'Data transaksi peminjaman yang ingin diedit tidak ditemukan.');
                redirectToPage('borrowings');
            }
        }
    } catch (Throwable $e) {
        $dbError = translateException($e);
    }
}

$isEdit = $editingBorrowing !== null;
$hasEquipments = !empty($equipments);
$hasBorrowers = !empty($borrowers);
$canSubmit = $hasEquipments && $hasBorrowers;

require_once __DIR__ . '/header.php';
?>

<section class="grid-layout">
    <div class="card">
        <h2 class="card-title"><?= $isEdit ? 'Edit Transaksi Peminjaman' : 'Catat Peminjaman Alat Baru' ?></h2>

        <?php if (!$hasEquipments || !$hasBorrowers): ?>
            <div class="alert alert-warning">
                <?php if (!$hasEquipments): ?>
                    <div>Data <strong>Alat Lab</strong> masih kosong. Silakan isi di halaman <a href="index.php?page=equipments">Alat Lab</a> terlebih dahulu.</div>
                <?php endif; ?>
                <?php if (!$hasBorrowers): ?>
                    <div>Data <strong>Peminjam</strong> masih kosong. Silakan isi di halaman <a href="index.php?page=borrowers">Data Peminjam</a> terlebih dahulu.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php?page=borrowings" class="form-stack">
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= e($editingBorrowing['id']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="equipment_id">Alat Laboratorium (Foreign Key)</label>
                <select id="equipment_id" name="equipment_id" required <?= !$canSubmit ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Alat Laboratorium --</option>
                    <?php foreach ($equipments as $eq): ?>
                        <?php
                        $selectedEq = isset($editingBorrowing['equipment_id'])
                            && (int) $editingBorrowing['equipment_id'] === (int) $eq['id'];
                        ?>
                        <option value="<?= e($eq['id']) ?>" <?= $selectedEq ? 'selected' : '' ?>>
                            [<?= e($eq['asset_code']) ?>] <?= e($eq['equipment_name']) ?> (Stok: <?= e($eq['stock']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="borrower_id">Peminjam (Foreign Key)</label>
                <select id="borrower_id" name="borrower_id" required <?= !$canSubmit ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Peminjam --</option>
                    <?php foreach ($borrowers as $bw): ?>
                        <?php
                        $selectedBw = isset($editingBorrowing['borrower_id'])
                            && (int) $editingBorrowing['borrower_id'] === (int) $bw['id'];
                        ?>
                        <option value="<?= e($bw['id']) ?>" <?= $selectedBw ? 'selected' : '' ?>>
                            <?= e($bw['identity_number']) ?> - <?= e($bw['full_name']) ?> (<?= e($bw['study_program']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="quantity">Jumlah Unit Dipinjam</label>
                <input type="number" id="quantity" name="quantity" min="1" max="100" required
                       value="<?= e($editingBorrowing['quantity'] ?? 1) ?>">
            </div>

            <div class="form-group">
                <label for="borrow_date">Tanggal Pinjam</label>
                <input type="date" id="borrow_date" name="borrow_date" required
                       value="<?= e($editingBorrowing['borrow_date'] ?? date('Y-m-d')) ?>">
            </div>

            <div class="form-group">
                <label for="status">Status Peminjaman</label>
                <select id="status" name="status" required>
                    <?php
                    $currentStatus = (string) ($editingBorrowing['status'] ?? Borrowing::STATUS_BORROWED);
                    ?>
                    <option value="<?= e(Borrowing::STATUS_BORROWED) ?>" <?= $currentStatus === Borrowing::STATUS_BORROWED ? 'selected' : '' ?>>
                        Dipinjam (Kurangi Stok)
                    </option>
                    <option value="<?= e(Borrowing::STATUS_RETURNED) ?>" <?= $currentStatus === Borrowing::STATUS_RETURNED ? 'selected' : '' ?>>
                        Dikembalikan (Stok Kembali)
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="return_date">Tanggal Kembali (Opsional jika masih Dipinjam)</label>
                <input type="date" id="return_date" name="return_date"
                       value="<?= e($editingBorrowing['return_date'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="purpose">Keperluan Peminjaman</label>
                <input type="text" id="purpose" name="purpose" maxlength="200" required
                       placeholder="Contoh: Praktikum Jaringan Komputer Kelompok 2"
                       value="<?= e($editingBorrowing['purpose'] ?? '') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= !$canSubmit ? 'disabled' : '' ?>>
                    <?= $isEdit ? 'Simpan Perubahan' : 'Catat Peminjaman' ?>
                </button>
                <?php if ($isEdit): ?>
                    <a href="index.php?page=borrowings" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Daftar Transaksi Peminjaman Alat (<?= count($borrowings) ?>)</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Alat Laboratorium</th>
                        <th>Peminjam</th>
                        <th>Jml</th>
                        <th>Tgl Pinjam / Kembali</th>
                        <th>Keperluan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($borrowings)): ?>
                        <tr>
                            <td colspan="8" class="empty-row">Belum ada transaksi peminjaman alat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($borrowings as $br): ?>
                            <?php $isBorrowed = ((string) $br['status']) === Borrowing::STATUS_BORROWED; ?>
                            <tr>
                                <td><?= e($br['id']) ?></td>
                                <td>
                                    <strong><?= e($br['equipment_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($br['asset_code']) ?> &bull; <?= e($br['category_name']) ?></small>
                                </td>
                                <td>
                                    <strong><?= e($br['borrower_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($br['identity_number']) ?> &bull; <?= e($br['study_program']) ?></small>
                                </td>
                                <td><strong><?= e($br['quantity']) ?></strong> unit</td>
                                <td>
                                    <div>Pinjam: <?= e($br['borrow_date']) ?></div>
                                    <small class="text-muted">
                                        Kembali: <?= $br['return_date'] !== null ? e($br['return_date']) : '-' ?>
                                    </small>
                                </td>
                                <td><?= e($br['purpose']) ?></td>
                                <td>
                                    <span class="badge <?= $isBorrowed ? 'badge-warning' : 'badge-success' ?>">
                                        <?= e($br['status']) ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <?php if ($isBorrowed): ?>
                                        <form method="post" action="index.php?page=borrowings" class="inline-form"
                                              onsubmit="return confirm('Tandai alat <?= e($br['equipment_name']) ?> sudah dikembalikan oleh <?= e($br['borrower_name']) ?>?');">
                                            <input type="hidden" name="action" value="return">
                                            <input type="hidden" name="id" value="<?= e($br['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-success">Kembalikan</button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="index.php?page=borrowings&amp;edit=<?= e($br['id']) ?>" class="btn btn-sm btn-warning">Edit</a>

                                    <form method="post" action="index.php?page=borrowings" class="inline-form"
                                          onsubmit="return confirm('Yakin ingin menghapus data peminjaman ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e($br['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/footer.php';
