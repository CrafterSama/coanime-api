<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Helper;
use App\Models\Magazine;
use App\Models\Post;
use App\Models\Title;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     * Now uses Spatie Media Library to save images
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'model' => 'required|string',
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240', // 10MB max
            'model_id' => 'sometimes|integer',
            'collection' => 'sometimes|string',
        ]);

        $modelName = $request->model;
        $modelId = $request->model_id;
        $collection = $request->collection ?? $this->getDefaultCollection($modelName);

        try {
            // Map model names to classes
            $modelClasses = [
                'posts' => Post::class,
                'titles' => Title::class,
                'magazines' => Magazine::class,
                'users' => User::class,
            ];

            if (!isset($modelClasses[$modelName])) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Invalid model type',
                ], Response::HTTP_BAD_REQUEST);
            }

            $modelClass = $modelClasses[$modelName];

            // If model_id is provided, use existing model, otherwise create a temporary one
            if ($modelId) {
                $model = $modelClass::findOrFail($modelId);
            } else {
                // For temporary uploads (like in editor), create a temporary model instance
                // This is a workaround - ideally the frontend should provide model_id
                $model = new $modelClass();
                // For posts, we might need to create a draft first
                if ($modelName === 'posts') {
                    $model->user_id = auth()->id() ?? 1;
                    $model->save();
                } else {
                    // For other models, we can't create without required fields
                    // So we'll just upload to a generic location
                    return response()->json([
                        'code' => 400,
                        'message' => 'model_id is required for this model type',
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Add media using Spatie Media Library
            $media = $model->addMediaFromRequest('file')
                ->usingName($request->file('file')->getClientOriginalName())
                ->toMediaCollection($collection);

            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage('Image uploaded successfully'),
                'url' => $media->getUrl(),
                'media_id' => $media->id,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get default collection name for model
     */
    private function getDefaultCollection(string $modelName): string
    {
        $collections = [
            'posts' => 'featured-image',
            'titles' => 'cover',
            'magazines' => 'cover',
            'users' => 'avatar',
        ];

        return $collections[$modelName] ?? 'default';
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        //
    }
}
