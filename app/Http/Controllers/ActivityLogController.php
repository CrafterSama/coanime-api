<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Obtiene todos los logs de actividad.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Activity::query();

            // Filtrar por usuario autenticado o específico
            if ($request->has('user_id')) {
                $query->where('causer_id', $request->user_id)
                    ->where('causer_type', 'App\Models\User');
            } elseif (Auth::check() && ! $request->has('all')) {
                // Por defecto, solo mostrar logs del usuario autenticado a menos que se pida 'all'
                $query->where('causer_id', Auth::id())
                    ->where('causer_type', 'App\Models\User');
            }

            // Filtrar por tipo de recurso
            if ($request->has('subject_type')) {
                $query->where('subject_type', $request->subject_type);
            }

            // Filtrar por descripción/acción
            if ($request->has('description')) {
                $query->where('description', 'like', '%'.$request->description.'%');
            }

            // Filtrar por log_name
            if ($request->has('log_name')) {
                $query->where('log_name', $request->log_name);
            }

            // Filtrar por rango de fechas
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            // Filtrar por controlador
            if ($request->has('controller')) {
                $query->where('properties->controller_name', 'like', '%'.$request->controller.'%');
            }

            // Ordenar
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginar
            $perPage = min($request->get('per_page', 20), 100); // Máximo 100 por página
            $logs = $query->with(['causer', 'subject'])->paginate($perPage);

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $logs,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene un log específico por ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $log = Activity::with(['causer', 'subject'])->find($id);

            if (! $log) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Log no encontrado',
                ], 404);
            }

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $log,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener el log',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene los logs de un usuario específico.
     *
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLogs($userId)
    {
        try {
            $query = Activity::where('causer_id', $userId)
                ->where('causer_type', 'App\Models\User');

            // Aplicar filtros adicionales
            if (request()->has('from_date')) {
                $query->whereDate('created_at', '>=', request()->from_date);
            }

            if (request()->has('to_date')) {
                $query->whereDate('created_at', '<=', request()->to_date);
            }

            $perPage = min(request()->get('per_page', 20), 100);
            $logs = $query->with(['causer', 'subject'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $logs,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener logs del usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de actividad.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        try {
            $query = Activity::query();

            // Filtrar por usuario si se proporciona
            if ($request->has('user_id')) {
                $query->where('causer_id', $request->user_id)
                    ->where('causer_type', 'App\Models\User');
            }

            // Filtrar por rango de fechas
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $stats = [
                'total_activities' => (clone $query)->count(),
                'activities_by_type' => (clone $query)
                    ->selectRaw('subject_type, count(*) as count')
                    ->whereNotNull('subject_type')
                    ->groupBy('subject_type')
                    ->get()
                    ->pluck('count', 'subject_type'),
                'activities_by_controller' => (clone $query)
                    ->whereRaw("JSON_EXTRACT(properties, '$.controller_name') IS NOT NULL")
                    ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.controller_name')) as controller_name, count(*) as count")
                    ->groupBy('controller_name')
                    ->get()
                    ->pluck('count', 'controller_name'),
                'activities_by_date' => (clone $query)
                    ->selectRaw('DATE(created_at) as date, count(*) as count')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->limit(30)
                    ->get()
                    ->pluck('count', 'date'),
                'most_active_users' => (clone $query)
                    ->whereNotNull('causer_id')
                    ->selectRaw('causer_id, count(*) as count')
                    ->groupBy('causer_id')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->with('causer')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'user_id' => $item->causer_id,
                            'user' => $item->causer,
                            'activities_count' => $item->count,
                        ];
                    }),
            ];

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
