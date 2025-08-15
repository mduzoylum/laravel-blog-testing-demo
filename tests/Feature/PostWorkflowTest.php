<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PostWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    public function test_complete_blog_post_lifecycle()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => 'My First Blog Post',
                'content' => 'This is a comprehensive blog post about Laravel testing.',
            ]);

        $response->assertStatus(201);
        $postId = $response->json('id');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'title' => 'My First Blog Post',
            'status' => 'draft',
            'user_id' => $user->id,
        ]);

        $updateResponse = $this->actingAs($user)
            ->putJson("/api/posts/{$postId}", [
                'title' => 'My Comprehensive Laravel Testing Guide',
                'content' => 'This is an updated and comprehensive blog post about Laravel testing with examples.',
            ]);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'title' => 'My Comprehensive Laravel Testing Guide',
            'status' => 'draft',
        ]);

        $publishResponse = $this->actingAs($user)
            ->postJson("/api/posts/{$postId}/publish");

        $publishResponse->assertStatus(200);
        $publishResponse->assertJson([
            'message' => 'Post published successfully',
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'status' => 'published',
        ]);

        $publishedPost = Post::find($postId);
        $this->assertNotNull($publishedPost->published_at);

        $listResponse = $this->getJson('/api/posts?published=1');
        
        $listResponse->assertStatus(200);
        $this->assertTrue(
            collect($listResponse->json('data'))
                ->contains('id', $postId)
        );

        $commentUser = User::factory()->create();
        $post = Post::find($postId);
        
        $post->comments()->create([
            'content' => 'Excellent guide! Very helpful.',
            'user_id' => $commentUser->id,
            'approved' => true,
        ]);

        $showResponse = $this->getJson("/api/posts/{$postId}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('comments.0.content', 'Excellent guide! Very helpful.');

        $finalPost = Post::with('user', 'comments.user')->find($postId);
        $this->assertEquals('published', $finalPost->status);
        $this->assertEquals(1, $finalPost->comments->count());
        $this->assertEquals('John Doe', $finalPost->user->name);
    }

    public function test_unauthorized_user_cannot_access_draft_post()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $draftPost = Post::factory()->draft()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/posts/{$draftPost->id}");

        $response->assertStatus(403);

        $guestResponse = $this->getJson("/api/posts/{$draftPost->id}");

        $guestResponse->assertStatus(403);

        $ownerResponse = $this->actingAs($owner)
            ->getJson("/api/posts/{$draftPost->id}");

        $ownerResponse->assertStatus(200);
    }

    public function test_published_post_is_accessible_to_everyone()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $publishedPost = Post::factory()->published()->create(['user_id' => $owner->id]);

        $guestResponse = $this->getJson("/api/posts/{$publishedPost->id}");
        $guestResponse->assertStatus(200);

        $userResponse = $this->actingAs($otherUser)
            ->getJson("/api/posts/{$publishedPost->id}");
        $userResponse->assertStatus(200);

        $ownerResponse = $this->actingAs($owner)
            ->getJson("/api/posts/{$publishedPost->id}");
        $ownerResponse->assertStatus(200);
    }

    public function test_post_with_comments_workflow()
    {
        $author = User::factory()->create(['name' => 'Post Author']);
        $commenter1 = User::factory()->create(['name' => 'First Commenter']);
        $commenter2 = User::factory()->create(['name' => 'Second Commenter']);
        
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Post with Comments',
            'status' => 'draft'
        ]);
        
        $post->publish();

        $comment1 = $post->comments()->create([
            'content' => 'This is the first comment',
            'user_id' => $commenter1->id,
            'approved' => false
        ]);
        
        $comment2 = $post->comments()->create([
            'content' => 'This is the second comment',
            'user_id' => $commenter2->id,
            'approved' => true
        ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment1->id,
            'approved' => false
        ]);
        
        $this->assertDatabaseHas('comments', [
            'id' => $comment2->id,
            'approved' => true
        ]);

        $comment1->approve();

        $this->assertDatabaseHas('comments', [
            'id' => $comment1->id,
            'approved' => true
        ]);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'comments');
        
        $comments = collect($response->json('comments'));
        $this->assertTrue($comments->contains('content', 'This is the first comment'));
        $this->assertTrue($comments->contains('content', 'This is the second comment'));
    }

    public function test_post_deletion_removes_related_comments()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        
        $comment1 = $post->comments()->create([
            'content' => 'First comment',
            'user_id' => User::factory()->create()->id,
        ]);
        
        $comment2 = $post->comments()->create([
            'content' => 'Second comment',
            'user_id' => User::factory()->create()->id,
        ]);

        $postId = $post->id;
        $comment1Id = $comment1->id;
        $comment2Id = $comment2->id;

        $response = $this->actingAs($user)
            ->deleteJson("/api/posts/{$postId}");

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('posts', ['id' => $postId]);
        $this->assertDatabaseMissing('comments', ['id' => $comment1Id]);
        $this->assertDatabaseMissing('comments', ['id' => $comment2Id]);
    }

    public function test_user_can_only_see_own_drafts_in_listing()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $user1PublishedPost = Post::factory()->published()->create(['user_id' => $user1->id]);
        $user1DraftPost = Post::factory()->draft()->create(['user_id' => $user1->id]);
        
        $user2PublishedPost = Post::factory()->published()->create(['user_id' => $user2->id]);
        $user2DraftPost = Post::factory()->draft()->create(['user_id' => $user2->id]);

        $guestResponse = $this->getJson('/api/posts');
        $guestPosts = collect($guestResponse->json('data'));
        
        $this->assertTrue($guestPosts->contains('id', $user1PublishedPost->id));
        $this->assertTrue($guestPosts->contains('id', $user2PublishedPost->id));
        $this->assertFalse($guestPosts->contains('id', $user1DraftPost->id));
        $this->assertFalse($guestPosts->contains('id', $user2DraftPost->id));

        $user1Response = $this->actingAs($user1)->getJson('/api/posts');
        $user1Posts = collect($user1Response->json('data'));
        
        $this->assertTrue($user1Posts->contains('id', $user1PublishedPost->id));
        $this->assertTrue($user1Posts->contains('id', $user2PublishedPost->id));
        $this->assertFalse($user1Posts->contains('id', $user1DraftPost->id));
        $this->assertFalse($user1Posts->contains('id', $user2DraftPost->id));
    }
}