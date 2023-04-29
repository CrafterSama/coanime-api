<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Title;
use App\Models\TitleType;

class EncyclopediaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $titles = Title::with('images', 'rating', 'type', 'genres')->orderBy('id', 'desc')->paginate(3);
        $people = People::orderBy('id', 'desc')->paginate(3);
        $magazine = Magazine::with('type', 'image', 'release')->orderBy('id', 'desc')->paginate(3);
        $companies = Company::orderBy('id', 'desc')->paginate(4);
        $types = TitleType::all();

        return response()->json([
            'title' => 'Enciclopedia',
            'description' => 'Enciclopedia de Referencia de Coanime.net',
            'titles' => $titles,
            'people' => $people,
            'magazine' => $magazine,
            'companies' => $companies,
            'types' => $types,
        ], 200);
    }
}
