<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Event;
use App\Models\Country;
use App\Models\City;
use App\Models\CountryLanguage;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if(Event::search($request->name)->with('users','city','country')->orderBy('date_start','asc')->paginate()->count() > 0):

            $events = Event::search($request->name)->with('users','city','country')->orderBy('date_start','asc')->simplePaginate();

            return view('events.home', compact('events'));
        else:
            return back()->with('errors', 'Error Trying to obtein the Event Data');
        endif;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $event = New Event;
        $countries = Country::all();
        return view('dashboard.events.create', compact('event','countries'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'name' => 'required',
            'address' => 'required',
            'description' => 'required',
            'country_code' => 'required',
            'city_id' => 'required',
            'date_start' => 'required',
            'date_end' => 'required',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $data = new Event;
        $request['user_id'] = \Auth::user()->id;
        $request['slug'] = str_slug($request['name']);
        if(Event::where('slug','like',$request['slug'])->count() > 0):
            $request['slug'] = str_slug($request['name']).'1';
        endif;

        if($request->file('image-client')):
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = \Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path().'/images/events/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path().'/images/events/thumbnails/';
            // Guardar Original
            $fileName = hash('sha256', $data['slug'] . strval(time()));

            $watermark = \Image::make(public_path() . '/images/logo_homepage.png');

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
        else:
            $request['image'] = NULL;
        endif;

        //dd($data);

        if($data = Event::create($request->all())):
            \Alert::success('Evento Agregado');
            return redirect()->to('dashboard/events');
        else:
            \Alert::error('No se ha podido guardar la Informacion Suministrada');
            return back();
        endif;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
		$id = Event::where('slug','like',$slug)->pluck('id');
		$event = Event::with('users')->find($id);

		return view('events.details', compact('event'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $event = Event::find($id);
        $cities = City::pluck('name','id');
        $countries = Country::pluck('name','code');
        return view('dashboard.events.create', compact('event','cities','countries'));
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
        $data = Event::find($id);

        $this->validate($request,[
            'name' => 'required',
            'address' => 'required',
            'description' => 'required',
            'country_code' => 'required',
            'city_id' => 'required',
            'date_start' => 'required',
            'date_end' => 'required',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $request['user_id'] = \Auth::user()->id;
        $request['slug']    = str_slug($request['name']);

        if($request->file('image-client')):
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = \Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path().'/images/events/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path().'/images/events/thumbnails/';
            // Guardar Original
            $fileName = hash('sha256', str_slug($request['name']) . strval(time()));

            $watermark = \Image::make(public_path() . '/images/logo_homepage.png');

            $watermark->opacity(30);

            $image->insert($watermark, 'bottom-right', 10, 10);

            $image->encode('jpg', 95);

            $image->save($originalPath.$fileName.'.jpg');
            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath.'thumb-'.$fileName.'.jpg');

            $request['image'] = $fileName.'.jpg';
        endif;

        if($data->update($request->all())):
            \Alert::success('Evento Actualizado');
            return redirect()->to('dashboard/events');
        else:
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
        $event = Event::find($id);

		if ($event->delete()):
			\Alert::success('El Post se ha Eliminado satisfactoriamente');
			return back();
		else:
			\Alert::error('El Post no se ha podido Eliminar');
			return back();
		endif;
    }

    public function name(Request $request, $name)
    {

    }
}
