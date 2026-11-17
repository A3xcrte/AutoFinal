-- Opcional: la página crea esto sola. También puedes importarlo en phpMyAdmin (pestaña Importar).
CREATE DATABASE IF NOT EXISTS autoelite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE autoelite;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  telefono VARCHAR(15) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  marca VARCHAR(40) NOT NULL,
  modelo VARCHAR(60) NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  km INT UNSIGNED NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  combustible VARCHAR(20) NOT NULL,
  transmision VARCHAR(20) NOT NULL,
  descripcion TEXT NOT NULL,
  estado ENUM('pendiente','aprobado','rechazado','vendido') NOT NULL DEFAULT 'pendiente',
  condicion VARCHAR(12) NOT NULL DEFAULT 'Seminuevo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (estado, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vehicle_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT UNSIGNED NOT NULL,
  filename VARCHAR(60) NOT NULL,
  position TINYINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(100) NOT NULL,
  cuerpo VARCHAR(300) NOT NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, created_at)
) ENGINE=InnoDB;

-- Administrador de ejemplo:  admin@autoelite.com  /  Demo1234   (cambia la contraseña después)
INSERT INTO users (nombre, email, telefono, password_hash, role)
VALUES ('Administrador', 'admin@autoelite.com', '5512345678', '$2y$12$jLaRlz7B7FeSNw0MK..X5uS0eNNR1k8/ekakk4miROSzz9otOpSAS', 'admin');
