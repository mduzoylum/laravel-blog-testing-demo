<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class UserTest extends TestCase
{
    use DatabaseMigrations;

    public function test_user_can_have_many_posts()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->posts()->createMany([
            ['title' => 'First Post', 'content' => 'Content 1', 'slug' => 'first-post'],
            ['title' => 'Second Post', 'content' => 'Content 2', 'slug' => 'second-post'],
        ]);

        // Assert
        $this->assertCount(2, $user->posts);
        $user->posts->each(function ($post) {
            $this->assertInstanceOf(Post::class, $post);
        });
    }

    public function test_user_can_have_many_comments()
    {
        // Arrange
        $user = User::factory()->create();
        $posts = Post::factory()->count(2)->create();

        // Act
        foreach ($posts as $post) {
            $user->comments()->create([
                'content' => 'Great post!',
                'post_id' => $post->id,
            ]);
        }

        // Assert
        $this->assertCount(2, $user->comments);
        $user->comments->each(function ($comment) {
            $this->assertInstanceOf(Comment::class, $comment);
        });
    }

    public function test_user_can_be_created_with_valid_data()
    {
        // Arrange
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ];

        // Act
        $user = User::create($userData);

        // Assert
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertNotNull($user->password);
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe'
        ]);
    }

    public function test_user_email_is_unique()
    {
        // Arrange
        User::factory()->create(['email' => 'test@example.com']);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'test@example.com']);
    }

    public function test_user_timestamps_are_set_automatically()
    {
        // Arrange & Act
        $user = User::factory()->create();

        // Assert
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->updated_at);
    }

    public function test_user_full_name_attribute()
    {
        // Arrange
        $user = User::factory()->create(['name' => 'John Doe']);

        // Act
        $fullName = $user->full_name;

        // Assert
        $this->assertEquals('John Doe', $fullName);
        $this->assertIsString($fullName);
    }

    public function test_get_user_fullname()
    {
        $user=User::factory()->create(['name'=>'Ali Veli']);
        $fullName= $user->dear_full_name;

        $this->assertEquals('Ali Veli',$fullName);
        $this->assertIsString($fullName);
    }
}
