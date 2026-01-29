# Whisper Transcriber - Audio de Gran Tamaño

Esta aplicación permite transcribir archivos de audio de más de 500MB utilizando el modelo Whisper de OpenAI. Utiliza una técnica de carga por fragmentos (chunked upload) para evitar límites de servidor y divide el audio en segmentos para cumplir con el límite de 25MB de la API de OpenAI.

## Estructura del Proyecto

- `index.html`: Interfaz de usuario moderna con Tailwind CSS.
- `assets/js/main.js`: Lógica de carga por fragmentos y gestión del progreso.
- `api/upload.php`: Backend que reconstruye los archivos subidos.
- `api/transcribe.php`: Backend que segmenta el audio y llama a OpenAI.
- `uploads/`: Carpeta temporal para procesar archivos.
- `composer.json`: Configuración de dependencias (PHP >= 7.4).

## Requisitos Previos

- **PHP 7.4+**
- **FFmpeg**: Necesario para dividir los archivos de audio automáticamente.
- **OpenAI API Key**: Para realizar las transcripciones.

## Instalación Local (XAMPP / WAMP / LAMP)

1. **Clonar/Descargar** el proyecto en tu carpeta `htdocs` (XAMPP) o `/var/www/html` (Linux).
2. **Instalar FFmpeg**:
   - **Windows**: Descarga el binario de [ffmpeg.org](https://ffmpeg.org/download.html), extrae el `.zip` y añade la carpeta `bin` a tus variables de entorno (PATH).
   - **Linux (Ubuntu/Debian)**: `sudo alt-get install ffmpeg`
   - **macOS**: `brew install ffmpeg`
3. **Permisos**: Asegúrate de que la carpeta `uploads/` tenga permisos de escritura (`chmod 777 uploads` en Linux).
4. **Configuración de PHP**: Edita tu `php.ini` para permitir archivos grandes y tiempos de ejecución largos:
   ```ini
   upload_max_filesize = 100M (Esto no importa tanto por el chunking, pero ayuda)
   post_max_size = 110M
   max_execution_time = 3600
   memory_limit = 512M
   ```
5. Accede a `http://localhost/tu-carpeta/` desde tu navegador.

## Guía de Despliegue

### Hosting Compartido (Bluehost, SiteGround, HostGator)
1. Sube todos los archivos vía FTP o el Administrador de Archivos de cPanel.
2. **Importante**: Muchos hostings compartidos **no** tienen `ffmpeg` instalado por defecto. Contacta con soporte para preguntar o revisa si puedes usar un binario estático de FFmpeg subido a tu carpeta.
3. Asegúrate de que PHP tenga `curl` habilitado.

### VPS (DigitalOcean, Linode)
1. Instala un servidor web (Nginx/Apache) y PHP.
2. Instala FFmpeg: `sudo apt update && sudo apt install ffmpeg`.
3. Configura los permisos de la carpeta y los límites de PHP como se indicó arriba.

### Heroku
1. Crea un archivo `Procfile`: `web: vendor/bin/heroku-php-apache2`.
2. Añade el buildpack de FFmpeg: `heroku buildpacks:add https://github.com/jonathanong/heroku-buildpack-ffmpeg-latest.git`.
3. Haz `git push heroku main`.

### Railway
1. Usa la imagen oficial de PHP.
2. Añade `ffmpeg` en tu configuración de entorno o via Dockerfile si usas despliegue por Docker.

## Uso

1. Ingresa tu **OpenAI API Key**.
2. Selecciona un archivo de audio (MP3, WAV, M4A, etc.).
3. Haz clic en **Comenzar Transcripción**.
4. La barra de progreso te mostrará el avance de la subida y el procesamiento.
5. El resultado aparecerá en pantalla y podrás copiarlo.

## Notas de Seguridad
- La clave API se envía al backend y se guarda en la sesión (`$_SESSION`) para facilitar reintentos, pero no se almacena en base de datos.
- Los archivos subidos se eliminan automáticamente del servidor después de ser procesados.
