<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\Title;
use App\Models\People;
use App\Models\Magazine;
use App\Models\Company;
use Carbon\Carbon;
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
        $carbon     = new Carbon;
        $titles     = Title::with('images', 'rating', 'type', 'genres')->orderBy('id', 'desc')->paginate(3);
        $people     = People::orderBy('id', 'desc')->paginate(3);
        $magazine     = Magazine::with('type', 'image', 'release')->orderBy('id', 'desc')->paginate(3);
        $companies  = Company::orderBy('id', 'desc')->paginate(4);
        $types         = TitleType::all();
        //dd($titles);
        return view('encyclopedia.index', compact('people', 'magazine', 'titles', 'companies', 'types', 'carbon'));
    }

    public function api()
    {
        $titles     = Title::with('images', 'rating', 'type', 'genres')->orderBy('id', 'desc')->paginate(3);
        $people     = People::orderBy('id', 'desc')->paginate(3);
        $magazine     = Magazine::with('type', 'image', 'release')->orderBy('id', 'desc')->paginate(3);
        $companies  = Company::orderBy('id', 'desc')->paginate(4);
        $types         = TitleType::all();

        return response()->json(array(
            'title' => 'Enciclopedia de Referencia de Coanime.net',
            'titles' => $titles,
            'people' => $people,
            'magazine' => $magazine,
            'companies' => $companies,
            'types' => $types
        ), 200);
    }
}
