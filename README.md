# High-Traffic News Feed API

A scalable Twitter/Instagram-style news feed API built with Laravel 12, designed for high-traffic scenarios (5M users, 500K posts/day). Features include user authentication, follow system, post CRUD, feed generation, likes, queued notifications, and Redis caching.

## Tech Stack

- **Framework:** Laravel 12 (PHP 8.2)
- **Database:** MySQL 8.0
- **Cache & Queue:** Redis (via Predis)
- **Auth:** Laravel Sanctum (token-based)
- **Containerization:** Docker (PHP-FPM + Nginx + MySQL + Redis + Queue Worker)

---

## Setup Instructions

### Docker (Recommended)

```bash
# 1. Clone and enter the project
git clone <repo-url> && cd news_feed

# 2. Copy environment file
cp .env.example .env

# 3. Update .env for Docker networking
#    DB_HOST=db
#    REDIS_HOST=redis

# 4. Start all services
docker-compose up -d --build

# 5. Run setup inside the app container
docker exec -it laravel-app composer setup

# 6. (Optional) Seed test data
docker exec -it laravel-app php artisan db:seed

# 7. Access phpMyAdmin (database UI)
# Add to docker-compose.yml under services:
#   phpmyadmin:
#     image: phpmyadmin/phpmyadmin
#     container_name: laravel-phpmyadmin
#     ports:
#       - "8080:80"
#     environment:
#       PMA_HOST: db
#       PMA_USER: sail
#       PMA_PASSWORD: password
#     depends_on:
#       - db
#     networks:
#       - laravel-network
# Then visit: http://localhost:8080
```

