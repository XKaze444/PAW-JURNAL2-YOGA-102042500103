<?php
require 'fungsi.php';
require 'koneksi.php';
require 'layout.php';

$aksi = $_GET['aksi'] ?? 'daftar';
$STATUS = ['Draf', 'Aktif', 'Selesai'];

function baca_tugas($conn, $id) {
    $s = $conn->prepare('SELECT t.id, t.mata_kuliah_id, t.judul, t.instruksi, t.tenggat, t.status, k.kode, k.nama AS mk_nama
                         FROM tugas t JOIN mata_kuliah k ON k.id = t.mata_kuliah_id WHERE t.id = ?');
    $s->bind_param('i', $id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

function input_tugas() {
    return ['mata_kuliah_id' => teks($_POST, 'mata_kuliah_id'), 'judul' => teks($_POST, 'judul'),
            'instruksi' => teks($_POST, 'instruksi'), 'tenggat' => teks($_POST, 'tenggat'),
            'status' => teks($_POST, 'status')];
}

function tanggal_valid($t) {
    $dt = DateTime::createFromFormat('Y-m-d', $t);
    return $dt && $dt->format('Y-m-d') === $t; // menolak 2026-02-31 dan sejenisnya
}

function cek_tugas($conn, $d, $STATUS) {
    $err = [];
    $mk = id_valid($d['mata_kuliah_id']);
    if (!$mk || !mk_ada($conn, $mk)) $err['mata_kuliah_id'] = 'Pilih mata kuliah yang tersedia.';
    if (!panjang_ok($d['judul'], 3, 150))     $err['judul'] = 'Judul harus 3-150 karakter.';
    if (!panjang_ok($d['instruksi'], 3, 2000)) $err['instruksi'] = 'Instruksi harus 3-2000 karakter.';
    if (!tanggal_valid($d['tenggat']))        $err['tenggat'] = 'Tanggal tenggat tidak valid.';
    if (!in_array($d['status'], $STATUS, true)) $err['status'] = 'Status hanya Draf, Aktif, atau Selesai.';
    return $err;
}

function form_tugas($conn, $d, $err, $judul, $STATUS, $id = 0) {
    $mks = daftar_mk($conn);
    ?>
    <h1><?= e($judul) ?></h1>
    <form method="post" action="tugas.php?aksi=<?= $id ? 'edit&amp;id=' . (int)$id : 'tambah' ?>">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <div class="grid">
        <div><label>Mata kuliah</label>
          <select name="mata_kuliah_id">
            <option value="">Pilih mata kuliah</option>
            <?php foreach ($mks as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= (string)$d['mata_kuliah_id'] === (string)$m['id'] ? 'selected' : '' ?>>
                <?= e($m['kode']) ?> - <?= e($m['nama']) ?></option>
            <?php endforeach; ?>
          </select><?= field_error($err, 'mata_kuliah_id') ?></div>
        <div><label>Judul tugas</label>
          <input type="text" name="judul" value="<?= e($d['judul']) ?>"><?= field_error($err, 'judul') ?></div>
      </div>
      <label>Instruksi tugas</label>
      <textarea name="instruksi"><?= e($d['instruksi']) ?></textarea><?= field_error($err, 'instruksi') ?>
      <div class="grid">
        <div><label>Tenggat</label>
          <input type="date" name="tenggat" value="<?= e($d['tenggat']) ?>"><?= field_error($err, 'tenggat') ?></div>
        <div><label>Status</label>
          <select name="status">
            <?php foreach ($STATUS as $st): ?>
              <option value="<?= e($st) ?>" <?= $d['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
          </select><?= field_error($err, 'status') ?></div>
      </div>
      <p><button class="btn utama" type="submit">Simpan data</button>
         <a class="btn" href="tugas.php">Batal</a></p>
    </form>
    <?php
}

// TAMBAH
if ($aksi === 'tambah') {
    $d = ['mata_kuliah_id' => '', 'judul' => '', 'instruksi' => '', 'tenggat' => '', 'status' => 'Draf'];
    $err = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak disimpan.');
            pindah('tugas.php');
        }
        $d = input_tugas();
        $err = cek_tugas($conn, $d, $STATUS);
        if (!$err) {
            $mk = (int)$d['mata_kuliah_id'];
            $s = $conn->prepare('INSERT INTO tugas (mata_kuliah_id, judul, instruksi, tenggat, status) VALUES (?, ?, ?, ?, ?)');
            $s->bind_param('issss', $mk, $d['judul'], $d['instruksi'], $d['tenggat'], $d['status']);
            $s->execute();
            set_pesan('sukses', 'Tugas berhasil ditambahkan.');
            pindah('tugas.php');
        }
    }
    tampil_header('Tambah tugas', 'tugas.php');
    form_tugas($conn, $d, $err, 'Tambah tugas', $STATUS);
    tampil_footer();
    exit;
}

// EDIT
if ($aksi === 'edit') {
    $id = id_valid($_GET['id'] ?? '');
    $lama = $id ? baca_tugas($conn, $id) : null;
    if (!$lama) {
        set_pesan('error', 'Tugas tidak ditemukan atau ID tidak valid.');
        pindah('tugas.php');
    }
    $d = $lama;
    $err = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak diubah.');
            pindah('tugas.php');
        }
        $d = input_tugas();
        $err = cek_tugas($conn, $d, $STATUS);
        if (!$err) {
            $mk = (int)$d['mata_kuliah_id'];
            $sama = $mk === (int)$lama['mata_kuliah_id'] && $d['judul'] === $lama['judul']
                 && $d['instruksi'] === $lama['instruksi'] && $d['tenggat'] === $lama['tenggat']
                 && $d['status'] === $lama['status'];
            if ($sama) {
                set_pesan('info', 'Tidak ada perubahan data.');
                pindah('tugas.php');
            }
            $s = $conn->prepare('UPDATE tugas SET mata_kuliah_id = ?, judul = ?, instruksi = ?, tenggat = ?, status = ? WHERE id = ?');
            $s->bind_param('issssi', $mk, $d['judul'], $d['instruksi'], $d['tenggat'], $d['status'], $id);
            $s->execute();
            set_pesan('sukses', 'Tugas berhasil diubah.');
            pindah('tugas.php');
        }
    }
    tampil_header('Edit tugas', 'tugas.php');
    form_tugas($conn, $d, $err, 'Edit tugas', $STATUS, $id);
    tampil_footer();
    exit;
}

// HAPUS
if ($aksi === 'hapus') {
    $kirim = $_SERVER['REQUEST_METHOD'] === 'POST';
    $id = id_valid($kirim ? ($_POST['id'] ?? '') : ($_GET['id'] ?? ''));
    $t = $id ? baca_tugas($conn, $id) : null;
    if (!$t) {
        set_pesan('error', 'Tugas tidak ditemukan atau ID tidak valid.');
        pindah('tugas.php');
    }
    if ($kirim) {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak dihapus.');
            pindah('tugas.php');
        }
        $s = $conn->prepare('DELETE FROM tugas WHERE id = ?');
        $s->bind_param('i', $id);
        $s->execute();
        set_pesan('sukses', 'Tugas berhasil dihapus.');
        pindah('tugas.php');
    }
    tampil_header('Hapus tugas', 'tugas.php');
    ?>
    <h1>Tugas</h1>
    <p>Hapus <b><?= e($t['judul']) ?></b>?</p>
    <p>Data baru dihapus setelah tombol konfirmasi dikirim.</p>
    <form method="post" action="tugas.php?aksi=hapus" class="inline">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <button class="btn bahaya" type="submit">Ya, hapus data</button>
    </form>
    <a class="btn" href="tugas.php">Batal</a>
    <?php
    tampil_footer();
    exit;
}

