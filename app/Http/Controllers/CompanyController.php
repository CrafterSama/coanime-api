<?php

namespace App\Http\Controllers;

use Exception;

use App\Models\Company;
use App\Models\Country;

use Illuminate\Http\Request;

use UxWeb\SweetAlert\SweetAlert as Alert;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function index(Request $request)
    {
        $companies = Company::search($request->name)->orderBy('name', 'asc')->paginate(30);
        if ($companies->count() > 0) :
            return view('companies.home', compact('companies'));
        else :
            return view('errors.404');
        endif;


    }

    /**
     * Display a listing of the resource in JSON format.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function apiIndex(Request $request)
    {
        $companies = Company::search($request->name)->orderBy('name', 'asc')->paginate(30);

        if ($companies->count() > 0) :
            return response()->json(array(
                'message' => 'Resource found',
                'companies' => $companies
            ), 200);
        else :
            return response()->json(array(
                'message' => 'Resource not found',
                'companies' => []
            ), 404);
        endif;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function create()
    {
        $company = new Company;
        return view('dashboard.companies.create', compact('company'));
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
            'country_code' => 'required',
            'website' => 'required',
            'foundation_date' => 'date_format:"Y-m-d H:i:s"',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        if (Company::where('slug', '=', str_slug($request->get('name')))->count() > 0) {
            \Alert::error('La Compañia que trata de agregar ya se encuentra en el sistema');
            return back();
        } else {
            $data = new Company;
            $request['user_id'] = \Auth::user()->id;
            $request['slug'] = str_slug($request['name']);
            if (Company::where('slug', 'like', $request['slug'])->count() > 0) :
                $request['slug'] = str_slug($request['name']) . '1';
            endif;

            if ($request->file('image-client')) :
                $file = $request->file('image-client');
                //Creamos una instancia de la libreria instalada
                $image = \Image::make($request->file('image-client')->getRealPath());
                //Ruta donde queremos guardar las imagenes
                $originalPath = public_path() . '/images/encyclopedia/companies/';
                //Ruta donde se guardaran los Thumbnails
                $thumbnailPath = public_path() . '/images/encyclopedia/companies/thumbnails/';

                $fileName = hash('sha256', $data['slug'] . strval(time()));

                $watermark = \Image::make(public_path() . '/images/logo_homepage.png');

                $watermark->opacity(30);

                $image->insert($watermark, 'bottom-right', 10, 10);

                // Guardar Original
                $image->save($originalPath . $fileName . '.jpg');
                // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
                $image->resize(300, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
                // Guardar
                $image->save($thumbnailPath . 'thumb-' . $fileName . '.jpg');

                $request['image'] = $fileName . '.jpg';
            else :
                //$request['image'] = NULL;
            endif;

            //dd($data);

            if ($data = Company::create($request->all())) :
                \Alert::success('Empresa Agregada');
                return redirect()->to('dashboard/companies');
            else :
                \Alert::error('No se ha podido guardar la Informacion Suministrada');
                return back();
            endif;
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function show($slug)
    {
        $company = Company::with('country')->whereSlug($slug)->firstOrFail();

        if ($company->count() > 0):
            return view('companies.details', compact('company'));
        else:
            return view('errors.404');
        endif;
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function apiShow($slug)
    {
        $company = Company::with('country')->whereSlug($slug)->firstOrFail();

        if ($company->count() > 0) :
            return response()->json(array(
                'message' => 'Resource found',
                'company' => $company
            ), 200);
        else :
            return response()->json(array(
                'message' => 'Resource not found',
                'company' => []
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
        $company = Company::find($id);
        $countries = Country::pluck('name', 'code');
        return view('dashboard.companies.create', compact('company', 'countries'));
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
            'country_code' => 'required',
            'website' => 'required',
            'foundation_date' => 'date_format:"Y-m-d H:i:s"',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $data = Company::find($id);
        $request['user_id'] = \Auth::user()->id;
        $request['slug'] = str_slug($request['name']);
        $request['edited_by'] = \Auth::user()->id;

        if ($request->file('image-client')) :
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = \Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path() . '/images/encyclopedia/companies/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path() . '/images/encyclopedia/companies/thumbnails/';
            // Guardar Original
            $fileName = hash('sha256', $data['slug'] . strval(time()));

            $watermark = \Image::make(public_path() . '/images/logo_homepage.png');

            $watermark->opacity(30);

            $image->insert($watermark, 'bottom-right', 10, 10);

            $image->save($originalPath . $fileName . '.jpg');
            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath . 'thumb-' . $fileName . '.jpg');

            $request['image'] = $fileName . '.jpg';
        else :
            //$request['image'] = NULL;
        endif;

        //dd($data);

        if ($data->update($request->all())) :
            \Alert::success('Empresa Actualizada');
            return redirect()->to('dashboard/companies');
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
    }

    public function name(Request $request, $name)
    {
    }
}
