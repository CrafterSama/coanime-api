<?php

namespace App\Http\Controllers;

use Alert;
use App\Models\Genre;
use App\Models\Ratings;
use App\Models\Tag;
use App\Models\Title;
use App\Models\TitleImage;
use App\Models\TitleType;
use App\Models\User;
use App\Models\Category;
use App\Models\Company;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Post;
use App\Models\Rate;
use App\Models\TitleRate;
use App\Models\Statistics;
use App\Models\TitleStatistics;
use App\Models\Helper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Jikan\JikanPHP\Client;
use GoogleTranslate;
use Exception;
use Image;
use Termwind\Components\Dd;

class TitleController extends Controller
{
    private $genres = [
        'adventure' => 1,
        'comedy' => 2,
        'romance' => 3,
        'drama' => 4,
        'sci-fi' => 5,
        'action' => 7,
        'magic' => 8,
        'psychological' => 9,
        'horror' => 10,
        'mystery' => 11,
        'supernatural' => 12,
        'erotic' => 13,
        'fantasy' => 14,
        'slice of life' => 15,
        'thriller' => 16,
        'suspense' => 16,
        'mecha' => 17,
        'historical' => 18,
        'ecchi' => 19,
        'cooking' => 20,
        'shojo' => 21,
        'detectives' => 22,
        'seinen' => 23,
        'maids' => 24,
        'moe' => 25,
        'shounen' => 26,
        'school life' => 27,
        'school' => 27,
        'gore' => 28,
        'harem' => 29,
        'yuri' => 30,
        'girls love' => 30,
        'yaoi' => 31,
        'boys love' => 31,
        'sports' => 32,
        'team sports' => 32,
        'strategy game' => 40,
        'martial arts' => 43,
        'survival' => 45,
        'shounen ai' => 50,
        'shoujo ai' => 51,
        'josei' => 52,
        'doujinshi' => 53,
        'music' => 54,
        'spacial' => 55,
        'gothic' => 56,
        'dark fantasy' => 57,
        'demons' => 58,
        'smut' => 59,
        'sentai' => 60,
        'parody' => 61,
        'super powers' => 62,
        'super power' => 62,
        'superhero' => 62,
        'military' => 63,
        'samurai' => 64,
        "childs" => 65,
        'video games' => 66,
        'video game' => 66,
        'police' => 67,
        'vampires' => 68,
        'racing' => 69,
        'monsters' => 70,
        'isekai' => 71,
        'monster girls' => 72,
        'delinquents' => 74,
        'reverse harem' => 75,
        'office workers' => 76,
        'tragedy' => 77,
        'crime' => 78,
        'magical girls' => 79,
        'medical' => 80,
        'philosophical' => 81,
        'wuxia' => 82,
        'aliens' => 83,
        'animals' => 84,
        'crossdressing' => 85,
        'genderswap' => 86,
        'ghosts' => 87,
        'gyaru' => 88,
        'incest' => 89,
        'loli' => 90,
        'mafia' => 91,
        'ninja' => 92,
        'post-apocalyptic' => 93,
        'music' => 94,
        'reincarnation' => 95,
        'shota' => 96,
        'time travel' => 97,
        'traditional games' => 98,
        'villainess' => 99,
        'virtual reality' => 100,
        'zombies' => 101,
        'romantic subtext' => 102,
        'mythology' => 103,
        'high stakes game' => 104,
        'love polygon' => 105,
        'avant garde' => 106,
        'otaku culture' => 107,
        'gourmet' => 108,
        'hentai' => 109,
    ];

    private $status = [
        'Currently Airing' => 'En emisión',
        'Finished Airing' => 'Finalizado',
        'Not yet aired' => 'Estreno',
    ];

    private $rating = [
        'g - all ages' => 1,
        'g' => 1,
        'pg - children' => 2,
        'pg' => 2,
        'pg-13 - teens 13 or older' => 3,
        'pg-13' => 3,
        'r - 17+ (violence & profanity)' => 4,
        'r' => 4,
        'r+ - mild nudity' => 5,
        'r+' => 5,
        'rx - hentai' => 6,
        'rx' => 6,
    ];

