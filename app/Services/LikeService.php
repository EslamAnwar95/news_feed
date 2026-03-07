<?php

namespace App\Services;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;

class LikeService
{
    /**
     * Like a post. Returns true if newly liked, false if already liked.
     */
    public function like(User $user, Post $post): bool
    {
        $like = Like::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        if ($like->wasRecentlyCreated) {
            $post->increment('likes_count');
            return true;
        }

        return false;
    }

    /**
     * Unlike a post. Returns true if unliked, false if was not liked.
     */
    public function unlike(User $user, Post $post): bool
    {
        $deleted = Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        if ($deleted > 0) {
            $post->decrement('likes_count');
            return true;
        }

        return false;
    }
}
