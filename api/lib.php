<?php
$CFG = require __DIR__ . '/config.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

function is_sqlite() { global $CFG; return ($CFG['db']['driver'] ?? 'mysql') === 'sqlite'; }

// Ejecuta un archivo de esquema, sentencia por sentencia
function run_schema($pdo, $file) {
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../database/' . $file));
    foreach (explode(';', $sql) as $st) {
        $st = trim($st);
        if ($st === '' || preg_match('/^(CREATE DATABASE|USE)\b/i', $st)) continue;
        $pdo->exec($st);
    }
}

function has_col($p, $t, $c, $sqlite) {
    if ($sqlite) { foreach ($p->query("PRAGMA table_info($t)") as $r) if ($r['name'] === $c) return true; return false; }
    return (bool)$p->query("SHOW COLUMNS FROM $t LIKE '$c'")->fetchColumn();
}
// Agrega columnas nuevas a bases creadas con versiones anteriores (no borra datos)
function migrate($p, $sqlite) {
    if (!has_col($p, 'users', 'activo', $sqlite))
        $p->exec($sqlite ? "ALTER TABLE users ADD COLUMN activo INTEGER NOT NULL DEFAULT 1" : "ALTER TABLE users ADD activo TINYINT(1) NOT NULL DEFAULT 1");
    if (!has_col($p, 'vehicles', 'condicion', $sqlite))
        $p->exec($sqlite ? "ALTER TABLE vehicles ADD COLUMN condicion TEXT NOT NULL DEFAULT 'Seminuevo'" : "ALTER TABLE vehicles ADD condicion VARCHAR(12) NOT NULL DEFAULT 'Seminuevo'");
}

function db() {
    static $pdo; global $CFG;
    if ($pdo) return $pdo;
    $c = $CFG['db'];
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

    if (!is_sqlite()) {
        if (!preg_match('/^\w+$/', $c['name'])) throw new RuntimeException('Nombre de base de datos no válido en api/config.php');
        $p = new PDO("mysql:host={$c['host']};port=" . (int)$c['port'] . ";charset=utf8mb4", $c['user'], $c['pass'], $opts);
        try { $p->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Throwable $e) {}
        $p->exec("USE `{$c['name']}`");
        if (!$p->query("SHOW TABLES LIKE 'users'")->fetchColumn()) run_schema($p, 'schema.sql');
        migrate($p, false);
        return $pdo = $p;
    }

    $p = new PDO('sqlite:' . $c['sqlite'], null, null, $opts);
    $p->exec('PRAGMA foreign_keys = ON');
    if (!$p->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn()) {
        run_schema($p, 'schema.sqlite.sql');
        $d = $CFG['demo'];
        $p->prepare('INSERT INTO users(nombre,email,telefono,password_hash,role) VALUES(?,?,?,?,?)')
          ->execute([$d['nombre'], $d['email'], $d['telefono'], password_hash($d['password'], PASSWORD_DEFAULT), 'admin']);
    }
    migrate($p, true);
    return $pdo = $p;
}

function out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail($msg, $code = 400, $extra = []) { out(['error' => $msg] + $extra, $code); }
function input() {
    $j = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($j) ? $j : $_POST;
}
function me() {
    if (empty($_SESSION['uid'])) return null;
    $s = db()->prepare('SELECT id,nombre,email,telefono,role,activo FROM users WHERE id=?');
    $s->execute([$_SESSION['uid']]);
    $u = $s->fetch();
    if (!$u || !(int)$u['activo']) return null;
    $u['id'] = (int)$u['id'];
    return $u;
}
function need_user() { $u = me(); if (!$u) fail('Inicia sesión para continuar.', 401); return $u; }
function need_admin() { $u = need_user(); if ($u['role'] !== 'admin') fail('Sin permisos.', 403); return $u; }
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function notify($uid, $titulo, $cuerpo) {
    db()->prepare('INSERT INTO notifications(user_id,titulo,cuerpo) VALUES(?,?,?)')->execute([(int)$uid, $titulo, $cuerpo]);
}

// Correo: un fallo aquí nunca debe romper la petición del usuario.
function send_mail($to, $subject, $html) {
    global $CFG; $m = $CFG['mail'];
    try {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if ($m['driver'] !== 'smtp' || !is_file($autoload)) {
            @file_put_contents(__DIR__ . '/../storage/mail.log',
                '[' . date('Y-m-d H:i:s') . "] PARA: $to\nASUNTO: $subject\n" . trim(strip_tags($html)) . "\n---\n", FILE_APPEND | LOCK_EX);
            return;
        }
        require_once $autoload;
        $p = new PHPMailer\PHPMailer\PHPMailer(true);
        $p->isSMTP(); $p->Host = $m['host']; $p->Port = (int)$m['port'];
        $p->SMTPAuth = true; $p->Username = $m['user']; $p->Password = $m['pass'];
        $p->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $p->CharSet = 'UTF-8'; $p->setFrom($m['from'], $m['from_name']);
        $p->addAddress($to); $p->isHTML(true); $p->Subject = $subject; $p->Body = $html;
        $p->send();
    } catch (Throwable $e) { error_log('Correo no enviado: ' . $e->getMessage()); }
}

// Protección CSRF: las peticiones que cambian datos solo se aceptan desde nuestras propias páginas.
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true) && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'AutoElite')
    fail('Petición no permitida.', 403);
