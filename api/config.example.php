<?php
// Configuración del servidor.
return [
  'debug' => true,   // true = muestra el detalle técnico de los errores (ponlo en false al publicar)
  'db' => [
    // 'mysql'  = MySQL (se administra con phpMyAdmin). La base y las tablas se crean solas.
    // 'sqlite' = archivo local en storage/ (alternativa sin MySQL)
    // Con Docker estos valores vienen de docker-compose.yml; sin Docker (XAMPP) se usan los de la derecha.
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3306),     // XAMPP: si MySQL usa otro puerto (ej. 3307), cámbialo
    'name' => getenv('DB_NAME') ?: 'autoelite',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'sqlite' => __DIR__ . '/../storage/autoelite.sqlite',
  ],
  // Administrador para el modo SQLite (en MySQL viene en database/schema.sql)
  'demo' => ['nombre' => 'Administrador', 'email' => 'admin@autoelite.com', 'telefono' => '5512345678', 'password' => 'Demo1234'],
  'mail' => [
    'driver'    => 'log',                  // 'log' = guarda los correos en storage/mail.log | 'smtp' = envío real
    'from'      => 'no-reply@autoelite.com',
    'from_name' => 'AutoElite',
    'admin'     => 'admin@autoelite.com',  // recibe el aviso de cada anuncio nuevo
    'host' => 'smtp.gmail.com', 'port' => 587, 'user' => '', 'pass' => '',
  ],
  'max_files' => 6,
  'max_mb'    => 5,
];
