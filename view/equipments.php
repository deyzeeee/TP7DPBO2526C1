<?php

declare(strict_types=1);

/** @var Equipment|null $equipmentRepo */
/** @var Category|null $categoryRepo */
/** @var string|null $dbError */

if ($equipmentRepo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($action === 'create') {
            $equipmentRepo->create($_POST);
            setFlash('success', 'Data alat laboratorium berhasil ditambahkan.');
        } elseif ($action === 'update') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $equipmentRepo->update($id, $_POST);
            setFlash('success', 'Data alat laboratorium berhasil diperbarui.');
        } elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $equipmentRepo->delete($id);
            setFlash('success', 'Data alat laboratorium berhasil dihapus.');
        }
    } catch (Throwable $e) {
        setFlash('error', translateException($e));
    }

    redirectToPage('equipments');
}

$equipments = [];
$categories = [];
$editingEquipment = null;

if ($equipmentRepo !== null && $categoryRepo !== null) {
    try {
        $equipments = $equipmentRepo->findAll();
        $categories = $categoryRepo->findAll();

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editingEquipment = $equipmentRepo->findById((int) $_GET['edit']);
            if ($editingEquipment === null) {
                setFlash('error', 'Data alat laboratorium yang ingin diedit tidak ditemukan.');
                redirectToPage('equipments');
            }
        }
    } catch (Throwable $e) {
        $dbError = translateException($e);
    }
}

$isEdit = $editingEquipment !== null;
$hasCategories = !empty($categories);

require_once __DIR__ . '/header.php';
?>

<section class="grid-layout">
    <div class="card">
        <h2 class="card-title"><?= $isEdit ? 'Edit Data Alat Lab' : 'Tambah Alat Lab Baru' ?></h2>

        <?php if (!$hasCategories): ?>
            <div class="alert alert-warning">
                Tabel <strong>Kategori Alat</strong> masih kosong. Silakan isi terlebih dahulu di halaman
                <a href="index.php?page=categories">Kategori Alat</a> sebelum menambahkan alat laboratorium.
            </div>
        <?php endif; ?>

        <form method="post" action="index.php?page=equipments" class="form-stack">
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= e($editingEquipment['id']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="category_id">Kategori Alat (Foreign Key)</label>
                <select id="category_id" name="category_id" required <?= !$hasCategories ? 'disabled' : '' ?>>
                    <option value="">-- Pilih Kategori Alat --</option>
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        $selected = isset($editingEquipment['category_id'])
                            && (int) $editingEquipment['category_id'] === (int) $cat['id'];
                        ?>
                        <option value="<?= e($cat['id']) ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= e($cat['category_code']) ?> - <?= e($cat['category_name']) ?> (<?= e($cat['lab_room']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="asset_code">Kode Inventaris / Aset</label>
                <input type="text" id="asset_code" name="asset_code" maxlength="30" required
                       placeholder="Contoh: INV-IOT-003"
                       value="<?= e($editingEquipment['asset_code'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="equipment_name">Nama Alat Laboratorium</label>
                <input type="text" id="equipment_name" name="equipment_name" maxlength="120" required
                       placeholder="Contoh: Arduino Mega 2560 Rev3"
                       value="<?= e($editingEquipment['equipment_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="brand">Merek / Pabrikan</label>
                <input type="text" id="brand" name="brand" maxlength="80" required
                       placeholder="Contoh: Arduino / Cisco / Meta"
                       value="<?= e($editingEquipment['brand'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="stock">Stok Tersedia Saat Ini</label>
                <input type="number" id="stock" name="stock" min="0" max="9999" required
                       value="<?= e($editingEquipment['stock'] ?? 1) ?>">
            </div>

            <div class="form-group">
                <label for="item_condition">Kondisi Alat</label>
                <select id="item_condition" name="item_condition" required>
                    <?php foreach (Equipment::ALLOWED_CONDITIONS as $cond): ?>
                        <?php
                        $selectedCond = ($editingEquipment['item_condition'] ?? 'Baik') === $cond;
                        ?>
                        <option value="<?= e($cond) ?>" <?= $selectedCond ? 'selected' : '' ?>>
                            <?= e($cond) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= !$hasCategories ? 'disabled' : '' ?>>
                    <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Alat Lab' ?>
                </button>
                <?php if ($isEdit): ?>
                    <a href="index.php?page=equipments" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Daftar Inventaris Alat Laboratorium (<?= count($equipments) ?>)</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kode Aset</th>
                        <th>Nama Alat</th>
                        <th>Merek</th>
                        <th>Kategori &amp; Ruang Lab</th>
                        <th>Stok Tersedia</th>
                        <th>Kondisi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipments)): ?>
                        <tr>
                            <td colspan="8" class="empty-row">Belum ada data alat laboratorium.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipments as $eq): ?>
                            <tr>
                                <td><?= e($eq['id']) ?></td>
                                <td><span class="badge badge-code"><?= e($eq['asset_code']) ?></span></td>
                                <td><strong><?= e($eq['equipment_name']) ?></strong></td>
                                <td><?= e($eq['brand']) ?></td>
                                <td>
                                    <?= e($eq['category_name']) ?><br>
                                    <small class="text-muted"><?= e($eq['lab_room']) ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= (int) $eq['stock'] > 0 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= e($eq['stock']) ?> unit
                                    </span>
                                </td>
                                <td><?= e($eq['item_condition']) ?></td>
                                <td class="actions-cell">
                                    <a href="index.php?page=equipments&amp;edit=<?= e($eq['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <form method="post" action="index.php?page=equipments" class="inline-form"
                                          onsubmit="return confirm('Yakin ingin menghapus alat <?= e($eq['equipment_name']) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e($eq['id']) ?>">
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
