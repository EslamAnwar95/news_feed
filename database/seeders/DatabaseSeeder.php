<?php

namespace Database\Seeders;

use App\Models\Follow;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates a realistic dataset for performance testing:
     * - 100 users, ~1000 follow relationships, ~500 posts, ~2000 likes
     */
    public function run(): void
    {
        // Create a known test user
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create additional users
        $users = User::factory(99)->create();
        $allUsers = $users->prepend($testUser);
        $userIds = $allUsers->pluck('id')->all();

        // Create follow relationships (~10 follows per user)
        $follows = [];
        foreach ($userIds as $followerId) {
            $toFollow = collect($userIds)
                ->reject(fn ($id) => $id === $followerId)
                ->random(min(10, count($userIds) - 1));

            foreach ($toFollow as $followingId) {
                $follows[] = [
                    'follower_id' => $followerId,
                    'following_id' => $followingId,
                    'created_at' => now(),
                ];
            }
        }
        // Insert in chunks to avoid memory issues
        foreach (array_chunk($follows, 500) as $chunk) {
            DB::table('follows')->insert($chunk);
        }

        // Create posts (~5 per user)
        $posts = Post::factory(500)->recycle($allUsers)->create();

        // Create likes (~4 per post on average)
        $likes = [];
        foreach ($posts as $post) {
            $likers = collect($userIds)->random(rand(0, 8));
            foreach ($likers as $likerId) {
                $likes[] = [
                    'user_id' => $likerId,
                    'post_id' => $post->id,
                    'created_at' => now(),
                ];
            }
        }
        foreach (array_chunk($likes, 500) as $chunk) {
            DB::table('likes')->insert($chunk);
        }

        // Update likes_count on posts
        DB::statement('UPDATE posts SET likes_count = (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id)');
    }
}
