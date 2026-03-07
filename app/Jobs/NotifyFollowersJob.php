<?php

namespace App\Jobs;

use App\Models\Post;
use App\Notifications\NewPostNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyFollowersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Post $post,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $author = $this->post->user;

        // Chunk followers to avoid memory issues at scale
        $author->followers()->chunk(500, function ($followers) {
            foreach ($followers as $follower) {
                $follower->notify(new NewPostNotification($this->post));
            }
        });
    }
}
