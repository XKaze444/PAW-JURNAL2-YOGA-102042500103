<?php
require 'fungsi.php';
require 'koneksi.php';
require 'layout.php';

$jml_mk     = hitung($conn, 'mata_kuliah');
$jml_materi = hitung($conn, 'materi');
$jml_tugas  = hitung($conn, 'tugas');
$daftar     = daftar_mk($conn);

tampil_header('Beranda', 'index.php');
?>
<h1>Learning Management System</h1>
<p>Pengelolaan mata kuliah, materi pembelajaran, dan tugas.</p>
<div class="ringkas">
  <div>Mata kuliah<b><?= $jml_mk ?></b></div>
  <div>Materi<b><?= $jml_materi ?></b></div>
  <div>Tugas<b><?= $jml_tugas ?></b></div>
</div>
<p>
  <a class="btn utama" href="mata_kuliah.php">Kelola mata kuliah</a>
  <a class="btn utama" href="materi.php">Kelola materi</a>
  <a class="btn utama" href="tugas.php">Kelola tugas</a>
</p>
<h3>Mata kuliah tersedia</h3>
<?php if (!$daftar): ?>
  <div class="kosong">Belum ada mata kuliah.</div>
<?php else: ?>
  <ul>
  <?php foreach ($daftar as $mk): ?>
    <li><?= e($mk['kode']) ?> - <?= e($mk['nama']) ?></li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php tampil_footer();
