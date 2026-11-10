<?php

declare(strict_types=1);

/** @var Borrower|null $borrowerRepo */
/** @var string|null $dbError */

if ($borrowerRepo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($action === 'create') {
            $borrowerRepo->create($_POST);
            setFlash('success', 'Data peminjam berhasil ditambahkan.');
        } elseif ($action === 'update') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $borrowerRepo->update($id, $_POST);
            setFlash('success', 'Data peminjam berhasil diperbarui.');
        } elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $borrowerRepo->delete($id);
            setFlash('success', 'Data peminjam berhasil dihapus.');
        }
    } catch (Throwable $e) {
        setFlash('error', translateException($e));
    }

    redirectToPage('borrowers');
}

$borrowers = [];
$editingBorrower = null;

if ($borrowerRepo !== null) {
    try {
        $borrowers = $borrowerRepo->findAll();

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editingBorrower = $borrowerRepo->findById((int) $_GET['edit']);
            if ($editingBorrower === null) {
                setFlash('error', 'Data peminjam yang ingin diedit tidak ditemukan.');
                redirectToPage('borrowers');
            }
        }
    } catch (Throwable $e) {
        $dbError = translateException($e);
    }
}

$isEdit = $editingBorrower !== null;

require_once __DIR__ . '/header.php';
?>

<section class="grid-layout">
    <div class="card">
        <h2 class="card-title"><?= $isEdit ? 'Edit Data Peminjam' : 'Tambah Data Peminjam Baru' ?></h2>
        <form method="post" action="index.php?page=borrowers" class="form-stack">
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= e($editingBorrower['id']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="identity_number">NIM / NIP (Nomor Identitas)</label>
                <input type="text" id="identity_number" name="identity_number" maxlength="30" required
                       placeholder="Contoh: 2305123"
                       value="<?= e($editingBorrower['identity_number'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="full_name">Nama Lengkap</label>
                <input type="text" id="full_name" name="full_name" maxlength="120" required
                       placeholder="Contoh: Budi Santoso"
                       value="<?= e($editingBorrower['full_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="study_program">Program Studi / Unit</label>
                <input type="text" id="study_program" name="study_program" maxlength="80" required
                       placeholder="Contoh: Ilmu Komputer"
                       value="<?= e($editingBorrower['study_program'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" name="email" maxlength="120" required
                       placeholder="Contoh: budi.santoso@example.edu"
                       value="<?= e($editingBorrower['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="phone">Nomor Telepon / WhatsApp</label>
                <input type="text" id="phone" name="phone" maxlength="25" required
                       placeholder="Contoh: 081234567890"
                       value="<?= e($editingBorrower['phone'] ?? '') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Peminjam' ?>
                </button>
                <?php if ($isEdit): ?>
                    <a href="index.php?page=borrowers" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Daftar Peminjam Terdaftar (<?= count($borrowers) ?>)</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NIM / NIP</th>
                        <th>Nama Lengkap</th>
                        <th>Program Studi</th>
                        <th>Kontak</th>
                        <th>Pinjaman Aktif</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($borrowers)): ?>
                        <tr>
                            <td colspan="7" class="empty-row">Belum ada data peminjam.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($borrowers as $bw): ?>
                            <tr>
                                <td><?= e($bw['id']) ?></td>
                                <td><span class="badge badge-code"><?= e($bw['identity_number']) ?></span></td>
                                <td><strong><?= e($bw['full_name']) ?></strong></td>
                                <td><?= e($bw['study_program']) ?></td>
                                <td>
                                    <?= e($bw['email']) ?><br>
                                    <small class="text-muted"><?= e($bw['phone']) ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= (int) ($bw['active_borrowings'] ?? 0) > 0 ? 'badge-warning' : 'badge-success' ?>">
                                        <?= e($bw['active_borrowings'] ?? 0) ?> aktif
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <a href="index.php?page=borrowers&amp;edit=<?= e($bw['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <form method="post" action="index.php?page=borrowers" class="inline-form"
                                          onsubmit="return confirm('Yakin ingin menghapus peminjam <?= e($bw['full_name']) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e($bw['id']) ?>">
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
