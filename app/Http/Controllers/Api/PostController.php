<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        protected PostService $postService,
    ) {}

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Post created successfully.',
            'post' => new PostResource($post),
        ], 201);
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $post = $this->postService->findOrFail($post->id, $request->user());

        return response()->json([
            'post' => new PostResource($post),
        ]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->postService->delete($post, $request->user());

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }
}
