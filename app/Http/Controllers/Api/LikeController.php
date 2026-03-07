<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\LikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct(
        protected LikeService $likeService,
    ) {}

    public function like(Request $request, Post $post): JsonResponse
    {
        $created = $this->likeService->like($request->user(), $post);

        if (! $created) {
            return response()->json(['message' => 'Already liked this post.'], 409);
        }

        return response()->json([
            'message' => 'Post liked successfully.',
            'likes_count' => $post->fresh()->likes_count,
        ], 201);
    }

    public function unlike(Request $request, Post $post): JsonResponse
    {
        $deleted = $this->likeService->unlike($request->user(), $post);

        if (! $deleted) {
            return response()->json(['message' => 'You have not liked this post.'], 404);
        }

        return response()->json([
            'message' => 'Post unliked successfully.',
            'likes_count' => $post->fresh()->likes_count,
        ]);
    }
}
