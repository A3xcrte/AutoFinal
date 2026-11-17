<?php
header("Content-Type: text/plain; charset=utf-8");
echo "PHP " . PHP_VERSION . (version_compare(PHP_VERSION, "7.4.0", ">=") ? "  -> OK\n" : "  -> MUY VIEJO: actualiza XAMPP\n");
foreach (["pdo_mysql", "pdo_sqlite"] as $e) echo "Extensión $e: " . (extension_loaded($e) ? "OK" : "no activa") . "\n";
echo "Carpeta uploads escribible: " . (is_writable(__DIR__ . "/uploads") ? "SÍ" : "NO") . "\n";
echo "Carpeta storage escribible: " . (is_writable(__DIR__ . "/storage") ? "SÍ" : "NO") . "\n\n";
try {
    require __DIR__ . "/api/lib.php";
    $c = $CFG["db"];
    echo "Modo: " . (is_sqlite() ? "SQLite (archivo local)" : "MySQL en {$c['host']}:{$c['port']}, base '{$c['name']}'") . "\n";
    $n = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Base de datos: OK. Usuarios registrados: $n\n";
    echo "\nTodo bien. Abre http://localhost/autoelite/ e inicia sesión.\n";
} catch (Throwable $e) {
    $m = $e->getMessage();
    echo "ERROR: $m\n\nQué hacer:\n";
    if (stripos($m, "could not find driver") !== false) echo "- Falta la extensión de PHP. Abre C:\\xampp\\php\\php.ini, quita el ; de extension=pdo_mysql (o pdo_sqlite) y reinicia Apache.\n";
    elseif (preg_match('/2002|refused|actively refused|No connection|getaddrinfo/i', $m)) echo "- MySQL está apagado o usa otro puerto. Enciéndelo en XAMPP (botón Start de MySQL).\n- Si el Panel muestra otro puerto (por ejemplo 3307), ponlo en 'port' en api/config.php.\n";
    elseif (stripos($m, "1045") !== false || stripos($m, "Access denied") !== false) echo "- Usuario o contraseña de MySQL incorrectos. Revisa 'user' y 'pass' en api/config.php (XAMPP: root sin contraseña).\n";
    else echo "- Si MySQL te da problemas, en api/config.php cambia 'driver' => 'mysql' por 'sqlite' (solo necesita Apache).\n";
}
