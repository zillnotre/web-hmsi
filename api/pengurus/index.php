<?php
// ============================================================
//  api/pengurus/index.php
//  CRUD API untuk Pengurus HMSI UNIPEM
//
//  Endpoint:
//   GET    /api/pengurus/         → semua pengurus (publik)
//   GET    /api/pengurus/?id=N    → detail 1 pengurus (publik)
//   POST   /api/pengurus/         → tambah pengurus [admin]
//   PUT    /api/pengurus/?id=N    → edit pengurus   [admin]
//   DELETE /api/pengurus/?id=N    → hapus pengurus  [admin]
// ============================================================

require_once __DIR__ . '/../gallery/config.php';

// ---- Upload config khusus pengurus ----
define('PENGURUS_UPLOAD_DIR', __DIR__ . '/../../img/pengurus/');
define('PENGURUS_UPLOAD_URL', 'img/pengurus/');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_GET['_method'])) {
    $method = strtoupper($_GET['_method']);
}
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

match ($method) {
    'GET'    => handleGet($id),
    'POST'   => handlePost(),
    'PUT'    => handlePut($id),
    'DELETE' => handleDelete($id),
    default  => jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405),
};

// ============================================================
//  GET
// ============================================================
function handleGet(?int $id): void {
    $db = getDB();

    if ($id) {
        $stmt = $db->prepare("
            SELECT p.*, j.nama AS jabatan_nama, j.tipe AS jabatan_tipe,
                   j.icon AS jabatan_icon, j.deskripsi AS jabatan_deskripsi
            FROM pengurus p
            JOIN pengurus_jabatan j ON p.jabatan_id = j.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'message' => 'Pengurus tidak ditemukan'], 404);
        jsonResponse(['success' => true, 'data' => $item]);
    }

    $jabatanId  = isset($_GET['jabatan_id']) ? (int) $_GET['jabatan_id'] : null;
    $tipe       = $_GET['tipe']   ?? null;   // 'pimpinan' | 'divisi'
    $activeOnly = ($_GET['active'] ?? '1') === '1';

    $sql = "
        SELECT p.id, p.jabatan_id, p.nama, p.nim, p.angkatan, p.peran,
               p.foto_path, p.quote, p.is_active, p.urutan, p.created_at,
               j.nama AS jabatan_nama, j.tipe AS jabatan_tipe,
               j.icon AS jabatan_icon, j.deskripsi AS jabatan_deskripsi
        FROM pengurus p
        JOIN pengurus_jabatan j ON p.jabatan_id = j.id
        WHERE 1=1
    ";
    $params = [];

    if ($activeOnly)  { $sql .= " AND p.is_active = 1"; }
    if ($jabatanId)   { $sql .= " AND p.jabatan_id = ?"; $params[] = $jabatanId; }
    if ($tipe)        { $sql .= " AND j.tipe = ?";       $params[] = $tipe; }

    $sql .= " ORDER BY j.urutan ASC, p.urutan ASC, p.created_at ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $jabatan = $db->query("SELECT * FROM pengurus_jabatan ORDER BY urutan ASC")->fetchAll();

    jsonResponse([
        'success'  => true,
        'jabatan'  => $jabatan,
        'data'     => $items,
        'total'    => count($items),
    ]);
}

