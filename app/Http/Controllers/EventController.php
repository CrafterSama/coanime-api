<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $events = Event::search($request->name)->with('users', 'city', 'country')->orderBy('date_start', 'desc')->paginate(30);

        if ($events->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Lista de Eventos Encontrada',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => $events,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se encontraron Eventos',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => [],
            ], 404);
        }
    }

    /**
     * Display a listing of the resource By Country.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexByCountry(Request $request, $slug)
    {
        $country = Country::where('name', ucfirst($slug))->first()->iso3;
        $events = Event::search($request->name)->where('country_code', $country)->with('users', 'city', 'country')->orderBy('date_start', 'desc')->paginate(30);

        if ($events->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Lista de Eventos Encontrada',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => $events,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se encontraron Eventos',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => [],
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
            'address' => 'required',
            'description' => 'required',
            'country_code' => 'required',
            'city_id' => 'required',
            'date_start' => 'required',
            'date_end' => 'required',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $data = new Event();
        $request['user_id'] = Auth::user()->id;
        $request['slug'] = Str::slug($request['name']);
        if (Event::where('slug', 'like', $request['slug'])->count() > 0) {
            $request['slug'] = Str::slug($request['name']).'1';
        }

        if ($request->file('image-client')) {
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path().'/images/events/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path().'/images/events/thumbnails/';
            // Guardar Original
            $fileName = hash('sha256', $data['slug'].strval(time()));

            $watermark = Image::make(public_path().'/images/logo_homepage.png');

            $watermark->opacity(30);

            $image->insert($watermark, 'bottom-right', 10, 10);

            $image->encode('jpg', 95);
            //dd($fileName);
            $image->save($originalPath.$fileName.'.jpg');
            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath.'thumb-'.$fileName.'.jpg');

            $request['image'] = $fileName.'.jpg';
        } else {
            $request['image'] = null;
        }

        //dd($data);

        if ($data = Event::create($request->all())) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Evento Creado',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => $data,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se pudo crear el Evento',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => [],
            ], 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($slug)
    {
        $id = Event::where('slug', 'like', $slug)->pluck('id');
        $event = Event::with('users')->find($id);

        if ($event) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Evento Encontrado',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => $event,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se encontró el Evento',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => [],
            ], 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiShow($slug)
    {
        $id = Event::where('slug', 'like', $slug)->pluck('id');
        $event = Event::with('users', 'country', 'city')->findOrFail($id)->first();

        if ($event) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Evento Encontrado',
                ],
                'title' => 'Coanime.net - Eventos - '.$event->name.'',
                'description' => $event->name.' tendrá lugar en '.$event->address.' ubicado en la ciudad de '.$event->city->name.' en '.$event->country->name,
                'result' => $event,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se encontró el Evento',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => [],
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
        $data = Event::find($id);

        $this->validate($request, [
            'name' => 'required',
            'address' => 'required',
            'description' => 'required',
            'country_code' => 'required',
            'city_id' => 'required',
            'date_start' => 'required',
            'date_end' => 'required',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $request['user_id'] = Auth::user()->id;
        $request['slug'] = Str::slug($request['name']);
        $request['image'] = is_string($request->get('image')) ? $request->get('image') : null;

        if ($data->update($request->all())) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Evento Actualizado',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => $data,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se pudo actualizar el Evento',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => [],
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
        $event = Event::find($id);

        if ($event->delete()) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Evento Eliminado',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => $event,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se pudo eliminar el Evento',
                ],
                'title' => 'Coanime.net - Eventos',
                'description' => 'Lista de Eventos en Coanime.net',
                'result' => [],
            ], 404);
        }
    }

    public function name(Request $request, $name)
    {
    }
}