### Local (Laragon / Valet)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed          # optional
php artisan serve
php artisan queue:work redis --queue=notifications,default
```

### Running Tests

```bash
php artisan test
# or
vendor/bin/phpunit
```

---

## API Documentation

All API routes are prefixed with `/api`. Authenticated routes require a `Bearer` token in the `Authorization` header.

### Authentication

| Method | Endpoint     | Description   | Auth |
|--------|-------------|---------------|------|
| POST   | `/register` | Create user   | No   |
| POST   | `/login`    | Login & get token | No |

**Register:** `POST /api/register`
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Login:** `POST /api/login`
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

Response includes a `token` field — use it as `Authorization: Bearer <token>`.

### Follow System

| Method | Endpoint              | Description                  |
|--------|-----------------------|------------------------------|
| GET    | `/users/suggestions`  | Get suggested users to follow |
| POST   | `/follow/{user_id}`   | Follow user                  |
| DELETE | `/follow/{user_id}`   | Unfollow user                |

**Get Suggestions:** `GET /api/users/suggestions?limit=10`
- Returns users the authenticated user is NOT following
- Query param: `limit` (default: 10, max: 50)
- Response includes `id`, `name`, `email`, `created_at`

### Posts

| Method | Endpoint      | Description  |
|--------|--------------|--------------|
| POST   | `/posts`     | Create post  |
| GET    | `/posts/{id}`| View post    |
| DELETE | `/posts/{id}`| Delete post  |

**Create Post:** `POST /api/posts`
```json
{ "content": "Hello world!" }
```

### Feed

| Method | Endpoint | Description |
|--------|---------|-------------|
| GET    | `/feed` | Get feed (paginated) |

Query params: `per_page` (default 20). Uses cursor-based pagination — follow `next_cursor` in response `meta`.

### Likes

| Method | Endpoint              | Description |
|--------|-----------------------|-------------|
| POST   | `/posts/{id}/like`    | Like post   |
| DELETE | `/posts/{id}/like`    | Unlike post |

---

## Architecture Decisions

### 1. Service Layer Pattern

```
Controller (thin) → Service (business logic) → Model (data access)
```

- **Controllers** handle HTTP concerns only (validation, response formatting)
- **Services** (`FeedService`, `FollowService`, `PostService`, `LikeService`) contain all business logic
- **Models** define relationships, scopes, and casts

Why not Repository pattern? For this scope, Eloquent already abstracts the database. Adding repositories would create indirection without benefit. Services keep controllers clean without over-engineering.

### 2. Pull-Model Feed (Fan-Out-On-Read)

```sql
SELECT * FROM posts
WHERE user_id IN (…followed_user_ids)
ORDER BY created_at DESC
```

**Why pull over push (fan-out-on-write)?**
- At 500K posts/day, writing each post into millions of follower feeds is extremely write-heavy
- Pull + Redis cache is simpler, costs less storage, and scales well with proper indexing
- Push model is better for read-heavy celebrity accounts, but requires far more infrastructure

**Hybrid approach at true scale:** Use pull for regular users and push for users with < 1000 followers. Cache celebrity feeds separately.

### 3. Cursor-Based Pagination

Uses Laravel's `cursorPaginate()` instead of `paginate()` (offset-based):
- **Offset:** `OFFSET 10000` scans and discards 10K rows — O(n)
- **Cursor:** `WHERE created_at < ?` uses index directly — O(1)
- Essential at 500K posts/day where offset pagination degrades fast

### 4. Denormalized Like Counts

`posts.likes_count` is incremented/decremented atomically via `$post->increment('likes_count')`:
- Avoids expensive `COUNT(*)` on every request
- Atomic operations prevent race conditions
- Falls back to cache for hot reads

### 5. N+1 Query Prevention

- **Eager loading:** `Post::with('user:id,name,email')` loads all users in 1 query
- **Subquery for is_liked:** `EXISTS(SELECT 1 FROM likes WHERE ...)` — single query, no N+1
- **Denormalized counts:** `likes_count` column avoids `withCount('likes')` aggregation

---

## Database Design & Indexes

### Schema

```
users: id, name, email, password, timestamps, deleted_at (soft delete)
follows: id, follower_id, following_id, created_at
posts: id, user_id, content, likes_count, timestamps, deleted_at (soft delete)
likes: id, user_id, post_id, created_at
```

### Soft Deletes

The `users` and `posts` tables implement soft deletes:
- **Users:** Allows account deactivation without losing data (posts, likes, follows preserved)
- **Posts:** Allows post recovery and maintains referential integrity
- Soft-deleted records are excluded from queries by default
- Use `withTrashed()` or `onlyTrashed()` to access deleted records

### Index Strategy

| Table    | Index                              | Purpose                                   |
|----------|-----------------------------------|-------------------------------------------|
| `follows`| `UNIQUE(follower_id, following_id)` | Prevent duplicate follows, fast lookup     |
| `follows`| `INDEX(following_id)`              | Reverse lookup (find user's followers)     |
| `posts`  | `INDEX(user_id, created_at)`       | Feed query — covers WHERE + ORDER BY       |
| `likes`  | `UNIQUE(user_id, post_id)`         | Prevent duplicate likes                   |
| `likes`  | `INDEX(post_id)`                   | Count likes per post                      |

### Why these indexes matter at scale

The feed query `WHERE user_id IN (...) ORDER BY created_at DESC` is the most critical. The composite index on `(user_id, created_at)` allows MySQL to:
1. Seek directly to each followed user's posts
2. Read them in `created_at` order without sorting
3. Merge-sort the results efficiently

Without this index, MySQL would full-scan the posts table (500K rows/day = 180M rows/year).

---

## Queue System Design

### Flow

```
User creates post → PostService → dispatch(NotifyFollowersJob)
                                        ↓
                              Redis Queue (notifications)
                                        ↓
                              Queue Worker picks up job
                                        ↓
                              Chunk followers (500/batch)
                                        ↓
                              Send database notification to each
