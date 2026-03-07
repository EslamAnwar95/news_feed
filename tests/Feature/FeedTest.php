<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_returns_posts_from_followed_users(): void
    {
        $user = User::factory()->create();
        $followed = User::factory()->create();
        $notFollowed = User::factory()->create();

        // Follow one user
        $user->following()->attach($followed->id);

        // Create posts
        $visiblePost = Post::factory()->create(['user_id' => $followed->id, 'content' => 'Visible']);
        Post::factory()->create(['user_id' => $notFollowed->id, 'content' => 'Hidden']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/feed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Visible');
    }

    public function test_feed_is_sorted_newest_first(): void
    {
        $user = User::factory()->create();
        $followed = User::factory()->create();
        $user->following()->attach($followed->id);

        $old = Post::factory()->create([
            'user_id' => $followed->id,
            'content' => 'Old post',
            'created_at' => now()->subHours(2),
        ]);
        $new = Post::factory()->create([
            'user_id' => $followed->id,
            'content' => 'New post',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/feed');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($new->id, $data[0]['id']);
        $this->assertEquals($old->id, $data[1]['id']);
    }

    public function test_feed_includes_is_liked_flag(): void
    {
        $user = User::factory()->create();
        $followed = User::factory()->create();
        $user->following()->attach($followed->id);

        $post = Post::factory()->create(['user_id' => $followed->id]);
        $post->likes()->create(['user_id' => $user->id]);
        $post->increment('likes_count');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/feed');

        $response->assertOk()
            ->assertJsonPath('data.0.is_liked', true)
            ->assertJsonPath('data.0.likes_count', 1);
    }

    public function test_feed_is_paginated(): void
    {
        $user = User::factory()->create();
        $followed = User::factory()->create();
        $user->following()->attach($followed->id);

        Post::factory()->count(25)->create(['user_id' => $followed->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/feed?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_empty_feed_when_not_following_anyone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/feed');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_user_cannot_access_feed(): void
    {
        $response = $this->getJson('/api/feed');

        $response->assertStatus(401);
    }
}
