<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request, $id)
    {
        $page = '';

        return view('pages.detail', compact($page));
    }
}
