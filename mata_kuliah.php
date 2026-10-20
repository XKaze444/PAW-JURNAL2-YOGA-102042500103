<?php
require 'fungsi.php';
require 'koneksi.php';
require 'layout.php';

$aksi = $_GET['aksi'] ?? 'daftar';

function baca_mk($conn, $id) {
    $s = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah WHERE id = ?');
    $s->bind_param('i', $id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

function cek_mk($conn, $d, $id_sendiri) {
    $err = [];
    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $d['kode'])) {
        $err['kode'] = 'Kode 3-20 karakter: huruf kapital, angka, atau tanda -.';
    } else {
        $s = $conn->prepare('SELECT id FROM mata_kuliah WHERE kode = ? AND id <> ?');
        $s->bind_param('si', $d['kode'], $id_sendiri);
        $s->execute();
        if ($s->get_result()->fetch_assoc()) {
            $err['kode'] = 'Kode mata kuliah sudah digunakan.';
        }
    }
    if (!panjang_ok($d['nama'], 3, 100))  $err['nama']  = 'Nama harus 3-100 karakter.';
    if (!panjang_ok($d['dosen'], 3, 100)) $err['dosen'] = 'Dosen harus 3-100 karakter.';
    if (!ctype_digit($d['semester']) || (int)$d['semester'] < 1 || (int)$d['semester'] > 14) {
        $err['semester'] = 'Semester harus bilangan bulat 1-14.';
    }
    return $err;
}

function form_mk($d, $err, $judul, $id = 0) {
    ?>
    <h1><?= e($judul) ?></h1>
    <form method="post" action="mata_kuliah.php?aksi=<?= $id ? 'edit&amp;id=' . (int)$id : 'tambah' ?>">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <div class="grid">
        <div><label>Kode mata kuliah</label>
          <input type="text" name="kode" value="<?= e($d['kode']) ?>"><?= field_error($err, 'kode') ?></div>
        <div><label>Nama mata kuliah</label>
          <input type="text" name="nama" value="<?= e($d['nama']) ?>"><?= field_error($err, 'nama') ?></div>
        <div><label>Dosen pengampu</label>
          <input type="text" name="dosen" value="<?= e($d['dosen']) ?>"><?= field_error($err, 'dosen') ?></div>
        <div><label>Semester</label>
          <input type="text" name="semester" value="<?= e($d['semester']) ?>"><?= field_error($err, 'semester') ?></div>
      </div>
      <p><button class="btn utama" type="submit">Simpan data</button>
         <a class="btn" href="mata_kuliah.php">Batal</a></p>
    </form>
    <?php
}

function input_mk() {
    return ['kode' => teks($_POST, 'kode'), 'nama' => teks($_POST, 'nama'),
            'dosen' => teks($_POST, 'dosen'), 'semester' => teks($_POST, 'semester')];
}

// CREATE
if ($aksi === 'tambah') {
    $d = ['kode' => '', 'nama' => '', 'dosen' => '', 'semester' => ''];
    $err = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak disimpan.');
            pindah('mata_kuliah.php');
        }
        $d = input_mk();
        $err = cek_mk($conn, $d, 0);
        if (!$err) {
            try {
                $s = $conn->prepare('INSERT INTO mata_kuliah (kode, nama, dosen, semester) VALUES (?, ?, ?, ?)');
                $sem = (int)$d['semester'];
                $s->bind_param('sssi', $d['kode'], $d['nama'], $d['dosen'], $sem);
                $s->execute();
                set_pesan('sukses', 'Mata kuliah berhasil ditambahkan.');
                pindah('mata_kuliah.php');
            } catch (mysqli_sql_exception $ex) {
                // 1062 = duplikat UNIQUE (jaga-jaga jika dua permintaan datang bersamaan)
                $err['kode'] = ($ex->getCode() == 1062) ? 'Kode mata kuliah sudah digunakan.' : 'Data gagal disimpan.';
            }
        }
    }
    tampil_header('Tambah mata kuliah', 'mata_kuliah.php');
    form_mk($d, $err, 'Tambah mata kuliah');
    tampil_footer();
    exit;
}

// EDIT
if ($aksi === 'edit') {
    $id = id_valid($_GET['id'] ?? '');
    $lama = $id ? baca_mk($conn, $id) : null;
    if (!$lama) {
        set_pesan('error', 'Mata kuliah tidak ditemukan atau ID tidak valid.');
        pindah('mata_kuliah.php');
    }
    $d = $lama;
    $err = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak diubah.');
            pindah('mata_kuliah.php');
        }
        $d = input_mk();
        $err = cek_mk($conn, $d, $id);
        if (!$err) {
            $sem = (int)$d['semester'];
            $sama = $d['kode'] === $lama['kode'] && $d['nama'] === $lama['nama']
                 && $d['dosen'] === $lama['dosen'] && $sem === (int)$lama['semester'];
            if ($sama) {
                set_pesan('info', 'Tidak ada perubahan data.');
                pindah('mata_kuliah.php');
            }
            try {
                $s = $conn->prepare('UPDATE mata_kuliah SET kode = ?, nama = ?, dosen = ?, semester = ? WHERE id = ?');
                $s->bind_param('sssii', $d['kode'], $d['nama'], $d['dosen'], $sem, $id);
                $s->execute();
                set_pesan('sukses', 'Mata kuliah berhasil diubah.');
                pindah('mata_kuliah.php');
            } catch (mysqli_sql_exception $ex) {
                $err['kode'] = ($ex->getCode() == 1062) ? 'Kode mata kuliah sudah digunakan.' : 'Data gagal diubah.';
            }
        }
    }
    tampil_header('Edit mata kuliah', 'mata_kuliah.php');
    form_mk($d, $err, 'Edit mata kuliah', $id);
    tampil_footer();
    exit;
}

