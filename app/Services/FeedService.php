<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;

class FeedService
{
    /**
     * Get the feed for a user (posts from followed users), with caching.
     */
    public function getFeed(User $user, int $perPage = 20): CursorPaginator
    {
        $followingIds = $this->getFollowingIds($user);

        if (empty($followingIds)) {
            return Post::whereRaw('1 = 0')->cursorPaginate($perPage);
        }

        return Post::whereIn('user_id', $followingIds)
            ->with('user:id,name,email')
            ->selectRaw('posts.*, EXISTS(SELECT 1 FROM likes WHERE likes.post_id = posts.id AND likes.user_id = ?) as is_liked', [$user->id])
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);
    }

    /**
     * Get cached list of user IDs that the given user follows.
     */
    public function getFollowingIds(User $user): array
    {
        return Cache::remember(
            "user:{$user->id}:following_ids",
            now()->addMinutes(10),
            fn () => $user->following()->pluck('users.id')->all()
        );
    }

    /**
     * Invalidate the feed-related caches for a user.
     */
    public function invalidateFollowingCache(User $user): void
    {
        Cache::forget("user:{$user->id}:following_ids");
    }
}
