<?php
// ============================================================
//  api/gallery/config.php
//  Konfigurasi koneksi database
//  Sesuaikan dengan hosting / XAMPP kamu
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ganti dengan user MySQL kamu
define('DB_PASS', '');           // ganti dengan password MySQL kamu
define('DB_NAME', 'hmsi_unipem');
define('DB_CHARSET', 'utf8mb4');

// ---- Upload config ----
define('UPLOAD_DIR', __DIR__ . '/../../img/gallery/');   // folder penyimpanan foto
define('UPLOAD_URL', 'img/gallery/');                     // path relatif untuk HTML
define('MAX_FILE_SIZE', 5 * 1024 * 1024);                 // 5 MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ---- Session secret (ganti!) ----
define('ADMIN_SESSION_NAME', 'hmsi_gallery_admin');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // CORS untuk development lokal
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function isAdminLoggedIn(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION[ADMIN_SESSION_NAME]);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}