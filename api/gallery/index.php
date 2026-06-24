<?php
// ============================================================
//  api/gallery/index.php
//  CRUD API untuk Gallery HMSI UNIPEM
//
//  Endpoint:
//   GET    /api/gallery/         → ambil semua item (publik)
//   GET    /api/gallery/?id=N    → detail 1 item (publik)
//   POST   /api/gallery/         → tambah item [admin]
//   PUT    /api/gallery/?id=N    → edit item   [admin]
//   DELETE /api/gallery/?id=N    → hapus item  [admin]
// ============================================================

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

match ($method) {
    'GET'    => handleGet($id),
    'POST'   => handlePost(),
    'PUT'    => handlePut($id),
    'DELETE' => handleDelete($id),
    default  => jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405),
};

// ============================================================
//  GET — Ambil data gallery (publik)
// ============================================================
function handleGet(?int $id): void {
    $db = getDB();

    if ($id) {
        // detail 1 item
        $stmt = $db->prepare("
            SELECT gi.*, gc.slug AS category_slug, gc.label AS category_label
            FROM gallery_items gi
            JOIN gallery_categories gc ON gi.category_id = gc.id
            WHERE gi.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'message' => 'Item tidak ditemukan'], 404);
        jsonResponse(['success' => true, 'data' => $item]);
    }

    // query params opsional
    $catSlug   = $_GET['category'] ?? null;
    $activeOnly = ($_GET['active'] ?? '1') === '1';

    $sql = "
        SELECT gi.id, gi.title, gi.description, gi.image_path,
               gi.image_height, gi.is_active, gi.sort_order, gi.created_at,
               gc.id AS category_id, gc.slug AS category_slug, gc.label AS category_label
        FROM gallery_items gi
        JOIN gallery_categories gc ON gi.category_id = gc.id
        WHERE 1=1
    ";
    $params = [];

    if ($activeOnly) { $sql .= " AND gi.is_active = 1"; }
    if ($catSlug)    {
        $sql .= " AND gc.slug = ?";
        $params[] = $catSlug;
    }

    $sql .= " ORDER BY gi.sort_order ASC, gi.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Ambil juga kategori
    $cats  = $db->query("SELECT * FROM gallery_categories ORDER BY label")->fetchAll();

    jsonResponse(['success' => true, 'categories' => $cats, 'data' => $items, 'total' => count($items)]);
}

// ============================================================
//  POST — Tambah item baru [admin]
// ============================================================
function handlePost(): void {
    requireAdmin();

    // Ambil field teks
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $imgHeight   = $_POST['image_height'] ?? 'md';
    $sortOrder   = (int) ($_POST['sort_order'] ?? 0);
    $isActive    = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

    if (!$title || !$categoryId) {
        jsonResponse(['success' => false, 'message' => 'Judul dan kategori wajib diisi'], 422);
    }

    // Upload gambar
    $imagePath = uploadImage();

    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO gallery_items
            (category_id, title, description, image_path, image_height, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$categoryId, $title, $description, $imagePath, $imgHeight, $sortOrder, $isActive]);
    $newId = $db->lastInsertId();

    jsonResponse(['success' => true, 'message' => 'Foto berhasil ditambahkan', 'id' => $newId], 201);
}

// ============================================================
//  PUT — Edit item [admin]
// ============================================================
function handlePut(?int $id): void {
    requireAdmin();
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);

    // PHP PUT tidak otomatis parse $_POST, perlu parse sendiri jika JSON
    // Tapi karena kita kirim FormData (ada file), kita tetap pakai $_POST + $_FILES
    // Trik: override method via ?_method=PUT di URL dan kirim sebagai POST
    parse_str(file_get_contents('php://input'), $putData);

    $title       = trim($_POST['title']       ?? $putData['title']       ?? '');
    $description = trim($_POST['description'] ?? $putData['description'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? $putData['category_id'] ?? 0);
    $imgHeight   = $_POST['image_height']  ?? $putData['image_height']  ?? 'md';
    $sortOrder   = (int) ($_POST['sort_order']  ?? $putData['sort_order']  ?? 0);
    $isActive    = (int) ($_POST['is_active']   ?? $putData['is_active']   ?? 1);

    if (!$title || !$categoryId) {
        jsonResponse(['success' => false, 'message' => 'Judul dan kategori wajib diisi'], 422);
    }

    $db = getDB();

    // Cek item ada
    $stmt = $db->prepare("SELECT image_path FROM gallery_items WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) jsonResponse(['success' => false, 'message' => 'Item tidak ditemukan'], 404);

    // Upload gambar baru jika ada
    $imagePath = $existing['image_path'];
    if (!empty($_FILES['image']['name'])) {
        $newPath = uploadImage();
        // Hapus file lama jika bukan path luar
        $oldFile = __DIR__ . '/../../' . $existing['image_path'];
        if (file_exists($oldFile) && str_contains($existing['image_path'], 'img/gallery/')) {
            @unlink($oldFile);
        }
        $imagePath = $newPath;
    }

    $stmt = $db->prepare("
        UPDATE gallery_items
        SET category_id=?, title=?, description=?, image_path=?,
            image_height=?, sort_order=?, is_active=?
        WHERE id=?
    ");
    $stmt->execute([$categoryId, $title, $description, $imagePath, $imgHeight, $sortOrder, $isActive, $id]);

    jsonResponse(['success' => true, 'message' => 'Foto berhasil diperbarui']);
}

// ============================================================
//  DELETE — Hapus item [admin]
// ============================================================
function handleDelete(?int $id): void {
    requireAdmin();
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);

    $db   = getDB();
    $stmt = $db->prepare("SELECT image_path FROM gallery_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) jsonResponse(['success' => false, 'message' => 'Item tidak ditemukan'], 404);

    // Hapus file gambar jika ada di folder gallery
    if (str_contains($item['image_path'], 'img/gallery/')) {
        $file = __DIR__ . '/../../' . $item['image_path'];
        if (file_exists($file)) @unlink($file);
    }

    $db->prepare("DELETE FROM gallery_items WHERE id = ?")->execute([$id]);

    jsonResponse(['success' => true, 'message' => 'Foto berhasil dihapus']);
}

// ============================================================
//  HELPER — Upload gambar
// ============================================================
function uploadImage(): string {
    if (empty($_FILES['image']['name'])) {
        jsonResponse(['success' => false, 'message' => 'File gambar wajib diunggah'], 422);
    }

    $file     = $_FILES['image'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        jsonResponse(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.'], 422);
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        jsonResponse(['success' => false, 'message' => 'Ukuran file maksimal 5 MB'], 422);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $ext      = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    };
    $filename = uniqid('gal_', true) . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file'], 500);
    }

    return UPLOAD_URL . $filename;
}