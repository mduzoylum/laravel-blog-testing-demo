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
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'content', 'status', 'user_id', 'created_at']
            ]
        ]);
    }

    public function test_guest_can_filter_posts_by_published_status()
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $response = $this->getJson('/api/posts?published=1');

        $response->assertStatus(200);
        $publishedPosts = collect($response->json('data'));
        $publishedPosts->each(function ($post) {
            $this->assertEquals('published', $post['status']);
        });
    }

    public function test_authenticated_user_can_create_post()
    {
        $user = User::factory()->create();
        $postData = [
            'title' => 'My New Post',
            'content' => 'This is the content of my new post.',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/posts', $postData);

        $response->assertStatus(201);
        $response->assertJson([
            'title' => 'My New Post',
            'content' => 'This is the content of my new post.',
            'user_id' => $user->id
        ]);
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
        $postData = [
            'title' => 'Unauthorized Post',
            'content' => 'This should not be created.',
        ];

        $response = $this->postJson('/api/posts', $postData);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Unauthorized Post',
        ]);
    }

    public function test_user_can_update_own_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ];

        $response = $this->actingAs($user)
            ->putJson("/api/posts/{$post->id}", $updateData);

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
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $updateData = [
            'title' => 'Hacked Title',
        ];

        $response = $this->actingAs($otherUser)
            ->putJson("/api/posts/{$post->id}", $updateData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
            'title' => 'Hacked Title',
        ]);
    }

    public function test_user_can_delete_own_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_user_cannot_delete_others_post()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_user_can_publish_own_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->draft()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/posts/{$post->id}/publish");

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
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->draft()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/posts/{$post->id}/publish");

        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'draft',
        ]);
    }

    public function test_post_show_returns_single_post_with_relationships()
    {
        $post = Post::factory()->published()->create();
        $comment = $post->comments()->create([
            'content' => 'Great post!',
            'user_id' => User::factory()->create()->id,
            'approved' => true,
        ]);

        $response = $this->getJson("/api/posts/{$post->id}");

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
        $response = $this->getJson('/api/posts/999');

        $response->assertStatus(404);
    }
}
