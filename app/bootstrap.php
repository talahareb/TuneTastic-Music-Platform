<?php
// Small, dependency-free PHP application for a local academic portfolio demo.
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function escape_html(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function valid_price(string $value): ?string {
    if (!preg_match('/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/', $value)) return null;
    $number = (float) $value;
    return $number <= 999999.99 ? number_format($number, 2, '.', '') : null;
}
function safe_name(string $name): bool {
    return (bool) preg_match('/^[a-f0-9]{32}\.(?:mp3|wav|ogg|jpg|png|webp)$/', $name);
}
function setting(string $key, mixed $fallback = null): mixed {
    static $settings = null;
    if ($settings === null) {
        $path = dirname(__DIR__) . '/config.local.php';
        $settings = is_file($path) ? require $path : [];
    }
    return $settings[$key] ?? $fallback;
}
function database(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        setting('db_host','127.0.0.1'), (int) setting('db_port',3306), setting('db_name','tunetastic'));
    $pdo = new PDO($dsn, (string) setting('db_user','root'), (string) setting('db_password',''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function user_id(): ?int {
    return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
}
function current_user(): ?array {
    $id = user_id();
    if (!$id) return null;
    $stmt = database()->prepare('SELECT id, display_name, email FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) unset($_SESSION['uid']);
    return $user ?: null;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . escape_html(csrf_token()) . '">';
}
function require_csrf(): void {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Invalid form token. Go back, refresh the page, and retry.');
    }
}
function flash(string $message, string $type = 'info'): void {
    $_SESSION['notice'] = [$message, $type];
}
function redirect(string $target = '?page=discover'): never {
    header('Location: ' . $target, true, 303); exit;
}
function require_login(): int {
    $id = user_id();
    if (!$id) { flash('Please sign in first.', 'error'); redirect('?page=login'); }
    return $id;
}
function one_beat(int $beatId): ?array {
    $stmt = database()->prepare('SELECT b.*, u.display_name AS creator FROM beats b JOIN users u ON u.id=b.owner_id WHERE b.id=?');
    $stmt->execute([$beatId]);
    return $stmt->fetch() ?: null;
}
function upload_media(?array $file, string $kind, bool $required): ?array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) throw new RuntimeException('Please select a ' . $kind . ' file.');
        return null;
    }
    if (($file['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The uploaded file could not be received.');
    }
    $allowed = $kind === 'audio'
        ? ['audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/x-wav'=>'wav','audio/wav'=>'wav','audio/ogg'=>'ogg']
        : ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $limit = $kind === 'audio' ? 12 * 1024 * 1024 : 3 * 1024 * 1024;
    if ($file['size'] < 1 || $file['size'] > $limit) throw new RuntimeException('File size exceeds the allowed limit.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) throw new RuntimeException('Unsupported ' . $kind . ' format.');
    if ($kind === 'image' && @getimagesize($file['tmp_name']) === false) throw new RuntimeException('Invalid image file.');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Storage directory unavailable.');
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Unable to save upload.');
    return ['path' => $name, 'mime' => $mime];
}
function delete_media(?string $name): void {
    if ($name && safe_name($name)) {
        $path = dirname(__DIR__) . '/storage/' . $name;
        if (is_file($path)) @unlink($path);
    }
}
function text_field(string $name, int $max = 120): string {
    $value = trim((string) ($_POST[$name] ?? ''));
    if (strlen($value) > $max) throw new RuntimeException(ucfirst($name) . ' is too long.');
    return $value;
}
