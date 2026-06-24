<?php
// ============================================================
//  api/event/index.php
//  CRUD API untuk Event HMSI UNIPEM
//
//  Endpoint:
//   GET    /api/event/                     → semua event (publik), ?status=upcoming|past
//   GET    /api/event/?id=N                 → detail 1 event (publik)
//   POST   /api/event/                      → tambah event [admin]  (multipart/form-data, field 'foto' opsional)
//   POST   /api/event/?id=N&_method=PUT     → edit event   [admin]  (multipart/form-data, field 'foto' opsional)
//   DELETE /api/event/?id=N                 → hapus event  [admin]
//
//  NB: PUT dikirim sebagai POST + ?_method=PUT karena PHP tidak mem-parse
//      $_POST / $_FILES otomatis untuk request method PUT asli ketika
//      mengunggah file (multipart/form-data).
// ============================================================

require_once __DIR__ . '/../gallery/config.php';

// ---- Upload config khusus poster/logo event ----
define('EVENT_UPLOAD_DIR', __DIR__ . '/../../img/event/');
define('EVENT_UPLOAD_URL', 'img/event/');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
// Support _method override untuk PUT via POST (wajib dipakai saat upload file)
if ($method === 'POST' && isset($_GET['_method'])) {
    $method = strtoupper($_GET['_method']);
}
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

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
        $stmt = $db->prepare("SELECT * FROM event WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
        jsonResponse(['success' => true, 'data' => $item]);
    }

    $status     = $_GET['status']    ?? null;   // 'upcoming' | 'past'
    $kategori   = $_GET['kategori']  ?? null;
    $activeOnly = ($_GET['active']   ?? '1') === '1';

    $sql    = "SELECT * FROM event WHERE 1=1";
    $params = [];

    if ($activeOnly) { $sql .= " AND is_active = 1"; }
    if ($status)     { $sql .= " AND status = ?";    $params[] = $status; }
    if ($kategori)   { $sql .= " AND kategori = ?";  $params[] = $kategori; }

    $sql .= " ORDER BY tanggal DESC, urutan ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Statistik cepat
    $total    = count($items);
    $upcoming = count(array_filter($items, fn($i) => $i['status'] === 'upcoming'));
    $past     = $total - $upcoming;

    jsonResponse([
        'success'  => true,
        'data'     => $items,
        'total'    => $total,
        'upcoming' => $upcoming,
        'past'     => $past,
    ]);
}

// ============================================================
//  POST — Tambah event baru [admin]
// ============================================================
function handlePost(): void {
    requireAdmin();

    // Field teks dikirim via FormData (multipart) supaya bisa sertakan file
    $judul       = trim($_POST['judul']       ?? '');
    $kategori    = trim($_POST['kategori']    ?? '');
    $deskripsi   = trim($_POST['deskripsi']   ?? '');
    $tanggal     = trim($_POST['tanggal']     ?? '');
    $waktu       = trim($_POST['waktu']       ?? '');
    $lokasi      = trim($_POST['lokasi']      ?? '');
    $kuota       = trim($_POST['kuota']       ?? '');
    $emoji       = trim($_POST['emoji']       ?? '📅');
    $link_daftar = trim($_POST['link_daftar'] ?? '');
    $status      = in_array($_POST['status']  ?? '', ['upcoming', 'past']) ? $_POST['status'] : 'upcoming';
    $urutan      = (int) ($_POST['urutan']     ?? 0);
    $is_active   = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

    if (!$judul || !$tanggal) {
        jsonResponse(['success' => false, 'message' => 'Judul dan tanggal wajib diisi'], 422);
    }

    // Upload poster/logo jika ada
    $fotoPath = !empty($_FILES['foto']['name']) ? uploadEventFoto() : null;

    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO event
            (judul, kategori, deskripsi, tanggal, waktu, lokasi, kuota, emoji, foto_path, link_daftar, status, urutan, is_active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $judul, $kategori ?: null,
        $deskripsi ?: null, $tanggal,
        $waktu ?: null, $lokasi ?: null,
        $kuota ?: null, $emoji,
        $fotoPath,
        $link_daftar ?: null, $status,
        $urutan, $is_active,
    ]);

    jsonResponse(['success' => true, 'message' => 'Event berhasil ditambahkan', 'id' => $db->lastInsertId()], 201);
}

