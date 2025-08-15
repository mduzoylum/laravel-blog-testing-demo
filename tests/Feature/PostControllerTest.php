<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function test_guest_can_view_published_posts()
    {
        // Arrange
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        // Act
        $response = $this->getJson('/api/posts');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'content', 'status', 'user_id', 'created_at']
            ]
        ]);
    }

    public function test_guest_can_filter_posts_by_published_status()
    {
        // Arrange
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        // Act
        $response = $this->getJson('/api/posts?published=1');

        // Assert
        $response->assertStatus(200);
        $publishedPosts = collect($response->json('data'));
        $publishedPosts->each(function ($post) {
            $this->assertEquals('published', $post['status']);
        });
    }

    public function test_authenticated_user_can_create_post()
    {
        // Arrange
        $user = User::factory()->create();
        $postData = [
            'title' => 'My New Post',
            'content' => 'This is the content of my new post.',
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/posts', $postData);

        // Assert
        $response->assertStatus(201);
        $response->assertJson([
            'title' => 'My New Post',
            'content' => 'This is the content of my new post.',
            'user_id' => $user->id
        ]);
        // Check that status is set (default is draft)
        $this->assertArrayHasKey('status', $response->json());
        $this->assertEquals('draft', $response->json('status'));

        $this->assertDatabaseHas('posts', [
            'title' => 'My New Post',
            'user_id' => $user->id,
            'slug' => 'my-new-post'
        ]);
    }

    public function test_guest_cannot_create_post()
    {
        // Arrange
        $postData = [
            'title' => 'Unauthorized Post',
            'content' => 'This should not be created.',
        ];

        // Act
        $response = $this->postJson('/api/posts', $postData);

        // Assert
        $response->assertStatus(401);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Unauthorized Post',
        ]);
    }

    public function test_user_can_update_own_post()
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ];

        // Act
        $response = $this->actingAs($user)
            ->putJson("/api/posts/{$post->id}", $updateData);

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_user_cannot_update_others_post()
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $updateData = [
            'title' => 'Hacked Title',
        ];

        // Act
        $response = $this->actingAs($otherUser)
            ->putJson("/api/posts/{$post->id}", $updateData);

        // Assert
        $response->assertStatus(403); // Forbidden
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
            'title' => 'Hacked Title',
        ]);
    }

    public function test_user_can_delete_own_post()
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->deleteJson("/api/posts/{$post->id}");

        // Assert
        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_user_cannot_delete_others_post()
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($otherUser)
            ->deleteJson("/api/posts/{$post->id}");

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_user_can_publish_own_post()
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->draft()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->postJson("/api/posts/{$post->id}/publish");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Post published successfully',
            'post' => [
                'status' => 'published',
            ],
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'published',
        ]);

        $post->refresh();
        $this->assertNotNull($post->published_at);
    }

    public function test_user_cannot_publish_others_post()
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->draft()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($otherUser)
            ->postJson("/api/posts/{$post->id}/publish");

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'draft',
        ]);
    }

    public function test_post_show_returns_single_post_with_relationships()
    {
        // Arrange
        $post = Post::factory()->published()->create();
        $comment = $post->comments()->create([
            'content' => 'Great post!',
            'user_id' => User::factory()->create()->id,
            'approved' => true,
        ]);

        // Act
        $response = $this->getJson("/api/posts/{$post->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $post->id,
            'title' => $post->title,
        ]);

        $response->assertJsonStructure([
            'id',
            'title',
            'content',
            'user' => ['id', 'name', 'email'],
            'comments' => [
                '*' => ['id', 'content', 'user' => ['id', 'name']]
            ]
        ]);
    }

    public function test_nonexistent_post_returns_404()
    {
        // Act
        $response = $this->getJson('/api/posts/999');

        // Assert
        $response->assertStatus(404);
    }
}
