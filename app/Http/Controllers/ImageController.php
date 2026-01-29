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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Store image temporarily (no model_id). Used for create flows (posts, titles, magazines).
     * Returns public URL so the frontend can send it when creating the record.
     */
    private function storeTemporaryImage(Request $request, string $modelName)
    {
        $file = $request->file('file');
        if (! $file) {
            return response()->json([
                'code' => 400,
                'message' => 'No file provided',
            ], Response::HTTP_BAD_REQUEST);
        }

        $folder = sprintf('uploads/%s/%s', $modelName, now()->format('Y/m'));
        $filename = sprintf('%s.%s', Str::uuid()->toString(), $file->getClientOriginalExtension());
        $filePath = Storage::disk('s3')->putFileAs($folder, $file, $filename, 'public');
        $url = Storage::disk('s3')->url($filePath);

        return response()->json([
            'code' => 200,
            'message' => Helper::successMessage('Image uploaded successfully'),
            'url' => $url,
            'media_id' => null,
        ], Response::HTTP_OK);
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

            if ($modelId) {
                $model = $modelClass::findOrFail($modelId);

                // Replace previous media when single-file collections (cover, avatar, featured-image)
                if (in_array($collection, ['cover', 'avatar', 'featured-image'], true)) {
                    $model->clearMediaCollection($collection);
                }

                $media = $model->addMediaFromRequest('file')
                    ->usingName($request->file('file')->getClientOriginalName())
                    ->toMediaCollection($collection);

                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage('Image uploaded successfully'),
                    'url' => $media->getUrl(),
                    'media_id' => $media->id,
                ], Response::HTTP_OK);
            }

            if (in_array($modelName, ['posts', 'titles', 'magazines', 'users'], true)) {
                return $this->storeTemporaryImage($request, $modelName);
            }

            return response()->json([
                'code' => 400,
                'message' => 'model_id is required for this model type',
            ], Response::HTTP_BAD_REQUEST);
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
