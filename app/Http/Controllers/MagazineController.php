<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Magazine;
use App\Models\MagazineImage;
use App\Models\MagazineType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MagazineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function index(Request $request)
    {
        $magazine = Magazine::search($request->name)->with('type', 'image', 'release', 'country')->orderBy('name', 'asc')->paginate(30);
        if ($magazine->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin Resultados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function apiIndex(Request $request)
    {
        $magazine = Magazine::search($request->name)->with('type', 'image', 'release', 'country')->orderBy('name', 'asc')->paginate(30);
        if ($magazine->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin Resultados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display a listing of the resource by slug.
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function apiIndexByDemography(Request $request, $slug)
    {
        $type = MagazineType::where('slug', $slug)->first()->id;
        $magazine = Magazine::search($request->name)->where('type_id', $type)->with('type', 'image', 'release', 'country')->orderBy('name', 'asc')->paginate(30);
        if ($magazine->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin Resultados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'about' => 'required',
            'type_id' => 'required',
            'release_id' => 'required',
            'country_code' => 'required',
            'website' => 'required',
            'foundation_date' => 'required|date_format:"Y-m-d H:i:s"',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $data = new Magazine();

        $request['slug'] = Str::slug($request['name']);

        $file = $request->file('image-client');
        //Creamos una instancia de la libreria instalada
        $image = Image::make($request->file('image-client')->getRealPath());
        //Ruta donde queremos guardar las imagenes
        $originalPath = public_path().'/images/encyclopedia/magazine/';
        //Ruta donde se guardaran los Thumbnails
        $thumbnailPath = public_path().'/images/encyclopedia/magazine/thumbnails/';
        // Guardar Original
        $fileName = hash('sha256', $request['slug'].strval(time()));

        $watermark = Image::make(public_path().'/images/logo_homepage.png');

        $watermark->opacity(30);

        $image->insert($watermark, 'bottom-right', 10, 10);

        $image->save($originalPath.$fileName.'.jpg');

        // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
        $image->resize(300, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        // Guardar
        $image->save($thumbnailPath.'thumb-'.$fileName.'.jpg');

        $request['user_id'] = Auth::user()->id;

        if (Magazine::where('slug', 'like', $request['slug'])->count() > 0) {
            $request['slug'] = Str::slug($request['name']).'-01';
        }
        $request['images'] = $fileName.'.jpg';

        $data = $request->all();

        if ($data = Magazine::create($data)) {
            $image = $data->image ?: new MagazineImage();
            $image->name = $request['images'];
            $data->image()->save($image);

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista creada correctamente',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $data,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al crear la revista',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($slug)
    {
        $mgz = Magazine::with('image', 'type', 'release')
            ->whereSlug($slug)
            ->firstOrFail();

        if ($mgz->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $mgz,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Revista no encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiShow($slug)
    {
        $mgz = Magazine::with('image', 'type', 'release', 'country')
            ->whereSlug($slug)
            ->firstOrFail();
        if ($mgz->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $mgz,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Revista no encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'about' => 'required',
            'type_id' => 'required',
            'release_id' => 'required',
            'country_code' => 'required',
            'website' => 'required',
            'foundation_date' => 'required|date_format:"Y-m-d H:i:s"',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $data = Magazine::find($id);

        if ($request->file('image-client')) {
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path().'/images/encyclopedia/magazine/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path().'/images/encyclopedia/magazine/thumbnails/';
            // Guardar Original
            $fileName = hash('sha256', Str::slug($request['name']).strval(time()));

            $watermark = Image::make(public_path().'/images/logo_homepage.png');

            $watermark->opacity(30);

            $image->insert($watermark, 'bottom-right', 10, 10);

            $image->save($originalPath.$fileName.'.jpg');

            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath.'thumb-'.$fileName.'.jpg');

            $request['images'] = $fileName.'.jpg';
        }

        $request['user_id'] = Auth::user()->id;
        $request['slug'] = Str::slug($request['name']);
        /*if(Magazine::where('slug','like', $request['slug'])->count() > 0):
            $request['slug'] = Str::slug($request['name']).'-01';
        */

        if ($data->update($request->all())) {
            if ($request->file('image-client')) {
                $image = $data->image ?: MagazineImage::where('magazine_id', $id);
                $image->name = $request['images'];
                $data->image()->save($image);
            }

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista actualizada correctamente',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $data,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al actualizar la revista',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        $magazine = Magazine::find($id);

        if ($magazine->delete()) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista eliminada correctamente',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al eliminar la revista',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    public function slugs()
    {
        $slugs = \App\Models\Company::all();
        foreach ($slugs as $s) {
            $s->slug = Str::slug($s->name);
            $s->update();
            echo $s->slug.'<br>';
        }
    }
}
