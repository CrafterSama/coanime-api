<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use App\Models\Helper;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function index(Request $request)
    {
        $companies = Company::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);
        if ($companies->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage(),
                'title' => 'Coanime.net - Entidades',
                'description' => 'Lista de Entidades relacionadas con el medio en Coanime.net',
                'result' => $companies,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => Helper::errorMessage(),
                'title' => 'Coanime.net - Entidades',
                'description' => 'Lista de Entidades relacionadas con el medio en Coanime.net',
                'result' => [],
            ], 404);
        }
    }

    /**
     * Display a listing of the resource in JSON format.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function apiIndex(Request $request)
    {
        $companies = Company::search($request->name)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);

        if ($companies->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage(),
                'title' => 'Coanime.net - Entidades',
                'description' => 'Lista de Entidades en la enciclopedia relacionadas al mundillo de la produccion de entretenimiento en Asia',
                'result' => $companies,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => Helper::errorMessage(),
                'title' => 'Coanime.net - Entidades',
                'description' => 'Lista de Entidades en la enciclopedia relacionadas al mundillo de la produccion de entretenimiento en Asia',
                'result' => [],
            ], 404);
        }
    }

    /**
     * Display a listing of the resource in JSON format.
     *
     * @return \Illuminate\Http\Response|mixed
     */
    public function apiIndexByCountry(Request $request, $slug)
    {
        $country = Country::where('name', ucfirst($slug))->first()->iso3;
        $companies = Company::search($request->name)->where('country_code', $country)->with('country', 'city')->orderBy('name', 'asc')->paginate(30);

        if ($companies->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => Helper::successMessage(),
                'title' => 'Coanime.net - Entidades',
                'description' => 'Lista de Entidades en la enciclopedia relacionadas al mundillo de la produccion de entretenimiento en Asia',
                'result' => $companies,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => Helper::errorMessage(),
                'title' => 'Coanime.net - Entidades',
                'description' => 'Lista de Entidades en la enciclopedia relacionadas al mundillo de la produccion de entretenimiento en Asia',
                'result' => [],
            ], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
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
        ]);

        if (Company::where('slug', '=', Str::slug($request->get('name')))->count() > 0) {
            return response()->json([
                'code' => 409,
                'message' => Helper::errorMessage('There is already a company with this name'),
            ], 409);
        } else {
            $data = new Company();
            $request['user_id'] = Auth::user()->id;
            $request['slug'] = Str::slug($request['name']);

            if (Company::where('slug', 'like', $request['slug'])->count() > 0) {
                $request['slug'] = Str::slug($request['name']).'1';
            }

            try {
                $data = Company::create($request->all());

                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage('Entity created successfully'),
                    'result' => $data,
                ], 200);
            } catch (Exception $e) {
                return response()->json([
                    'code' => 500,
                    'message' => Helper::errorMessage($e->getMessage()),
                    'result' => [],
                ], 500);
            }
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

        try {
            if ($company->count() > 0) {
                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage(),
                    'title' => 'Coanime.net - Entidades - '.$company->name.'',
                    'description' => 'Información acerca de la Entidad '.$company->name.' en Coanime.net',
                    'result' => $company,
                ], 200);
            } else {
                return response()->json([
                    'code' => 404,
                    'message' => Helper::errorMessage(),
                    'title' => 'Coanime.net - Entidades',
                    'description' => 'La Entidad que busca no esta disponible en Coanime.net',
                    'result' => [],
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => Helper::errorMessage($e->getMessage()),
                'result' => [],
            ], 500);
        }
    }

    public function apiShow($slug)
    {
        $company = Company::with('country')->whereSlug($slug)->firstOrFail();

        try {
            if ($company->count() > 0) {
                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage(),
                    'title' => 'Coanime.net - Entidades - '.$company->name.'',
                    'description' => 'Información acerca de la Entidad '.$company->name.' en Coanime.net',
                    'result' => $company,
                ], 200);
            } else {
                return response()->json([
                    'code' => 404,
                    'message' => Helper::errorMessage(),
                    'title' => 'Coanime.net - Entidades',
                    'description' => 'La Entidad que busca no esta disponible en Coanime.net',
                    'result' => [],
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => Helper::errorMessage($e->getMessage()),
                'result' => [],
            ], 500);
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
        $this->validate($request, [
            'name' => 'required',
            'about' => 'required',
            'country_code' => 'required',
            'website' => 'required',
            'foundation_date' => 'date_format:"Y-m-d H:i:s"',
            'image-client' => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $data = Company::find($id);
        $request['user_id'] = $data->user_id;
        $request['slug'] = Str::slug($request['name']);
        $request['edited_by'] = Auth::user()->id;

        try {
            if ($data->update($request->all())) {
                return response()->json([
                    'code' => 200,
                    'message' => Helper::successMessage('Entity updated successfully'),
                    'result' => $data,
                ], 200);
            } else {
                return response()->json([
                    'code' => 500,
                    'message' => Helper::errorMessage(),
                    'result' => [],
                ], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => Helper::errorMessage($e->getMessage()),
                'result' => [],
            ], 500);
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
    }
}