```

### Design Decisions

- **Redis as queue driver:** In-memory, fast enqueue/dequeue, supports delayed jobs
- **Dedicated queue name:** `notifications` queue separates notification jobs from other jobs
- **Chunked processing:** Followers are processed in batches of 500 to prevent memory exhaustion for users with millions of followers
- **Separate worker service:** Docker runs a dedicated queue worker container that can be horizontally scaled
- **Retry policy:** 3 attempts with backoff — failed jobs go to `failed_jobs` table

### Scaling the queue

- Add more worker containers: `docker-compose up --scale queue=5`
- Use Laravel Horizon for monitoring and auto-scaling workers
- For celebrity users (1M+ followers), split into sub-jobs (fan-out the fan-out)

---

## Caching Strategy

### Cache Keys

| Key Pattern                         | TTL    | Invalidation Event            |
|-------------------------------------|--------|-------------------------------|
| `user:{id}:following_ids`           | 10 min | Follow / Unfollow             |

### Why Redis?

- **Sub-millisecond reads** for cached data
- **Atomic operations** for counters (`INCR`/`DECR`)
- **Built-in TTL** for automatic cache expiry
- **Pub/Sub** capability for future real-time features

### Cache Invalidation Strategy

Event-driven invalidation — caches are busted when the underlying data changes:
- **Follow/Unfollow:** Invalidate `user:{id}:following_ids`
- **New post:** Feed cache auto-expires via short TTL
- **Like/Unlike:** Denormalized counter is the source of truth; no cache needed

### Verifying Redis Cache

**Check if Redis extension is installed:**
```bash
docker exec laravel-app php -m | grep redis
```

**Test cache functionality via Tinker:**
```bash
docker exec laravel-app php artisan tinker --execute="use Illuminate\Support\Facades\Cache; Cache::put('test', 'working', 60); echo Cache::get('test');"
```

**Access Redis CLI directly:**
```bash
docker exec -it laravel-redis redis-cli

# Inside Redis CLI:
KEYS *                    # List all keys
INFO memory               # Memory usage
INFO keyspace             # Database statistics
MONITOR                   # Real-time command monitoring
```

**Check cache configuration:**
```bash
docker exec laravel-app php artisan tinker --execute="echo config('cache.default');"
# Should output: redis
```

---

## Scaling Strategy (5M Users, 500K Posts/Day)

### Database Layer

1. **Read replicas:** Route feed reads to replicas, writes to primary
2. **Connection pooling:** Use tools like ProxySQL to manage connection limits
3. **Table partitioning:** Range-partition `posts` by `created_at` (monthly) for archival and faster queries
4. **Sharding:** At extreme scale, shard `posts` by `user_id` hash

### Application Layer

1. **Horizontal scaling:** Stateless API servers behind a load balancer
2. **Queue workers:** Scale independently based on notification backlog
3. **Rate limiting:** Laravel's built-in throttle middleware to prevent abuse

### Caching Layer

1. **Redis Cluster:** Shard cache across multiple Redis nodes
2. **Feed pre-warming:** Background job to pre-compute feeds for active users
3. **Cache-aside pattern:** Check cache → miss → query DB → store in cache

### Infrastructure

1. **CDN:** Cache API responses for public endpoints
2. **Monitoring:** Laravel Telescope / Horizon for queue and query monitoring
3. **Database query logging:** Identify slow queries and add targeted indexes

---

## Project Structure

```
app/
  Http/
    Controllers/Api/        # Thin API controllers
      AuthController.php
      FeedController.php
      FollowController.php
      LikeController.php
      PostController.php
    Requests/               # Form request validation
    Resources/              # API response transformers
  Models/                   # Eloquent models with relationships
  Services/                 # Business logic layer
  Jobs/                     # Queue jobs (NotifyFollowersJob)
  Notifications/            # Notification classes
database/
  migrations/               # Schema with optimized indexes
  factories/                # Test data factories
  seeders/                  # Performance test seeder
routes/
  api.php                   # All API route definitions
tests/
  Feature/                  # Auth, Follow, Post, Feed, Like tests
```

---

## Postman Collection

A complete Postman collection is included at `postman_collection.json`.

### Features
- **Auto-Scripts:** Token and IDs are automatically saved after login/register
- **Variable Inheritance:** `{{token}}`, `{{userId}}`, `{{postId}}`, `{{suggestedUserId}}`
- **Full Documentation:** Each endpoint includes descriptions, response codes, and examples

### Import Instructions
1. Open Postman
2. Click **Import** → **Upload Files**
3. Select `postman_collection.json`
4. Set `baseUrl` variable to `http://localhost:8000/api`
5. Run **Register** or **Login** first — token auto-saves!

---

## License

MIT
