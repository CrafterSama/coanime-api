<?php

declare(strict_types=1);

return [

    /*
     * Si el logging está activado.
     */
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    /*
     * El nombre de la tabla para almacenar los logs.
     */
    'table_name' => env('ACTIVITY_LOG_TABLE_NAME', 'activity_log'),

    /*
     * La conexión de base de datos a usar. Si está vacío, se usará la conexión por defecto.
     */
    'database_connection' => env('ACTIVITY_LOG_DB_CONNECTION'),

    /*
     * El nombre del log por defecto para los eventos de modelos.
     */
    'default_log_name' => env('ACTIVITY_LOG_DEFAULT_LOG_NAME', 'default'),

    /*
     * El nombre del modelo para el log de actividad.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * Eliminar registros más antiguos que X días.
     */
    'delete_records_older_than_days' => env('ACTIVITY_LOG_DELETE_RECORDS_OLDER_THAN_DAYS', 365),

    /*
     * Si se debe usar la cola para registrar actividades.
     */
    'use_queue' => env('ACTIVITY_LOG_USE_QUEUE', false),

    /*
     * El nombre de la cola a usar.
     */
    'queue_name' => env('ACTIVITY_LOG_QUEUE_NAME', 'default'),

    /*
     * Si se deben limpiar automáticamente los registros antiguos.
     */
    'cleanup_on_model_events' => [
        'created',
        'updated',
        'deleted',
    ],

];
