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
use App\Models\Helper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class TitleController extends Controller
{

    /**
     * Display a listing of titles serie.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
      if ($titles = Title::search($request->name)->with('images', 'rating', 'type', 'genres', 'users')->orderBy('name', 'asc')->simplePaginate()) {
          $types = TitleType::orderBy('name', 'asc')->get();
          $genres = Genre::orderBy('name', 'asc')->get();
          return response()->json(array(
              'code' => 200,
              'message' => [ 
                  'type' => 'success',
                  'text' => 'Resultados encontrados'
              ],
              'title' => 'Coanime.net - Lista de Titulos',
              'description' => 'Lista de titulos enla enciclopedia de coanime.net',
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
              'title' => 'Coanime.net - Lista de Titulos - Titulos No encontrados',
              'description' => 'Lista de titulos enla enciclopedia de coanime.net',
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
            if ($titles = Title::titles($request->name)->with('images', 'type', 'genres')->orderBy('name', 'asc')->paginate(30)) {
                return response()->json(array(
                    'code' => 200,
                    'message' => Helper::successMessage('Resultados encontrados'),
                    'title' => 'Coanime.net - Titulos',
                    'descripcion' => 'Títulos de la Enciclopedia, estos estan compuestos por títulos de TV, Mangas, Peliculas, Lives Actions, Doramas, Video Juegos, entre otros',
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
                'message' => 'Not Found',
                'title' => 'Coanime.net - Titulos - Titulo ya existe',
                'description' => 'La serie que trata de guardar ya esta en nuestros archivos',
            ));
            //return redirect()->back();
        } else {
            $this->validate($request, [
                'name' => 'required',
                'other_titles' => 'required',
                'type_id' => 'required',
                'sinopsis' => 'required',
                'episodies' => 'numeric',
                'just_year' => 'required',
                'broad_time' => 'required|date_format:"Y-m-d H:i:s"',
                'broad_finish' => 'date_format:"Y-m-d H:i:s"',
                'genre_id' => 'required',
                'rating_id' => 'required',
                'image-client' => 'required|max:1024|mimes:jpeg,gif,bmp,png|dimensions:min_width=300,min_height=400',
            ]);

            if (empty($request['broad_finish'])) {
                $request['broad_finish'] = null;
            }

            if (empty($request['episodies'])) {
                $request['episodies'] = null;
            }

            $data = new Title;

            $file = $request->file('image-client');

            //Creamos una instancia de la libreria instalada
            $image = Image::make($request->file('image-client')->getRealPath());

            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path() . '/images/encyclopedia/titles/';

            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path() . '/images/encyclopedia/titles/thumbnails/';

            $tName = TitleType::find($request['type_id']);

            // Guardar Original
            $fileName = hash('sha256', Str::slug($request['name']) . strval(time()));

            $watermark = Image::make(public_path() . '/images/logo_homepage.png');

            $watermark->opacity(30);

            if (($image->width() * .20) < 300) {
                if (($image->width() * .20) < 150) {
                    $watermark->resize(100, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                } else {
                    $watermark->resize(($image->width() * .20), null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }
            }

            $image->insert($watermark, 'bottom-right', 10, 10);

            $image->save($originalPath . $fileName . '.jpg');

            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath . 'thumb-' . $fileName . '.jpg');

            $request['user_id'] = Auth::user()->id;
            $request['slug'] = Str::slug($request['name']);

            if (Title::where('slug', '=', $request['slug'])->where('type_id', '=', $request['type_id'])->count() > 0) {
                $request['slug'] = Str::slug($request['name']) . '-01';
            }

            $request['images'] = 'https://coanime.net/images/encyclopedia/titles/' . $fileName . '.jpg';
            $request['thumbnail'] = 'https://coanime.net/images/encyclopedia/titles/thumbnails/thumb-' . $fileName . '.jpg';

            $data = $request->all();

            if ($data = Title::create($data)) {
                $images = $data->images ?: new TitleImage;
                $images->name = $request['images'];
                $images->thumbnail = $request['thumbnail'];
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
                    'code' => 403,
                    'message' => array(
                        'type' => 'Error',
                        'text' => 'No se pudo agregar el titulo',
                    ),
                    'title' => 'Coanime.net - Titulos - Titulo no Agregado',
                    'description' => 'El titulo no se ha podido agregar',
                ), 403);
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
    public function show($type, $slug)
    {
        if (!empty($type) || !empty($slug)){
            // TODO: Agregar/comprobar unicidad del campo slug
            $type_id = TitleType::whereSlug($type)->pluck('id');
            $title = Title::whereSlug($slug)->where('type_id', $type_id)->firstOrFail();
            return response()->json(array(
                'code' => 200,
                'message' => array(
                    'type' => 'Success',
                    'text' => 'Titulo encontrado',
                ),
                'title' => 'Coanime.net - Titulos - ' . $title->name,
                'description' => Str::words($title->sinopsis, 20),
                'data' => $title,
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

    public function apiShowTitle($type, $slug)
    {
        $type_id = TitleType::where('slug', '=', $type)->pluck('id');
        $title = Title::where('slug', '=', $slug)->where('type_id', $type_id);

        if ($title->count() > 0) {
            $id = $title->pluck('id');
            $name = $title->pluck('name');
            $description = $title->pluck('sinopsis');
            $title = Title::with('images', 'rating', 'type', 'genres', 'users', 'posts')->findOrFail($id);

            return response()->json(array(
                'code' => 200,
                'message' => Helper::successMessage('Titulo encontrado'),
                'title' => 'Coanime.net - Titulos - ' . $name->first(),
                'description' => Str::words(htmlentities(strip_tags($description->first())), 20),
                'data' => $title->first(),
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => Helper::errorMessage('Titulo no encontrado'),
            ), 404);
        }
    }

    public function apiTitlesByType($type)
    {
        $type_id = TitleType::where('slug', '=', $type)->pluck('id');
        $name = TitleType::where('slug', '=', $type)->pluck('name');
        $id = Title::where('type_id', $type_id)->pluck('id');
        $titles = Title::where('type_id', $type_id)->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->paginate(30);
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
            'types' => $types,
        ), 200);
    }

    public function apiAllByGenre($genre)
    {
        $genre_id = Genre::where('slug', '=', $genre)->pluck('id');
        $name = Genre::where('slug', '=', $genre)->pluck('name');

        $titles = Title::whereHas('genres', function ($q) use ($genre_id) {
            $q->where('genre_id', $genre_id);
        })->with('images', 'rating', 'type', 'genres')->orderBy('name', 'asc')->paginate(30);

        $genres = Genre::orderBy('name', 'asc')->get();

        return response()->json(array(
            'code' => 200,
            'message' => array(
                'type' => 'Success',
                'text' => 'Titulos encontrados',
            ),
            'title' => 'Coanime.net - Titulos - ' . $name->first(),
            'descripcion' => 'Títulos de la Enciclopedia en el aparatado de ' . $name->first(),
            'titles' => $titles,
            'genres' => $genres,
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

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'other_titles' => 'required',
            'type_id' => 'required',
            'sinopsis' => 'required',
            'episodies' => 'numeric',
            'just_year' => 'required',
            'broad_time' => 'required|date_format:"Y-m-d H:i:s"',
            'broad_finish' => 'date_format:"Y-m-d H:i:s"',
            'genre_id' => 'required',
            'rating_id' => 'required',
            'image-client' => 'max:1024|mimes:jpeg,gif,bmp,png|dimensions:min_width=300,min_height=400',
        ]);

        if (empty($request['broad_finish'])) :
            $request['broad_finish'] = null;
        endif;

        if (empty($request['episodies'])) :
            $request['episodies'] = '0';
        endif;

        $data = Title::find($id);

        if ($request->file('image-client')) {
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path() . '/images/encyclopedia/titles/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path() . '/images/encyclopedia/titles/thumbnails/';
            $tName = $data->type->name;
            // Guardar Original
            $fileName = hash('sha256', Str::slug($request['name']) . strval(time()));

            $watermark = Image::make(public_path() . '/images/logo_homepage.png');

            $watermark->opacity(30);

            if (($image->width() * .20) < 300) {
                if (($image->width() * .20) < 150) {
                    $watermark->resize(100, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                } else {
                    $watermark->resize(($image->width() * .20), null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }
            }

            $image->insert($watermark, 'bottom-right', 10, 10);

            $image->save($originalPath . $fileName . '.jpg');

            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath . 'thumb-' . $fileName . '.jpg');

            $request['images'] = 'https://coanime.net/images/encyclopedia/titles/' . $fileName . '.jpg';
            $request['thumbnail'] = 'https://coanime.net/images/encyclopedia/titles/thumbnails/thumb-' . $fileName . '.jpg';
        } else {
            $request['images'] = null;
            $request['thumbnail'] = null;
        }

        $request['user_id'] = $data['user_id'];
        $request['edited_by'] = Auth::user()->id;
        $request['slug'] = Str::slug($request['name']);

        //dd($request);

        if ($data->update($request->all())) {
            if ($request->file('image-client')) {
                if (TitleImage::where('title_id', $id)->count() > 0) {
                    $images = $data->images ?: TitleImage::where('title_id', $id);
                } else {
                    $images = $data->images ?: new TitleImage;
                }
                $images->name = $request['images'];
                $images->thumbnail = $request['thumbnail'];
                $data->images()->save($images);
            }
            $data->genres()->sync($request['genre_id']);
            return redirect()->to('dashboard/titles'); 
        } else {
            return back();
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

        if ($title->delete()) :
            Alert::success('El Titulo se ha Eliminado satisfactoriamente');
        return back(); else :
            Alert::error('El Post no se ha podido Eliminar');
        return back();
        endif;
    }

    public function name()
    {
        $title = Title::where('name', 'like', '%' . $value . '%')->get();
        return view('titles.details', ['title', $title]);
    }

    public function slugs()
    {
    }

    public function showCalendar()
    {
        $carbon = new Carbon;
        $titles = Title::orderBy('id', 'desc')->get();
        $people = People::orderBy('id', 'desc')->get();
        $magazine = Magazine::orderBy('id', 'desc')->get();
        $companies = Company::orderBy('id', 'desc')->get();

        return view('calendar.home', compact('titles', 'people', 'companies', 'magazine', 'carbon', 'legion'));
    }

    public function getJsonData(Request $request)
    {
        /* $url = 'http://www.ecma.animekaigen.xyz/api/content?cuantos=' . $request->get('a') . '&buscar=&ordenado=0&iniciar=' . $request->get('b'); */
        $url = 'http://www.ecma.animekaigen.xyz/api/content?cuantos=200&buscar=&ordenado=0&iniciar=1200';
        $content = file_get_contents($url);
        $json = json_decode($content, true);

        $data = [];

        $i = 1200;
        foreach ($json as $j) {
            $jdata = $j['response']['anime'];
            $find = [' (Latino)', ' (TV)', ' (latino)', ' (2011)', ' (2012)', ' (2010)', ' (2013)', ' (2014)', ' (2015)', ' (2016)', ' (2017)', ' (2018)', ' (2019)', ' (Sub-Inglés)', ' (Castellano)', ' (Movie)', ' latino', ' Latino', ' Movie', ' Castellano', ' Ova', ' ( )', ' ()'];
            $replace = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $data['name'] = str_replace($find, $replace, $jdata['nombre']);
            $data['name'] = str_replace($find, $replace, $jdata['nombre']);
            $data['slug'] = Str::slug($jdata['nombre']);
            $data['sinopsis'] = $jdata['sinopsis'];
            $data['episodies'] = $jdata['episodios'];
            $data['status'] = $jdata['estatus'];

            if (!empty($jdata['japones']) && !empty($jdata['nombre_alternativo'])) {
                $data['other_titles'] = $jdata['japones'] . '(Japonés),' . $jdata['nombre_alternativo'] . '(Sinonimo)';
            } elseif (!empty($jdata['japones']) && empty($jdata['nombre_alternativo'])) {
                $data['other_titles'] = $jdata['japones'] . '(Japonés)';
            } elseif (empty($jdata['japones']) && !empty($jdata['nombre_alternativo'])) {
                $data['other_titles'] = $jdata['nombre_alternativo'] . '(Sinonimo)';
            }

            $data['episodies'] = $jdata['episodios'];

            $data['user_id'] = 1;
            if ($jdata['tipo'] == 'Anime') {
                $data['type_id'] = 1;
            }
            if ($jdata['tipo'] == 'Película') {
                $data['type_id'] = 3;
            }
            if ($jdata['tipo'] == 'Ova') {
                $data['type_id'] = 4;
            }
            if ($jdata['tipo'] == 'Ona') {
                $data['type_id'] = 10;
            }

            $type_name = ['Aventuras', 'Comedia', 'Romance', 'Drama', 'Ciencia Ficción', 'Torneo', 'Acción', 'Magia', 'Psicológico', 'Demencia', 'Horror', 'Terror', 'Misterio', 'Sobrenatural', 'Erotico', 'Fantasía', 'Recuentos de la vida', 'Suspenso', 'Mecha', 'Historico', 'Ecchi', 'Cocina', 'Shoujo', 'Detectives', 'Seinen', 'Sirvientas', 'Moe', 'Shounen', 'Escolares', 'Gore', 'Harem', 'Yuri', 'Yaoi', 'Deportes', 'Arcade', 'Plataformas', 'Disparos', 'Lucha', 'Politica', 'RPG \/ Juegos de Rol', 'Puzzle', 'Estrategia', 'Simulación', 'Conducción', 'Carreras', 'Artes Marciales', 'Cyberpunk', 'Supervivencia', 'Construcción', 'Tablero', 'Educativo', 'Shounen-ai', 'Shoujo-ai', 'Josei', 'Doujinshi', 'Música', 'Espacial', 'Gotico', 'Fantasia Oscura', 'Demonios', 'Smut', 'Sentai', 'Parodia', 'Superpoderes', '_Superpoderes', 'Militar', 'Samurai', 'Infantil', 'Juegos', 'Policía', 'Vampiros', ', Latino-Español'];

            $type_id = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '9', '10', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '49', '38', '39', '40', '41', '42', '42', '43', '44', '45', '46', '47', '48', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '62', '63', '64', '65', '66', '67', '68'];

            if ($jdata['generos'] != "") :
                $generos = str_replace($type_name, $type_id, $jdata['generos']);

            $generos = str_replace(' ', '', $generos);

            $generos = explode(',', $generos);
            endif;

            //$titles = Title::all();

            //dd($titles->count());

            //return \App\Post::has('tags')->get();
            if (Title::search($data['name'])->where('type_id', '=', $data['type_id'])->count() > 0 || Title::where('slug', '=', $data['slug'])->where('type_id', '=', $data['type_id'])->count() > 0) :
                $i++;
            $oldId = Title::doesntHave('genres')->where('slug', '=', $data['slug'])->where('type_id', '=', $data['type_id'])->pluck('id');
            $oldId = Title::doesntHave('genres')->where('slug', '=', $data['slug'])->where('type_id', '=', $data['type_id'])->pluck('id');
            if ($jdata['generos'] != "") :
                    if ($oldId->count() > 0) :
                        if ($oldTitle = Title::find($oldId)) :
                            if ($oldTitle->has('genres')) :
                                $data['genre_id'] = $generos;
            $oldTitle->genres()->sync($data['genre_id']);
            echo '<p style="font-family: sans-serif">' . $i . '.- <span style="font-weight: bold">' . $data['name'] . '</span> (' . str_replace('Anime', 'Tv', $jdata['tipo']) . ') : Data Actualizada (Generos Actualizados: ' . $jdata['generos'] . ')</p>'; else :
                                echo '<p style="font-family: sans-serif">' . $i . '.- <span style="font-weight: bold">' . $data['name'] . '</span> (' . str_replace('Anime', 'Tv', $jdata['tipo']) . ') : Data Existente </p>';
            endif;
            endif; else :
                        echo '<p style="font-family: sans-serif">' . $i . '.- <span style="font-weight: bold">' . $data['name'] . '</span> (' . str_replace('Anime', 'Tv', $jdata['tipo']) . ') : Data Existente </p>';
            endif; else :
                    echo '<p style="font-family: sans-serif">' . $i . '.- <span style="font-weight: bold">' . $data['name'] . '</span> (' . str_replace('Anime', 'Tv', $jdata['tipo']) . ') : Data Existente </p>';
            endif; else :
                try {
                    if ($data = Title::create($data)) :
                        if ($jdata['generos'] != "") :
                            $data['genre_id'] = $generos;
                    //var_dump($data['genre_id']);
                    $data->genres()->sync($data['genre_id']);
                    endif;
                    $i++;
                    echo '<p style="font-family: sans-serif">' . $i . '.- <span style="font-weight: bold">' . $data['name'] . '</span> (' . str_replace('Anime', 'Tv', $jdata['tipo']) . ') : Data creada (Generos: ' . $jdata['generos'] . ')</p>';
                    endif;
                } catch (Error $e) {
                    echo $e;
                }
            endif;
        }
    }
}
