<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_belongs_to_user()
    {
        // Arrange
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $comment->user);
        $this->assertEquals($user->id, $comment->user->id);
    }

    public function test_comment_belongs_to_post()
    {
        // Arrange
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        // Act & Assert
        $this->assertInstanceOf(Post::class, $comment->post);
        $this->assertEquals($post->id, $comment->post->id);
    }

    public function test_comment_approve_method_sets_approved_to_true()
    {
        // Arrange
        $comment = Comment::factory()->pending()->create();
        $this->assertFalse($comment->approved);

        // Act
        $comment->approve();

        // Assert
        $comment->refresh();
        $this->assertTrue($comment->approved);
    }

    public function test_approved_scope_returns_only_approved_comments()
    {
        // Arrange
        Comment::factory()->approved()->count(3)->create();
        Comment::factory()->pending()->count(2)->create();

        // Act
        $approvedComments = Comment::approved()->get();

        // Assert
        $this->assertCount(3, $approvedComments);
        $approvedComments->each(function ($comment) {
            $this->assertTrue($comment->approved);
        });
    }

    public function test_comment_can_be_created_with_valid_data()
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create();
        
        $commentData = [
            'content' => 'This is a great comment!',
            'user_id' => $user->id,
            'post_id' => $post->id,
            'approved' => false
        ];

        // Act
        $comment = Comment::create($commentData);

        // Assert
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals('This is a great comment!', $comment->content);
        $this->assertEquals($user->id, $comment->user_id);
        $this->assertEquals($post->id, $comment->post_id);
        $this->assertFalse($comment->approved);
    }

    public function test_comment_approved_cast_works_correctly()
    {
        // Arrange & Act
        $approvedComment = Comment::factory()->approved()->create();
        $pendingComment = Comment::factory()->pending()->create();

        // Assert
        $this->assertIsBool($approvedComment->approved);
        $this->assertIsBool($pendingComment->approved);
        $this->assertTrue($approvedComment->approved);
        $this->assertFalse($pendingComment->approved);
    }
}