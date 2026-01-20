# Sistema de Logs de Actividad en Controladores - Documentación

## Descripción

Cada controlador ahora registra automáticamente la actividad cuando un usuario accede a sus métodos. El sistema utiliza el trait `LogsControllerActivity` que está integrado en el `Controller` base.

## Funcionalidad Automática

Todos los controladores que extienden `App\Http\Controllers\Controller` registran automáticamente:
- El método del controlador que se ejecutó
- El usuario que realizó la acción (si está autenticado)
- La IP del usuario
- El User Agent
- Los datos de la petición (sin información sensible)
- El recurso afectado (si es un modelo Eloquent)
- La URL y ruta utilizada
- El código de respuesta HTTP

## Acciones Registradas

El sistema determina automáticamente la acción basada en el método del controlador:

- `index` → "Accedió a la lista de {recurso}"
- `show`, `page`, `showApi` → "Vió {recurso}"
- `create` → "Intentó crear nuevo {recurso}"
- `store` → "Creó nuevo {recurso}"
- `edit` → "Editó {recurso}"
- `update` → "Actualizó {recurso}"
- `destroy`, `delete` → "Eliminó {recurso}"
- `approve`, `approved` → "Aprobó {recurso}"
- Otros métodos → "Ejecutó {método} en {recurso}"

## API Endpoints para Obtener Logs

### 1. Listar todos los logs

```
GET /api/external/activity-logs
```

**Parámetros de consulta:**
- `user_id` (opcional): Filtrar por ID de usuario
- `subject_type` (opcional): Filtrar por tipo de recurso (ej: `App\Models\Post`)
- `description` (opcional): Buscar en la descripción
- `log_name` (opcional): Filtrar por nombre del log
- `from_date` (opcional): Filtrar desde fecha (formato: YYYY-MM-DD)
- `to_date` (opcional): Filtrar hasta fecha (formato: YYYY-MM-DD)
- `controller` (opcional): Filtrar por nombre del controlador
- `sort_by` (opcional): Campo para ordenar (default: `created_at`)
- `sort_order` (opcional): Orden (default: `desc`)
- `per_page` (opcional): Resultados por página (default: 20, máximo: 100)
- `all` (opcional): Si está presente, muestra todos los logs. Sin esto, solo muestra logs del usuario autenticado

**Ejemplo:**
```bash
GET /api/external/activity-logs?user_id=1&from_date=2025-01-01&per_page=50
```

### 2. Obtener un log específico

```
GET /api/external/activity-logs/{id}
```

**Ejemplo:**
```bash
GET /api/external/activity-logs/123
```

### 3. Obtener logs de un usuario específico

```
GET /api/external/activity-logs/user/{userId}
```

**Parámetros de consulta:**
- `from_date` (opcional): Filtrar desde fecha
- `to_date` (opcional): Filtrar hasta fecha
- `per_page` (opcional): Resultados por página

**Ejemplo:**
```bash
GET /api/external/activity-logs/user/1?from_date=2025-01-01
```

### 4. Obtener estadísticas de actividad

```
GET /api/external/activity-logs/stats
```

**Parámetros de consulta:**
- `user_id` (opcional): Filtrar por ID de usuario
- `from_date` (opcional): Filtrar desde fecha
- `to_date` (opcional): Filtrar hasta fecha

**Respuesta incluye:**
- `total_activities`: Total de actividades
- `activities_by_type`: Actividades agrupadas por tipo de recurso
- `activities_by_controller`: Actividades agrupadas por controlador
- `activities_by_date`: Actividades agrupadas por fecha (últimos 30 días)
- `most_active_users`: Usuarios más activos (top 10)

**Ejemplo:**
```bash
GET /api/external/activity-logs/stats?from_date=2025-01-01
```

## Ejemplos de Respuestas

### Listar logs

```json
{
  "code": 200,
  "message": "Success",
  "result": {
    "data": [
      {
        "id": 1,
        "log_name": null,
        "description": "Accedió a la lista de posts",
        "subject_type": null,
        "subject_id": null,
        "event": null,
        "causer_type": "App\\Models\\User",
        "causer_id": 1,
        "properties": {
          "controller": "App\\Http\\Controllers\\PostController",
          "controller_name": "PostController",
          "method": "index",
          "ip_address": "192.168.1.1",
          "user_agent": "Mozilla/5.0...",
          "http_method": "GET",
          "route": "posts.index",
          "url": "https://api.coanime.net/api/external/posts",
          "path": "external/posts",
          "request_data": {},
          "route_parameters": {},
          "status_code": 200,
          "resource_type": null,
          "resource_id": null
        },
        "created_at": "2025-01-27T12:00:00.000000Z",
        "updated_at": "2025-01-27T12:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 100
  }
}
```

### Estadísticas

```json
{
  "code": 200,
  "message": "Success",
  "result": {
    "total_activities": 1500,
    "activities_by_type": {
      "App\\Models\\Post": 800,
      "App\\Models\\Title": 500,
      "App\\Models\\User": 200
    },
    "activities_by_controller": {
      "PostController": 800,
      "TitleController": 500,
      "UserController": 200
    },
    "activities_by_date": {
      "2025-01-27": 50,
      "2025-01-26": 45,
      ...
    },
    "most_active_users": [
      {
        "user_id": 1,
        "user": {
          "id": 1,
          "name": "Juan Pérez",
          "email": "juan@example.com"
        },
        "activities_count": 150
      }
    ]
  }
}
```

## Información Registrada

Cada log incluye:

- **Usuario**: Información del usuario que realizó la acción (si está autenticado)
- **Controlador**: Clase y nombre del controlador
- **Método**: Método del controlador ejecutado
- **IP**: Dirección IP del usuario
- **User Agent**: Navegador/cliente utilizado
- **HTTP Method**: GET, POST, PUT, DELETE, etc.
- **Ruta**: Nombre de la ruta y URL completa
- **Datos de petición**: Datos enviados (sin información sensible como passwords)
- **Recurso**: Tipo y ID del recurso afectado (si aplica)
- **Código de respuesta**: Código HTTP de la respuesta
- **Timestamps**: Fecha y hora de creación

## Seguridad

- Los campos sensibles (passwords, tokens, etc.) se reemplazan automáticamente por `***REDACTED***`
- Los datos de petición se limitan a 500 caracteres por campo
- Por defecto, los usuarios solo ven sus propios logs (excepto si usan el parámetro `all`)

## Notas

- El registro automático funciona para todos los controladores que extienden `App\Http\Controllers\Controller`
- El middleware `LogUserActivity` registra todas las interacciones HTTP generales
- El trait `LogsControllerActivity` registra específicamente las acciones de los controladores
- Ambos sistemas trabajan juntos para proporcionar un registro completo de actividades
