<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\People;
use App\Models\Helper;
use Illuminate\Http\Request;

class PeopleController extends Controller {

    /**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
        $people = People::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
		if ($people->count() > 0):
			return view('people.home', ['people' => $people]);
		else:
			return back()->with('errors', 'Error Trying to obtein the People Data');
		endif;

	}

    /**
	 * Display a listing of the resource in JSON format.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function apiIndex(Request $request) {
        $people = People::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
		if ($people->count() > 0):

            return response()->json(array(
                'message' => 'Resource found',
                'people' => $people
            ), 200);
		else:
            return response()->json(array(
                'message' => 'Resource not found',
                'people' => []
            ), 404);
		endif;

    }

    public function apiSearchPeople(Request $request) {
        $people = People::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
        if ($people->count() > 0) :
            return response()->json(array(
                'message' => 'Resource found',
                'people' => $people
            ), 200);
		else:
            return response()->json(array(
                'message' => 'Resource not found',
                'people' => []
            ), 404);
		endif;
    }

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create() {
		$person = new People;
		$falldown = ['no' => 'No', 'si' => 'Si'];
		$cities = City::pluck('name', 'id');
		$countries = Country::pluck('name', 'code');
		return view('dashboard.people.create', compact('person', 'falldown', 'cities', 'countries'));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		if (People::where('name', 'like', $request->get('name'))->where('birthday', '=', $request->get('birthday'))->count() > 0):
			\Alert::error('La serie que trata de guardar ya esta en nuestros archivos');
			return back();
		else:
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
				$request['birthday'] = NULL;
			}

			if (empty($request['falldown_date'])) {
				$request['falldown_date'] = NULL;
			}

			$data = new People;

			$request['user_id'] = \Auth::user()->id;
			$request['slug'] = str_slug($request['name']);
			if (People::where('slug', 'like', $request['slug'])->count() > 0):
				$request['slug'] = str_slug($request['name']) . '1';
			endif;

			if ($request->file('image-client')):
				$file = $request->file('image-client');
				//Creamos una instancia de la libreria instalada
				$image = \Image::make($request->file('image-client')->getRealPath());
				//Ruta donde queremos guardar las imagenes
				$originalPath = public_path() . '/images/encyclopedia/people/';
				//Ruta donde se guardaran los Thumbnails
				$thumbnailPath = public_path() . '/images/encyclopedia/people/thumbnails/';
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

				$request['image'] = $fileName.'.jpg';
			else:
				$request['image'] = NULL;
			endif;

			//dd($data);

			if ($data = People::create($request->all())):
				\Alert::success('Persona Agregada');
				return redirect()->to('dashboard/people');
			else:
				\Alert::error('No se ha podido guardar la Informacion Suministrada');
				return back();
			endif;
		endif;
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($slug) {
		if (People::where('slug', '=', $slug)->count() > 0):
			$people = People::with('city', 'country')->whereSlug($slug)->firstOrFail();

			//dd($people);
			return view('people.details', ['people' => $people]);
		else:
			return view('errors.404');
		endif;
    }

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function apiShow($slug) {
		if (People::where('slug', '=', $slug)->count() > 0):
            $people = People::with('city', 'country')->whereSlug($slug)->firstOrFail();
            $people->bio = Helper::parseBBCode($people->bio);

            return response()->json(array(
                'message' => 'Resource found',
                'person' => $people
            ), 200);
        else :
            return response()->json(array(
                'message' => 'Resource not found',
                'person' => null
            ), 404);
        endif;
    }

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit(Request $request, $id) {
		$person = People::find($id);
		$falldown = ['no' => 'No', 'si' => 'Si'];
		$cities = City::pluck('name', 'id');
		$countries = Country::pluck('name', 'code');
		return view('dashboard.people.create', compact('person', 'falldown', 'countries', 'cities'));
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {
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
			$request['falldown_date'] = NULL;
		}

		$request['user_id'] = \Auth::user()->id;
		$request['slug'] = str_slug($request['name']);



		if ($request->file('image-client')):
			$file = $request->file('image-client');
			//Creamos una instancia de la libreria instalada
			$image = \Image::make($request->file('image-client')->getRealPath());
			//Ruta donde queremos guardar las imagenes
			$originalPath = public_path() . '/images/encyclopedia/people/';
			//Ruta donde se guardaran los Thumbnails
			$thumbnailPath = public_path() . '/images/encyclopedia/people/thumbnails/';
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

			$request['image'] = $fileName.'.jpg';
		endif;

		if ($data->update($request->all())):
			\Alert::success('Persona Actualizada');
			return redirect()->to('dashboard/people');
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
	public function destroy($id, Request $request) {
		$people = People::find($id);

		if ($people->delete()):
			\Alert::success('El Titulo se ha Eliminado satisfactoriamente');
			return back();
		else:
			\Alert::error('El Post no se ha podido Eliminar');
			return back();
		endif;
	}

	public function name(Request $request, $name) {

	}
	public function slugs() {
		$people = People::all();
		foreach ($people as $p):
			if (is_null($p->falldown_date) || empty($p->falldown_date)):
				echo $p->id . ' - No Paso Nada<br>';
			else:
				if (preg_match("/([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{2,4})/", $p->falldown_date, $newBT)):
					$newBroadTime = $newBT[3] . "-" . $newBT[2] . "-" . $newBT[1] . ' 00:00:00';
					$p->falldown_date = $newBroadTime;
					$p->update();
					echo $p->id . ' -> ' . $p->falldown_date . ' -> Cambio Listo<br>';
				else:
					$newBroadTime = $p->falldown_date . ' 00:00:00';
					$p->falldown_date = $newBroadTime;
					$p->update();
					echo $p->id . ' -> ' . $p->falldown_date . ' -> Cambio Listo<br>';
				endif;
			endif;
		endforeach;
		/*$people = People::all();
					$magazine = Magazine::all();
					$events = Event::all();
					$titles = Title::all();
					$companies = Company::all();

			        /*foreach($people as $p):
			            $p->name = $p->first_name. ' ' .$p->last_name;
						$p->slug = str_slug($p->first_name. ' ' .$p->last_name);
			            $p->update();
			            echo 'People -> '.$p->name.'<br>'.$p->slug.'<br>';
			        endforeach;

			        foreach($magazine as $mgz):
						$mgz->slug = str_slug($mgz->name);
			            $mgz->update();
			            echo 'Magazine -> '.$mgz->slug.'<br>';
			        endforeach;

			        foreach($events as $event):
						$event->slug = str_slug($event->name);
			            $event->update();
			            echo 'Events -> '.$event->slug.'<br>';
			        endforeach;

			        foreach($titles as $title):
						$title->slug = str_slug($title->name);
			            $title->update();
			            echo 'Titles -> '.$title->slug.'<br>';
			        endforeach;

			        foreach($companies as $company):
						$company->slug = str_slug($company->name);
			            $company->update();
			            echo 'Companies -> '.$company->slug.'<br>';
		*/

	}
}
