<?php

namespace App\Http\Controllers;

use Exception;

use App\Models\User;
use App\Models\Country;
use App\Models\Magazine;
use App\Models\MagazineType;
use App\Models\MagazineImage;
use App\Models\MagazineRelease;

use Illuminate\Http\Request;

use UxWeb\SweetAlert\SweetAlert as Alert;

class MagazineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function index(Request $request)
    {
        $magazine = Magazine::search($request->name)->with('type', 'image', 'release', 'country')->orderBy('name', 'asc')->paginate(30);
        if ($magazine->count() > 0) :
            return view('magazines.home', ['magazine' => $magazine]);
        else :
            return view('errors.404');
        endif;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function apiIndex(Request $request)
    {
        $magazine = Magazine::search($request->name)->with('type', 'image', 'release', 'country')->orderBy('name', 'asc')->paginate(30);
        if ($magazine->count() > 0) :
            return response()->json(array(
                'message' => 'Resource found',
                'magazine' => $magazine
            ), 200);
        else :
            return response()->json(array(
                'message' => 'Resource not found',
                'magazine' => []
            ), 404);
        endif;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $types = MagazineType::pluck('name', 'id');
        $releases = MagazineRelease::pluck('name', 'id');
        $countries = Country::pluck('name', 'code');
        $image = new MagazineImage;
        return view('dashboard.magazine.create', compact('releases', 'image', 'types', 'countries'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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

        $data = new Magazine;

        $request['slug'] = str_slug($request['name']);

        $file = $request->file('image-client');
        //Creamos una instancia de la libreria instalada
        $image = \Image::make($request->file('image-client')->getRealPath());
        //Ruta donde queremos guardar las imagenes
        $originalPath = public_path() . '/images/encyclopedia/magazine/';
        //Ruta donde se guardaran los Thumbnails
        $thumbnailPath = public_path() . '/images/encyclopedia/magazine/thumbnails/';
        // Guardar Original
        $fileName = hash('sha256', $request['slug'] . strval(time()));

        $watermark = \Image::make(public_path() . '/images/logo_homepage.png');

        $watermark->opacity(30);

        $image->insert($watermark, 'bottom-right', 10, 10);

        $image->save($originalPath . $fileName.'.jpg');

        // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
        $image->resize(300, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        // Guardar
        $image->save($thumbnailPath . 'thumb-' . $fileName.'.jpg');

        $request['user_id'] = \Auth::user()->id;

        if (Magazine::where('slug', 'like', $request['slug'])->count() > 0) :
            $request['slug'] = str_slug($request['name']) . '-01';
        endif;
        $request['images'] = $fileName.'.jpg';

        $data = $request->all();

        if ($data = Magazine::create($data)) :
            $image = $data->image ?: new MagazineImage;
            $image->name = $request['images'];
            $data->image()->save($image);
            \Alert::success('Revista Agregado');
            return redirect()->to('dashboard/magazine');
        else :
            \Alert::error('No se ha podido guardar la Informacion Suministrada');
            return back();
        endif;
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
        $mgz = Magazine::with('image', 'type', 'release')
            ->whereSlug($slug)
            ->firstOrFail();

        if ($mgz->count() > 0) :
            return view('magazines.details', compact('mgz'));
        else :
            return view('errors.404');
        endif;
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function apiShow($slug)
    {
        $mgz = Magazine::with('image', 'type', 'release', 'country')
            ->whereSlug($slug)
            ->firstOrFail();
        if($mgz->count() > 0):
            return response()->json(array(
                'message' => 'Resource found',
                'magazine' => $mgz
            ), 200);
        else :
            return response()->json(array(
                'message' => 'Resource not found',
                'magazine' => []
            ), 404);
        endif;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $magazine = Magazine::with('image', 'release', 'type', 'users')->find($id);
        $types = MagazineType::pluck('name', 'id');
        $releases = MagazineRelease::pluck('name', 'id');
        $countries = Country::pluck('name', 'code');
        //dd($magazine);
        return view('dashboard.magazine.create', compact('releases', 'types', 'magazine', 'countries'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
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

        if ($request->file('image-client')) :
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = \Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path() . '/images/encyclopedia/magazine/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path() . '/images/encyclopedia/magazine/thumbnails/';
            // Guardar Original
            $fileName = hash('sha256', str_slug($request['name']) . strval(time()));

            $watermark = \Image::make(public_path() . '/images/logo_homepage.png');

            $watermark->opacity(30);

            $image->insert($watermark, 'bottom-right', 10, 10);

            $image->save($originalPath . $fileName.'.jpg');

            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath . 'thumb-' . $fileName.'.jpg');

            $request['images'] = $fileName.'.jpg';
        endif;

        $request['user_id'] = \Auth::user()->id;
        $request['slug'] = str_slug($request['name']);
        /*if(Magazine::where('slug','like', $request['slug'])->count() > 0):
                        $request['slug'] = str_slug($request['name']).'-01';
        */

        if ($data->update($request->all())) :
            if ($request->file('image-client')) :
                $image = $data->image ?: MagazineImage::where('magazine_id', $id);
                $image->name = $request['images'];
                $data->image()->save($image);
            endif;
            \Alert::success('Revista Actualizada');
            return redirect()->to('dashboard/magazine');
        else :
            \Alert::error('No se ha podido guardar la Informacion Suministrada');
            return back();
        endif;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $magazine = Magazine::find($id);

        if ($magazine->delete()) :
            Alert::success('El Titulo se ha Eliminado satisfactoriamente');
            return back();
        else :
            Alert::error('El Post no se ha podido Eliminar');
            return back();
        endif;
    }

    public function name(Request $request, $name)
    {
    }
    public function slugs()
    {
        $slugs = \App\Company::all();
        foreach ($slugs as $s) :
            $s->slug = str_slug($s->name);
            $s->update();
            echo $s->slug . '<br>';
        endforeach;
    }
}
