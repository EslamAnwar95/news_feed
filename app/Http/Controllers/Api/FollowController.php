<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(
        protected FollowService $followService,
    ) {}

    public function follow(Request $request, User $user): JsonResponse
    {
        try {
            $created = $this->followService->follow($request->user(), $user);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $created) {
            return response()->json(['message' => 'Already following this user.'], 409);
        }

        return response()->json(['message' => 'User followed successfully.'], 201);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $deleted = $this->followService->unfollow($request->user(), $user);

        if (! $deleted) {
            return response()->json(['message' => 'You are not following this user.'], 404);
        }

        return response()->json(['message' => 'User unfollowed successfully.']);
    }
}
