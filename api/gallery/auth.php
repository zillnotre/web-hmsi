<?php
// ============================================================
//  api/gallery/auth.php
//  Login & logout admin gallery
//
//  POST /api/gallery/auth.php         → login  { username, password }
//  DELETE /api/gallery/auth.php       → logout
//  GET    /api/gallery/auth.php       → cek status login
// ============================================================

require_once __DIR__ . '/config.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

if ($method === 'GET') {
    // Cek apakah sudah login
    jsonResponse([
        'success'    => true,
        'logged_in'  => !empty($_SESSION[ADMIN_SESSION_NAME]),
        'username'   => $_SESSION[ADMIN_SESSION_NAME] ?? null,
    ]);
}

if ($method === 'DELETE') {
    // Logout
    unset($_SESSION[ADMIN_SESSION_NAME]);
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Berhasil logout']);
}

if ($method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($body['username'] ?? $_POST['username'] ?? '');
    $password = trim($body['password'] ?? $_POST['password'] ?? '');

    if (!$username || !$password) {
        jsonResponse(['success' => false, 'message' => 'Username dan password wajib diisi'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM gallery_admin WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        jsonResponse(['success' => false, 'message' => 'Username atau password salah'], 401);
    }

    $_SESSION[ADMIN_SESSION_NAME] = $admin['username'];
    jsonResponse(['success' => true, 'message' => 'Login berhasil', 'username' => $admin['username']]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);