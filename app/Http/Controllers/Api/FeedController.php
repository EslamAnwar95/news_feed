<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Services\FeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function __construct(
        protected FeedService $feedService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $feed = $this->feedService->getFeed(
            $request->user(),
            (int) $request->query('per_page', 20)
        );

        return PostResource::collection($feed);
    }
}
