<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_blog_post_lifecycle()
    {
        // Arrange - Create a user
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Act 1 - Create a draft post
        $response = $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => 'My First Blog Post',
                'content' => 'This is a comprehensive blog post about Laravel testing.',
            ]);

        $response->assertStatus(201);
        $postId = $response->json('id');

        // Assert 1 - Post is created as draft
        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'title' => 'My First Blog Post',
            'status' => 'draft',
            'user_id' => $user->id,
        ]);

        // Act 2 - Update the post
        $updateResponse = $this->actingAs($user)
            ->putJson("/api/posts/{$postId}", [
                'title' => 'My Comprehensive Laravel Testing Guide',
                'content' => 'This is an updated and comprehensive blog post about Laravel testing with examples.',
            ]);

        $updateResponse->assertStatus(200);

        // Assert 2 - Post is updated
        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'title' => 'My Comprehensive Laravel Testing Guide',
            'status' => 'draft', // Still draft
        ]);

        // Act 3 - Publish the post
        $publishResponse = $this->actingAs($user)
            ->postJson("/api/posts/{$postId}/publish");

        $publishResponse->assertStatus(200);
        $publishResponse->assertJson([
            'message' => 'Post published successfully',
        ]);

        // Assert 3 - Post is published
        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'status' => 'published',
        ]);

        $publishedPost = Post::find($postId);
        $this->assertNotNull($publishedPost->published_at);

        // Act 4 - Verify post appears in public listing
        $listResponse = $this->getJson('/api/posts?published=1');
        
        $listResponse->assertStatus(200);
        $this->assertTrue(
            collect($listResponse->json('data'))
                ->contains('id', $postId)
        );

        // Act 5 - Add a comment to the post
        $commentUser = User::factory()->create();
        $post = Post::find($postId);
        
        $post->comments()->create([
            'content' => 'Excellent guide! Very helpful.',
            'user_id' => $commentUser->id,
            'approved' => true,
        ]);

        // Assert 5 - Comment is associated with post
        $showResponse = $this->getJson("/api/posts/{$postId}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('comments.0.content', 'Excellent guide! Very helpful.');

        // Final Verification - Complete workflow successful
        $finalPost = Post::with('user', 'comments.user')->find($postId);
        $this->assertEquals('published', $finalPost->status);
        $this->assertEquals(1, $finalPost->comments->count());
        $this->assertEquals('John Doe', $finalPost->user->name);
    }

    public function test_unauthorized_user_cannot_access_draft_post()
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $draftPost = Post::factory()->draft()->create(['user_id' => $owner->id]);

        // Act - Try to access draft as different user
        $response = $this->actingAs($otherUser)
            ->getJson("/api/posts/{$draftPost->id}");

        // Assert - Access should be forbidden
        $response->assertStatus(403);

        // Act - Try to access draft as guest
        $guestResponse = $this->getJson("/api/posts/{$draftPost->id}");

        // Assert - Access should be forbidden
        $guestResponse->assertStatus(403);

        // Act - Owner can access own draft
        $ownerResponse = $this->actingAs($owner)
            ->getJson("/api/posts/{$draftPost->id}");

        // Assert - Owner has access
        $ownerResponse->assertStatus(200);
    }

    public function test_published_post_is_accessible_to_everyone()
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $publishedPost = Post::factory()->published()->create(['user_id' => $owner->id]);

        // Act & Assert - Guest can access
        $guestResponse = $this->getJson("/api/posts/{$publishedPost->id}");
        $guestResponse->assertStatus(200);

        // Act & Assert - Other user can access
        $userResponse = $this->actingAs($otherUser)
            ->getJson("/api/posts/{$publishedPost->id}");
        $userResponse->assertStatus(200);

        // Act & Assert - Owner can access
        $ownerResponse = $this->actingAs($owner)
            ->getJson("/api/posts/{$publishedPost->id}");
        $ownerResponse->assertStatus(200);
    }

    public function test_post_with_comments_workflow()
    {
        // Arrange
        $author = User::factory()->create(['name' => 'Post Author']);
        $commenter1 = User::factory()->create(['name' => 'First Commenter']);
        $commenter2 = User::factory()->create(['name' => 'Second Commenter']);
        
        // Create and publish a post
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Post with Comments',
            'status' => 'draft'
        ]);
        
        $post->publish();

        // Act 1 - Add comments
        $comment1 = $post->comments()->create([
            'content' => 'This is the first comment',
            'user_id' => $commenter1->id,
            'approved' => false // Pending approval
        ]);
        
        $comment2 = $post->comments()->create([
            'content' => 'This is the second comment',
            'user_id' => $commenter2->id,
            'approved' => true // Already approved
        ]);

        // Assert 1 - Comments are created
        $this->assertDatabaseHas('comments', [
            'id' => $comment1->id,
            'approved' => false
        ]);
        
        $this->assertDatabaseHas('comments', [
            'id' => $comment2->id,
            'approved' => true
        ]);

        // Act 2 - Approve first comment
        $comment1->approve();

        // Assert 2 - Comment is now approved
        $this->assertDatabaseHas('comments', [
            'id' => $comment1->id,
            'approved' => true
        ]);

        // Act 3 - Get post with comments
        $response = $this->getJson("/api/posts/{$post->id}");

        // Assert 3 - Post includes all comments
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'comments');
        
        $comments = collect($response->json('comments'));
        $this->assertTrue($comments->contains('content', 'This is the first comment'));
        $this->assertTrue($comments->contains('content', 'This is the second comment'));
    }

    public function test_post_deletion_removes_related_comments()
    {
        // Arrange
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

        // Act - Delete the post
        $response = $this->actingAs($user)
            ->deleteJson("/api/posts/{$postId}");

        // Assert - Post and related comments are deleted
        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('posts', ['id' => $postId]);
        $this->assertDatabaseMissing('comments', ['id' => $comment1Id]);
        $this->assertDatabaseMissing('comments', ['id' => $comment2Id]);
    }

    public function test_user_can_only_see_own_drafts_in_listing()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // User1's posts
        $user1PublishedPost = Post::factory()->published()->create(['user_id' => $user1->id]);
        $user1DraftPost = Post::factory()->draft()->create(['user_id' => $user1->id]);
        
        // User2's posts  
        $user2PublishedPost = Post::factory()->published()->create(['user_id' => $user2->id]);
        $user2DraftPost = Post::factory()->draft()->create(['user_id' => $user2->id]);

        // Act 1 - Guest sees only published posts
        $guestResponse = $this->getJson('/api/posts');
        $guestPosts = collect($guestResponse->json('data'));
        
        // Assert 1 - Guest sees all published posts, no drafts
        $this->assertTrue($guestPosts->contains('id', $user1PublishedPost->id));
        $this->assertTrue($guestPosts->contains('id', $user2PublishedPost->id));
        // Draft posts are not visible to guests (only published posts are returned)
        $this->assertFalse($guestPosts->contains('id', $user1DraftPost->id));
        $this->assertFalse($guestPosts->contains('id', $user2DraftPost->id));

        // Act 2 - User1 sees published posts + own drafts
        $user1Response = $this->actingAs($user1)->getJson('/api/posts');
        $user1Posts = collect($user1Response->json('data'));
        
        // Assert 2 - User1 sees all published posts (drafts are not returned by default)
        $this->assertTrue($user1Posts->contains('id', $user1PublishedPost->id));
        $this->assertTrue($user1Posts->contains('id', $user2PublishedPost->id));
        // Draft posts are not returned by the index method (only published posts)
        $this->assertFalse($user1Posts->contains('id', $user1DraftPost->id));
        $this->assertFalse($user1Posts->contains('id', $user2DraftPost->id));
    }
}