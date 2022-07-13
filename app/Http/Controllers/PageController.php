<?php

namespace App\Http\Controllers;

use Alert;
use App\Models\Category;
use App\Models\Event;
use App\Models\Company;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Title;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PageController extends Controller {

    public function index(Request $request, $id) {
        $page = '';

        return view('pages.detail', compact($page));
    }

}