// DETAIL
if ($aksi === 'detail') {
    $id = id_valid($_GET['id'] ?? '');
    $t = $id ? baca_tugas($conn, $id) : null;
    if (!$t) {
        set_pesan('error', 'Tugas tidak ditemukan atau ID tidak valid.');
        pindah('tugas.php');
    }
    tampil_header('Detail tugas', 'tugas.php');
    ?>
    <h1>Tugas</h1>
    <dl>
      <dt>Mata kuliah</dt><dd><?= e($t['kode']) ?> - <?= e($t['mk_nama']) ?></dd>
      <dt>Judul tugas</dt><dd><?= e($t['judul']) ?></dd>
      <dt>Instruksi</dt><dd><?= nl2br(e($t['instruksi'])) ?></dd>
      <dt>Tenggat</dt><dd><?= e($t['tenggat']) ?></dd>
      <dt>Status</dt><dd><?= e($t['status']) ?></dd>
    </dl>
    <p><a class="btn utama" href="tugas.php?aksi=edit&amp;id=<?= (int)$t['id'] ?>">Edit data</a>
       <a class="btn" href="tugas.php">Kembali</a></p>
    <?php
    tampil_footer();
    exit;
}

// ---------------------------------------------------------------- DAFTAR + PENCARIAN + FILTER
$q  = teks($_GET, 'q');
$mk = id_valid($_GET['mk'] ?? '');
$sql = 'SELECT t.id, t.judul, t.tenggat, t.status, k.kode, k.nama AS mk_nama
        FROM tugas t JOIN mata_kuliah k ON k.id = t.mata_kuliah_id WHERE 1 = 1';
