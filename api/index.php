<?php
require __DIR__ . '/lib.php';

const TIPOS = ['Sedán', 'SUV', 'Pickup', 'Coupé', 'Hatchback'];
const COMB  = ['Gasolina', 'Diésel', 'Híbrido', 'Eléctrico'];
const TRANS = ['Manual', 'Automática'];
const MIME  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function len($s) { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); }

function vehicles($where, $params = [], $extra = '') {
    $s = db()->prepare("SELECT v.id,v.marca,v.modelo,v.anio,v.km,v.precio,v.tipo,v.combustible,v.transmision,v.descripcion,v.estado,v.condicion,v.created_at,
                        u.nombre AS vendedor, u.role AS rol $extra FROM vehicles v JOIN users u ON u.id=v.user_id WHERE $where ORDER BY v.created_at DESC, v.id DESC");
    $s->execute($params);
    $rows = $s->fetchAll();
    $imgs = [];
    if ($rows) {
        $ids = implode(',', array_map(function ($r) { return (int)$r['id']; }, $rows));
        foreach (db()->query("SELECT vehicle_id,filename FROM vehicle_images WHERE vehicle_id IN ($ids) ORDER BY position") as $i)
            $imgs[(int)$i['vehicle_id']][] = $i['filename'];
    }
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id']; $r['anio'] = (int)$r['anio']; $r['km'] = (int)$r['km']; $r['precio'] = (float)$r['precio'];
        $r['imagenes'] = isset($imgs[$r['id']]) ? $imgs[$r['id']] : [];
    }
    return $rows;
}

