# Guía de Despliegue en Easypanel (vía GitHub)

Para desplegar tu aplicación en `whisper.comfasucre.co` usando Easypanel, sigue estos pasos:

## 1. Preparar GitHub
1. Sube todos los archivos del proyecto (incluyendo el nuevo `Dockerfile` que acabo de crear) a un repositorio en GitHub.
2. Asegúrate de que el repositorio sea público o que Easypanel tenga acceso a tus repositorios privados.

## 2. Configurar en Easypanel
1. Entra a tu dashboard de Easypanel.
2. Crea un nuevo **Project** o selecciona uno existente.
3. Haz clic en **Add Service** -> **App**.
4. En **Source**:
   - Selecciona **GitHub**.
   - Conecta tu cuenta si es necesario.
   - Selecciona el repositorio de Whisper.
   - Branch: `main` (o el que uses).
5. En **Build**:
   - Easypanel detectará automáticamente el `Dockerfile`. Asegúrate de que esté seleccionado el método de construcción **Docker**.
6. En **Domains**:
   - Añade tu subdominio: `whisper.comfasucre.co`.
7. En **Environment Variables** (Opcional):
   - Puedes añadir una variable si quieres pre-configurar algo, pero en este caso la aplicación pide la API Key en la interfaz.

## 3. Desplegar
1. Haz clic en **Deploy**.
2. Easypanel construirá la imagen (instalando PHP y FFmpeg automáticamente según el `Dockerfile`).
3. Una vez termine, tu aplicación estará en línea.

> [!IMPORTANT]
> El `Dockerfile` que he creado ya configura PHP para que acepte archivos grandes y tiempos de ejecución largos (1 hora), además de instalar FFmpeg en la ruta estándar de Linux (`ffmpeg`).

## Notas sobre Seguridad
Recuerda que la carpeta `uploads/` se limpia automáticamente después de cada transcripción según el código PHP, por lo que no deberías tener problemas de almacenamiento en tu servidor Easypanel.
