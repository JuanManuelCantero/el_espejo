# El Espejo: continuidad del proyecto

Última actualización: 8 de octubre de 2026.

## Estado actual

- Sitio productivo: <https://el-espejo.com.ar/>
- Repositorio: `JuanManuelCantero/el_espejo`
- Rama de trabajo: `juanmanuelcantero-actualizando-sitio-el-espejo`
- Pull request abierto hacia `main`: [#3](https://github.com/JuanManuelCantero/el_espejo/pull/3)
- Último commit de la rama: `3628010 Restaurar acceso al inicio y mejorar accesibilidad`.
- El dominio de producción **no se publica desde GitHub Pages**. Se actualiza directamente en el hosting mediante FTPS.

## Arquitectura

El sitio es principalmente estático:

- Página principal: `index.html`
- Estilos: `css/style.css`
- Comportamiento de interfaz: `js/script.js`
- Imágenes institucionales: `images/`
- También existen páginas PHP históricas para agenda, administración e inicio de sesión.

Las actualizaciones solicitadas hasta hoy se concentraron en la portada (`index.html`) y su hoja de estilos.

## Cambios realizados

### Contenido e identidad

- Se actualizaron textos institucionales, misión, visión, valores, áreas de atención, FAQ, horarios, email y WhatsApp.
- El correo de contacto vigente es `elespejo.gestalt@gmail.com`.
- El WhatsApp vigente es `+54 9 358 518-5508`, destinado solo a mensajes.
- Se incorporaron perfiles actuales del equipo, separados entre atención presencial y online.
- Se renovó la galería de los espacios y se incorporó el video institucional.

### Imágenes y presentación

- Las imágenes de la galería y las fichas del equipo se optimizaron y se muestran con `object-fit: cover`, sin deformarlas.
- Las tarjetas del equipo tienen alturas uniformes.
- El anterior banner `images/background/3.jpg` fue retirado. No debe restaurarse.
- La cabecera de contacto usa una grilla HTML de ocho retratos; se redujo a una sola fila para evitar cortes de rostros.
- Se añadió una pantalla de carga con isologo.

### Mapa, CSP y rendimiento

- El mapa usa un iframe responsive de Google Maps (`.map-embed`).
- Se eliminaron la API JavaScript de Maps y `plugins/google-map/gmap.js` de la página principal porque no se utilizaban.
- `.htaccess` permite el iframe de Google Maps mediante `frame-src https://www.google.com`.
- La CSP permite las conexiones necesarias de Google Analytics.
- La hoja CSS se versiona en el HTML para evitar servir estilos antiguos desde la caché del navegador. La versión actual es `css/style.css?v=20261008-4`.

### Footer y navegación

- El footer azul se reorganizó horizontalmente en escritorio y se adapta a tablet y móvil.
- Se corrigió el enlace de WhatsApp del footer para que se vea en blanco sobre el fondo azul.
- Se agregó una sombra de separación con la banda naranja inferior.
- El botón de regreso al inicio es `#back-to-top`; aparece luego de 200 px de scroll y se ubica encima del botón flotante de WhatsApp para no quedar tapado.
- El botón tiene etiquetas accesibles, foco visible y desplazamiento suave.

### Accesibilidad, SEO y correcciones finales

- La URL canónica, Open Graph y Schema.org apuntan a `https://el-espejo.com.ar/`.
- Se corrigió `aria-controls` de la navegación móvil.
- Se eliminó un ID duplicado en la sección de áreas.
- Los enlaces de la galería tienen etiquetas accesibles.
- La imagen del protocolo COVID tiene texto alternativo.
- El logo del footer enlaza a `#Home`, un ancla existente.

## Despliegue en producción

El hosting utiliza FTPS explícito. La cuenta autenticada queda situada directamente en el document root: no crear ni usar una carpeta adicional `public_html`.

Proceso seguro:

1. Obtener la contraseña actual por un canal seguro. No almacenar contraseñas en el repositorio ni en este documento.
2. Subir primero imágenes u otros recursos nuevos.
3. Subir `css/style.css`.
4. Subir `index.html` al final.
5. Subir `.htaccess` cuando cambie la CSP o cualquier encabezado.
6. Verificar el HTML y los encabezados públicos en `https://el-espejo.com.ar/`.
7. Si se modificó CSS, actualizar el parámetro `v=` de `css/style.css` en `index.html` para invalidar caché.

El certificado FTPS del host no coincidía previamente con el nombre de servidor. Validar esa situación con el propietario antes de aceptar excepciones de certificado en futuras conexiones.

## Verificaciones recomendadas

Antes de desplegar:

```powershell
git diff --check
git status --short
```

Después de desplegar:

```powershell
curl.exe --silent --show-error --fail -I https://el-espejo.com.ar/
curl.exe --silent --show-error --fail https://el-espejo.com.ar/
```

Comprobar manualmente en navegador:

- Carga inicial y desaparición del loader.
- Mapa visible y sin bloqueos CSP en la consola.
- Fotos de profesionales sin deformación ni cortes indebidos.
- Footer adaptable y enlace de WhatsApp visible.
- Botón flotante de WhatsApp y botón de volver arriba sin superposición.
- Galería, video y navegación móvil.

## Pendientes y consideraciones

- El pull request #3 sigue abierto y todavía no fue fusionado a `main`. La producción ya contiene sus cambios porque se publicó por FTPS.
- La plantilla depende de manejadores JavaScript inline para cargar algunos CSS con `media="print" onload=...`. La CSP actual permite esta conducta. Una futura mejora de seguridad sería eliminar esos manejadores inline y endurecer la CSP, probando cuidadosamente la carga visual.
- Hay contenido PHP histórico que no forma parte de la portada actual. Cualquier cambio en agenda, login o administración debe probarse directamente en el hosting que ejecute PHP.
- No modificar ni revelar credenciales de hosting. Solicitarlas nuevamente al propietario cuando sean necesarias.
