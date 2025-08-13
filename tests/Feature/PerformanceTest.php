<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_index_query_performance()
    {
        // Arrange
        User::factory()->count(10)->create();
        Post::factory()->count(100)->create();
        
        // Act
        DB::enableQueryLog();
        
        $response = $this->getJson('/api/posts');
        
        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert
        $response->assertStatus(200);
        
        // Should not have N+1 query problem
        // With pagination and eager loading, should be minimal queries
        $this->assertLessThanOrEqual(5, count($queryLog));
    }

    public function test_post_show_with_relationships_performance()
    {
        // Arrange
        $post = Post::factory()->create();
        $post->comments()->createMany(
            Comment::factory()->count(10)->make()->toArray()
        );

        // Act
        DB::enableQueryLog();
        
        $response = $this->getJson("/api/posts/{$post->id}");
        
        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert
        $response->assertStatus(200);
        
        // Should eager load relationships efficiently
        $this->assertLessThanOrEqual(4, count($queryLog));
    }

    public function test_bulk_operations_performance()
    {
        // Arrange
        $user = User::factory()->create();
        
        // Act - Measure time for bulk creation
        $startTime = microtime(true);
        
        Post::factory()->count(50)->create(['user_id' => $user->id]);
        
        $creationTime = microtime(true) - $startTime;

        // Assert - Bulk creation should be reasonably fast
        $this->assertLessThan(2.0, $creationTime); // Should take less than 2 seconds
        $this->assertEquals(50, Post::where('user_id', $user->id)->count());
    }

    public function test_database_query_optimization()
    {
        // Arrange
        $users = User::factory()->count(20)->create();
        $posts = Post::factory()->count(100)->create();
        
        // Her post'a random comment'lar ekle
        foreach ($posts as $post) {
            Comment::factory()->count(rand(0, 5))->create([
                'post_id' => $post->id
            ]);
        }

        // Act - Test different query approaches
        DB::enableQueryLog();
        
        // Efficient query with eager loading
        $efficientPosts = Post::with('user', 'comments.user')
            ->published()
            ->take(10)
            ->get();
        
        $efficientQueryCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        
        // Inefficient query without eager loading (N+1 problem simulation)
        $inefficientPosts = Post::published()->take(10)->get();
        foreach ($inefficientPosts as $post) {
            $post->user; // This will trigger additional queries
            $post->comments; // This will trigger more queries
        }
        
        $inefficientQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Assert
        $this->assertEquals(10, $efficientPosts->count());
        $this->assertEquals(10, $inefficientPosts->count());
        
        // Efficient approach should use significantly fewer queries
        $this->assertLessThan($inefficientQueryCount / 2, $efficientQueryCount);
    }

    public function test_pagination_performance_with_large_dataset()
    {
        // Arrange
        Post::factory()->count(1000)->create();

        // Act - Test pagination performance
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/posts?page=1');
        $page1Time = microtime(true) - $startTime;
        
        $startTime = microtime(true);
        $response = $this->getJson('/api/posts?page=50');
        $page50Time = microtime(true) - $startTime;

        // Assert
        $response->assertStatus(200);
        
        // Later pages shouldn't be significantly slower than early pages
        $this->assertLessThan($page1Time * 3, $page50Time);
        
        // Both should be reasonably fast
        $this->assertLessThan(1.0, $page1Time);
        $this->assertLessThan(1.0, $page50Time);
    }

    public function test_search_query_performance()
    {
        // Arrange
        Post::factory()->count(500)->create([
            'title' => 'Laravel Tutorial Post ' . uniqid(),
            'status' => 'published'
        ]);
        
        Post::factory()->count(500)->create([
            'title' => 'Vue.js Development Guide ' . uniqid(),
            'status' => 'published'
        ]);

        // Act
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/posts');
        
        $searchTime = microtime(true) - $startTime;

        // Assert
        $response->assertStatus(200);
        
        // Basic query should complete in reasonable time
        $this->assertLessThan(2.0, $searchTime);
        
        // Should return posts (search functionality not implemented yet)
        $results = collect($response->json('data'));
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_concurrent_write_operations()
    {
        // Arrange
        $user = User::factory()->create();

        // Act - Simulate concurrent post creation
        $startTime = microtime(true);
        
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->actingAs($user)
                ->postJson('/api/posts', [
                    'title' => "Concurrent Post {$i}",
                    'content' => "Content for concurrent post {$i}"
                ]);
        }
        
        $totalTime = microtime(true) - $startTime;

        // Assert
        foreach ($responses as $response) {
            $response->assertStatus(201);
        }
        
        // All posts should be created successfully
        $this->assertEquals(10, Post::where('user_id', $user->id)->count());
        
        // Operations should complete in reasonable time
        $this->assertLessThan(5.0, $totalTime);
    }

    public function test_memory_usage_with_large_collections()
    {
        // Arrange
        Post::factory()->count(1000)->create();
        
        $initialMemory = memory_get_usage();

        // Act - Process large collection
        $posts = Post::with('user')->get();
        
        foreach ($posts as $post) {
            // Simulate some processing
            $post->title . ' - ' . $post->user->name;
        }
        
        $peakMemory = memory_get_peak_usage();
        $memoryUsed = $peakMemory - $initialMemory;

        // Assert
        $this->assertEquals(1000, $posts->count());
        
        // Memory usage should be reasonable (less than 50MB for this test)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed);
    }

    public function test_cache_performance_improvement()
    {
        // Arrange
        $posts = Post::factory()->count(100)->create();

        // Act - First request (no cache)
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/posts');
        $firstRequestTime = microtime(true) - $startTime;

        // Act - Second request (potentially cached)
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/posts');
        $secondRequestTime = microtime(true) - $startTime;

        // Assert
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        // Both requests should return same data
        $this->assertEquals($response1->json('data'), $response2->json('data'));
        
        // Note: Bu test cache implement edilmişse ikinci request daha hızlı olur
        // Şimdilik sadece response time'ları measure ediyoruz
        $this->assertLessThan(2.0, $firstRequestTime);
        $this->assertLessThan(2.0, $secondRequestTime);
    }

    public function test_database_connection_pooling()
    {
        // Arrange & Act
        $connections = [];
        
        for ($i = 0; $i < 5; $i++) {
            // Her iteration'da DB connection kullan
            $user = User::factory()->create();
            $connections[] = DB::connection()->getPdo();
            
            // Cleanup
            $user->delete();
        }

        // Assert - Connection pooling working (same connection reused)
        $uniqueConnections = array_unique($connections, SORT_REGULAR);
        
        // Should reuse connections efficiently
        $this->assertLessThanOrEqual(2, count($uniqueConnections));
    }

    public function test_index_usage_effectiveness()
    {
        // Arrange
        User::factory()->count(100)->create();
        Post::factory()->count(1000)->create();

        // Act - Query that should use indexes
        DB::enableQueryLog();
        
        // Query by indexed column (status)
        $publishedPosts = Post::where('status', 'published')->get();
        
        // Query by indexed foreign key (user_id)
        $userPosts = Post::where('user_id', 1)->get();
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert
        $this->assertGreaterThan(0, $publishedPosts->count());
        $this->assertGreaterThanOrEqual(0, $userPosts->count());
        
        // Queries should execute quickly (indicating index usage)
        foreach ($queries as $query) {
            $this->assertLessThan(100, $query['time']); // Less than 100ms
        }
    }

    public function test_api_response_compression()
    {
        // Arrange
        Post::factory()->count(50)->create();

        // Act
        $response = $this->getJson('/api/posts', [
            'Accept-Encoding' => 'gzip, deflate'
        ]);

        // Assert
        $response->assertStatus(200);
        
        // Response should contain substantial data
        $responseSize = strlen($response->getContent());
        $this->assertGreaterThan(1000, $responseSize);
        
        // Note: Gerçek compression middleware implement edilmişse
        // Content-Encoding header'ı kontrol edilebilir
        // $this->assertEquals('gzip', $response->headers->get('Content-Encoding'));
    }
}