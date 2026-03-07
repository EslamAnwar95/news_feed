<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FollowService
{
    public function __construct(
        protected FeedService $feedService,
    ) {}

    /**
     * Follow a user. Returns true if newly followed, false if already following.
     */
    public function follow(User $follower, User $target): bool
    {
        if ($follower->id === $target->id) {
            throw new \InvalidArgumentException('You cannot follow yourself.');
        }

        $created = Follow::firstOrCreate([
            'follower_id' => $follower->id,
            'following_id' => $target->id,
        ]);

        if ($created->wasRecentlyCreated) {
            $this->feedService->invalidateFollowingCache($follower);
            return true;
        }

        return false;
    }

    /**
     * Unfollow a user. Returns true if unfollowed, false if was not following.
     */
    public function unfollow(User $follower, User $target): bool
    {
        $deleted = Follow::where('follower_id', $follower->id)
            ->where('following_id', $target->id)
            ->delete();

        if ($deleted > 0) {
            $this->feedService->invalidateFollowingCache($follower);
            return true;
        }

        return false;
    }
}
