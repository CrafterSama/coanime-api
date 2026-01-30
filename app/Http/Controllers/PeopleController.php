<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PeopleStoreRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Helper;
use App\Models\People;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PeopleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = People::search($request->name)->with('country', 'city');

        // Filters
        if ($request->has('country_code') && $request->country_code) {
            $query->where('country_code', $request->country_code);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'asc';
        
        // Allowed sort columns
        $allowedSortColumns = ['name', 'created_at', 'updated_at', 'id'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 30);
        $perPage = min(max((int) $perPage, 1), 100); // Between 1 and 100

        $people = $query->paginate($perPage);

        // Get filter options (solo si se solicita con ?include_filters=1)
        if ($request->get('include_filters')) {
            $countries = \App\Models\Country::whereHas('people')->orderBy('name', 'asc')->get(['iso3 as id', 'name']);

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Personas',
                'description' => 'Lista de Personas en la enciclopedia de coanime.net',
                'result' => $people,
                'filters' => [
                    'countries' => $countries,
                ],
            ], 200);
        }

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
     * @return \Illuminate\Http\JsonResponse
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
     * @return \Illuminate\Http\JsonResponse
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
     * Return countries and cities for the people create/edit form.
     * Prefer using countries-search and cities-search for async search (debounce, min 3 chars).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function formFilters()
    {
        $countries = Country::orderBy('name', 'asc')->get(['iso3 as id', 'name']);
        $cities = City::orderBy('name', 'asc')->get(['id', 'name']);

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'success',
                'text' => 'Filtros cargados',
            ],
            'countries' => $countries,
            'cities' => $cities,
        ], 200);
    }

    /**
     * Search countries by name. For people form async select.
     * Query param: q (min 3 chars), limit (optional, default 20).
     * Returns [{ value: iso3, label: name }].
     */
    public function countriesSearch(Request $request)
    {
        $q = $request->input('q', '');
        $q = \is_string($q) ? trim($q) : '';

        if (\strlen($q) < 3) {
            return response()->json([
                'code' => 200,
                'result' => [],
                'message' => ['type' => 'success', 'text' => 'Escribe al menos 3 caracteres'],
            ], 200);
        }

        $limit = min(max((int) $request->input('limit', 20), 1), 50);
        $countries = Country::where('name', 'like', '%' . $q . '%')
            ->orderBy('name', 'asc')
            ->limit($limit)
            ->get(['iso3', 'name'])
            ->map(fn ($c) => ['value' => $c->iso3, 'label' => $c->name]);

        return response()->json([
            'code' => 200,
            'result' => $countries,
            'message' => ['type' => 'success', 'text' => 'OK'],
        ], 200);
    }

    /**
     * Search cities by name, filtered by country. For people form async select.
     * Query params: q (min 3 chars), country_code (required, iso3), limit (optional, default 20).
     * Returns [{ value: id, label: name }].
     */
    public function citiesSearch(Request $request)
    {
        $q = $request->input('q', '');
        $q = \is_string($q) ? trim($q) : '';
        $countryCode = $request->input('country_code', '');
        $countryCode = \is_string($countryCode) ? trim($countryCode) : '';

        if (!$countryCode) {
            return response()->json([
                'code' => 400,
                'result' => [],
                'message' => ['type' => 'error', 'text' => 'country_code is required'],
            ], 400);
        }

        if (\strlen($q) < 3) {
            return response()->json([
                'code' => 200,
                'result' => [],
                'message' => ['type' => 'success', 'text' => 'Escribe al menos 3 caracteres'],
            ], 200);
        }

        $country = Country::where('iso3', $countryCode)->first();
        if (!$country) {
            return response()->json([
                'code' => 200,
                'result' => [],
                'message' => ['type' => 'success', 'text' => 'País no encontrado'],
            ], 200);
        }

        $limit = min(max((int) $request->input('limit', 20), 1), 50);
        $cities = City::where('country_id', $country->id)
            ->where('name', 'like', '%' . $q . '%')
            ->orderBy('name', 'asc')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => (int) $c->id, 'label' => $c->name]);

        return response()->json([
            'code' => 200,
            'result' => $cities,
            'message' => ['type' => 'success', 'text' => 'OK'],
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PeopleStoreRequest $request)
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
        }

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

        $payload = $request->except(['image', 'image-client', 'bio']);
        $payload['about'] = $request->input('bio') ?? $request->input('about');

        if ($data = People::create($payload)) {
            if ($request->filled('image') && \is_string($request->image)) {
                $data->clearMediaCollection('default');
                try {
                    $data->addMediaFromUrl($request->image)->toMediaCollection('default');
                } catch (\Exception $e) {
                    \Log::warning('People addMediaFromUrl failed: '.$e->getMessage());
                }
            } elseif ($request->file('image-client')) {
                $data->clearMediaCollection('default');
                $data->addMediaFromRequest('image-client')->toMediaCollection('default');
            }
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

    /**
     * Build person payload for API (include image + media).
     *
     * @param  \App\Models\People  $person
     * @return array<string, mixed>
     */
    private function personResult(People $person): array
    {
        $arr = $person->toArray();
        $arr['image'] = $person->image;
        $arr['media'] = $person->getMedia('default')->map(fn ($m) => [
            'id' => $m->id,
            'url' => $m->getUrl(),
            'file_name' => $m->file_name,
        ])->values()->all();

        return $arr;
    }

    /**
     * Display the specified resource.
     *
     * @param  int|string  $idOrSlug
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($idOrSlug)
    {
        $person = \is_numeric($idOrSlug)
            ? People::with('city', 'country')->find($idOrSlug)
            : People::with('city', 'country')->where('slug', $idOrSlug)->first();

        if (! $person) {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Persona no encontrada',
                ],
                'title' => 'Coanime.net - Persona',
                'description' => 'Ver la información de una persona',
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'success',
                'text' => 'Persona encontrada',
            ],
            'title' => 'Coanime.net - Persona',
            'description' => 'Ver la información de una persona',
            'result' => $this->personResult($person),
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiShow($slug)
    {
        if (People::where('slug', '=', $slug)->count() > 0) {
            $people = People::with('city', 'country')->whereSlug($slug)->firstOrFail();
            $people->about = Helper::bbcodeToHtml($people->about);

            //dd($people);

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $data = People::findOrFail($id);

        $this->validate($request, [
            'name' => 'required|max:255',
            'japanese_name' => 'required',
            'areas_skills_hobbies' => 'required',
            'bio' => 'required_without:about|nullable|string',
            'about' => 'required_without:bio|nullable|string',
            'city_id' => 'required',
            'birthday' => 'nullable|date_format:Y-m-d H:i:s',
            'country_code' => 'required',
            'falldown' => 'required',
            'falldown_date' => 'nullable|date_format:Y-m-d H:i:s',
            'image' => 'nullable|string|max:500',
            'image-client' => 'nullable|max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        if (empty($request['falldown_date'])) {
            $request['falldown_date'] = null;
        }

        $request['user_id'] = Auth::user()->id;
        $request['slug'] = Str::slug($request['name']);

        $updatePayload = $request->except(['image', 'image-client', 'bio']);
        $updatePayload['about'] = $request->input('bio') ?? $request->input('about');

        if ($data->update($updatePayload)) {
            $currentUrl = $data->getFirstMedia('default')?->getUrl();

            if ($request->file('image-client')) {
                $data->clearMediaCollection('default');
                $data->addMediaFromRequest('image-client')->toMediaCollection('default');
            } elseif ($request->filled('image') && \is_string($request->image)) {
                $newUrl = trim($request->image);
                if ($newUrl !== $currentUrl) {
                    $data->clearMediaCollection('default');
                    try {
                        $data->addMediaFromUrl($newUrl)->toMediaCollection('default');
                    } catch (\Exception $e) {
                        \Log::warning('People addMediaFromUrl failed: '.$e->getMessage());
                    }
                }
            }

            $data->load(['city', 'country']);

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Persona actualizada correctamente',
                ],
                'title' => 'Coanime.net - Editar Persona',
                'description' => 'Editar una persona',
                'result' => $this->personResult($data),
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
     * @return \Illuminate\Http\JsonResponse
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