<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_another_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/follow/{$target->id}");

        $response->assertStatus(201)
            ->assertJson(['message' => 'User followed successfully.']);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $user->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_user_cannot_follow_self(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/follow/{$user->id}");

        $response->assertStatus(422);
    }

    public function test_duplicate_follow_returns_conflict(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/follow/{$target->id}");
        $response = $this->postJson("/api/follow/{$target->id}");

        $response->assertStatus(409);
    }

    public function test_user_can_unfollow(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/follow/{$target->id}");
        $response = $this->deleteJson("/api/follow/{$target->id}");

        $response->assertOk()
            ->assertJson(['message' => 'User unfollowed successfully.']);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $user->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_unfollow_non_followed_user_returns_404(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/follow/{$target->id}");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_follow(): void
    {
        $target = User::factory()->create();

        $response = $this->postJson("/api/follow/{$target->id}");

        $response->assertStatus(401);
    }
}