    private $typeById = [
        'tv' => 1,
        'manga' => 2,
        'movie' => 3,
        'ova' => 4,
        'manhwa' => 5,
        'manhua' => 6,
        'ona' => 10,
        'light novel' => 11,
        'special' => 13,
        'one-shot' => 14,
        'doujinshi' => 15,
        'novel' => 16,
    ];

    private $typeInCloud = [
        'tv',
        'manga',
        'movie',
        'ova',
        'manhwa',
        'manhua',
        'ona',
        'light novel',
        'special',
        'one-shot',
        'doujinshi',
        'novel',
    ];

    private $typeTranslations = [
        'tv' => 'tv',
        'manga' => 'manga',
        'pelicula' => 'movie',
        'ova' => 'ova',
        'manhwa' => 'manhwa',
        'manhua' => 'manhua',
        'ona' => 'ona',
        'novela-ligera' => 'light novel',
        'especial' => 'special',
        'one-shot' => 'one-shot',
        'doujinshi' => 'doujinshi',
        'novela' => 'novel',
    ];

    /**
     * Display a listing of titles serie.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres', 'users')->orderBy('created_at', 'desc')->paginate()) {
            $types = TitleType::orderBy('name', 'asc')->get();
            $genres = Genre::orderBy('name', 'asc')->get();
            return response()->json(array(
                'code' => 200,
                'message' => [ 
                    'type' => 'success',
                    'text' => 'Resultados encontrados'
                ],
                'title' => 'Coanime.net - Lista de Títulos',
                'description' => 'Lista de títulos en la enciclopedia de Coanime.net',
                'result' => $titles,
                'types' => $types,
                'genres' => $genres,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'No se encontraron resultados'
                ],
                'title' => 'Coanime.net - Lista de Títulos - Títulos No encontrados',
                'description' => 'Lista de títulos en la enciclopedia de Coanime.net',
            ), 404);
        }
    }


    /**
     * Display a listing of Titles Series with JSON Response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function apiSearchTitles(Request $request)
    {
        $types = TitleType::orderBy('name', 'asc')->get();
        $genres = Genre::orderBy('name', 'asc')->get();
        try {
            if ($titles = Title::titles($request->name)->with('images', 'type', 'genres')->orderBy('name', 'asc')->paginate(10)) {
                return response()->json(array(
                    'code' => 200,
                    'message' => Helper::successMessage('Resultados encontrados'),
                    'title' => 'Coanime.net - Títulos',
                    'descripcion' => 'Títulos de la Enciclopedia, estos están compuestos por títulos de TV, Mangas, Películas, Lives Actions, Doramas, Video Juegos, entre otros',
                    'result' => $titles,
                    'types' => $types,
                    'genres' => $genres
                ), 200);
            } else {
                return response()->json(array(
                    'code' => 404,
                    'message' => Helper::errorMessage('No se encontraron resultados'),
                    'title' => 'Coanime.net - Titulos - No encontrados',
                    'descripcion' => 'Títulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
                    'result' => $titles,
                    'types' => $types,
                    'genres' => $genres
                ), 404);
            }
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => Helper::errorMessage('Error al buscar los titulos ' . $e->getMessage()),
                'title' => 'Coanime.net - Titulos - Titulos No encontrados',
                'descripcion' => 'Títulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
            ), 500);
        }
    }

    /**
     * Get all the Data in JSON format to create a Title.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        try {
            $ratings = Ratings::all();
            $genres = Genre::all();
            $types = TitleType::all();
            return response()->json(array(
                'code' => 200,
                'message' => array(
                    'type' => 'Success',
                    'text' => 'Titulo encontrado',
                ),
                'genres' => $genres,
                'types' => $types,
                'ratings' => $ratings,
            ), 200);
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 404,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'No se encontraron resultados, Error: ' . $e->getMessage(),
                ),
            ), 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (Title::where('name', '=', $request->get('name'))->where('type_id', '=', $request->get('type_id'))->count() > 0) {
            return response()->json(array(
                'code' => 403,
                'message' => Helper::errorMessage('El titulo ya existe'),
            ), 403);
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

            $data = new Title;

            $request['user_id'] = Auth::user()->id;
            $request['slug'] = Str::slug($request['name']);

            if (Title::where('slug', '=', $request['slug'])->where('type_id', '=', $request['type_id'])->count() > 0) {
                $request['slug'] = Str::slug($request['name']) . '-01';
            }

            $data = $request->all();

            if ($data = Title::create($data)) {
                $images = $data->images ?: new TitleImage;
                $images->name = $request['images'];
                $images->thumbnail = $request['images'];
                $data->images()->save($images);
                $data->genres()->sync($request['genre_id']);
                return response()->json(array(
                    'code' => 200,
                    'message' => array(
                        'type' => 'Success',
                        'text' => 'El titulo se ha guardado correctamente',
                    ),
                    'title' => 'Coanime.net - Titulos - Titulo Agregado',
                    'description' => 'El titulo se ha agregado correctamente',
                ), 200);
            } else {
                return response()->json(array(
                    'code' => 400,
                    'message' => array(
                        'type' => 'Error',
                        'text' => 'No se pudo agregar el titulo',
                    ),
                    'title' => 'Coanime.net - Titulos - Titulo no Agregado',
                    'description' => 'El titulo no se ha podido agregar',
                ), 400);
            }
        }
    }

    /**
     * Display a single encyclopedia title.
     *
     * @param  string  $type
     * @param  string  $slug
     * @return \Illuminate\Http\Response|mixed
     */
    public function show(Request $request, $id)
    {
        if ($title = Title::with('genres', 'type', 'images', 'users', 'rating')->find($id)) {
            $ratings = Ratings::all();
            $genres = Genre::all();
            $types = TitleType::all();
            return response()->json(array(
                'code' => 200,
                'message' => array(
                    'type' => 'Success',
                    'text' => 'Titulo encontrado',
                ),
                'data' => $title,
                'genres' => $genres,
                'types' => $types,
                'ratings' => $ratings,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'No se pudo encontrar el titulo',
                ),
                'title' => 'Coanime.net - Titulos - Titulo no encontrado',
                'description' => 'El titulo no se ha podido encontrar',
            ), 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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

            if (empty($request['episodies'])) :
                $request['episodies'] = '0';
            endif;

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
                        $images = $data->images ?: new TitleImage;
                    }
                    $images->name = $request['images'];
                    $images->thumbnail = $request['images'];
                    $data->images()->save($images);
                }
    
