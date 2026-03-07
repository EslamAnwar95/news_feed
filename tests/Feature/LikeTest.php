<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_like_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/posts/{$post->id}/like");

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Post liked successfully.',
                'likes_count' => 1,
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_duplicate_like_returns_conflict(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/posts/{$post->id}/like");
        $response = $this->postJson("/api/posts/{$post->id}/like");

        $response->assertStatus(409);
    }

    public function test_user_can_unlike_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/posts/{$post->id}/like");
        $response = $this->deleteJson("/api/posts/{$post->id}/like");

        $response->assertOk()
            ->assertJson([
                'message' => 'Post unliked successfully.',
                'likes_count' => 0,
            ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_unlike_not_liked_post_returns_404(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/posts/{$post->id}/like");

        $response->assertStatus(404);
    }

    public function test_likes_count_is_accurate(): void
    {
        $post = Post::factory()->create();
        $users = User::factory()->count(5)->create();

        Sanctum::actingAs($users[0]);

        foreach ($users as $u) {
            Sanctum::actingAs($u);
            $this->postJson("/api/posts/{$post->id}/like");
        }

        $post->refresh();
        $this->assertEquals(5, $post->likes_count);
    }

    public function test_unauthenticated_user_cannot_like(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/like");

        $response->assertStatus(401);
    }
}
