<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\TripPost;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    // POST /api/posts/{post}/like
    public function toggle(Request $request, TripPost $post)
{
    $userId = $request->user()->id;

    $like = Like::where('user_id', $userId)
        ->where('trip_post_id', $post->id)
        ->first();

    if ($like) {
        $like->delete();
        $count = Like::where('trip_post_id', $post->id)->count();

        return response()->json(['liked' => false, 'likes_count' => $count]);
    }

    Like::create([
        'user_id' => $userId,
        'trip_post_id' => $post->id,
    ]);

    $count = Like::where('trip_post_id', $post->id)->count();

    return response()->json(['liked' => true, 'likes_count' => $count]);
}

}