                $data->genres()->sync($request['genre_id']);
    
                return response()->json(array(
                    'code' => 200,
                    'message' => array(
                        'type' => 'Success',
                        'text' => 'Titulo actualizado',
                    ),
                    'title' => 'Coanime.net - Titulos - ' . $request['name'],
                    'descripcion' => 'Títulos de la Enciclopedia en el aparatado de ' . $request['name'],
                    'result' => $data,
                ), 200);
            } else {
                return response()->json(array(
                    'code' => 404,
                    'message' => array(
                        'type' => 'Error',
                        'text' => 'Titulo no pudo ser actualizado',
                    ),
                ), 404);
            }
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'Error al tratar de guardar. Error: ' . $e->getMessage(),
                ),
            ), 500);
        }
    }

    /**
     * Update the Statistics of specific resource by user and statistic type
     *
     * @param  int  $id
     * @param  int  $statistics_id
     * @return \Illuminate\Http\Response
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
                $stats = new TitleStatistics;
                $stats->title_id = $request->title_id;
                $stats->user_id = $user->id;
                $stats->statistics_id = $request->statistics_id;
                $stats->save();
            }
            return response()->json(array(
                'code' => 200,
                'message' => array(
                    'type' => 'Success',
                    'text' => 'Estadística actualizada',
                ),
                'result' => $stats,
            ), 200);
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'Error al tratar de guardar. Error: ' . $e->getMessage(),
                ),
            ), 500);
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
                $rates = new TitleRate;
                $rates->title_id = $request->title_id;
                $rates->user_id = $user->id;
                $rates->rate_id = $request->rate_id;
                $rates->save();
            }
            return response()->json(array(
                'code' => 200,
                'message' => array(
                    'type' => 'Success',
                    'text' => 'Rate actualizado',
                ),
                'result' => $rates,
            ), 200);
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'Error al tratar de guardar. Error: ' . $e->getMessage(),
                ),
            ), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $title = Title::find($id);

        if ($title->delete()) {
            return response()->json(array(
                'code' => 200,
                'message' => array(
                    'type' => 'Success',
                    'text' => 'Título eliminado',
                ),
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'Título no eliminado',
                ),
            ), 404);
        }
    }

    public function userTitleList(Request $request)
    {
        $abandoned = TitleStatistics::where('user_id', Auth::user()->id)->with(['statistics', 'titles'])->where('statistics_id', '1')->paginate();
        $stopped = TitleStatistics::where('user_id', Auth::user()->id)->with(['statistics', 'titles'])->where('statistics_id', '2')->paginate();
        $wantWatch = TitleStatistics::where('user_id', Auth::user()->id)->with(['statistics', 'titles'])->where('statistics_id', '3')->paginate();
        $watching = TitleStatistics::where('user_id', Auth::user()->id)->with(['statistics', 'titles'])->where('statistics_id', '4')->paginate();
        $watched = TitleStatistics::where('user_id', Auth::user()->id)->with(['statistics', 'titles'])->where('statistics_id', '5')->paginate();
        $titles = TitleStatistics::where('user_id', Auth::user()->id)->with('titles', 'statistics')->orderBy('created_at', 'desc')->paginate(30);
        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Titulos en tu Lista encontrados',
            ),
            'title' => 'Coanime.net - Titulos - Lista de Titulos',
            'descripcion' => 'Tu Lista de Titulos, los puedes agregar a la lista a traves de las Watch Options',
            'keywords' => 'Lista de Titulos, Titulos, Lista, Titulos en tu Lista, lista anime, lista manga, lista ova, lista película, lista especial, lista ona, lista ovas, lista películas, lista especiales, lista onas',
            'results' => $titles,
            'abandoned' => $abandoned,
            'stopped' => $stopped,
            'wantWatch' => $wantWatch,
            'watching' => $watching,
            'watched' => $watched,
        ), 200);
    }

    public function statisticsByUser(Request $request)
    {
        $statistics = TitleStatistics::where('user_id', $request->user)->where('title_id', $request->title)->with('statistics')->get();
        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Estadistica Encontrada',
            ),
            'data' => $statistics->first(),
        ), 200);
    }

    public function ratesByUser(Request $request)
    {
        $rates = TitleRate::where('user_id', $request->user)->where('title_id', $request->title)->with('rates')->get();
        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Rate Encontrado',
            ),
            'data' => $rates->first(),
        ), 200);
    }

    /**
     * Get all items of the titles by type.
     *
     * @param  str  $type
     * @return \Illuminate\Http\Response
     */
    public function showAllByType($type)
    {
        $type_id = TitleType::whereSlug($type)->pluck('id');
        if ($type_id->count() > 0):
            $type_name = TitleType::where('slug', '=', $type)->pluck('name');
        $id = Title::where('type_id', $type_id)->pluck('id');
        $titles = Title::where('type_id', $type_id)->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->paginate(30);
        $types = TitleType::orderBy('name', 'asc')->get();
        $genres = Genre::orderBy('name', 'asc')->get();

        return view('titles.home', compact('titles', 'types', 'genres', 'type_name')); else:
            return view('errors.404');
        endif;
    }

    /**
     * Get all items of the genre.
     *
     */
    public function showAllGenre()
    {
        // TODO: Convert to pagination
        // TODO: Move to its own Controller
        $genre = Genre::withCount('titles')->orderBy('name', 'asc')->get();

        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Generos encontrados',
            ),
            'title' => 'Coanime.net - Titulos - Generos',
            'description' => 'Se han encontrado los generos',
            'data' => $genre,
        ), 200);
    }

    /**
     * Get all items of the titles by genre.
     *
     * @param  str  $genre
     * @return \Illuminate\Http\Response
     */
    public function showAllByGenre($genre)
    {
        $genre_id = Genre::where('slug', 'like', $genre)->pluck('id');

        $titles = Title::whereHas('genres', function ($q) use ($genre_id) {
            $q->where('genre_id', $genre_id);
        })->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->simplePaginate(12);
        $genres = Genre::orderBy('name', 'asc')->get();
        $types = TitleType::orderBy('name', 'asc')->get();

        $data = array(
            'titles' => $titles,
            'genres' => $genres,
            'types' => $types,
        );

        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ),
            'title' => 'Coanime.net - Titulos - ' . $genre,
            'description' => 'Se han encontrado los titulos',
            'data' => $data,
        ), 200);

        return view('titles.home', compact('titles', 'genres', 'types'));
    }

    public function getAllBySearch(Request $request)
    {
        $titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->get();
        
        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ),
            'title' => 'Coanime.net - Titulos - Busqueda de ' . $request->name,
            'description' => 'Se han encontrado los siguientes titulos',
            'data' => $titles,
        ), 200);
    }

    /**
     * Get the Titles in JSON Format from th API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function apiTitles(Request $request)
    {
        $titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres', 'users', 'posts')->orderBy('name', 'asc')->paginate(30);
        $types = TitleType::orderBy('name', 'asc')->get();
        $genres = Genre::orderBy('name', 'asc')->get();

        return response()->json(array(
            'code' => 200,
            'message' => Helper::successMessage('Titulos encontrados'),
            'title' => 'Coanime.net - Titulos',
            'descripcion' => 'Títulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
            'keywords' => 'TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
            'result' => $titles,
            'types' => $types,
            'genres' => $genres
        ), 200);
    }

    /**
     * Get the Titles in JSON Format from th API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function getAllTitles(Request $request)
    {
        if ($titles = Title::with('type')->orderBy('name', 'asc')->get()) {
            return response()->json(array(
                'code' => 200,
                'message' => Helper::successMessage('Titulos encontrados'),
                'result' => $titles,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => Helper::errorMessage('No se encontraron titulos'),
            ), 404);
        }

    }

    public function apiShowTitle($type, $slug)
    {
        $jikan = Client::create();
        $type_id = TitleType::where('slug', '=', $type)->pluck('id')->first();
        $title = Title::where('type_id', $type_id)->where('slug', '=', $slug)->first();
        //dd($title->name);
        //dd($thisTitle);
        if ($title?->id !== null) {
            $thisTitle = Title::find($title->id);
            $cloudTitlesTemp = collect($jikan->getAnimeSearch(['q' => $title->name, 'type' => $type])->getData());

            $cloudTitlesTemp = $cloudTitlesTemp->filter(function ($value) use ($title) {
                return strtolower($value->getTitle()) === strtolower($title->name);
            });

            $cloudTitlesTemp = $cloudTitlesTemp->filter(function ($value) use ($type) {
                return strtolower($value->getType()) === $this->typeTranslations[$type];
            });

            $cloudTitle = $cloudTitlesTemp?->first() ?: null;
            //dd($cloudTitle);

            $id = $title->id;
            $name = $title->name;
            $description = $title->sinopsis;
            $title = $title->load('images', 'rating', 'type', 'genres', 'users', 'posts');
            //dd($title);

            if ($cloudTitle?->getTitle() !== null) {

                if(empty($thisTitle->other_titles)) {

                    $thisTitle->other_titles .= $cloudTitle->getTitleJapanese() ? $cloudTitle->getTitleJapanese() . ' (Japonés)' : '';
                    $thisTitle->other_titles .= $cloudTitle->getTitleEnglish() ? ', ' . $cloudTitle->getTitleEnglish() . ' (Inglés)' : '';
                    $thisTitle->save();
                }
    
                if (empty($title->sinopsis) || $thisTitle->sinopsis == 'Sinopsis no disponible' || $thisTitle->sinopsis == 'Pendiente de agregar sinopsis...') {
                    $thisTitle->sinopsis = GoogleTranslate::trans(str_replace('[Written by MAL Rewrite]', '', $cloudTitle->getSynopsis()), 'es');
                    $thisTitle->save();
                }
    
                if ((empty($thisTitle->trailer_url) || $thisTitle->trailer_url === null || $thisTitle->trailer_url === '') && $cloudTitle->getTrailer()->getUrl() !== null) {
                    $thisTitle->trailer_url = $cloudTitle->getTrailer()->getUrl();
                    $thisTitle->save();
                }
    
                if (!$thisTitle->status || $this->status[$cloudTitle->getStatus()] !== $thisTitle->status) {
                    $thisTitle->status = $this->status[$cloudTitle->getStatus()];
                    $thisTitle->save();
                }
    
                if (!$thisTitle->rating_id || $thisTitle->rating_id === 7) {
                    $thisTitle->rating_id = $this->rating[strtolower($cloudTitle->getRating())] ?? 7;
                    $thisTitle->save();
                }
    
                if ($thisTitle->episodies === 0 || $thisTitle->episodies === null || empty($thisTitle->episodies)) {
                    $thisTitle->episodies = $cloudTitle->getEpisodes();
                    $thisTitle->save();
                }

                if ($thisTitle->broad_time === null || $thisTitle->broad_time === '0000-00-00 00:00:00') {
                    $thisTitle->broad_time = $cloudTitle->getAired()->getFrom();
                    $thisTitle->save();
                }

                if ($thisTitle->broad_finish === null || $thisTitle->broad_finish === '0000-00-00 00:00:00') {
                    $thisTitle->broad_finish = $cloudTitle->getAired()->getTo();
                    $thisTitle->save();
                }
    
                if (!$title->images) {
                    $imageUrl = $cloudTitle->getImages()->getWebp()->getLargeImageUrl();
                    $processingImage = file_get_contents($imageUrl);
                    $image = Image::make($processingImage);
                    $fileName = hash('sha256', strval(time()));
                    $image->encode('webp', 100);
                    
                    if ($image->width() > 2560) {
                        $image->resize(2560, null, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                    }
                    
                    $path = '/titles/';
                    
                    $filePath = $path . $fileName . '.webp';
                    $imageUrl = Storage::disk('s3')->put($filePath, $image);
                    $imageUrl = Storage::disk('s3')->url($filePath);
                    $images = new TitleImage;
                    $images->create([
                        'title_id' => $thisTitle->id,
                        'name' => $imageUrl,
                        'thumbnail' => $imageUrl,
                    ]);
                    dd($images);
                }
                
                if ($title->genres->count() === 0) {
                    $newGenres = [];
                    foreach ($cloudTitle->getGenres() as $key => $gen) {
                        $newGenres[] = $this->genres[strtolower($gen->getName())];
                    }
                    $title->genres()->sync($newGenres);
                }
            }

            $rates = Rate::all();
            $statistics = Statistics::all();

            $meta = [
                'statuses' => [
                    'emision' => 'En Emisión',
                    'finalizado' => 'Finalizado',
                    'estreno' => 'Estreno',
                ],
            ];
            ///dd($title);
            return response()->json(array(
                'code' => 200,
                'message' => Helper::successMessage('Titulo encontrado'),
                'title' => 'Coanime.net - Titulos - ' . $name,
                'description' => Str::words(htmlentities(strip_tags($description)), 20),
                'result' => $title,
                'rates' => $rates,
                'statistics' => $statistics,
                'meta' => $meta,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => Helper::errorMessage('Titulo no encontrado'),
            ), 200);
        }
    }

    public function apiShowTitlesByType($type)
    {
        $type_id = TitleType::where('slug', '=', $type)->pluck('id');
        $name = TitleType::where('slug', '=', $type)->pluck('name');
        $id = Title::where('type_id', $type_id)->pluck('id');
        $titles = Title::where('type_id', $type_id)->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->paginate(30);
        $types = TitleType::orderBy('name', 'asc')->get();
        $genres = Genre::orderBy('name', 'asc')->get();

        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ),
            'title' => 'Coanime.net - Titulos - ' . $name->first(),
            'descripcion' => 'Títulos de la Enciclopedia en el aparatado de ' . $name->first(),
            'result' => $titles,
            'types' => $types,
            'genres' => $genres
        ), 200);
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

        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ),
            'title' => 'Coanime.net - Titulos - ' . $name->first(),
            'descripcion' => 'Títulos de la Enciclopedia en el aparatado de ' . $name->first(),
            'result' => $titles,
            'genres' => $genres,
            'types' => $types,
        ), 200);
    }

    public function postsTitle($type, $slug)
    {
        $tag_id = Tag::where('slug', '=', $slug)->pluck('id');

        if ($tag_id->count() > 0) {
            $posts = Post::getByTitle($tag_id);
            if (!empty($tag_id) && $posts->count() > 0) {
                $posts = $posts->orderBy('posts.postponed_to', 'desc')->simplePaginate();
                return response()->json(array(
                    'code' => 200,
                    'message' => array(
                        'type' => 'Success',
                        'text' => 'Posts encontrados',
                    ),
                    'title' => 'Coanime.net - Posts - ' . $slug,
                    'descripcion' => 'Posts de la Enciclopedia en el aparatado de ' . $slug,
                    'quantity' => $posts->count(),
                    'data' => $posts,
                ), 200); 
            } else {
                return response()->json(array(
                    'code' => 404,
                    'message' => array(
                        'type' => 'Error',
                        'text' => 'Posts no encontrados',
                    ),
                ), 404);
            }
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'Posts no encontrados',
                ),
            ), 404);
        }
        /* return view('web.home', compact('posts')); */
    }

    public function consumeMangas(Request $request)
    {
        try {

            $jikan = Client::create();
            $page = intval($request->get('page')) ?? 1;
            //dd($page);
            $results = $jikan->getSeasonUpcoming(['page' => $page]);
            $proccess = [];
            $proccess[] = '<p>Pagina ' . $page . '</p>';
            foreach ($results->getData() as $key => $value) {
                //dd($value);
                $title = new Title;
                $newGenres = [];
                $title->name = $value->getTitle();
                $title->slug = Str::slug($value->getTitle());
                $title->user_id = 1;
                $title->just_year = 'false';
                if (Title::where('slug', $title->slug)->first()) {
                    $proccess[] = '<p>' . $title->name . ' Ya existe</p>';
                } elseif($value->getType() === null) {
                    $proccess[] = '<p>' . $title->name . ' No tiene determinado el Tipo</p>';
                } else {
                    $proccess[] = '<p>' . $title->name . ' Procesando</p>';
                    if(empty($title->other_titles)) {
                        $title->other_titles .= $value->getTitleJapanese() ? $value->getTitleJapanese() . ' (Japonés)' : '';
                        $title->other_titles .= $value->getTitleEnglish() ? ', ' . $value->getTitleEnglish() . ' (Inglés)' : '';
                    }
    
                    if (empty($title->sinopsis) || $title->sinopsis == 'Sinopsis no disponible' || $title->sinopsis == 'Pendiente de agregar sinopsis...') {
                        $title->sinopsis = $value->getSynopsis() ? GoogleTranslate::trans(str_replace('[Written by MAL Rewrite]', '', $value->getSynopsis()), 'es') : 'Sinopsis en Proceso';
                    }
    
                    if ((empty($title->trailer_url) || $title->trailer_url === null || $title->trailer_url === '') && $value->getTrailer()->getUrl() !== null) {
                        $title->trailer_url = $value->getTrailer()->getUrl();
                    }
    
                    if (!$title->status || $this->status[$value->getStatus()] !== $title->status) {
                        $title->status = $this->status[$value->getStatus()];
                    }
    
                    if (!$title->type) {
                        $title->type_id = $this->typeById[strtolower($value->getType())];
                    }
    
                    if (!$title->rating_id || $title->rating_id === 7) {
                        $title->rating_id = $this->rating[strtolower($value->getRating())] ?? 7;
                    }
    
                    if ($title->episodies === 0 || $title->episodies === null || empty($title->episodies)) {
                        $title->episodies = $value->getEpisodes();
                    }
    
                    if ($title->broad_time === null || $title->broad_time === '0000-00-00 00:00:00') {
                        $title->broad_time = $value->getAired()->getFrom();
                    }
    
                    if ($title->broad_finish === null || $title->broad_finish === '0000-00-00 00:00:00') {
                        $title->broad_finish = $value->getAired()->getTo();
                    }
    
                    $title->save();
    
                    if ($title->genres->count() === 0) {
                        foreach ($value->getGenres() as $key => $gen) {
                            if ($gen !== '' || $gen !== null) {
                                $newGenres[] = $this->genres[strtolower($gen->getName())];
                            }
                        }
                        $title->genres()->sync($newGenres);
                    }
    
                    $title->save();
                    $proccess[] = '<p>' . $title->name . ' Guardado</p>';
                }
            }
            return response()->json(array(
                'code' => 200,
                'message' => array(
                    'type' => 'Success',
                    'text' => 'Titulos de la pagina ' . $page . ' Guardados',
                ),
                'data' => $proccess,
            ), 200);
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => array(
                    'type' => 'Error',
                    'text' => 'Error al procesar la pagina ' . $page,
                ),
                'data' => $e->getMessage(),
            ), 500);
        }

    }
}
