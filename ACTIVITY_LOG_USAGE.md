# Sistema de Logs de Eventos - Documentación

Este proyecto utiliza `spatie/laravel-activitylog` para registrar todos los eventos e interacciones de los usuarios en la base de datos.

## Instalación

El paquete ya está agregado en `composer.json`. Ejecuta:

```bash
composer install
php artisan migrate
```

## Configuración

El sistema está configurado para registrar automáticamente:

1. **Todas las interacciones HTTP** a través del middleware `LogUserActivity`
2. **Eventos de autenticación** (login/logout) en `AuthenticatedSessionController`
3. **Cambios en el modelo User** usando el trait `LogsActivity`

## Uso

### Registro Automático

El middleware `LogUserActivity` está registrado en el grupo `api` del Kernel y captura automáticamente:
- Método HTTP (GET, POST, PUT, PATCH, DELETE)
- URL y ruta
- IP del usuario
- User Agent
- Datos de la petición (sin información sensible)
- Código de respuesta HTTP
- Tiempo de respuesta

### Registro Manual en Controladores

Puedes registrar eventos personalizados en cualquier controlador:

```php
use Illuminate\Support\Facades\Auth;

// Ejemplo: Registrar una acción personalizada
activity()
    ->causedBy(Auth::user())
    ->performedOn($post) // Opcional: recurso relacionado
    ->withProperties([
        'custom_data' => 'valor',
        'ip' => $request->ip(),
    ])
    ->log('Creó un nuevo post');
```

### Registrar Cambios en Modelos

Para que un modelo registre automáticamente sus cambios (created, updated, deleted), agrega el trait:

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Post extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'content', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Post {$eventName}");
    }
}
```

### Consultar Logs

```php
use Spatie\Activitylog\Models\Activity;

// Obtener todos los logs de un usuario
$user->activityLogs; // Relación automática del trait

// Obtener todos los logs
Activity::all();

// Filtrar por usuario
Activity::causedBy($user)->get();

// Filtrar por modelo afectado
Activity::forSubject($post)->get();

// Filtrar por descripción
Activity::where('description', 'like', '%creó%')->get();

// Logs recientes
Activity::latest()->take(50)->get();
```

## Exclusión de Rutas

Las siguientes rutas están excluidas del registro automático:
- `sanctum.csrf-cookie`
- `health`, `ping`, `metrics`
- `debugbar`, `telescope`

Puedes modificar las rutas excluidas en `app/Http/Middleware/LogUserActivity.php`.

## Información Sensible

El sistema automáticamente excluye campos sensibles como:
- `password`, `password_confirmation`, `current_password`
- `token`, `api_token`, `secret`
- `credit_card`, `cvv`, `ssn`

Estos campos aparecen como `***REDACTED***` en los logs.

## Estructura de la Base de Datos

La tabla `activity_log` contiene:
- `id`: ID del log
- `log_name`: Nombre del log (opcional)
- `description`: Descripción del evento
- `subject_type`, `subject_id`: Modelo afectado (polimórfico)
- `causer_type`, `causer_id`: Usuario que causó el evento (polimórfico)
- `properties`: JSON con datos adicionales
- `event`: Tipo de evento (created, updated, deleted, etc.)
- `batch_uuid`: UUID del lote (para agrupar eventos relacionados)
- `created_at`, `updated_at`: Timestamps

## Limpieza Automática

El sistema está configurado para eliminar registros más antiguos de 365 días. Puedes cambiar esto en `config/activitylog.php`:

```php
'delete_records_older_than_days' => 365,
```

Para limpiar manualmente:

```bash
php artisan activitylog:clean
```
