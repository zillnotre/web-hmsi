<?php
// ============================================================
//  api/gallery/categories.php
//  CRUD Kategori Gallery
//
//  GET    → semua kategori (publik)
//  POST   → tambah kategori [admin]
//  PUT    → edit kategori   [admin]  ?id=N
//  DELETE → hapus kategori  [admin]  ?id=N
// ============================================================

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$db     = getDB();

if ($method === 'GET') {
    $cats = $db->query("SELECT * FROM gallery_categories ORDER BY label")->fetchAll();
    jsonResponse(['success' => true, 'data' => $cats]);
}

requireAdmin();

if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $slug  = trim($body['slug'] ?? $_POST['slug'] ?? '');
    $label = trim($body['label'] ?? $_POST['label'] ?? '');

    if (!$slug || !$label) jsonResponse(['success' => false, 'message' => 'Slug dan label wajib diisi'], 422);

    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
    $db->prepare("INSERT INTO gallery_categories (slug, label) VALUES (?, ?)")->execute([$slug, $label]);
    jsonResponse(['success' => true, 'message' => 'Kategori ditambahkan', 'id' => $db->lastInsertId()], 201);
}

if ($method === 'PUT') {
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $label = trim($body['label'] ?? '');
    if (!$label) jsonResponse(['success' => false, 'message' => 'Label wajib diisi'], 422);
    $db->prepare("UPDATE gallery_categories SET label=? WHERE id=?")->execute([$label, $id]);
    jsonResponse(['success' => true, 'message' => 'Kategori diperbarui']);
}

if ($method === 'DELETE') {
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID diperlukan'], 400);
    $db->prepare("DELETE FROM gallery_categories WHERE id=?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Kategori dihapus']);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);