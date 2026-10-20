<?php
function tampil_header($judul, $aktif = '')
{
    $menu = [
        'index.php'       => 'Beranda',
        'mata_kuliah.php' => 'Mata kuliah',
        'materi.php'      => 'Materi',
        'tugas.php'       => 'Tugas',
    ];
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | LMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <?php foreach ($menu as $file => $label): ?>
            <a href="<?= e($file) ?>"<?= $file === $aktif ? ' class="aktif"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <main class="kartu">
    <?php
    $pesan = ambil_pesan();
    if ($pesan) {
        echo '<div class="pesan ' . e($pesan['tipe']) . '">' . e($pesan['teks']) . '</div>';
    }
}

function tampil_footer()
{
    ?>
    </main>
</body>
</html>
    <?php
}

function field_error($err, $kunci)
{
    if (!isset($err[$kunci])) {
        return '';
    }
    return '<div class="galat">' . e($err[$kunci]) . '</div>';
}