function register() {
    $in = input();
    $nombre = trim((string)($in['nombre'] ?? '')); $email = strtolower(trim((string)($in['email'] ?? '')));
    $tel = preg_replace('/\D/', '', (string)($in['telefono'] ?? '')); $pass = (string)($in['password'] ?? '');
    if (len($nombre) < 2 || len($nombre) > 80) fail('Escribe tu nombre.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) fail('Correo no válido.');
    if (strlen($tel) < 10 || strlen($tel) > 13) fail('Teléfono no válido (10 dígitos).');
    if (strlen($pass) < 8 || strlen($pass) > 72) fail('La contraseña debe tener entre 8 y 72 caracteres.');
    try {
        db()->prepare('INSERT INTO users(nombre,email,telefono,password_hash) VALUES(?,?,?,?)')
            ->execute([$nombre, $email, $tel, password_hash($pass, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') fail('Ese correo ya está registrado.', 409);
        throw $e;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)db()->lastInsertId();
    send_mail($email, 'Bienvenido a AutoElite', '<p>Hola ' . esc($nombre) . ',</p><p>Tu cuenta fue creada. Ya puedes publicar tu auto para venderlo.</p>');
    out(['user' => me()], 201);
}

function login() {
    $in = input(); $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $since = is_sqlite() ? "datetime('now','-15 minutes')" : 'NOW() - INTERVAL 15 MINUTE';
    $c = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND created_at > $since");
    $c->execute([$ip]);
    if ((int)$c->fetchColumn() >= 10) fail('Demasiados intentos. Espera 15 minutos.', 429);
    $s = db()->prepare('SELECT id,password_hash,activo FROM users WHERE email=?');
    $s->execute([strtolower(trim((string)($in['email'] ?? '')))]);
    $u = $s->fetch();
    if (!$u || !password_verify((string)($in['password'] ?? ''), $u['password_hash'])) {
        db()->prepare('INSERT INTO login_attempts(ip) VALUES(?)')->execute([$ip]);
        fail('Correo o contraseña incorrectos.', 401);
    }
    if (!(int)$u['activo']) fail('Tu cuenta está suspendida. Contacta a la concesionaria.', 403);
    db()->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    out(['user' => me()]);
}

function parse_vehicle($src, $admin) {
    $v = [
        'marca' => trim((string)($src['marca'] ?? '')), 'modelo' => trim((string)($src['modelo'] ?? '')),
        'anio' => (int)($src['anio'] ?? 0), 'km' => (int)($src['km'] ?? -1), 'precio' => (float)($src['precio'] ?? 0),
        'tipo' => (string)($src['tipo'] ?? ''), 'combustible' => (string)($src['combustible'] ?? ''),
        'transmision' => (string)($src['transmision'] ?? ''), 'descripcion' => trim((string)($src['descripcion'] ?? '')),
        'condicion' => ($admin && ($src['condicion'] ?? '') === 'Nuevo') ? 'Nuevo' : 'Seminuevo',
    ];
    if ($v['marca'] === '' || $v['modelo'] === '' || len($v['marca']) > 40 || len($v['modelo']) > 60) fail('Marca y modelo son obligatorios.');
    if ($v['anio'] < 1980 || $v['anio'] > (int)date('Y') + 1) fail('Año no válido.');
    if ($v['km'] < 0) fail('Kilometraje no válido.');
    if ($v['precio'] <= 0 || $v['precio'] >= 1e8) fail('Precio no válido.');
    if (!in_array($v['tipo'], TIPOS, true) || !in_array($v['combustible'], COMB, true) || !in_array($v['transmision'], TRANS, true)) fail('Tipo, combustible o transmisión no válidos.');
    if (len($v['descripcion']) > 2000) fail('La descripción es muy larga.');
    return $v;
}

function create_vehicle() {
    global $CFG; $u = need_user(); $admin = $u['role'] === 'admin';
    if (!$admin) {
        $p = db()->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id=? AND estado='pendiente'");
        $p->execute([$u['id']]);
        if ((int)$p->fetchColumn() >= 5) fail('Ya tienes 5 anuncios en revisión. Espera a que se revisen.', 429);
    }
    $v = parse_vehicle($_POST, $admin);
    $estado = $admin ? 'aprobado' : 'pendiente';   // los autos de la concesionaria se publican directo

    // Fotos: se comprueba que el archivo sea realmente una imagen JPG, PNG o WebP
    $f = isset($_FILES['imagenes']) ? $_FILES['imagenes'] : null; $files = [];
    if ($f && is_array($f['name']))
        foreach ($f['name'] as $i => $_) if ($f['error'][$i] !== UPLOAD_ERR_NO_FILE) $files[] = ['tmp' => $f['tmp_name'][$i], 'size' => $f['size'][$i], 'err' => $f['error'][$i]];
    if (!$files) fail('Sube al menos una foto.');
    if (count($files) > $CFG['max_files']) fail("Máximo {$CFG['max_files']} fotos.");
    foreach ($files as &$x) {
        $info = $x['err'] === UPLOAD_ERR_OK ? @getimagesize($x['tmp']) : false;
        $mime = $info ? $info['mime'] : '';
        if ($x['err'] !== UPLOAD_ERR_OK || $x['size'] > $CFG['max_mb'] * 1048576 || !isset(MIME[$mime]))
            fail("Cada foto debe ser JPG, PNG o WebP de máximo {$CFG['max_mb']} MB.");
        $x['name'] = bin2hex(random_bytes(16)) . '.' . MIME[$mime];
    }
    unset($x);

    $saved = [];
    try {
        if (!is_dir(__DIR__ . '/../uploads')) @mkdir(__DIR__ . '/../uploads', 0775, true);
        db()->beginTransaction();
        db()->prepare('INSERT INTO vehicles(user_id,marca,modelo,anio,km,precio,tipo,combustible,transmision,descripcion,condicion,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$u['id'], $v['marca'], $v['modelo'], $v['anio'], $v['km'], $v['precio'], $v['tipo'], $v['combustible'], $v['transmision'], $v['descripcion'], $v['condicion'], $estado]);
        $id = (int)db()->lastInsertId();
        foreach ($files as $i => $x) {
            if (!move_uploaded_file($x['tmp'], __DIR__ . '/../uploads/' . $x['name'])) throw new RuntimeException('No se pudo guardar la foto. Revisa que la carpeta uploads exista y tenga permisos.');
            $saved[] = $x['name'];
            db()->prepare('INSERT INTO vehicle_images(vehicle_id,filename,position) VALUES(?,?,?)')->execute([$id, $x['name'], $i]);
        }
        if (!$admin) notify($u['id'], 'Anuncio en revisión', "Tu {$v['marca']} {$v['modelo']} está en revisión. Te avisaremos cuando se publique.");
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        foreach ($saved as $n) @unlink(__DIR__ . '/../uploads/' . $n);
        throw $e;
    }
    if (!$admin) send_mail($CFG['mail']['admin'], "Nuevo anuncio por revisar: {$v['marca']} {$v['modelo']}",
        '<p>' . esc($u['nombre']) . ' (' . esc($u['email']) . ') publicó un ' . esc($v['marca']) . ' ' . esc($v['modelo']) . ' ' . $v['anio'] . '.</p><p>Revísalo en el panel de administración.</p>');
    out(['id' => $id, 'estado' => $estado], 201);
}

/* ---------- Panel de administración ---------- */
function admin_stats() {
    need_admin();
    $q = function ($sql) { return (int)db()->query($sql)->fetchColumn(); };
    out(['publicados' => $q("SELECT COUNT(*) FROM vehicles WHERE estado='aprobado'"), 'pendientes' => $q("SELECT COUNT(*) FROM vehicles WHERE estado='pendiente'"),
         'vendidos' => $q("SELECT COUNT(*) FROM vehicles WHERE estado='vendido'"), 'usuarios' => $q('SELECT COUNT(*) FROM users')]);
}
function admin_vehicles() {
    need_admin(); $e = (string)($_GET['estado'] ?? '');
    out($e !== '' ? vehicles('v.estado=?', [$e], ',u.email,u.telefono') : vehicles('1=1', [], ',u.email,u.telefono'));
}
function admin_update_vehicle() {
    need_admin(); $in = input(); $id = (int)($in['id'] ?? 0);
    $v = parse_vehicle($in, true);
    $s = db()->prepare('SELECT id FROM vehicles WHERE id=?'); $s->execute([$id]);
    if (!$s->fetch()) fail('No existe ese anuncio.', 404);
    db()->prepare('UPDATE vehicles SET marca=?,modelo=?,anio=?,km=?,precio=?,tipo=?,combustible=?,transmision=?,descripcion=?,condicion=? WHERE id=?')
        ->execute([$v['marca'], $v['modelo'], $v['anio'], $v['km'], $v['precio'], $v['tipo'], $v['combustible'], $v['transmision'], $v['descripcion'], $v['condicion'], $id]);
    out(['ok' => true]);
}
function admin_users() {
    need_admin();
    $rows = db()->query('SELECT u.id,u.nombre,u.email,u.telefono,u.role,u.activo,u.created_at,(SELECT COUNT(*) FROM vehicles v WHERE v.user_id=u.id) AS autos FROM users u ORDER BY u.id')->fetchAll();
    foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['activo'] = (int)$r['activo']; $r['autos'] = (int)$r['autos']; }
    out($rows);
}
function admin_user_update() {
    $me = need_admin(); $in = input(); $id = (int)($in['id'] ?? 0);
    if ($id === $me['id']) fail('No puedes modificar tu propia cuenta desde aquí.');
    $s = db()->prepare('SELECT id FROM users WHERE id=?'); $s->execute([$id]);
    if (!$s->fetch()) fail('No existe ese usuario.', 404);
    if (isset($in['role'])) {
        if (!in_array($in['role'], ['user', 'admin'], true)) fail('Rol no válido.');
        db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$in['role'], $id]);
    }
    if (isset($in['activo'])) db()->prepare('UPDATE users SET activo=? WHERE id=?')->execute([$in['activo'] ? 1 : 0, $id]);
    out(['ok' => true]);
}
function admin_user_delete() {
    $me = need_admin(); $id = (int)(input()['id'] ?? 0);
    if ($id === $me['id']) fail('No puedes eliminar tu propia cuenta.');
    $s = db()->prepare('SELECT i.filename FROM vehicle_images i JOIN vehicles v ON v.id=i.vehicle_id WHERE v.user_id=?'); $s->execute([$id]);
    $names = $s->fetchAll(PDO::FETCH_COLUMN);
    db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    foreach ($names as $n) @unlink(__DIR__ . '/../uploads/' . basename($n));
    out(['ok' => true]);
}

function set_estado() {
    need_admin(); $in = input(); $estado = (string)($in['estado'] ?? '');
    if (!in_array($estado, ['pendiente', 'aprobado', 'rechazado', 'vendido'], true)) fail('Estado no válido.');
    $s = db()->prepare('SELECT v.id,v.user_id,v.marca,v.modelo,u.nombre,u.email FROM vehicles v JOIN users u ON u.id=v.user_id WHERE v.id=?');
    $s->execute([(int)($in['id'] ?? 0)]);
    $v = $s->fetch();
    if (!$v) fail('No existe ese anuncio.', 404);
    db()->prepare('UPDATE vehicles SET estado=? WHERE id=?')->execute([$estado, $v['id']]);
    notify($v['user_id'], "Anuncio $estado", "Tu {$v['marca']} {$v['modelo']} fue marcado como $estado.");
    $t = ['aprobado' => 'ya está publicado y visible para los compradores.', 'rechazado' => 'no pudo publicarse. Revisa las fotos y los datos e inténtalo de nuevo.', 'vendido' => 'fue marcado como vendido.', 'pendiente' => 'volvió a estar en revisión.'];
    send_mail($v['email'], "Tu anuncio: {$v['marca']} {$v['modelo']}", '<p>Hola ' . esc($v['nombre']) . ',</p><p>Tu ' . esc($v['marca']) . ' ' . esc($v['modelo']) . ' ' . $t[$estado] . '</p>');
    out(['id' => (int)$v['id'], 'estado' => $estado]);
}

function delete_vehicle() {
    $u = need_user(); $id = (int)(input()['id'] ?? 0);
    $s = db()->prepare('SELECT user_id FROM vehicles WHERE id=?'); $s->execute([$id]);
    $v = $s->fetch();
    if (!$v) fail('No existe ese anuncio.', 404);
    if ((int)$v['user_id'] !== $u['id'] && $u['role'] !== 'admin') fail('Sin permisos.', 403);
    $i = db()->prepare('SELECT filename FROM vehicle_images WHERE vehicle_id=?'); $i->execute([$id]);
    $names = $i->fetchAll(PDO::FETCH_COLUMN);
    db()->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);
    foreach ($names as $n) @unlink(__DIR__ . '/../uploads/' . basename($n));
    out(['ok' => true]);
}

try {
    $r = isset($_GET['r']) ? $_GET['r'] : '';
    $post = $_SERVER['REQUEST_METHOD'] === 'POST';

    if ($r === 'auth/me') out(['user' => me()]);
    if ($r === 'auth/register' && $post) register();
    if ($r === 'auth/login' && $post) login();
    if ($r === 'auth/logout' && $post) { $_SESSION = []; session_destroy(); out(['ok' => true]); }

    if ($r === 'vehicles') out(vehicles("v.estado='aprobado'", [], ',u.telefono'));
    if ($r === 'vehicles/mine') out(vehicles('v.user_id=?', [need_user()['id']]));
    if ($r === 'vehicles/pending') { need_admin(); out(vehicles("v.estado='pendiente'", [], ',u.email,u.telefono')); }
    if ($r === 'vehicles/create' && $post) create_vehicle();
    if ($r === 'vehicles/estado' && $post) set_estado();
    if ($r === 'vehicles/delete' && $post) delete_vehicle();

    if ($r === 'notifications') {
        $s = db()->prepare('SELECT id,titulo,cuerpo,leida,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 50');
        $s->execute([need_user()['id']]);
        out($s->fetchAll());
    }
    if ($r === 'notifications/read' && $post) {
        db()->prepare('UPDATE notifications SET leida=1 WHERE user_id=?')->execute([need_user()['id']]);
        out(['ok' => true]);
    }
    if ($r === 'admin/stats') admin_stats();
    if ($r === 'admin/vehicles') admin_vehicles();
    if ($r === 'admin/vehicles/update' && $post) admin_update_vehicle();
    if ($r === 'admin/users') admin_users();
    if ($r === 'admin/users/update' && $post) admin_user_update();
    if ($r === 'admin/users/delete' && $post) admin_user_delete();
    fail('Ruta no encontrada.', 404);
} catch (Throwable $e) {
    error_log($e->getMessage());
    fail('Error del servidor.', 500, !empty($CFG['debug']) ? ['detalle' => $e->getMessage()] : []);
}
