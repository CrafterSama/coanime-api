<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Helper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Image;

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
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'model' => 'required:string',
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $model = $request->model;

        $path = '/'.$model.'/';

        if ($request->file('file')) {
            try {
                $image = Image::make($request->file('file')->getRealPath());
                $fileName = hash('sha256', strval(time()));
                $image->encode('webp', 100);

                if ($image->width() > 2560) {
                    $image->resize(2560, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }

                $filePath = $path.$fileName.'.webp';
                $imageUrl = Storage::disk('s3')->put($filePath, $image);
                $imageUrl = Storage::disk('s3')->url($filePath);

                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage('Image uploaded successfully'),
                    'url' => $imageUrl,
                ], Response::HTTP_OK);
            } catch (Exception $e) {
                return response()->json([
                    'code' => 500,
                    'message' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            return response()->json([
                'code' => 400,
                'message' => 'Error!! Image not Uploaded',
            ], Response::HTTP_BAD_REQUEST);
        }
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
