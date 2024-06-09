<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Helper;
use App\Models\HiddenSeeker;
use App\Models\Post;
use App\Models\Rate;
use App\Models\Ratings;
use App\Models\Statistics;
use App\Models\Tag;
use App\Models\Title;
use App\Models\TitleImage;
use App\Models\TitleRate;
use App\Models\TitleStatistics;
use App\Models\TitleType;
use App\Models\User;
use Carbon\Carbon;
use Goutte\Client as GoutteClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Image;
use Jikan\JikanPHP\Client;
use PHPUnit\Util\Json;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TitleController extends Controller
{
    /**
     * Display a listing of titles serie.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if ($titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres', 'users')->orderBy('created_at', 'desc')->paginate()) {
            $types = TitleType::orderBy('name', 'asc')->get();
            $genres = Genre::orderBy('name', 'asc')->get();

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Títulos',
                'description' => 'Lista de títulos en la enciclopedia de Coanime.net',
                'result' => $titles,
                'types' => $types,
                'genres' => $genres,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se encontraron resultados',
                ],
                'title' => 'Coanime.net - Lista de Títulos - Títulos No encontrados',
                'description' => 'Lista de títulos en la enciclopedia de Coanime.net',
            ], 404);
        }
    }

    /**
     * Display a listing of Titles Series with JSON Response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiSearchTitles(Request $request)
    {
        $types = TitleType::orderBy('name', 'asc')->get();
        $genres = Genre::orderBy('name', 'asc')->get();
        try {
            if ($titles = Title::titles($request->name)->with('images', 'type', 'genres')->orderBy('name', 'asc')->paginate(10)) {
                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage('Resultados encontrados'),
                    'title' => 'Coanime.net - Títulos',
                    'descripcion' => 'Títulos de la Enciclopedia, estos están compuestos por títulos de TV, Mangas, Películas, Lives Actions, Doramas, Video Juegos, entre otros',
                    'result' => $titles,
                    'types' => $types,
                    'genres' => $genres,
                ], 200);
            } else {
                return response()->json([
                    'code' => 404,
                    'message' => Helper::errorMessage('No se encontraron resultados'),
                    'title' => 'Coanime.net - Titulos - No encontrados',
                    'descripcion' => 'Títulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
                    'result' => $titles,
                    'types' => $types,
                    'genres' => $genres,
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => Helper::errorMessage('Error al buscar los titulos '.$e->getMessage()),
                'title' => 'Coanime.net - Titulos - Titulos No encontrados',
                'descripcion' => 'Títulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
            ], 500);
        }
    }

    /**
     * Get all the Data in JSON format to create a Title.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            $ratings = Ratings::all();
            $genres = Genre::all();
            $types = TitleType::all();

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'Success',
                    'text' => 'Titulo encontrado',
                ],
                'genres' => $genres,
                'types' => $types,
                'ratings' => $ratings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'Error',
                    'text' => 'No se encontraron resultados, Error: '.$e->getMessage(),
                ],
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
        $requestedName = strtolower($request->get('name'));
        $requestedType = strtolower($request->get('type_id'));

        if (Title::where('name', '=', $requestedName)->where('type_id', '=', $requestedType)->exists()) {
            return response()->json([
                'code' => 403,
                'message' => Helper::errorMessage('El titulo ya existe'),
            ], 403);
        } else {
            $this->validate($request, [
                'name' => 'required',
                'other_titles' => 'required',
                'type_id' => 'required',
                'sinopsis' => 'required',
                'episodies' => 'numeric',
                'just_year' => 'required',
                'broad_time' => 'required|date_format:"Y-m-d"',
                'broad_finish' => 'date_format:"Y-m-d"',
                'genre_id' => 'required',
                'rating_id' => 'required',
                'images' => 'required',
            ]);

            if (empty($request['broad_finish'])) {
                $request['broad_finish'] = null;
            }

            if (empty($request['episodies'])) {
                $request['episodies'] = 0;
            }

            $data = new Title();

            $request['user_id'] = Auth::user()->id;
            $request['slug'] = Str::slug($request['name']);

            if (Title::where('slug', '=', $request['slug'])->where('type_id', '=', $requestedType)->exists()) {
                return response()->json([
                    'code' => 403,
                    'message' => Helper::errorMessage('El titulo ya existe'),
                ], 403);
            }

            $data = $request->all();

            if ($data = Title::create($data)) {
                $images = $data->images ?: new TitleImage();
                $images->name = $request['images'];
                $images->thumbnail = $request['images'];
                $data->images()->save($images);
                $data->genres()->sync($request['genre_id']);

                return response()->json([
                    'code' => 200,
                    'message' => [
                        'type' => 'Success',
                        'text' => 'El título se ha guardado correctamente',
                    ],
                    'title' => 'Coanime.net - Títulos - Título Agregado',
                    'description' => 'El titulo se ha agregado correctamente',
                ], 200);
            } else {
                return response()->json([
                    'code' => 400,
                    'message' => [
                        'type' => 'Error',
                        'text' => 'No se pudo agregar el título',
                    ],
                    'title' => 'Coanime.net - Títulos - Título no Agregado',
                    'description' => 'El titulo no se ha podido agregar',
                ], 400);
            }
        }
    }

    /**
     * Display a single encyclopedia title.
     *
     * @param  string  $type
     * @param  string  $slug
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function show(Request $request, $id)
    {
        if ($title = Title::with('genres', 'type', 'images', 'users', 'rating')->find($id)) {
            $ratings = Ratings::all();
            $genres = Genre::all();
            $types = TitleType::all();

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'Success',
                    'text' => 'Titulo encontrado',
                ],
                'data' => $title,
                'genres' => $genres,
                'types' => $types,
                'ratings' => $ratings,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'Error',
                    'text' => 'No se pudo encontrar el titulo',
                ],
                'title' => 'Coanime.net - Titulos - Titulo no encontrado',
                'description' => 'El titulo no se ha podido encontrar',
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
        try {
            $this->validate($request, [
                'name' => 'required',
                'other_titles' => 'required',
                'type_id' => 'required',
                'sinopsis' => 'required',
                'episodies' => 'numeric',
                'just_year' => 'required',
                'broad_time' => 'required|date_format:"Y-m-d"',
                'genre_id' => 'required',
                'rating_id' => 'required',
                'images' => 'string',
            ]);

            if ($request['broad_finish']) {
                $this->validate($request, [
                    'broad_finish' => 'date_format:"Y-m-d"',
                ]);
            } else {
                $request['broad_finish'] = null;
            }

            if (empty($request['episodies'])) {
                $request['episodies'] = '0';
            }

            $data = Title::find($id);
            $request['user_id'] = $data['user_id'];
            $request['edited_by'] = Auth::user()->id;
            $request['slug'] = Str::slug($request['name']);

            if ($data->update($request->all())) {
                if ($request->images) {
                    $images = '';
                    if (TitleImage::where('title_id', $id)->count() > 0) {
                        $images = $data->images ?: TitleImage::where('title_id', $id);
                    } else {
                        $images = $data->images ?: new TitleImage();
                    }
                    $images->name = $request['images'];
                    $images->thumbnail = $request['images'];
                    $data->images()->save($images);
                }

                $data->genres()->sync($request['genre_id']);

                return response()->json([
                    'code' => 200,
                    'message' => [
                        'type' => 'Success',
                        'text' => 'Titulo actualizado',
                    ],
                    'title' => 'Coanime.net - Titulos - '.$request['name'],
                    'descripcion' => 'Títulos de la Enciclopedia en el aparatado de '.$request['name'],
                    'result' => $data,
                ], 200);
            } else {
                return response()->json([
                    'code' => 404,
                    'message' => [
                        'type' => 'Error',
                        'text' => 'Titulo no pudo ser actualizado',
                    ],
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Error al tratar de guardar. Error: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Update the Statistics of specific resource by user and statistic type
     *
     * @param  int  $id
     * @param  int  $statistics_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatistics(Request $request)
    {
        try {
            $data = Title::find($request->title_id);
            $user = User::find(Auth::user()->id);
            $stats_id = TitleStatistics::where('title_id', $request->title_id)->where('user_id', $user->id)->pluck('id')->first();
            $stats = TitleStatistics::find($stats_id);
            if ($stats_id && $stats->count() > 0) {
                $stats->statistics_id = $request->statistics_id;
                $stats->update();
            } else {
                $stats = new TitleStatistics();
                $stats->title_id = $request->title_id;
                $stats->user_id = $user->id;
                $stats->statistics_id = $request->statistics_id;
                $stats->save();
            }

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'Success',
                    'text' => 'Estadística actualizada',
                ],
                'result' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Error al tratar de guardar. Error: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    public function updateRates(Request $request)
    {
        try {
            $data = Title::find($request->title_id);
            $user = User::find(Auth::user()->id);
            $rate_id = TitleRate::where('title_id', $request->title_id)->where('user_id', $user->id)->pluck('id')->first();
            $rates = TitleRate::find($rate_id);
            if ($rate_id && $rates->count() > 0) {
                $rates->rate_id = $request->rate_id;
                $rates->update();
            } else {
                $rates = new TitleRate();
                $rates->title_id = $request->title_id;
                $rates->user_id = $user->id;
                $rates->rate_id = $request->rate_id;
                $rates->save();
            }

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'Success',
                    'text' => 'Rate actualizado',
                ],
                'result' => $rates,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Error al tratar de guardar. Error: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $title = Title::find($id);

        if ($title->delete()) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'Success',
                    'text' => 'Título eliminado',
                ],
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Título no eliminado',
                ],
            ], 404);
        }
    }

    public function userTitleList(Request $request)
    {
        $statisticsOptions = Statistics::select('id', 'name')->get();
        $abandoned = TitleStatistics::getStatisticsByAuthUser('1');
        $stopped = TitleStatistics::getStatisticsByAuthUser('2');
        $wantWatch = TitleStatistics::getStatisticsByAuthUser('3');
        $watching = TitleStatistics::getStatisticsByAuthUser('4');
        $watched = TitleStatistics::getStatisticsByAuthUser('5');
        $titles = TitleStatistics::where('user_id', Auth::user()->id)->with('titles', 'statistics')->orderBy('created_at', 'desc')->paginate(30);

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Titulos en tu Lista encontrados',
            ],
            'title' => 'Coanime.net - Titulos - Lista de Titulos',
            'descripcion' => 'Tu Lista de Titulos, los puedes agregar a la lista a traves de las Watch Options',
            'keywords' => 'Lista de Titulos, Titulos, Lista, Titulos en tu Lista, lista anime, lista manga, lista ova, lista película, lista especial, lista ona, lista ovas, lista películas, lista especiales, lista onas',
            'results' => $titles,
            'abandoned' => $abandoned,
            'stopped' => $stopped,
            'wantWatch' => $wantWatch,
            'watching' => $watching,
            'watched' => $watched,
            'meta' => ['statistics' => $statisticsOptions],
        ], 200);
    }

    public function statisticsByUser(Request $request)
    {
        $statistics = TitleStatistics::where('user_id', $request->user)->where('title_id', $request->title)->with('statistics')->get();

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Estadistica Encontrada',
            ],
            'data' => $statistics->first(),
        ], 200);
    }

    public function ratesByUser(Request $request)
    {
        $rates = TitleRate::where('user_id', $request->user)->where('title_id', $request->title)->with('rates')->get();

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Rate Encontrado',
            ],
            'data' => $rates->first(),
        ], 200);
    }

    /**
     * Get all items of the titles by type.
     *
     * @param  string  $type
     * @return \Illuminate\Contracts\View\View
     */
    public function showAllByType($type)
    {
        $type_id = TitleType::whereSlug($type)->pluck('id');
        if ($type_id->count() > 0) {
            $type_name = TitleType::where('slug', '=', $type)->pluck('name');
            $id = Title::where('type_id', $type_id)->pluck('id');
            $titles = Title::where('type_id', $type_id)->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->paginate(30);
            $types = TitleType::orderBy('name', 'asc')->get();
            $genres = Genre::orderBy('name', 'asc')->get();

            return view('titles.home', compact('titles', 'types', 'genres', 'type_name'));
        } else {
            return view('errors.404');
        }
    }

    /**
     * Get all items of the genre.
     */
    public function showAllGenre()
    {
        // TODO: Convert to pagination
        // TODO: Move to its own Controller
        $genre = Genre::withCount('titles')->orderBy('name', 'asc')->get();

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Generos encontrados',
            ],
            'title' => 'Coanime.net - Titulos - Generos',
            'description' => 'Se han encontrado los generos',
            'data' => $genre,
        ], 200);
    }

    /**
     * Get all items of the titles by genre.
     *
     * @param  string  $genre
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllByGenre($genre)
    {
        $genre_id = Genre::where('slug', 'like', $genre)->pluck('id');

        $titles = Title::whereHas('genres', function ($q) use ($genre_id) {
            $q->where('genre_id', $genre_id);
        })->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->simplePaginate(12);
        $genres = Genre::orderBy('name', 'asc')->get();
        $types = TitleType::orderBy('name', 'asc')->get();

        $data = [
            'titles' => $titles,
            'genres' => $genres,
            'types' => $types,
        ];

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ],
            'title' => 'Coanime.net - Titulos - '.$genre,
            'description' => 'Se han encontrado los titulos',
            'data' => $data,
        ], 200);

        // return view('titles.home', compact('titles', 'genres', 'types'));
    }

    public function getAllBySearch(Request $request)
    {
        $titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->get();

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ],
            'title' => 'Coanime.net - Titulos - Busqueda de '.$request->name,
            'description' => 'Se han encontrado los siguientes titulos',
            'data' => $titles,
        ], 200);
    }

    /**
     * Get the Titles in JSON Format from th API.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiTitles(Request $request)
    {
        $titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres', 'users', 'posts')->orderBy('name', 'asc')->paginate(30);
        $types = TitleType::orderBy('name', 'asc')->get();
        $genres = Genre::orderBy('name', 'asc')->get();

        return response()->json([
            'code' => 200,
            'message' => Helper::successMessage('Titulos encontrados'),
            'title' => 'Coanime.net - Titulos',
            'descripcion' => 'Títulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
            'keywords' => 'TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
            'result' => $titles,
            'types' => $types,
            'genres' => $genres,
        ], 200);
    }

    /**
     * Get the Titles in JSON Format from th API.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllTitles(Request $request)
    {
        if ($titles = Title::with('type')->orderBy('name', 'asc')->get()) {
            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage('Titulos encontrados'),
                'result' => $titles,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => Helper::errorMessage('No se encontraron titulos'),
            ], 404);
        }
    }

    /**
     * Get the Titles Upcoming in JSON Format from th API.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiTitlesUpcoming(Request $request)
    {
        if ($titles = Title::with('images', 'rating', 'type', 'genres', 'users', 'posts')->where('broad_time', '>', Carbon::now())->where('status', 'Estreno')->orderBy('broad_time', 'asc')->paginate()) {
            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage('Titulos encontrados'),
                'title' => 'Coanime.net - Titulos - Próximos Estrenos',
                'descripcion' => 'Titulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, entre otros que estan por estrenarse',
                'keywords' => 'TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, ona, ova, doujinshi, one shot, entre otros',
                'result' => $titles,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => Helper::errorMessage('No se encontraron titulos'),
                'title' => 'Coanime.net - Titulos - Próximos Estrenos',
                'descripcion' => 'Titulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, entre otros que estan por estrenarse',
                'keywords' => 'TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, ona, ova, doujinshi, one shot, entre otros',
                'result' => [],
            ], 404);
        }
    }

    /**
     * Get the Titles in JSON Format from th API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function apiShowTitle(string $type, string $slug)
    {
        //dd($type);
        $type_id = TitleType::where('slug', '=', $type)->pluck('id')->first();
        //dd(Title::where('type_id', $type_id)->where('slug', '=', $slug)->exists());
        if (Title::where('type_id', $type_id)->where('slug', '=', $slug)->exists()) {
            $title = Title::where('type_id', $type_id)->where('slug', '=', $slug)->first();
            $title = $title->load('images', 'rating', 'type', 'genres', 'users', 'posts');

            HiddenSeeker::updateSeriesByTitle($title, $type);

            $rates = Rate::all();
            $statistics = Statistics::all();
            $name = $title->name;
            $description = is_null($title->sinopsis) ? 'Sin descripción' : $title->sinopsis;

            $meta = [
                'statuses' => [
                    'emision' => 'En Emisión',
                    'finalizado' => 'Finalizado',
                    'estreno' => 'Estreno',
                ],
            ];
            ///dd($title);
            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage('Titulo encontrado'),
                'title' => 'Coanime.net - Titulos - '.$name,
                'description' => Str::words(htmlentities(strip_tags($description)), 20),
                'result' => $title,
                'rates' => $rates,
                'statistics' => $statistics,
                'meta' => $meta,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => Helper::errorMessage('Titulo no encontrado'),
            ], 200);
        }
    }

    public function apiShowTitlesByType($type)
    {
        $type_id = TitleType::where('slug', '=', $type)->pluck('id');
        //dd($type_id);
        $name = TitleType::where('slug', '=', $type)->pluck('name');
        $id = Title::where('type_id', $type_id)->pluck('id');
        $titles = Title::where('type_id', $type_id)->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->paginate(30);
        $types = TitleType::orderBy('name', 'asc')->get();
        $genres = Genre::orderBy('name', 'asc')->get();

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ],
            'title' => 'Coanime.net - Titulos - '.$name->first(),
            'descripcion' => 'Títulos de la Enciclopedia en el aparatado de '.$name->first(),
            'result' => $titles,
            'types' => $types,
            'genres' => $genres,
        ], 200);
    }

    public function apiShowTitlesByGenre($genre)
    {
        $genre_id = Genre::where('slug', '=', $genre)->pluck('id');
        $name = Genre::where('slug', '=', $genre)->pluck('name');

        $titles = Title::whereHas('genres', function ($q) use ($genre_id) {
            $q->where('genre_id', $genre_id);
        })->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->paginate(30);

        $genres = Genre::orderBy('name', 'asc')->get();
        $types = TitleType::orderBy('name', 'asc')->get();

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ],
            'title' => 'Coanime.net - Titulos - '.$name->first(),
            'descripcion' => 'Títulos de la Enciclopedia en el aparatado de '.$name->first(),
            'result' => $titles,
            'genres' => $genres,
            'types' => $types,
        ], 200);
    }

    public function postsTitle($type, $slug)
    {
        $tag_id = Tag::where('slug', '=', $slug)->pluck('id');

        if ($tag_id->count() > 0) {
            $posts = Post::getByTitle($tag_id);
            if (! empty($tag_id) && $posts->count() > 0) {
                $postsCount = $posts->count();
                $posts = $posts->orderBy('posts.postponed_to', 'desc')->simplePaginate();

                return response()->json([
                    'code' => 200,
                    'message' => [
                        'type' => 'Success',
                        'text' => 'Posts encontrados',
                    ],
                    'title' => 'Coanime.net - Posts - '.$slug,
                    'descripcion' => 'Posts de la Enciclopedia en el aparatado de '.$slug,
                    'quantity' => $postsCount,
                    'data' => $posts,
                ], 200);
            } else {
                return response()->json([
                    'code' => 404,
                    'message' => [
                        'type' => 'Error',
                        'text' => 'Posts no encontrados',
                    ],
                ], 404);
            }
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Posts no encontrados',
                ],
            ], 404);
        }
        /* return view('web.home', compact('posts')); */
    }

    public function titlesWithPosts($type, $slug)
    {
        $tag_id = Tag::where('slug', '=', $slug)->pluck('id');

        if ($tag_id->count() > 0) {
            $posts = Post::getByTitle($tag_id);
            if (! empty($tag_id) && $posts->count() > 0) {
                $postsCount = $posts->count();
                $posts = $posts->orderBy('posts.postponed_to', 'desc')->simplePaginate();

                return response()->json([
                    'code' => 200,
                    'message' => [
                        'type' => 'Success',
                        'text' => 'Posts encontrados',
                    ],
                    'title' => 'Coanime.net - Posts - '.$slug,
                    'descripcion' => 'Posts de la Enciclopedia en el aparatado de '.$slug,
                    'quantity' => $postsCount,
                    'data' => $posts,
                ], 200);
            } else {
                return response()->json([
                    'code' => 404,
                    'message' => [
                        'type' => 'Error',
                        'text' => 'Posts no encontrados',
                    ],
                ], 404);
            }
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Posts no encontrados',
                ],
            ], 404);
        }
        /* return view('web.home', compact('posts')); */
    }

    public function saveTitlesBySeason(Request $request)
    {
        $jikan = Client::create();
        $page = intval($request->get('page')) ?? 1;
        $year = intval($request->get('year')) ?? 2024;
        $season = $request->get('season') ?? 'winter';
        try {
            $results = $jikan->getSeason($year, $season, compact('page'));
            $process = [];
            //dd($results->getData());
            $process[] = '<p>Pagina '.$page.'</p>';
            foreach ($results->getData() as $key => $cloudTitle) {
                $title = new Title();
                $newGenres = [];
                $title->name = $cloudTitle->getTitle();

                $title->slug = Str::slug($cloudTitle->getTitle());
                $title->user_id = 1;
                $title->just_year = 'false';
                if (Title::where('slug', $title->slug)->first()) {
                    $process[] = '<p>'.$title->name.' Ya existe</p>';
                } elseif ($cloudTitle->getType() === null || $cloudTitle->getType() === 'Unknown' || $cloudTitle->getType() === 'Music') {
                    $process[] = '<p>'.$title->name.' No tiene determinado el Tipo</p>';
                } else {
                    $process[] = '<p>'.$title->name.' Procesando</p>';

                    if ($cloudTitle?->getTitles() !== null) {
                        $otherTitles = [];
                        foreach ($cloudTitle->getTitles() as $value) {
                            if (strtolower($value->getType()) === 'english') {
                                $titleEnglish = $value->getTitle().' (Inglés)';
                                if (!in_array($titleEnglish, $otherTitles)) {
                                    $otherTitles[] = $titleEnglish;
                                }
                            }
                            if (strtolower($value->getType()) === 'japanese') {
                                $titleJapanese = $value->getTitle().' (Japonés)';
                                if (!in_array($titleJapanese, $otherTitles)) {
                                    $otherTitles[] = $titleJapanese;
                                }
                            }
                            $title->other_titles = implode(', ', $otherTitles);
                            //$title->save();
                        }
                    }


                    if ((empty($title->sinopsis) || $title->sinopsis == 'Sinopsis no disponible' || $title->sinopsis == 'Pendiente de agregar sinopsis...' || $title->sinopsis == 'Sinopsis no disponible.' || $title->sinopsis == 'Sinopsis en Proceso') && $cloudTitle->getSynopsis() !== null) {
                        $title->sinopsis = GoogleTranslate::trans(str_replace('[Written by MAL Rewrite]', '', $cloudTitle->getSynopsis()), 'es');
                    }

                    if ((empty($title->trailer_url) || $title->trailer_url === null || $title->trailer_url === '') && $cloudTitle->getTrailer()->getUrl() !== null) {
                        $title->trailer_url = $cloudTitle->getTrailer()->getUrl();
                    }

                    if (empty($title->status) || HiddenSeeker::getStatus($cloudTitle->getStatus()) !== $title->status) {
                        $title->status = HiddenSeeker::getStatus($cloudTitle->getStatus());
                    }

                    if (empty($title->type)) {
                        $title->type_id = HiddenSeeker::getTypeById(strtolower($cloudTitle->getType()));
                    }

                    if (empty($title->rating_id) || $title->rating_id === 7) {
                        $title->rating_id = is_null($cloudTitle->getRating()) ? $title->rating_id === 7 : HiddenSeeker::getRatingId(strtolower($cloudTitle->getRating()));
                    }

                    if ($title->episodies === 0 || $title->episodies === null || empty($title->episodies)) {
                        $title->episodies = $cloudTitle->getEpisodes();
                    }

                    if ($title->broad_time === null || $title->broad_time === '0000-00-00 00:00:00') {
                        $title->broad_time = Carbon::create($cloudTitle->getAired()->getFrom())->format('Y-m-d');
                    }

                    if ($title->broad_finish === null || $title->broad_finish === '0000-00-00 00:00:00') {
                        $title->broad_finish = Carbon::create($cloudTitle->getAired()->getTo())->format('Y-m-d');
                    }
                    $title->save();

                    if ($title->genres->count() === 0) {
                        foreach ($cloudTitle->getGenres() as $key => $gen) {
                            if ($gen !== '' || $gen !== null) {
                                $newGenres[] = HiddenSeeker::getGenres(strtolower($gen->getName()));
                            }
                        }
                        $title->genres()->sync($newGenres);
                    }

                    $title->save();
                    $process[] = '<p>'.$title->name.' Guardado</p>';
                }
            }

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'Success',
                    'text' => 'Titulos de la pagina ' . $page . ' Guardados',
                ],
                'data' => $process,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Error al procesar la pagina ' . $page,
                ],
                'data' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveTitlesByAlphabetic(Request $request)
    {
        try {
            $jikan = Client::create();
            $query = [];
            $query['page'] = intval($request->get('page')) ?? 1;
            $request->get('q') ? $query['q'] = $request->get('q') : '';
            $request->get('letter') ? $query['letter'] = $request->get('letter') : '';
            $request->get('type') ? $query['type'] = $request->get('type') : '';
            $isManga = $request->get('type') === 'manga' || false;

            $results = $isManga ? $jikan->getMangaSearch($query) : $jikan->getAnimeSearch($query);

            $proccess = [];
            $proccess[] = '<p>Pagina '.$query['page'] ?? 1 .'</p>';

            foreach ($results->getData() as $key => $value) {
                // dd($value);
                $title = new Title();
                $newGenres = [];
                $title->name = $value->getTitle();
                $title->slug = Str::slug($value->getTitle());
                $title->user_id = 1;
                $title->just_year = 'false';
                if (Title::where('slug', $title->slug)->first()) {
                    $proccess[] = '<p>'.$title->name.' Ya existe</p>';
                } elseif ($value->getType() === null || $value->getType() === 'Unknown' || $value->getType() === 'Music' || $value->getType() === 'Award Winning') {
                    $proccess[] = '<p>'.$title->name.' No tiene determinado el Tipo</p>';
                } else {
                    $proccess[] = '<p>'.$title->name.' Procesando</p>';
                    if (empty($title->other_titles)) {
                        $title->other_titles .= $value->getTitleJapanese() ? $value->getTitleJapanese().' (Japonés)' : '';
                        $title->other_titles .= $value->getTitleEnglish() ? ', '.$value->getTitleEnglish().' (Inglés)' : '';
                    }

                    if (empty($title->sinopsis) || $title->sinopsis == 'Sinopsis no disponible' || $title->sinopsis == 'Pendiente de agregar sinopsis...') {
                        $title->sinopsis = $value->getSynopsis() ? GoogleTranslate::trans(str_replace('[Written by MAL Rewrite]', '', str_replace('(Source: MAL News)', '', $value->getSynopsis())), 'es') : 'Sinopsis en Proceso';
                    }

                    if ($query['type'] !== 'manga') {
                        if ((empty($title->trailer_url) || $title->trailer_url === null || $title->trailer_url === '') && $value->getTrailer()->getUrl() !== null) {
                            $title->trailer_url = $value->getTrailer()->getUrl();
                        }
                        if (! $title->rating_id || $title->rating_id === 7) {
                            $title->rating_id = HiddenSeeker::getRatingId(strtolower($value->getRating())) ?? 7;
                        }

                        if ($title->broad_time === null || $title->broad_time === '0000-00-00 00:00:00') {
                            $title->broad_time = Carbon::create($value->getAired()->getFrom())->format('Y-m-d');
                        }

                        if ($title->broad_finish === null || $title->broad_finish === '0000-00-00 00:00:00') {
                            $title->broad_finish = Carbon::create($value->getAired()->getTo())->format('Y-m-d');
                        }
                    }

                    if ($isManga) {
                        if ($title->broad_time === null || $title->broad_time === '0000-00-00 00:00:00') {
                            $title->broad_time = Carbon::create($value->getPublished()->getFrom())->format('Y-m-d');
                        }

                        if ($title->broad_finish === null || $title->broad_finish === '0000-00-00 00:00:00') {
                            $title->broad_finish = Carbon::create($value->getPublished()->getTo())->format('Y-m-d');
                        }
                    }

                    if ($title->episodies === 0 || $title->episodies === null || empty($title->episodies)) {
                        $title->episodies = $isManga ? $value->getChapters() : $value->getEpisodes();
                    }

                    if (! $title->status || HiddenSeeker::getStatus($value->getStatus()) !== $title->status) {
                        $title->status = HiddenSeeker::getStatus($value->getStatus());
                    }

                    if (! $title->type) {
                        $title->type_id = HiddenSeeker::getTypeById(strtolower($value->getType()));
                    }

                    $title->save();

                    if ($title->genres->count() === 0) {
                        foreach ($value->getGenres() as $key => $gen) {
                            if ($gen !== '' || $gen !== null || HiddenSeeker::getGenres(strtolower($gen->getName())) || $gen->getName() !== 'Award Winning' || HiddenSeeker::getGenres(strtolower($gen->getName()))) {
                                $newGenres[] = HiddenSeeker::getGenres(strtolower($gen->getName()));
                            }
                        }
                        foreach ($value->getDemographics() as $key => $gen) {
                            if ($gen !== '' || $gen !== null || HiddenSeeker::getGenres(strtolower($gen->getName()))) {
                                $newGenres[] = HiddenSeeker::getGenres(strtolower($gen->getName()));
                            }
                        }
                        $title->genres()->sync($newGenres);
                    }

                    $title->save();
                    $proccess[] = '<p>'.$title->name.' Guardado</p>';
                }
            }

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'Success',
                    'text' => 'Titulos de la pagina '.$query['page'] ?? 1 .' Guardados',
                ],
                'total_pages' => $results->getPagination()->getLastVisiblePage(),
                'data' => $proccess,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => [
                    'type' => 'Error',
                    'text' => 'Error al procesar la pagina '.$query['page'] ?? 1,
                ],
                'data' => $e->getMessage(),
            ], 500);
        }
    }

    public function consumeAnimes(Request $request)
    {
        $client = new GoutteClient();
        $page = $client->request('GET', 'https://jkanime.net/directorio/');
        $result = [];
        $page->filter('div.card.mb-3.custom_item2')->each(function ($node) use ($client) {
            $node->filter('a')->each(function ($node) use ($client) {
                $url = $node->attr('href');
                $page = $client->request('GET', $url);
                $result[] = $page;
            });
        });

        return response()->json([
            'code' => 200,
            'message' => [
                'type' => 'Success',
                'text' => 'Pagina Cargada',
            ],
            'data' => $result,
        ], 200);
    }
}