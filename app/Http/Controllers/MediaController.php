<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Display a listing of media with search and filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Load media with model relationship - this ensures model_type and model_id are always available
        $query = Media::with('model');

        // Search by file name or model name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%")
                  ->orWhereHasMorph('model', [\App\Models\Post::class], function($q) use ($search) {
                      $q->where('title', 'like', "%{$search}%");
                  })
                  ->orWhereHasMorph('model', [\App\Models\Title::class], function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHasMorph('model', [\App\Models\User::class], function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHasMorph('model', [\App\Models\Magazine::class], function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by model type
        if ($request->has('model_type') && $request->model_type) {
            $modelType = 'App\\Models\\' . $request->model_type;
            $query->where('model_type', $modelType);
        }

        // Filter by collection
        if ($request->has('collection') && $request->collection) {
            $query->where('collection_name', $request->collection);
        }

        // Filter by placeholder status
        if ($request->has('is_placeholder')) {
            $isPlaceholder = filter_var($request->is_placeholder, FILTER_VALIDATE_BOOLEAN);
            if ($isPlaceholder) {
                $query->whereJsonContains('custom_properties->is_placeholder', true);
            } else {
                $query->where(function($q) {
                    $q->whereJsonDoesntContain('custom_properties->is_placeholder', true)
                      ->orWhereNull('custom_properties');
                });
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'desc';
        
        $allowedSortColumns = ['created_at', 'updated_at', 'name', 'file_name', 'size'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $perPage = min(max((int) $perPage, 1), 100);

        $media = $query->paginate($perPage);

        // Transform data to include model information
        $media->getCollection()->transform(function($item) {
            // Always get model type from model_type field - this should always exist in media table
            $modelName = null;
            if ($item->model_type) {
                $modelName = class_basename($item->model_type);
            } elseif ($item->model_id) {
                // If model_type is null but model_id exists, try to infer from custom_properties
                $customProps = $item->custom_properties ?? [];
                if (isset($customProps['model_type'])) {
                    $modelName = class_basename($customProps['model_type']);
                }
            }
            
            $modelId = $item->model_id;
            $modelTitle = null;
            $modelSlug = null;
            $modelTitleType = null;
            $model = null;

            // Try to load model (handles both eager loaded and lazy loaded cases, including soft deletes)
            if ($item->model_type && $item->model_id) {
                try {
                    // First try to get from relationship (if eager loaded)
                    $model = $item->model;
                    
                    // If not loaded, try to load manually
                    if (!$model) {
                        $modelClass = $item->model_type;
                        if (class_exists($modelClass)) {
                            // Check if model uses SoftDeletes trait
                            $usesSoftDeletes = in_array(
                                \Illuminate\Database\Eloquent\SoftDeletes::class,
                                class_uses_recursive($modelClass)
                            );
                            
                            if ($usesSoftDeletes) {
                                $model = $modelClass::withTrashed()->find($item->model_id);
                            } else {
                                $model = $modelClass::find($item->model_id);
                            }
                        }
                    }
                    
                    // Extract model information if loaded
                    if ($model) {
                        if ($model instanceof \App\Models\Post) {
                            $modelTitle = $model->title ?? null;
                            $modelSlug = $model->slug ?? null;
                        } elseif ($model instanceof \App\Models\Title) {
                            $modelTitle = $model->name ?? null;
                            $modelSlug = $model->slug ?? null;
                            $model->loadMissing('type');
                            $modelTitleType = $model->type?->name ?? null;
                        } elseif ($model instanceof \App\Models\User) {
                            $modelTitle = $model->name ?? null;
                            $modelSlug = $model->slug ?? null;
                        } elseif ($model instanceof \App\Models\Magazine) {
                            $modelTitle = $model->name ?? null;
                            $modelSlug = $model->slug ?? null;
                        }
                    }
                } catch (\Exception $e) {
                    // Model doesn't exist or can't be loaded, keep modelName from model_type field
                    \Log::debug("Could not load model for media {$item->id}: " . $e->getMessage());
                }
            }

            // Ensure collection_name is never null or empty - use actual value
            $collectionName = $item->collection_name;
            if (empty($collectionName) || $collectionName === 'default') {
                $collectionName = null; // Let frontend handle null display
            }

            // Ensure created_at is never null - this should always exist
            $createdAt = $item->created_at;
            if (!$createdAt) {
                // If created_at is null (shouldn't happen), use updated_at or current time
                $createdAt = $item->updated_at ?? now();
            }

            return [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'name' => $item->name ?? '',
                'file_name' => $item->file_name ?? '',
                'mime_type' => $item->mime_type ?? 'image/jpeg',
                'size' => $item->size ?? 0,
                'collection_name' => $collectionName,
                'disk' => $item->disk ?? 's3',
                'url' => $item->getUrl(),
                'thumb_url' => $item->hasGeneratedConversion('thumb') ? $item->getUrl('thumb') : null,
                'is_placeholder' => $item->getCustomProperty('is_placeholder', false),
                'original_url' => $item->getCustomProperty('original_url'),
                'model_type' => $modelName,
                'model_id' => $modelId,
                'model_title' => $modelTitle,
                'model_slug' => $modelSlug,
                'model_title_type' => $modelTitleType,
                'created_at' => $createdAt->toIso8601String(),
                'updated_at' => ($item->updated_at ?? now())->toIso8601String(),
            ];
        });

        // Get filter options if requested
        $includeFilters = $request->get('include_filters') ?? $request->get('includeFilters');
        if ($includeFilters == '1' || $includeFilters == 1 || $includeFilters === true || $includeFilters === 'true') {
            $modelTypes = Media::select('model_type')
                ->distinct()
                ->get()
                ->map(function($item) {
                    return class_basename($item->model_type);
                })
                ->unique()
                ->values()
                ->toArray();

            $collections = Media::select('collection_name')
                ->distinct()
                ->orderBy('collection_name', 'asc')
                ->pluck('collection_name')
                ->toArray();

            return response()->json([
                'code' => 200,
                'message' => 'Success',
                'result' => $media,
                'filters' => [
                    'model_types' => $modelTypes,
                    'collections' => $collections,
                ],
            ], 200);
        }

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'result' => $media,
        ], 200);
    }

    /**
     * Display the specified media
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $media = Media::with('model')->findOrFail($id);

        $model = $media->model;
        $modelName = null;
        $modelId = null;
        $modelTitle = null;
        $modelSlug = null;
        $modelTitleType = null;

        if ($model) {
            $modelName = class_basename($media->model_type);
            $modelId = $media->model_id;
            
            if ($model instanceof \App\Models\Post) {
                $modelTitle = $model->title;
                $modelSlug = $model->slug;
            } elseif ($model instanceof \App\Models\Title) {
                $modelTitle = $model->name;
                $modelSlug = $model->slug;
                $model->loadMissing('type');
                $modelTitleType = $model->type?->name ?? null;
            } elseif ($model instanceof \App\Models\User) {
                $modelTitle = $model->name;
                $modelSlug = $model->slug;
            } elseif ($model instanceof \App\Models\Magazine) {
                $modelTitle = $model->name;
                $modelSlug = $model->slug;
            }
        }

        $data = [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'collection_name' => $media->collection_name,
            'disk' => $media->disk,
            'url' => $media->getUrl(),
            'thumb_url' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : null,
            'medium_url' => $media->hasGeneratedConversion('medium') ? $media->getUrl('medium') : null,
            'large_url' => $media->hasGeneratedConversion('large') ? $media->getUrl('large') : null,
            'is_placeholder' => $media->getCustomProperty('is_placeholder', false),
            'original_url' => $media->getCustomProperty('original_url'),
            'custom_properties' => $media->custom_properties,
            'model_type' => $modelName,
            'model_id' => $modelId,
            'model_title' => $modelTitle,
            'model_slug' => $modelSlug,
            'model_title_type' => $modelTitleType,
            'created_at' => $media->created_at?->toIso8601String(),
            'updated_at' => $media->updated_at?->toIso8601String(),
        ];

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'data' => $data,
        ], 200);
    }

    /**
     * Update the specified media
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $media = Media::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'file' => 'sometimes|file|image|max:10240', // 10MB max
            'url' => 'sometimes|url',
        ]);

        // Update name if provided
        if ($request->has('name')) {
            $media->name = $request->name;
        }

        $model = $media->model;
        $collectionName = $media->collection_name;
        $mediaName = $request->name ?? $media->name;

        // Replace file from URL if provided
        $imageUrl = $request->input('url');
        if ($imageUrl && !$request->hasFile('file')) {
            if (!$model) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Model not found for this media',
                ], 404);
            }
            try {
                $media->delete();
                $newMedia = $model->addMediaFromUrl($imageUrl)
                    ->usingName($mediaName)
                    ->toMediaCollection($collectionName);
                if ($newMedia->getCustomProperty('is_placeholder', false)) {
                    $customProperties = $newMedia->custom_properties ?? [];
                    unset($customProperties['is_placeholder']);
                    unset($customProperties['file_not_accessible']);
                    $newMedia->custom_properties = $customProperties;
                    $newMedia->save();
                }
                return response()->json([
                    'code' => 200,
                    'message' => 'Media updated successfully',
                    'data' => [
                        'id' => $newMedia->id,
                        'name' => $newMedia->name,
                        'file_name' => $newMedia->file_name,
                        'url' => $newMedia->getUrl(),
                    ],
                ], 200);
            } catch (\Throwable $e) {
                \Log::warning('MediaController: could not add media from URL', [
                    'media_id' => $id,
                    'url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'code' => 422,
                    'message' => 'Could not load image from URL. Check that the URL is public and points to an image.',
                ], 422);
            }
        }

        // Replace file from upload if provided
        if ($request->hasFile('file')) {
            if (!$model) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Model not found for this media',
                ], 404);
            }

            $media->delete();

            $newMedia = $model->addMediaFromRequest('file')
                ->usingName($mediaName)
                ->toMediaCollection($collectionName);

            if ($newMedia->getCustomProperty('is_placeholder', false)) {
                $customProperties = $newMedia->custom_properties ?? [];
                unset($customProperties['is_placeholder']);
                unset($customProperties['file_not_accessible']);
                $newMedia->custom_properties = $customProperties;
                $newMedia->save();
            }

            return response()->json([
                'code' => 200,
                'message' => 'Media updated successfully',
                'data' => [
                    'id' => $newMedia->id,
                    'name' => $newMedia->name,
                    'file_name' => $newMedia->file_name,
                    'url' => $newMedia->getUrl(),
                ],
            ], 200);
        }

        // Save name changes
        $media->save();

        return response()->json([
            'code' => 200,
            'message' => 'Media updated successfully',
            'data' => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
            ],
        ], 200);
    }
}
