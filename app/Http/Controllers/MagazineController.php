<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Magazine;
use App\Models\MagazineImage;
use App\Models\MagazineType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MagazineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function index(Request $request)
    {
        $query = Magazine::search($request->name)->with('type', 'image', 'release', 'country');

        // Filters
        if ($request->has('type_id') && $request->type_id) {
            $query->where('magazine_type_id', $request->type_id);
        }

        if ($request->has('release_id') && $request->release_id) {
            $query->where('release_id', $request->release_id);
        }

        if ($request->has('country_code') && $request->country_code) {
            $query->where('country_code', $request->country_code);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'asc';
        
        // Allowed sort columns
        $allowedSortColumns = ['name', 'created_at', 'updated_at', 'id', 'foundation_date'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 30);
        $perPage = min(max((int) $perPage, 1), 100); // Between 1 and 100

        $magazine = $query->paginate($perPage);

        // Get filter options (solo si se solicita con ?include_filters=1)
        if ($request->get('include_filters')) {
            $types = \App\Models\MagazineType::orderBy('name', 'asc')->get();
            $releases = \App\Models\MagazineRelease::orderBy('name', 'asc')->get();
            $countries = \App\Models\Country::whereHas('magazines')->orderBy('name', 'asc')->get(['iso3 as id', 'name']);

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $magazine,
                'filters' => [
                    'types' => $types,
                    'releases' => $releases,
                    'countries' => $countries,
                ],
            ], 200);
        }

        if ($magazine->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin Resultados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function apiIndex(Request $request)
    {
        $magazine = Magazine::search($request->name)->with('type', 'image', 'release', 'country')->orderBy('name', 'asc')->paginate(30);
        if ($magazine->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin Resultados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display a listing of the resource by slug.
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function apiIndexByDemography(Request $request, $slug)
    {
        $type = MagazineType::where('slug', $slug)->first()->id;
        $magazine = Magazine::search($request->name)->where('type_id', $type)->with('type', 'image', 'release', 'country')->orderBy('name', 'asc')->paginate(30);
        if ($magazine->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Resultados encontrados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Sin Resultados',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
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

        $data = new Magazine();

        $request['slug'] = Str::slug($request['name']);
        $request['user_id'] = Auth::user()->id;

        if (Magazine::where('slug', 'like', $request['slug'])->count() > 0) {
            $request['slug'] = Str::slug($request['name']).'-01';
        }

        // Remove images from data array - we'll handle it separately with Media Library
        $data = $request->all();
        unset($data['images']);
        unset($data['image-client']);

        if ($data = Magazine::create($data)) {
            // Handle image upload via Media Library
            if ($request->hasFile('image-client')) {
                $data->addMediaFromRequest('image-client')
                    ->usingName("Magazine {$data->id} - {$data->name}")
                    ->toMediaCollection('cover');
            }

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista creada correctamente',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
                'result' => $data,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al crear la revista',
                ],
                'title' => 'Coanime.net - Lista de Revistas',
                'description' => 'Lista de revistas en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($slug)
    {
        $mgz = Magazine::with('image', 'type', 'release')
            ->whereSlug($slug)
            ->firstOrFail();

        if ($mgz->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $mgz,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Revista no encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiShow($slug)
    {
        $mgz = Magazine::with('image', 'type', 'release', 'country')
            ->whereSlug($slug)
            ->firstOrFail();
        if ($mgz->count() > 0) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $mgz,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Revista no encontrada',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
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

        // Handle image separately for Media Library
        $updateData = $request->all();
        unset($updateData['images']);
        unset($updateData['image-client']);

        $request['user_id'] = Auth::user()->id;
        $request['slug'] = Str::slug($request['name']);

        if ($data->update($updateData)) {
            // Handle image upload via Media Library
            if ($request->hasFile('image-client')) {
                // Remove old image
                $data->clearMediaCollection('cover');
                
                // Add new image
                $data->addMediaFromRequest('image-client')
                    ->usingName("Magazine {$data->id} - {$data->name}")
                    ->toMediaCollection('cover');
            }

            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista actualizada correctamente',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $data,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al actualizar la revista',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
            ], 404);
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
        $magazine = Magazine::find($id);

        if ($magazine->delete()) {
            return response()->json([
                'code' => 200,
                'message' => [
                    'type' => 'success',
                    'text' => 'Revista eliminada correctamente',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
                'result' => $magazine,
            ], 200);
        } else {
            return response()->json([
                'code' => 404,
                'message' => [
                    'type' => 'error',
                    'text' => 'Error al eliminar la revista',
                ],
                'title' => 'Coanime.net - Revista',
                'description' => 'Revista en la enciclopedia de coanime.net',
            ], 404);
        }
    }

    public function slugs()
    {
        $slugs = \App\Models\Company::all();
        foreach ($slugs as $s) {
            $s->slug = Str::slug($s->name);
            $s->update();
            echo $s->slug.'<br>';
        }
    }
}
