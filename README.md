# Sistema de Despacho Contable

## Descripción
Sistema web para la gestión de despachos contables, incluyendo manejo de clientes, facturas CFDI, recibos y reportes.

## Mejoras Implementadas

### ✅ Correcciones Críticas
- **Archivo corrupto reparado**: `modulos/reportes/descargar_xml.php` - Syntax error corregido
- **Configuración centralizada**: Base de datos ahora usa constantes de configuración
- **Manejo de errores mejorado**: Logging de errores de base de datos y eventos de seguridad

### ✅ Mejoras de Seguridad
- **Protección CSRF**: Tokens de seguridad en formularios
- **Sanitización mejorada**: Funciones de sanitización por tipo de dato
- **Headers de seguridad**: Protección contra XSS, Clickjacking, etc.
- **Rate limiting**: Prevención de ataques de fuerza bruta
- **Validación de archivos XML**: Verificación de integridad de archivos
- **Logging de seguridad**: Registro de eventos sospechosos

### ✅ Mejoras de Configuración
- **Soporte para variables de entorno**: Configuración flexible para diferentes entornos
- **Detección de entorno**: Comportamiento diferente en desarrollo vs producción
- **Logging estructurado**: Archivos de log organizados por tipo
- **Directorio de logs automático**: Creación automática de directorios necesarios

### ✅ Mejoras de Código
- **Validación de RFC**: Formato correcto de RFC mexicano
- **Validación de UUID**: Verificación de UUIDs válidos
- **Error handling**: Manejo consistente de excepciones PDO
- **Documentación**: Comentarios y documentación mejorados

## Instalación

### Requisitos
- PHP 8.0 o superior
- MySQL 5.7 o superior
- Servidor web (Apache/Nginx)

### Configuración

1. **Clonar el repositorio**
   ```bash
   git clone [url-del-repositorio]
   cd despacho-contable
   ```

2. **Configurar entorno**
   ```bash
   cp .env.example .env
   # Editar .env con tus configuraciones
   ```

3. **Configurar base de datos**
   - Crear base de datos MySQL
   - Importar schema desde `config/schema_update.php`
   - Configurar credenciales en `.env`

4. **Configurar permisos**
   ```bash
   chmod 755 uploads/ logs/
   ```

### Variables de Entorno Importantes

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `APP_ENV` | Entorno de la aplicación | `development` o `production` |
| `DB_HOST` | Servidor de base de datos | `localhost` |
| `DB_NAME` | Nombre de la base de datos | `despacho_contable` |
| `RFC_PROPIO` | RFC de tu empresa | `ABC123456789` |

## Estructura del Proyecto

```
despacho-contable/
├── config/           # Configuración y base de datos
├── includes/         # Funciones y helpers
├── modulos/          # Módulos principales
│   ├── clientes/     # Gestión de clientes
│   ├── recibos/      # Manejo de recibos
│   ├── reportes/     # Reportes y facturas
│   └── usuarios/     # Autenticación
├── uploads/          # Archivos subidos
├── logs/             # Archivos de log
└── assets/           # CSS, JS, imágenes
```

## Funcionalidades Principales

- ✅ **Gestión de Clientes**: CRUD completo con validaciones
- ✅ **Facturas CFDI**: Procesamiento de XML y validación SAT
- ✅ **Recibos**: Generación y control de pagos
- ✅ **Reportes**: Exportación y análisis de datos
- ✅ **Usuarios**: Sistema de autenticación y roles

## Seguridad

### Implementado
- Protección CSRF en formularios
- Sanitización de entradas
- Headers de seguridad HTTP
- Rate limiting básico
- Logging de eventos de seguridad
- Validación de archivos XML

### Recomendaciones Adicionales
- Usar HTTPS en producción
- Configurar firewall de aplicación web
- Implementar 2FA para usuarios admin
- Auditorías regulares de seguridad
- Backup automático de base de datos

## Logs

El sistema genera varios tipos de logs:

- `logs/security.log` - Eventos de seguridad
- `logs/database.log` - Errores de base de datos  
- `logs/php_errors.log` - Errores de PHP (solo en producción)

## Contribución

1. Fork el proyecto
2. Crear rama para feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## Licencia

[Especificar licencia]

## Soporte

Para reportar problemas o solicitar nuevas funcionalidades, crear un issue en el repositorio.