// ============================================================
//  PUT — Edit event [admin]
// ============================================================
function handlePut(?int $id): void {
    requireAdmin();
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);

    $judul       = trim($_POST['judul']       ?? '');
    $kategori    = trim($_POST['kategori']    ?? '');
    $deskripsi   = trim($_POST['deskripsi']   ?? '');
    $tanggal     = trim($_POST['tanggal']     ?? '');
    $waktu       = trim($_POST['waktu']       ?? '');
    $lokasi      = trim($_POST['lokasi']      ?? '');
    $kuota       = trim($_POST['kuota']       ?? '');
    $emoji       = trim($_POST['emoji']       ?? '📅');
    $link_daftar = trim($_POST['link_daftar'] ?? '');
    $status      = in_array($_POST['status']  ?? '', ['upcoming', 'past']) ? $_POST['status'] : 'upcoming';
    $urutan      = (int) ($_POST['urutan']     ?? 0);
    $is_active   = (int) ($_POST['is_active']  ?? 1);
    $hapusFoto   = ($_POST['hapus_foto'] ?? '0') === '1';

    if (!$judul || !$tanggal) {
        jsonResponse(['success' => false, 'message' => 'Judul dan tanggal wajib diisi'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT foto_path FROM event WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);

    $fotoPath = $existing['foto_path'];

    // Upload poster baru jika ada
    if (!empty($_FILES['foto']['name'])) {
        $newPath = uploadEventFoto();
        if ($fotoPath && str_contains($fotoPath, 'img/event/')) {
            $oldFile = __DIR__ . '/../../' . $fotoPath;
            if (file_exists($oldFile)) @unlink($oldFile);
        }
        $fotoPath = $newPath;
    } elseif ($hapusFoto) {
        // Hapus poster tanpa upload baru
        if ($fotoPath && str_contains($fotoPath, 'img/event/')) {
            $oldFile = __DIR__ . '/../../' . $fotoPath;
            if (file_exists($oldFile)) @unlink($oldFile);
        }
        $fotoPath = null;
    }

    $stmt = $db->prepare("
        UPDATE event
        SET judul=?, kategori=?, deskripsi=?, tanggal=?, waktu=?, lokasi=?,
            kuota=?, emoji=?, foto_path=?, link_daftar=?, status=?, urutan=?, is_active=?
        WHERE id=?
    ");
    $stmt->execute([
        $judul, $kategori ?: null,
        $deskripsi ?: null, $tanggal,
        $waktu ?: null, $lokasi ?: null,
        $kuota ?: null, $emoji,
        $fotoPath,
        $link_daftar ?: null, $status,
        $urutan, $is_active, $id,
    ]);

    jsonResponse(['success' => true, 'message' => 'Event berhasil diperbarui']);
}

// ============================================================
//  DELETE — Hapus event [admin]
// ============================================================
function handleDelete(?int $id): void {
    requireAdmin();
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);

    $db   = getDB();
    $stmt = $db->prepare("SELECT foto_path FROM event WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);

    if (!empty($item['foto_path']) && str_contains($item['foto_path'], 'img/event/')) {
        $file = __DIR__ . '/../../' . $item['foto_path'];
        if (file_exists($file)) @unlink($file);
    }

    $db->prepare("DELETE FROM event WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Event berhasil dihapus']);
}

// ============================================================
//  HELPER — Upload poster/logo event
// ============================================================
function uploadEventFoto(): string {
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
    if (!is_dir(EVENT_UPLOAD_DIR)) {
        mkdir(EVENT_UPLOAD_DIR, 0755, true);
    }

    $ext = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    };
    $filename = uniqid('evt_', true) . '.' . $ext;
    $dest     = EVENT_UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file'], 500);
    }

    return EVENT_UPLOAD_URL . $filename;
}