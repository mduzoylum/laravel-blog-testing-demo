<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PostTest extends TestCase
{
    use DatabaseTransactions;

    public function test_post_belongs_to_user()
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $post->user);
        $this->assertEquals($user->id, $post->user->id);
    }

    public function test_post_can_have_many_comments()
    {
        // Arrange
        $post = Post::factory()->create();
        
        // Act
        $post->comments()->create([
            'content' => 'Great post!',
            'user_id' => User::factory()->create()->id,
        ]);

        // Assert
        $this->assertCount(1, $post->comments);
        $this->assertInstanceOf(Comment::class, $post->comments->first());
    }

    public function test_post_is_published_method()
    {
        // Arrange
        $publishedPost = Post::factory()->published()->create();
        $draftPost = Post::factory()->draft()->create();

        // Act & Assert
        $this->assertTrue($publishedPost->isPublished());
        $this->assertFalse($draftPost->isPublished());
    }

    public function test_post_publish_method_updates_status_and_date()
    {
        // Arrange
        $post = Post::factory()->draft()->create();
        $this->assertNull($post->published_at);
        
        // Act
        $post->publish();

        // Assert
        $post->refresh();
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_post_generates_slug_automatically()
    {
        // Arrange & Act
        $post = Post::factory()->create(['title' => 'My Amazing Blog Post']);

        // Assert
        $this->assertEquals('my-amazing-blog-post', $post->slug);
    }

    public function test_post_get_excerpt_method()
    {
        // Arrange
        $longContent = str_repeat('This is a long content. ', 50);
        $post = Post::factory()->create(['content' => $longContent]);

        // Act
        $excerpt = $post->excerpt;

        // Assert
        $this->assertStringContainsString('This is a long content.', $excerpt);
        $this->assertLessThan(strlen($longContent), strlen($excerpt));
    }

    public function test_published_scope_returns_only_published_posts()
    {
        // Arrange
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        // Act
        $publishedPosts = Post::published()->get();

        // Assert
        $this->assertCount(3, $publishedPosts);
        $publishedPosts->each(function ($post) {
            $this->assertEquals('published', $post->status);
        });
    }

    public function test_draft_scope_returns_only_draft_posts()
    {
        // Arrange
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        // Act
        $draftPosts = Post::draft()->get();

        // Assert
        $this->assertCount(2, $draftPosts);
        $draftPosts->each(function ($post) {
            $this->assertEquals('draft', $post->status);
        });
    }

    public function test_generate_slug_method_creates_proper_slug()
    {
        // Arrange
        $post = Post::factory()->create([
            'title' => "Laravel Testing İle Güçlü Kod Yazımı!",
            'content' => 'Test content for slug generation'
        ]);

        // Act
        $slug = $post->generateSlug();

        // Assert
        $this->assertEquals('laravel-testing-ile-guclu-kod-yazimi', $slug);
    }
}