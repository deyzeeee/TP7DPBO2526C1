<?php

declare(strict_types=1);

/** @var Category|null $categoryRepo */
/** @var string|null $dbError */

if ($categoryRepo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($action === 'create') {
            $categoryRepo->create($_POST);
            setFlash('success', 'Kategori alat laboratorium berhasil ditambahkan.');
        } elseif ($action === 'update') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $categoryRepo->update($id, $_POST);
            setFlash('success', 'Data kategori alat berhasil diperbarui.');
        } elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $categoryRepo->delete($id);
            setFlash('success', 'Kategori alat berhasil dihapus.');
        }
    } catch (Throwable $e) {
        setFlash('error', translateException($e));
    }

    redirectToPage('categories');
}

$categories = [];
$editingCategory = null;

if ($categoryRepo !== null) {
    try {
        $categories = $categoryRepo->findAll();

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editingCategory = $categoryRepo->findById((int) $_GET['edit']);
            if ($editingCategory === null) {
                setFlash('error', 'Data kategori yang ingin diedit tidak ditemukan.');
                redirectToPage('categories');
            }
        }
    } catch (Throwable $e) {
        $dbError = translateException($e);
    }
}

$isEdit = $editingCategory !== null;

require_once __DIR__ . '/header.php';
?>

<section class="grid-layout">
    <div class="card">
        <h2 class="card-title"><?= $isEdit ? 'Edit Kategori Alat' : 'Tambah Kategori Alat Baru' ?></h2>
        <form method="post" action="index.php?page=categories" class="form-stack">
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= e($editingCategory['id']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="category_code">Kode Kategori</label>
                <input type="text" id="category_code" name="category_code" maxlength="20" required
                       placeholder="Contoh: CAT-IOT"
                       value="<?= e($editingCategory['category_code'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="category_name">Nama Kategori</label>
                <input type="text" id="category_name" name="category_name" maxlength="100" required
                       placeholder="Contoh: Perangkat IoT & Mikrokontroler"
                       value="<?= e($editingCategory['category_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="lab_room">Lokasi Ruang Laboratorium</label>
                <input type="text" id="lab_room" name="lab_room" maxlength="60" required
                       placeholder="Contoh: Lab Sistem Tertanam Lantai 2"
                       value="<?= e($editingCategory['lab_room'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="description">Deskripsi Kategori</label>
                <textarea id="description" name="description" rows="3" maxlength="255" required
                          placeholder="Penjelasan singkat jenis perangkat pada kategori ini"><?= e($editingCategory['description'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Kategori' ?>
                </button>
                <?php if ($isEdit): ?>
                    <a href="index.php?page=categories" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Daftar Kategori Peralatan (<?= count($categories) ?>)</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Nama Kategori</th>
                        <th>Ruang Lab</th>
                        <th>Deskripsi</th>
                        <th>Jml Jenis Alat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="7" class="empty-row">Belum ada data kategori peralatan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= e($cat['id']) ?></td>
                                <td><span class="badge badge-code"><?= e($cat['category_code']) ?></span></td>
                                <td><strong><?= e($cat['category_name']) ?></strong></td>
                                <td><?= e($cat['lab_room']) ?></td>
                                <td><?= e($cat['description']) ?></td>
                                <td><?= e($cat['total_equipments'] ?? 0) ?> alat</td>
                                <td class="actions-cell">
                                    <a href="index.php?page=categories&amp;edit=<?= e($cat['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <form method="post" action="index.php?page=categories" class="inline-form"
                                          onsubmit="return confirm('Yakin ingin menghapus kategori <?= e($cat['category_name']) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e($cat['id']) ?>">
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
