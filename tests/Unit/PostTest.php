<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PostTest extends TestCase
{
    use DatabaseMigrations;

    public function test_post_belongs_to_user()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $post->user);
        $this->assertEquals($user->id, $post->user->id);
    }

    public function test_post_can_have_many_comments()
    {
        $post = Post::factory()->create();
        
        $post->comments()->create([
            'content' => 'Great post!',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertCount(1, $post->comments);
        $this->assertInstanceOf(Comment::class, $post->comments->first());
    }

    public function test_post_is_published_method()
    {
        $publishedPost = Post::factory()->published()->create();
        $draftPost = Post::factory()->draft()->create();

        $this->assertTrue($publishedPost->isPublished());
        $this->assertFalse($draftPost->isPublished());
    }

    public function test_post_publish_method_updates_status_and_date()
    {
        $post = Post::factory()->draft()->create();
        $this->assertNull($post->published_at);
        
        $post->publish();

        $post->refresh();
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_post_generates_slug_automatically()
    {
        $post = Post::factory()->create(['title' => 'My Amazing Blog Post']);

        $this->assertEquals('my-amazing-blog-post', $post->slug);
    }

    public function test_post_get_excerpt_method()
    {
        $longContent = str_repeat('This is a long content. ', 50);
        $post = Post::factory()->create(['content' => $longContent]);

        $excerpt = $post->excerpt;

        $this->assertStringContainsString('This is a long content.', $excerpt);
        $this->assertLessThan(strlen($longContent), strlen($excerpt));
    }

    public function test_published_scope_returns_only_published_posts()
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $publishedPosts = Post::published()->get();

        $this->assertCount(3, $publishedPosts);
        $publishedPosts->each(function ($post) {
            $this->assertEquals('published', $post->status);
        });
    }

    public function test_draft_scope_returns_only_draft_posts()
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $draftPosts = Post::draft()->get();

        $this->assertCount(2, $draftPosts);
        $draftPosts->each(function ($post) {
            $this->assertEquals('draft', $post->status);
        });
    }

    public function test_generate_slug_method_creates_proper_slug()
    {
        $post = Post::factory()->create([
            'title' => "Laravel Testing İle Güçlü Kod Yazımı!",
            'content' => 'Test content for slug generation'
        ]);

        $slug = $post->generateSlug();

        $this->assertEquals('laravel-testing-ile-guclu-kod-yazimi', $slug);
    }
}