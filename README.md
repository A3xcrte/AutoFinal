# AutoElite

Sitio web de una concesionaria: inventario con filtros, calculadora de mensualidad, contacto por WhatsApp, cuentas de usuario y anuncios de autos publicados por usuarios (con revisión del administrador).

**Tecnologías:** HTML, CSS y JavaScript + PHP 7.4 o superior + MySQL (phpMyAdmin).

## Cómo correrlo con Docker (recomendado)
1. Instala [Docker Desktop](https://www.docker.com/products/docker-desktop/) y ábrelo (espera a que diga que está en ejecución).
2. En la carpeta del proyecto, abre una terminal (en VS Code: Terminal > Nueva terminal) y ejecuta:
   ```
   docker compose up -d --build
   ```
3. Abre:
   - Página: http://localhost:8080
   - Comprobación: http://localhost:8080/diagnostico.php (debe terminar con "Todo bien")
   - phpMyAdmin: http://localhost:8081 (usuario `autoelite`, contraseña `autoelite_pass`; o `root` / `root_pass`)
4. Entra en **Mi cuenta** con `l23140549@queretaro.tecnm.mx` / `0924Cervantes` y sube tus autos en **Panel de administración > Subir auto**. El inventario empieza vacío.

Comandos útiles: `docker compose logs -f web` (ver errores), `docker compose down` (apagar, conserva los datos), `docker compose down -v` (apagar y **borrar la base de datos**). Las fotos quedan en la carpeta `uploads/`.

## Cómo correrlo sin Docker (XAMPP)
1. Copia esta carpeta a `C:\\xampp\\htdocs\\autoelite` (los archivos deben quedar directamente ahí, sin otra carpeta dentro).
2. Si descargaste el repositorio de GitHub, copia `api/config.example.php` como `api/config.php`.
3. En el Panel de XAMPP pulsa **Start** en **Apache** y en **MySQL**.
4. Abre http://localhost/autoelite/diagnostico.php: crea la base `autoelite` y sus tablas solo, y debe terminar con "Todo bien".
5. Abre http://localhost/autoelite/ y entra en **Mi cuenta**. En http://localhost/phpmyadmin verás la base `autoelite`.

**Sin MySQL:** en `api/config.php` cambia `'driver' => 'mysql'` por `'driver' => 'sqlite'`; solo necesitas Apache.

## Administrador de ejemplo
Correo `l23140549@queretaro.tecnm.mx`, contraseña `0924Cervantes`. Cámbiala antes de publicar el sitio.

## Cómo funciona
- Cualquiera crea una cuenta y publica un auto con hasta 6 fotos (**Vende tu auto**).
- El anuncio queda pendiente; el administrador lo aprueba en **Mi cuenta > Revisar anuncios** y aparece en el inventario como "Particular".
- Cada paso genera una notificación y un correo (por defecto guardado en `storage/mail.log`).

## Panel de administración (Mi cuenta > Panel de administración)
- **Autos:** ve todo lo publicado (tuyo y de clientes), filtra por estado, cambia el estado (pendiente, aprobado, rechazado, vendido), edita precio y datos, o elimina.
- **Usuarios:** cambia el rol (usuario o administrador), suspende o activa cuentas y elimina cuentas (con sus anuncios).
- **Subir auto:** publica inventario de la concesionaria directo (Nuevo o Seminuevo) con fotos. Si usas esto, puedes vaciar los autos de ejemplo de `js/data.js`.

## Notas
- Abre siempre con `http://localhost/...`, no con doble clic ni Live Server.
- Cambia tu WhatsApp en `js/config.js` y el correo del administrador en `api/config.php`.
- Antes de publicar en internet: usuario de MySQL propio con contraseña, HTTPS, `'debug' => false` y borrar `diagnostico.php`.
