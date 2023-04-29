<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\Helper;
use App\Models\People;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PeopleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $people = People::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
        if ($people->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista de Personas en la enciclopedia de coanime.net',
                'result' => $people,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin resultados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista sin resultados',
            ], 404);
        }
    }

    /**
     * Display a listing of the resource in JSON format.
     *
     * @return \Illuminate\Http\Response
     */
    public function apiIndex(Request $request)
    {
        $people = People::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
        if ($people->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista de Personas en la enciclopedia de coanime.net',
                'result' => $people,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin resultados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista sin resultados',
            ], 404);
        }
    }

    /**
     * Display a listing of the resource by Country in JSON format.
     *
     * @return \Illuminate\Http\Response
     */
    public function apiIndexByCountry(Request $request, $slug)
    {
        $country = Country::where('name', ucfirst($slug))->first()->iso3;
        $people = People::search($request->name)->where('country_code', $country)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
        if ($people->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista de Personas en la enciclopedia de coanime.net',
                'result' => $people,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin resultados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista sin resultados',
            ], 404);
        }
    }

    public function apiSearchPeople(Request $request)
    {
        $people = People::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
        if ($people->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista de Personas en la enciclopedia de coanime.net',
                'result' => $people,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin resultados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista sin resultados',
            ], 404);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $person = new People();
        $falldown = ['no' => 'No', 'si' => 'Si'];
        $cities = City::pluck('name', 'id');
        $countries = Country::pluck('name', 'code');

        return view('dashboard.people.create', compact('person', 'falldown', 'cities', 'countries'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (People::where('name', 'like', $request->get('name'))->where('birthday', '=', $request->get('birthday'))->count() > 0) {
            return response()->json([
                'code' => 400,
                'message' => [
                    'type' => 'error',
                    'text' => 'Ya existe una persona con ese nombre y fecha de nacimiento',
                ],
                'title' => 'Coanime.net - Crear Persona',
                'description' => 'Crear una nueva persona',
            ], 400);
        } else {
            $this->validate($request, [
                'name' => 'required|max:255',
                'japanese_name' => 'required',
                'areas_skills_hobbies' => 'required',
                'bio' => 'required',
                'city_id' => 'required',
                'birthday' => 'date_format:"Y-m-d H:i:s"',
                'country_code' => 'required',
                'falldown' => 'required',
                'falldown_date' => 'date_format:"Y-m-d H:i:s"',
                'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
            ]);

            if (empty($request['birthday'])) {
                $request['birthday'] = null;
            }

            if (empty($request['falldown_date'])) {
                $request['falldown_date'] = null;
            }

            $data = new People();

            $request['user_id'] = Auth::user()->id;
            $request['slug'] = Str::slug($request['name']);

            if (People::where('slug', 'like', $request['slug'])->count() > 0) {
                $request['slug'] = Str::slug($request['name']).'1';
            }

            if ($request->file('image-client')) {
                $file = $request->file('image-client');
                //Creamos una instancia de la libreria instalada
                $image = Image::make($request->file('image-client')->getRealPath());
                //Ruta donde queremos guardar las imagenes
                $originalPath = public_path().'/images/encyclopedia/people/';
                //Ruta donde se guardaran los Thumbnails
                $thumbnailPath = public_path().'/images/encyclopedia/people/thumbnails/';
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

                $request['image'] = $fileName.'.jpg';
            } else {
                $request['image'] = null;
            }

            //dd($data);

            if ($data = People::create($request->all())) {
                return response()->json([
                    'code' => 200,
                    'message' => [
                        'type' => 'success',
                        'text' => 'Persona creada correctamente',
                    ],
                    'title' => 'Coanime.net - Crear Persona',
                    'description' => 'Crear una nueva persona',
                ], 200);
            } else {
                return response()->json([
                    'code' => 400,
                    'message' => [
                        'type' => 'error',
                        'text' => 'Error al crear la persona',
                    ],
                    'title' => 'Coanime.net - Crear Persona',
                    'description' => 'Crear una nueva persona',
                ], 400);
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
        if (People::where('slug', '=', $slug)->count() > 0) {
            $people = People::with('city', 'country')->whereSlug($slug)->firstOrFail();
            //dd($people);
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Persona encontrada',
                ],
                'title' => 'Coanime.net - Persona',
                'description' => 'Ver la información de una persona',
                'result' => $people,
            ], 200);
        } else {
            return response()->json([
                'code' => 400,
                'message' => [
                    'type' => 'error',
                    'text' => 'Persona no encontrada',
                ],
                'title' => 'Coanime.net - Persona',
                'description' => 'Ver la información de una persona',
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function apiShow($slug)
    {
        if (People::where('slug', '=', $slug)->count() > 0) {
            $people = People::with('city', 'country')->whereSlug($slug)->firstOrFail();
            $people->bio = Helper::parseBBCode($people->bio);

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Persona encontrada',
                ],
                'title' => 'Coanime.net - Buscar Persona',
                'description' => 'Buscar una persona',
                'result' => $people,
            ], 200);
        } else {
            return response()->json([
                'code' => 400,
                'message' => [
                    'type' => 'error',
                    'text' => 'Persona no encontrada',
                ],
                'title' => 'Coanime.net - Buscar Persona',
                'description' => 'Buscar una persona',
            ], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = People::find($id);

        $this->validate($request, [
            'name' => 'required|max:255',
            'japanese_name' => 'required',
            'areas_skills_hobbies' => 'required',
            'bio' => 'required',
            'city_id' => 'required',
            'birthday' => 'date_format:"Y-m-d H:i:s"',
            'country_code' => 'required',
            'falldown' => 'required',
            'falldown_date' => 'date_format:"Y-m-d H:i:s"',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        if (empty($request['falldown_date'])) {
            $request['falldown_date'] = null;
        }

        $request['user_id'] = Auth::user()->id;
        $request['slug'] = Str::slug($request['name']);

        if ($request->file('image-client')) {
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path().'/images/encyclopedia/people/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path().'/images/encyclopedia/people/thumbnails/';
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

            $request['image'] = $fileName.'.jpg';
        }

        if ($data->update($request->all())) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Persona actualizada correctamente',
                ],
                'title' => 'Coanime.net - Editar Persona',
                'description' => 'Editar una persona',
            ], 200);
        } else {
            return response()->json([
                'code' => 400,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al actualizar la persona',
                ],
                'title' => 'Coanime.net - Editar Persona',
                'description' => 'Editar una persona',
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $people = People::find($id);

        if ($people->delete()) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Persona eliminada correctamente',
                ],
                'title' => 'Coanime.net - Eliminar Persona',
                'description' => 'Eliminar una persona',
            ], 200);
        } else {
            return response()->json([
                'code' => 400,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al eliminar la persona',
                ],
                'title' => 'Coanime.net - Eliminar Persona',
                'description' => 'Eliminar una persona',
            ], 400);
        }
    }

    public function name(Request $request, $name)
    {
    }

    public function slugs()
    {
        $people = People::all();
        foreach ($people as $p) {
            if (is_null($p->falldown_date) || empty($p->falldown_date)) {
                echo $p->id.' - No Paso Nada<br>';
            } else {
                if (preg_match("/([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{2,4})/", $p->falldown_date, $newBT)) {
                    $newBroadTime = $newBT[3].'-'.$newBT[2].'-'.$newBT[1].' 00:00:00';
                    $p->falldown_date = $newBroadTime;
                    $p->update();
                    echo $p->id.' -> '.$p->falldown_date.' -> Cambio Listo<br>';
                } else {
                    $newBroadTime = $p->falldown_date.' 00:00:00';
                    $p->falldown_date = $newBroadTime;
                    $p->update();
                    echo $p->id.' -> '.$p->falldown_date.' -> Cambio Listo<br>';
                }
            }
        }
        /*$people = People::all();
                    $magazine = Magazine::all();
                    $events = Event::all();
                    $titles = Title::all();
                    $companies = Company::all();

                    /*foreach($people as $p):
                        $p->name = $p->first_name. ' ' .$p->last_name;
                        $p->slug = Str::slug($p->first_name. ' ' .$p->last_name);
                        $p->update();
                        echo 'People -> '.$p->name.'<br>'.$p->slug.'<br>';
                    endforeach;

                    foreach($magazine as $mgz):
                        $mgz->slug = Str::slug($mgz->name);
                        $mgz->update();
                        echo 'Magazine -> '.$mgz->slug.'<br>';
                    endforeach;

                    foreach($events as $event):
                        $event->slug = Str::slug($event->name);
                        $event->update();
                        echo 'Events -> '.$event->slug.'<br>';
                    endforeach;

                    foreach($titles as $title):
                        $title->slug = Str::slug($title->name);
                        $title->update();
                        echo 'Titles -> '.$title->slug.'<br>';
                    endforeach;

                    foreach($companies as $company):
                        $company->slug = Str::slug($company->name);
                        $company->update();
                        echo 'Companies -> '.$company->slug.'<br>';
        */
    }
}
