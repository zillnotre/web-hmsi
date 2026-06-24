<?php
// ============================================================
//  api/pengurus/jabatan.php
//  CRUD Jabatan / Divisi Pengurus
//
//  GET    → semua jabatan (publik)
//  POST   → tambah jabatan [admin]
//  PUT    → edit jabatan   [admin]  ?id=N
//  DELETE → hapus jabatan  [admin]  ?id=N
// ============================================================

require_once __DIR__ . '/../gallery/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$db     = getDB();

if ($method === 'GET') {
    $jabatan = $db->query("SELECT * FROM pengurus_jabatan ORDER BY urutan ASC, nama ASC")->fetchAll();
    // Sertakan jumlah anggota per jabatan
    foreach ($jabatan as &$j) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM pengurus WHERE jabatan_id = ? AND is_active = 1");
        $stmt->execute([$j['id']]);
        $j['jumlah_aktif'] = (int) $stmt->fetchColumn();
    }
    jsonResponse(['success' => true, 'data' => $jabatan]);
}

requireAdmin();

if ($method === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $nama      = trim($body['nama']      ?? $_POST['nama']      ?? '');
    $tipe      = trim($body['tipe']      ?? $_POST['tipe']      ?? 'divisi');
    $icon      = trim($body['icon']      ?? $_POST['icon']      ?? '👤');
    $deskripsi = trim($body['deskripsi'] ?? $_POST['deskripsi'] ?? '');
    $urutan    = (int) ($body['urutan']  ?? $_POST['urutan']    ?? 0);

    if (!$nama) jsonResponse(['success' => false, 'message' => 'Nama jabatan wajib diisi'], 422);
    if (!in_array($tipe, ['pimpinan', 'divisi'])) $tipe = 'divisi';

    $db->prepare("INSERT INTO pengurus_jabatan (nama, tipe, icon, deskripsi, urutan) VALUES (?, ?, ?, ?, ?)")
       ->execute([$nama, $tipe, $icon ?: '👤', $deskripsi ?: null, $urutan]);

    jsonResponse(['success' => true, 'message' => 'Jabatan ditambahkan', 'id' => $db->lastInsertId()], 201);
}

if ($method === 'PUT') {
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $nama      = trim($body['nama']      ?? '');
    $tipe      = trim($body['tipe']      ?? 'divisi');
    $icon      = trim($body['icon']      ?? '👤');
    $deskripsi = trim($body['deskripsi'] ?? '');
    $urutan    = (int) ($body['urutan']  ?? 0);

    if (!$nama) jsonResponse(['success' => false, 'message' => 'Nama jabatan wajib diisi'], 422);
    if (!in_array($tipe, ['pimpinan', 'divisi'])) $tipe = 'divisi';

    $db->prepare("UPDATE pengurus_jabatan SET nama=?, tipe=?, icon=?, deskripsi=?, urutan=? WHERE id=?")
       ->execute([$nama, $tipe, $icon ?: '👤', $deskripsi ?: null, $urutan, $id]);

    jsonResponse(['success' => true, 'message' => 'Jabatan diperbarui']);
}

if ($method === 'DELETE') {
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);

    // Cek apakah masih ada pengurus
    $stmt = $db->prepare("SELECT COUNT(*) FROM pengurus WHERE jabatan_id = ?");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        jsonResponse(['success' => false, 'message' => 'Tidak bisa dihapus: masih ada pengurus di jabatan ini. Pindahkan atau hapus pengurus terlebih dahulu.'], 409);
    }

    $db->prepare("DELETE FROM pengurus_jabatan WHERE id=?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Jabatan dihapus']);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);