// ============================================================
//  POST
// ============================================================
function handlePost(): void {
    requireAdmin();

    $jabatanId = (int) ($_POST['jabatan_id'] ?? 0);
    $nama      = trim($_POST['nama']     ?? '');
    $nim       = trim($_POST['nim']      ?? '');
    $angkatan  = $_POST['angkatan']      ?? null;
    $peran     = trim($_POST['peran']    ?? 'Anggota');
    $quote     = trim($_POST['quote']    ?? '');
    $urutan    = (int) ($_POST['urutan'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

    if (!$jabatanId || !$nama) {
        jsonResponse(['success' => false, 'message' => 'Jabatan dan nama wajib diisi'], 422);
    }

    $fotoPath = !empty($_FILES['foto']['name']) ? uploadFoto() : null;

    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO pengurus
            (jabatan_id, nama, nim, angkatan, peran, foto_path, quote, urutan, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $jabatanId, $nama,
        $nim ?: null,
        $angkatan ?: null,
        $peran, $fotoPath,
        $quote ?: null,
        $urutan, $isActive,
    ]);

    jsonResponse(['success' => true, 'message' => 'Pengurus berhasil ditambahkan', 'id' => $db->lastInsertId()], 201);
}

// ============================================================
//  PUT
// ============================================================
function handlePut(?int $id): void {
    requireAdmin();
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);

    $jabatanId = (int) ($_POST['jabatan_id'] ?? 0);
    $nama      = trim($_POST['nama']     ?? '');
    $nim       = trim($_POST['nim']      ?? '');
    $angkatan  = $_POST['angkatan']      ?? null;
    $peran     = trim($_POST['peran']    ?? 'Anggota');
    $quote     = trim($_POST['quote']    ?? '');
    $urutan    = (int) ($_POST['urutan'] ?? 0);
    $isActive  = (int) ($_POST['is_active'] ?? 1);

    if (!$jabatanId || !$nama) {
        jsonResponse(['success' => false, 'message' => 'Jabatan dan nama wajib diisi'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT foto_path FROM pengurus WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) jsonResponse(['success' => false, 'message' => 'Pengurus tidak ditemukan'], 404);

    $fotoPath = $existing['foto_path'];
    if (!empty($_FILES['foto']['name'])) {
        $newPath = uploadFoto();
        // Hapus foto lama jika ada di folder pengurus
        if ($fotoPath && str_contains($fotoPath, 'img/pengurus/')) {
            $oldFile = __DIR__ . '/../../' . $fotoPath;
            if (file_exists($oldFile)) @unlink($oldFile);
        }
        $fotoPath = $newPath;
    }

    // Hapus foto jika diminta
    if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1') {
        if ($fotoPath && str_contains($fotoPath, 'img/pengurus/')) {
            $oldFile = __DIR__ . '/../../' . $fotoPath;
            if (file_exists($oldFile)) @unlink($oldFile);
        }
        $fotoPath = null;
    }

    $stmt = $db->prepare("
        UPDATE pengurus
        SET jabatan_id=?, nama=?, nim=?, angkatan=?, peran=?,
            foto_path=?, quote=?, urutan=?, is_active=?
        WHERE id=?
    ");
    $stmt->execute([
        $jabatanId, $nama,
        $nim ?: null,
        $angkatan ?: null,
        $peran, $fotoPath,
        $quote ?: null,
        $urutan, $isActive, $id,
    ]);

    jsonResponse(['success' => true, 'message' => 'Pengurus berhasil diperbarui']);
}

// ============================================================
//  DELETE
// ============================================================
function handleDelete(?int $id): void {
    requireAdmin();
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);

    $db   = getDB();
    $stmt = $db->prepare("SELECT foto_path FROM pengurus WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) jsonResponse(['success' => false, 'message' => 'Pengurus tidak ditemukan'], 404);

    if ($item['foto_path'] && str_contains($item['foto_path'], 'img/pengurus/')) {
        $file = __DIR__ . '/../../' . $item['foto_path'];
        if (file_exists($file)) @unlink($file);
    }

    $db->prepare("DELETE FROM pengurus WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Pengurus berhasil dihapus']);
}

// ============================================================
//  HELPER — Upload foto
// ============================================================
function uploadFoto(): string {
    $file     = $_FILES['foto'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        jsonResponse(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.'], 422);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        jsonResponse(['success' => false, 'message' => 'Ukuran file maksimal 5 MB'], 422);
    }
    if (!is_dir(PENGURUS_UPLOAD_DIR)) {
        mkdir(PENGURUS_UPLOAD_DIR, 0755, true);
    }

    $ext      = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    };
    $filename = uniqid('peng_', true) . '.' . $ext;
    $dest     = PENGURUS_UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file'], 500);
    }

    return PENGURUS_UPLOAD_URL . $filename;
}