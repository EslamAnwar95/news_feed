<?php

namespace App\Services;

use App\Jobs\NotifyFollowersJob;
use App\Models\Post;
use App\Models\User;

class PostService
{
    /**
     * Create a new post and dispatch follower notifications.
     */
    public function create(User $user, array $data): Post
    {
        $post = $user->posts()->create($data);

        NotifyFollowersJob::dispatch($post);

        return $post->load('user:id,name,email');
    }

    /**
     * Get a single post with user and like info for the authenticated user.
     */
    public function findOrFail(int $postId, ?User $authUser = null): Post
    {
        $query = Post::with('user:id,name,email');

        if ($authUser) {
            $query->selectRaw('posts.*, EXISTS(SELECT 1 FROM likes WHERE likes.post_id = posts.id AND likes.user_id = ?) as is_liked', [$authUser->id]);
        }

        return $query->findOrFail($postId);
    }

    /**
     * Delete a post (only by its owner).
     */
    public function delete(Post $post, User $user): void
    {
        if ($post->user_id !== $user->id) {
            abort(403, 'You can only delete your own posts.');
        }

        $post->delete();
    }
}
