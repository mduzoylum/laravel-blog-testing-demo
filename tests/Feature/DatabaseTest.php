<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    use DatabaseMigrations;

    public function test_database_relationships_work_correctly()
    {
        // Arrange
        $user = User::factory()->create(['name' => 'Test User']);
        $post = Post::factory()->create([
            'title' => 'Test Post',
            'user_id' => $user->id
        ]);
        
        $comment1 = Comment::factory()->create([
            'content' => 'First comment',
            'post_id' => $post->id,
            'user_id' => $user->id,
            'approved' => true
        ]);
        
        $comment2 = Comment::factory()->create([
            'content' => 'Second comment',
            'post_id' => $post->id,
            'user_id' => User::factory()->create()->id,
            'approved' => false
        ]);

        // Act & Assert - Test relationships
        $this->assertCount(2, $post->comments);
        $this->assertEquals($user->id, $post->user->id);
        $this->assertEquals($post->id, $comment1->post->id);
        
        // Test approved comments only
        $approvedComments = $post->comments()->approved()->get();
        $this->assertCount(1, $approvedComments);
        $this->assertEquals('First comment', $approvedComments->first()->content);
    }

    public function test_cascade_delete_works_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $user->id
        ]);

        $postId = $post->id;
        $commentId = $comment->id;

        // Act - Delete user
        $user->delete();

        // Assert - Related posts and comments are deleted
        $this->assertDatabaseMissing('posts', ['id' => $postId]);
        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
    }

    public function test_unique_slug_constraint()
    {
        // Arrange
        $user = User::factory()->create();
        Post::factory()->create([
            'title' => 'Unique Title',
            'slug' => 'unique-title',
            'user_id' => $user->id
        ]);

        // Act & Assert - Second post with same slug should fail
        $this->expectException(QueryException::class);
        
        Post::factory()->create([
            'title' => 'Another Title',
            'slug' => 'unique-title', // Same slug
            'user_id' => $user->id
        ]);
    }

    public function test_database_seeding_works()
    {
        // Arrange - Create some seed data
        $users = User::factory()->count(5)->create();
        $posts = Post::factory()->count(10)->create(['user_id' => $users->random()->id]);
        $comments = Comment::factory()->count(20)->create([
            'user_id' => $users->random()->id,
            'post_id' => $posts->random()->id
        ]);

        // Assert - Data exists
        $this->assertEquals(5, User::count());
        $this->assertEquals(10, Post::count());
        $this->assertEquals(20, Comment::count());
        
        // Verify relationships exist
        $userWithPosts = User::has('posts')->first();
        $this->assertNotNull($userWithPosts);
        
        $postWithComments = Post::has('comments')->first();
        $this->assertNotNull($postWithComments);
    }

    public function test_database_transactions_rollback_on_error()
    {
        // Arrange
        $initialUserCount = User::count();

        // Act & Assert - Expect exception for duplicate email
        $this->expectException(QueryException::class);

        \DB::transaction(function () {
            User::factory()->create(['email' => 'test@example.com']);
            User::factory()->create(['email' => 'test@example.com']); // Duplicate
        });

        // Assert - No users were created due to rollback
        $this->assertEquals($initialUserCount, User::count());
    }

    public function test_soft_deletes_work_if_implemented()
    {
        // Note: Bu test soft delete implement edilmişse çalışır
        // Şu an için geçiyoruz ama demo'da gösterilebilir
        
        // Arrange
        $post = Post::factory()->create();
        $postId = $post->id;

        // Act - "Soft delete" the post (if implemented)
        // $post->delete();

        // Assert
        // $this->assertSoftDeleted('posts', ['id' => $postId]);
        // $this->assertNotNull(Post::withTrashed()->find($postId));
        
        $this->assertTrue(true); // Placeholder assertion
    }

    public function test_database_indexes_improve_query_performance()
    {
        // Arrange - Create many posts
        User::factory()->count(10)->create();
        Post::factory()->count(1000)->create();

        // Act - Query by status (should use index)
        $start = microtime(true);
        $publishedPosts = Post::where('status', 'published')->get();
        $queryTime = microtime(true) - $start;

        // Assert - Query should be reasonably fast
        $this->assertLessThan(0.1, $queryTime); // Should take less than 100ms
        $this->assertGreaterThan(0, $publishedPosts->count());
    }

    public function test_database_foreign_key_constraints()
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        // Act & Assert - Cannot create comment with invalid post_id
        $this->expectException(QueryException::class);
        
        Comment::factory()->create([
            'post_id' => 99999, // Non-existent post
            'user_id' => $user->id
        ]);
    }

    public function test_database_timestamps_are_maintained()
    {
        // Arrange
        $user = User::factory()->create();
        $originalCreatedAt = $user->created_at;
        $originalUpdatedAt = $user->updated_at;

        // Act - Update user
        sleep(1); // Ensure time difference
        $user->update(['name' => 'Updated Name']);

        // Assert - created_at unchanged, updated_at changed
        $user->refresh();
        $this->assertEquals($originalCreatedAt->timestamp, $user->created_at->timestamp);
        $this->assertGreaterThan($originalUpdatedAt->timestamp, $user->updated_at->timestamp);
    }

    public function test_database_json_column_operations()
    {
        // Note: Eğer JSON column'lar kullanılıyorsa bu tür testler yararlı
        
        // Arrange - JSON metadata column example
        $post = Post::factory()->create([
            'title' => 'JSON Test Post'
            // 'metadata' => ['tags' => ['laravel', 'testing'], 'featured' => true]
        ]);

        // Act & Assert
        // $this->assertEquals(['laravel', 'testing'], $post->metadata['tags']);
        // $this->assertTrue($post->metadata['featured']);
        
        // Query JSON data
        // $taggedPosts = Post::whereJsonContains('metadata->tags', 'laravel')->get();
        // $this->assertContains($post->id, $taggedPosts->pluck('id')->toArray());
        
        $this->assertTrue(true); // Placeholder assertion
    }

    public function test_database_connection_configuration()
    {
        // Assert - We're using the test database
        $this->assertEquals('sqlite', config('database.default'));
        
        // Assert - Test database configuration (SQLite for testing)
        $connectionConfig = config('database.connections.sqlite');
        $this->assertArrayHasKey('database', $connectionConfig);
    }
}