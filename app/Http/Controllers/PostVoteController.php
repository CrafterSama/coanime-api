<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PostVote;
use Illuminate\Http\Request;

class PostVoteController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function vote(Request $request)
    {
        $data = $request->all();
        //dd($data);
        $postVote = PostVote::where('post_id', $data['post_id'])->where('user_id', $data['user_id'])->get();

        if (count($postVote->pluck('id')) > 0) {
            $vote = PostVote::find($postVote->pluck('id'));
            if ($data['status'] != $vote['status']) {
                $vote->status = $data['status'];
                $vote->save();

                return response()->json(['success' => true, 'data' => $vote], 201);
            } else {
                return response()->json(['success' => false, 'message' => 'No se puede votar dos veces'], 200);
            }
        } else {
            if ($data = PostVote::create($data)) {
                return response()->json(['success' => true, 'data' => $data], 201);
            }
        }
    }
}
