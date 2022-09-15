<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Http\Requests;
use App\Models\User;
use App\Models\Post;
use App\Models\Helper;

use App\Models\Title;
use App\Models\People;
use App\Models\Magazine;
use App\Models\Company;
use App\Models\Event;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use Image;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $users = User::with('roles')->where('remember_token', '!=', NULL)->paginate();
        return view('dashboard.users.home', compact('users'));
    }

    public function updateImage()
    {
        /* $users = User::all();

        foreach($users as $user) {

            $path = 'https://coanime.net/images/profiles/' . $user['image'];

            //dd($user);

            $user['image'] = $path;

            $user->save();
        }
        return 'Usuarios Actualizados'; */ }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    { }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $user = User::with('roles')->find($id);

        return $user->pivot()->role_id;
    }
   
    public function user(Request $request) {
        $id = Auth::user()->id;
        return User::find($id)->with('roles')->first();
    }

    public function me(Request $request)
    {
        $id = Auth::user()->id;
        
        if ($user = User::with('roles')->find($id)) {
            return response()->json(array(
                'code' => 200,
                'message' => Helper::successMessage('User found'),
                'title' => 'Coanime.net - Profile edition',
                'description' => 'This is to Edit the User Profile',
                'result' => $user,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => Helper::errorMessage('User not found'),
                'title' => 'Coanime.net - Profile',
                'description' => 'This is the User Profile',
                'result' => [],
            ), 404);
        }
    }

    public function updateMe(Request $request)
    {
        $user = User::find(Auth::user()->id);
        if (!empty($request->password)) {
            $request->validate([
                'password' => [
                    'required',
                    'string',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                    'confirmed'
                ],
            ]);
            $user->password = Hash::make($request->password);
        }
        $user->name = $request->name;
        $user->username = $request->username;
        $user->bio = $request->bio;
        $user->youtube = $request->youtube;
        $user->twitter = $request->twitter;
        $user->website = $request->website;
        $user->instagram = $request->instagram;
        $user->facebook = $request->facebook;
        $user->tiktok = $request->tiktok;
        $user->pinterest = $request->pinterest;
        if ($request->profile_photo_path) {
            $user->profile_photo_path = $request->profile_photo_path;
        }
        if ($request->profile_cover_path) {
            $user->profile_cover_path = $request->profile_cover_path;
        }
        try {
            if ($user->save()) {
                return response()->json(array(
                    'code' => 200,
                    'message' => Helper::successMessage('Your profile was updated successfully'),
                    'result' => $user,
                ), 200);
            } else {
                return response()->json(array(
                    'code' => 400,
                    'message' => Helper::errorMessage('Your profile was not updated'),
                    'result' => [],
                ), 400);
            }
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 403,
                'message' => Helper::errorMessage('Something went wrong '. $e->getMessage()),
                'result' => [],
            ), 403);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $user = User::find($id);

        return response()->json($user);
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
            'nick'          => 'required|max:255',
            'name'          => 'required|max:255',
            'bio'           => 'required|max:255',
            'facebook'      => 'max:255',
            'instagram'     => 'max:255',
            'twitter'       => 'max:255',
            'googleplus'    => 'max:255',
            'pinterest'     => 'max:255',
            'tumblr'        => 'max:255',
            'behance'       => 'max:255',
            'deviantart'    => 'max:255',
            'website'       => 'max:255',
            'genre'         => 'required',
            'birthday'      => 'date_format: "Y-m-d H:i:s"',
            'image-client'  => 'max:2048|mimes:jpeg,gif,bmp,png',
        ]);

        $data = User::find($id);
        $user = $request->all();
        /*$currentUser = \Auth::user()->id;
        $data['edited_by'] = $currentUser;
        /*$data['user_id'] = $currentUser;*/
        $user['slug'] = Str::slug($user['name']);

        if ($request->file('image-client')) {
            $file = $request->file('image-client');
            //Creamos una instancia de la libreria instalada
            $image = Image::make($request->file('image-client')->getRealPath());
            //Ruta donde queremos guardar las imagenes
            $originalPath = public_path() . '/images/profiles/';
            //Ruta donde se guardaran los Thumbnails
            $thumbnailPath = public_path() . '/images/profiles/';

            $fileName = hash('sha256', $data['slug'] . strval(time()));

            $image->save($originalPath . $fileName . '.jpg');
            // Cambiar de tamaño Tomando en cuenta el radio para hacer un thumbnail
            $image->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            // Guardar
            $image->save($thumbnailPath . 'thumb-' . $fileName . '.jpg');

            $data['image'] = 'https://coanime.net/images/profiles/' . $fileName . '.jpg';
        }

        if ($data->update($user)) {
            return response()->json(array(
                'status' => 'Success',
                'message' => 'Usuario actualizado correctamente',
                'data' => $data
            ), 200);
        } else {
            return response()->json(array(
                'status' => 'error',
                'message' => 'No se pudo Actualizar el Usuario',
                'data' => $data
            ), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    { }

    /**
     * Show the Profile page to Edit the profile user data
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function profile(Request $request, $slug = null, $id = null)
    {
        if (User::where('slug', '=', $slug)->pluck('id')->count() > 0) {
            $id = User::whereSlug($slug)->pluck('id')->first();
            $user = User::with('roles', 'posts', 'titles', 'people', 'magazine', 'companies', 'events')->find($id);
            
            return response()->json(array(
                'status' => 'Success',
                'message' => 'Usuario encontrado',
                'data' => $user
            ), 200);
            //return view('users.details', compact('user', 'carbon'));
        } else {
            return response()->json(array(
                'status' => 'error',
                'message' => 'Usuario no encontrado',
                'data' => $id
            ), 404);
        }
    }

    public function apiProfile(Request $request)
    {
        try {
            if (User::whereSlug($request->slug)->pluck('id')->count() > 0) {
                $id = User::whereSlug($request->slug)->pluck('id')->first();
                $user = User::with('roles')->find($id);

                return response()->json(array(
                    'code' => 200,
                    'message' => Helper::successMessage('User found'),
                    'title' => 'Coanime.net - Perfil - ' . $user->name,
                    'description' => 'Perfil de ' . $user->name . ' en Coanime.net',
                    'result' => $user
                ), 200);
                //return view('users.details', compact('user', 'carbon'));
            } else {
                return response()->json(array(
                    'code' => 404,
                    'message' => Helper::errorMessage('User not found'),
                    'title' => 'Coanime.net - Perfil no enontrado - ' . $request->slug,
                    'description' => 'No se consiguio el perfil de ' . $request->slug . ' en Coanime.net',
                    'result' => $request->slug
                ), 404);
            }
        } catch (\Exception $e) {
            return response()->json(array(
                'code' => 500,
                'message' => Helper::errorMessage('Internal Server Error, Error: ' . $e->getMessage()),
                'title' => 'Coanime.net - Error Interno',
                'description' => 'Error Interno en Coanime.net',
                'data' => $e
            ), 500);
        }
    }

    public function postsProfile(Request $request, $id)
    {

        if (Post::where('user_id', $id)->count() > 0) {
            $posts = Post::where('user_id', $id)/*->where('view_counter', '>', 300)*/->where('image', '!=', null)->where('image', '=', 'https://api.coanime.net/storage/images/posts/')->with('categories')->paginate();
            return response()->json(array(
                'code' => 200,
                'message' => Helper::successMessage('Posts founds'),
                'result' => $posts,
            ), 200);
        } else {
            return response()->json(array(
                'code' => 404,
                'message' => Helper::errorMessage('Posts not founds'),
                'result' => null,
            ), 404);
        }
    }

    public function titlesProfile(Request $request, $id)
    {

        if (Title::where('user_id', $id)->count() > 0) {
            $titles = Title::where('user_id', $id)->paginate(10);
            return response()->json(array(
                'message' => 'Success',
                'quantity' => $titles->count(),
                'data' => $titles,
            ), 200);
        } else {
            return response()->json(array(
                'message' => 'Not Found!'
            ), 404);
        }
    }

    public function eventsProfile(Request $request, $id)
    {
        if (Event::where('user_id', $id)->count() > 0) {
            $events = Event::where('user_id', $id)->paginate(10);
            return response()->json(array(
                'message' => 'Success',
                'quantity' => $events->count(),
                'data' => $events
            ), 200);
        } else {
            return response()->json(array(
                'message' => 'Not Found!'
            ), 404);
        }
    }

    public function peopleProfile(Request $request, $id)
    {
        if (People::where('user_id', $id)->count() > 0) {
            $people = People::where('user_id', $id)->paginate(10);

            return response()->json(array(
                'message' => 'Success',
                'quantity' => $people->count(),
                'data' => $people,
            ), 200);
        } else {
            return response()->json(array(
                'message' => 'Not Found!'
            ), 404);
        }
    }

    public function companiesProfile(Request $request, $id)
    {

        if (Company::where('user_id', $id)->count() > 0) {
            $companies = Company::where('user_id', $id)->paginate(10);

            return response()->json(array(
                'message' => 'Success',
                'quantity' => $companies->count(),
                'data' => $companies,
            ), 200);
        } else {
            return response()->json(array(
                'message' => 'Not Found!'
            ), 404);
        }
    }

    public function magazineProfile(Request $request, $id)
    {

        if (Magazine::where('user_id', $id)->count() > 0) {
            $magazine = Magazine::where('user_id', $id)->paginate(10);

            return response()->json(array(
                'message' => 'Success',
                'quantity' => $magazine->count(),
                'data' => $magazine,
            ), 200);
        } else {
            return response()->json(array(
                'message' => 'Not Found!'
            ), 404);
        }
    }
}
