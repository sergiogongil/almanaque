[README.md](https://github.com/user-attachments/files/27185406/README.md)
# Almanaque interactivo (PHP + MySQL + Tailwind)

Almanaque web moderno e interactivo con **calendario mensual**, **notas rápidas por día (múltiples)** y **agenda lateral**. Las notas se guardan en **MySQL** mediante **PHP (PDO)** y se visualizan al instante en la interfaz.

<img width="1280" height="640" alt="social-almanaque" src="https://github.com/user-attachments/assets/257fb826-e3c7-45df-9b06-3755f4f1946e" />


## Qué incluye

- **Calendario mensual**: navegar mes anterior/siguiente y botón **Hoy**.
- **Notas por día (múltiples)**:
  - Clic en un día → modal con lista de notas existentes.
  - Añadir notas nuevas (también con **Ctrl/Cmd + Enter**).
  - Borrar notas con confirmación.
- **Agenda lateral del mes**:
  - Lista todas las notas del mes con scroll interno.
  - Las notas del **día de hoy** se resaltan con un borde/brillo especial.
- **UI moderna** con Tailwind y scrollbars sutiles personalizados.

## Tecnologías

- **PHP 8+** (endpoint `notes.php`)
- **PDO** para conexión a base de datos (configurado en `db.php`)
- **MySQL/MariaDB** (base `almanaque`)
- **Tailwind CSS** vía CDN (en `index.html`)
- **JavaScript** (frontend sin frameworks)

## Requisitos

- WAMP instalado (Apache + PHP + MySQL/MariaDB)
- PHP con extensión `pdo_mysql` habilitada (en WAMP suele venir habilitada)

## Instalación en WAMP (Windows)

1. **Copia la carpeta del proyecto** dentro de tu `www` de WAMP.
   - Ejemplo: `C:\wamp64\www\almanaque\`

2. **Crea la base de datos** en phpMyAdmin (o consola MySQL):
   - Nombre: `almanaque`

3. **Configura la conexión** en `db.php` si lo necesitas:
   - Host, usuario, password, base de datos, charset.
   - Por defecto en WAMP suele ser:
     - usuario: `root`
     - password: *(vacía)*

4. **Arranca WAMP** y asegúrate de que Apache y MySQL estén en verde.

5. Abre la app en el navegador:
   - `http://localhost/almanaque/index.html`

## Base de datos / tabla

La primera vez que uses la app, el backend crea (y si aplica migra) automáticamente la tabla:

- `notes`
  - `id` (AUTO_INCREMENT)
  - `note_date` (DATE)
  - `note_text` (TEXT)
  - `created_at` (TIMESTAMP)

> Si anteriormente existía una tabla `notes` del formato antiguo (una nota por día), el sistema intenta migrarla automáticamente.

## Endpoints

El frontend consume el endpoint `notes.php`:

- **GET** `notes.php?month=YYYY-MM`  
  Devuelve las notas del mes.

- **GET** `notes.php?date=YYYY-MM-DD`  
  Devuelve las notas de un día.

- **POST** `notes.php` (JSON)  
  - Añadir nota: `{ "date": "YYYY-MM-DD", "note": "texto" }`
  - Borrar nota: `{ "action": "delete", "id": 123 }`

## Estructura del proyecto

- `index.html` — UI del calendario + modal + agenda (Tailwind CDN + JS)
- `db.php` — conexión PDO a MySQL
- `notes.php` — API para crear/listar/insertar/borrar notas (usa `db.php`)

## Solución de problemas

- **No conecta a MySQL**: revisa `db.php` (credenciales/host) y que MySQL esté iniciado en WAMP.
- **Error al migrar `notes`**: revisa si existe una tabla `notes` con un esquema previo. Puedes renombrarla manualmente y recargar.
- **Permisos/URL**: asegúrate de abrirlo como `http://localhost/...` (no como archivo local) para que el fetch a `notes.php` funcione correctamente.

