<?php

session_start();


function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function token() {
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token'];
}
function token_cocok($kiriman) {
    return is_string($kiriman) && isset($_SESSION['token'])
        && hash_equals($_SESSION['token'], $kiriman);
}

function set_pesan($tipe, $teks) {
    $_SESSION['pesan'] = ['tipe' => $tipe, 'teks' => $teks];
}
function ambil_pesan() {
    $p = $_SESSION['pesan'] ?? null;
    unset($_SESSION['pesan']);
    return $p;
}

function pindah($url) {
    header('Location: ' . $url);
    exit;
}

function id_valid($nilai) {
    if (!is_string($nilai) || !ctype_digit($nilai) || strlen($nilai) > 10 || (int)$nilai < 1) {
        return null;
    }
    return (int)$nilai;
}

function teks($sumber, $kunci) {
    $v = $sumber[$kunci] ?? '';
    return is_string($v) ? trim($v) : '';
}

function hitung($conn, $tabel) {
    $boleh = ['mata_kuliah', 'materi', 'tugas'];
    if (!in_array($tabel, $boleh, true)) {
        return 0;
    }
    $hasil = $conn->query('SELECT COUNT(*) AS total FROM ' . $tabel);
    return (int)$hasil->fetch_assoc()['total'];
}


function daftar_mk($conn) {
    $s = $conn->prepare('SELECT id, kode, nama FROM mata_kuliah ORDER BY nama');
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function mk_ada($conn, $id) {
    $s = $conn->prepare('SELECT id FROM mata_kuliah WHERE id = ?');
    $s->bind_param('i', $id);
    $s->execute();
    return (bool)$s->get_result()->fetch_assoc();
}

function panjang_ok($s, $min, $max) {
    $n = mb_strlen($s, 'UTF-8');
    return $n >= $min && $n <= $max;
}
