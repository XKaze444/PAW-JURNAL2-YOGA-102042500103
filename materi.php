<?php
require 'fungsi.php';
require 'koneksi.php';
require 'layout.php';

$aksi = $_GET['aksi'] ?? 'daftar';

function baca_materi($conn, $id) {
    $s = $conn->prepare('SELECT m.id, m.mata_kuliah_id, m.judul, m.isi, m.tautan, k.kode, k.nama AS mk_nama
                         FROM materi m JOIN mata_kuliah k ON k.id = m.mata_kuliah_id WHERE m.id = ?');
    $s->bind_param('i', $id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

function input_materi() {
    return ['mata_kuliah_id' => teks($_POST, 'mata_kuliah_id'), 'judul' => teks($_POST, 'judul'),
            'isi' => teks($_POST, 'isi'), 'tautan' => teks($_POST, 'tautan')];
}

function cek_materi($conn, $d) {
    $err = [];
    $mk = id_valid($d['mata_kuliah_id']);
    if (!$mk || !mk_ada($conn, $mk)) $err['mata_kuliah_id'] = 'Pilih mata kuliah yang tersedia.';
    if (!panjang_ok($d['judul'], 3, 150))  $err['judul'] = 'Judul harus 3-150 karakter.';
    if (!panjang_ok($d['isi'], 3, 2000))   $err['isi'] = 'Isi harus 3-2000 karakter.';
    // Hanya http:// atau https:// yang diterima, jadi javascript: dan sejenisnya ditolak.
    if (strlen($d['tautan']) > 255 || !preg_match('~^https?://~i', $d['tautan'])
        || !filter_var($d['tautan'], FILTER_VALIDATE_URL)) {
        $err['tautan'] = 'Gunakan tautan http/https yang valid (maksimal 255 karakter).';
    }
    return $err;
}

function form_materi($conn, $d, $err, $judul, $id = 0) {
    $mks = daftar_mk($conn);
    ?>
    <h1><?= e($judul) ?></h1>
    <form method="post" action="materi.php?aksi=<?= $id ? 'edit&amp;id=' . (int)$id : 'tambah' ?>">
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
        <div><label>Judul materi</label>
          <input type="text" name="judul" value="<?= e($d['judul']) ?>"><?= field_error($err, 'judul') ?></div>
      </div>
      <label>Isi materi</label>
      <textarea name="isi"><?= e($d['isi']) ?></textarea><?= field_error($err, 'isi') ?>
      <label>Tautan referensi</label>
      <input type="text" name="tautan" value="<?= e($d['tautan']) ?>"><?= field_error($err, 'tautan') ?>
      <p><button class="btn utama" type="submit">Simpan data</button>
         <a class="btn" href="materi.php">Batal</a></p>
    </form>
    <?php
}

// TAMBAH
if ($aksi === 'tambah') {
    $d = ['mata_kuliah_id' => '', 'judul' => '', 'isi' => '', 'tautan' => ''];
    $err = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak disimpan.');
            pindah('materi.php');
        }
        $d = input_materi();
        $err = cek_materi($conn, $d);
        if (!$err) {
            $mk = (int)$d['mata_kuliah_id'];
            $s = $conn->prepare('INSERT INTO materi (mata_kuliah_id, judul, isi, tautan) VALUES (?, ?, ?, ?)');
            $s->bind_param('isss', $mk, $d['judul'], $d['isi'], $d['tautan']);
            $s->execute();
            set_pesan('sukses', 'Materi berhasil ditambahkan.');
            pindah('materi.php');
        }
    }
    tampil_header('Tambah materi', 'materi.php');
    form_materi($conn, $d, $err, 'Tambah materi');
    tampil_footer();
    exit;
}

// EDIT
if ($aksi === 'edit') {
    $id = id_valid($_GET['id'] ?? '');
    $lama = $id ? baca_materi($conn, $id) : null;
    if (!$lama) {
        set_pesan('error', 'Materi tidak ditemukan atau ID tidak valid.');
        pindah('materi.php');
    }
    $d = $lama;
    $err = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak diubah.');
            pindah('materi.php');
        }
        $d = input_materi();
        $err = cek_materi($conn, $d);
        if (!$err) {
            $mk = (int)$d['mata_kuliah_id'];
            $sama = $mk === (int)$lama['mata_kuliah_id'] && $d['judul'] === $lama['judul']
                 && $d['isi'] === $lama['isi'] && $d['tautan'] === $lama['tautan'];
            if ($sama) {
                set_pesan('info', 'Tidak ada perubahan data.');
                pindah('materi.php');
            }
            $s = $conn->prepare('UPDATE materi SET mata_kuliah_id = ?, judul = ?, isi = ?, tautan = ? WHERE id = ?');
            $s->bind_param('isssi', $mk, $d['judul'], $d['isi'], $d['tautan'], $id);
            $s->execute();
            set_pesan('sukses', 'Materi berhasil diubah.');
            pindah('materi.php');
        }
    }
    tampil_header('Edit materi', 'materi.php');
    form_materi($conn, $d, $err, 'Edit materi', $id);
    tampil_footer();
    exit;
}