// DELEETE
if ($aksi === 'hapus') {
    $kirim = $_SERVER['REQUEST_METHOD'] === 'POST';
    $id = id_valid($kirim ? ($_POST['id'] ?? '') : ($_GET['id'] ?? ''));
    $mk = $id ? baca_mk($conn, $id) : null;
    if (!$mk) {
        set_pesan('error', 'Mata kuliah tidak ditemukan atau ID tidak valid.');
        pindah('mata_kuliah.php');
    }
    $galat = '';
    if ($kirim) {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak dihapus.');
            pindah('mata_kuliah.php');
        }
        try {
            $s = $conn->prepare('DELETE FROM mata_kuliah WHERE id = ?');
            $s->bind_param('i', $id);
            $s->execute();
            set_pesan('sukses', 'Mata kuliah berhasil dihapus.');
            pindah('mata_kuliah.php');
        } catch (mysqli_sql_exception $ex) {
            $galat = ($ex->getCode() == 1451)
                ? 'Data belum tersimpan. Mata kuliah masih memiliki materi atau tugas. Hapus data terkait terlebih dahulu.'
                : 'Data gagal dihapus.';
        }
    }
    tampil_header('Hapus mata kuliah', 'mata_kuliah.php');
    echo '<h1>Mata kuliah</h1>';
    if ($galat) echo '<div class="pesan error">' . e($galat) . '</div>';
    ?>
    <p>Hapus <b><?= e($mk['nama']) ?></b>?</p>
    <p>Data baru dihapus setelah tombol konfirmasi dikirim.</p>
    <form method="post" action="mata_kuliah.php?aksi=hapus" class="inline">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$mk['id'] ?>">
      <button class="btn bahaya" type="submit">Ya, hapus data</button>
    </form>
    <a class="btn" href="mata_kuliah.php">Batal</a>
    <?php
    tampil_footer();
    exit;
}

// DETAIL
if ($aksi === 'detail') {
    $id = id_valid($_GET['id'] ?? '');
    $mk = $id ? baca_mk($conn, $id) : null;
    if (!$mk) {
        set_pesan('error', 'Mata kuliah tidak ditemukan atau ID tidak valid.');
        pindah('mata_kuliah.php');
    }
    tampil_header('Detail mata kuliah', 'mata_kuliah.php');
    ?>
    <h1>Mata kuliah</h1>
    <dl>
      <dt>Kode</dt><dd><?= e($mk['kode']) ?></dd>
      <dt>Nama</dt><dd><?= e($mk['nama']) ?></dd>
      <dt>Dosen</dt><dd><?= e($mk['dosen']) ?></dd>
      <dt>Semester</dt><dd><?= (int)$mk['semester'] ?></dd>
    </dl>
    <p><a class="btn utama" href="mata_kuliah.php?aksi=edit&amp;id=<?= (int)$mk['id'] ?>">Edit data</a>
       <a class="btn" href="mata_kuliah.php">Kembali</a></p>
    <?php
    tampil_footer();
    exit;
}

// DAFTAR &&& PENCARIAN
$q = teks($_GET, 'q');
$sql = 'SELECT id, kode, nama, dosen, semester FROM mata_kuliah';
$tipe = '';
$par = [];
if ($q !== '') {
    $sql .= ' WHERE kode LIKE ? OR nama LIKE ?';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $tipe = 'ss';
    $par = [$like, $like];
}
$sql .= ' ORDER BY nama';
$s = $conn->prepare($sql);
if ($par) $s->bind_param($tipe, ...$par);
$s->execute();
$baris = $s->get_result()->fetch_all(MYSQLI_ASSOC);

tampil_header('Mata kuliah', 'mata_kuliah.php');
?>
<h1>Mata kuliah</h1>
<div class="baris"><span>Menampilkan <?= count($baris) ?> data.</span>
  <a class="btn utama" href="mata_kuliah.php?aksi=tambah">Tambah mata kuliah</a></div>
<form class="cari" method="get" action="mata_kuliah.php">
  <input type="text" name="q" placeholder="Cari kode atau nama" value="<?= e($q) ?>">
  <button class="btn utama" type="submit">Cari</button>
  <a class="btn" href="mata_kuliah.php">Reset</a>
</form>
<?php if (!$baris): ?>
  <div class="kosong">Data tidak ditemukan.</div>
<?php else: ?>
<table>
  <tr><th>Mata kuliah</th><th>Dosen / semester</th><th>Aksi</th></tr>
  <?php foreach ($baris as $r): ?>
  <tr>
    <td><?= e($r['kode']) ?> - <?= e($r['nama']) ?></td>
    <td><?= e($r['dosen']) ?> / Semester <?= (int)$r['semester'] ?></td>
    <td>
      <a class="btn kecil" href="mata_kuliah.php?aksi=detail&amp;id=<?= (int)$r['id'] ?>">Detail</a>
      <a class="btn kecil" href="mata_kuliah.php?aksi=edit&amp;id=<?= (int)$r['id'] ?>">Edit</a>
      <a class="btn kecil bahaya" href="mata_kuliah.php?aksi=hapus&amp;id=<?= (int)$r['id'] ?>">Hapus</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php tampil_footer();