$tipe = '';
$par = [];
if ($q !== '') {
    $sql .= ' AND t.judul LIKE ?';
    $tipe .= 's';
    $par[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($mk) {
    $sql .= ' AND t.mata_kuliah_id = ?';
    $tipe .= 'i';
    $par[] = $mk;
}
$sql .= ' ORDER BY t.tenggat';
$s = $conn->prepare($sql);
if ($par) $s->bind_param($tipe, ...$par);
$s->execute();
$baris = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$mks = daftar_mk($conn);

tampil_header('Tugas', 'tugas.php');
?>
<h1>Tugas</h1>
<div class="baris"><span>Menampilkan <?= count($baris) ?> data.</span>
  <a class="btn utama" href="tugas.php?aksi=tambah">Tambah tugas</a></div>
<form class="cari" method="get" action="tugas.php">
  <input type="text" name="q" placeholder="Cari judul tugas" value="<?= e($q) ?>">
  <select name="mk">
    <option value="">Semua mata kuliah</option>
    <?php foreach ($mks as $m): ?>
      <option value="<?= (int)$m['id'] ?>" <?= $mk === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode']) ?> - <?= e($m['nama']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn utama" type="submit">Cari</button>
  <a class="btn" href="tugas.php">Reset</a>
</form>
<?php if (!$baris): ?>
  <div class="kosong">Data tidak ditemukan.</div>
<?php else: ?>
<table>
  <tr><th>Judul</th><th>Mata kuliah</th><th>Tenggat / status</th><th>Aksi</th></tr>
  <?php foreach ($baris as $r): ?>
  <tr>
    <td><?= e($r['judul']) ?></td>
    <td><?= e($r['kode']) ?> - <?= e($r['mk_nama']) ?></td>
    <td><?= e($r['tenggat']) ?> / <?= e($r['status']) ?></td>
    <td>
      <a class="btn kecil" href="tugas.php?aksi=detail&amp;id=<?= (int)$r['id'] ?>">Detail</a>
      <a class="btn kecil" href="tugas.php?aksi=edit&amp;id=<?= (int)$r['id'] ?>">Edit</a>
      <a class="btn kecil bahaya" href="tugas.php?aksi=hapus&amp;id=<?= (int)$r['id'] ?>">Hapus</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php tampil_footer();