// HAPUS
if ($aksi === 'hapus') {
    $kirim = $_SERVER['REQUEST_METHOD'] === 'POST';
    $id = id_valid($kirim ? ($_POST['id'] ?? '') : ($_GET['id'] ?? ''));
    $m = $id ? baca_materi($conn, $id) : null;
    if (!$m) {
        set_pesan('error', 'Materi tidak ditemukan atau ID tidak valid.');
        pindah('materi.php');
    }
    if ($kirim) {
        if (!token_cocok($_POST['token'] ?? null)) {
            set_pesan('error', 'Token tidak valid. Data tidak dihapus.');
            pindah('materi.php');
        }
        $s = $conn->prepare('DELETE FROM materi WHERE id = ?');
        $s->bind_param('i', $id);
        $s->execute();
        set_pesan('sukses', 'Materi berhasil dihapus.');
        pindah('materi.php');
    }
    tampil_header('Hapus materi', 'materi.php');
    ?>
    <h1>Materi</h1>
    <p>Hapus <b><?= e($m['judul']) ?></b>?</p>
    <p>Data baru dihapus setelah tombol konfirmasi dikirim.</p>
    <form method="post" action="materi.php?aksi=hapus" class="inline">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <button class="btn bahaya" type="submit">Ya, hapus data</button>
    </form>
    <a class="btn" href="materi.php">Batal</a>
    <?php
    tampil_footer();
    exit;
}

// DETAIL
if ($aksi === 'detail') {
    $id = id_valid($_GET['id'] ?? '');
    $m = $id ? baca_materi($conn, $id) : null;
    if (!$m) {
        set_pesan('error', 'Materi tidak ditemukan atau ID tidak valid.');
        pindah('materi.php');
    }
    tampil_header('Detail materi', 'materi.php');
    ?>
    <h1>Materi</h1>
    <dl>
      <dt>Mata kuliah</dt><dd><?= e($m['kode']) ?> - <?= e($m['mk_nama']) ?></dd>
      <dt>Judul materi</dt><dd><?= e($m['judul']) ?></dd>
      <dt>Isi materi</dt><dd><?= nl2br(e($m['isi'])) ?></dd>
      <dt>Tautan referensi</dt><dd><a href="<?= e($m['tautan']) ?>" target="_blank" rel="noopener noreferrer"><?= e($m['tautan']) ?></a></dd>
    </dl>
    <p><a class="btn utama" href="materi.php?aksi=edit&amp;id=<?= (int)$m['id'] ?>">Edit data</a>
       <a class="btn" href="materi.php">Kembali</a></p>
    <?php
    tampil_footer();
    exit;
}

// DAFTAR + PENCARIAN + FILTER
$q  = teks($_GET, 'q');
$mk = id_valid($_GET['mk'] ?? '');
$sql = 'SELECT m.id, m.judul, k.kode, k.nama AS mk_nama
        FROM materi m JOIN mata_kuliah k ON k.id = m.mata_kuliah_id WHERE 1 = 1';
$tipe = '';
$par = [];
if ($q !== '') {
    $sql .= ' AND m.judul LIKE ?';
    $tipe .= 's';
    $par[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($mk) {
    $sql .= ' AND m.mata_kuliah_id = ?';
    $tipe .= 'i';
    $par[] = $mk;
}
$sql .= ' ORDER BY m.id DESC';
$s = $conn->prepare($sql);
if ($par) $s->bind_param($tipe, ...$par);
$s->execute();
$baris = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$mks = daftar_mk($conn);

tampil_header('Materi', 'materi.php');
?>
<h1>Materi</h1>
<div class="baris"><span>Menampilkan <?= count($baris) ?> data.</span>
  <a class="btn utama" href="materi.php?aksi=tambah">Tambah materi</a></div>
<form class="cari" method="get" action="materi.php">
  <input type="text" name="q" placeholder="Cari judul materi" value="<?= e($q) ?>">
  <select name="mk">
    <option value="">Semua mata kuliah</option>
    <?php foreach ($mks as $m): ?>
      <option value="<?= (int)$m['id'] ?>" <?= $mk === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['kode']) ?> - <?= e($m['nama']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn utama" type="submit">Cari</button>
  <a class="btn" href="materi.php">Reset</a>
</form>
<?php if (!$baris): ?>
  <div class="kosong">Data tidak ditemukan.</div>
<?php else: ?>
<table>
  <tr><th>Judul</th><th>Mata kuliah</th><th>Aksi</th></tr>
  <?php foreach ($baris as $r): ?>
  <tr>
    <td><?= e($r['judul']) ?></td>
    <td><?= e($r['kode']) ?> - <?= e($r['mk_nama']) ?></td>
    <td>
      <a class="btn kecil" href="materi.php?aksi=detail&amp;id=<?= (int)$r['id'] ?>">Detail</a>
      <a class="btn kecil" href="materi.php?aksi=edit&amp;id=<?= (int)$r['id'] ?>">Edit</a>
      <a class="btn kecil bahaya" href="materi.php?aksi=hapus&amp;id=<?= (int)$r['id'] ?>">Hapus</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php tampil_footer();
