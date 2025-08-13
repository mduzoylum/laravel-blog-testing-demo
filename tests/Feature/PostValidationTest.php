<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_creation_validates_required_fields()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/posts', []);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'content']);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'title',
                'content'
            ]
        ]);
    }

    public function test_post_creation_validates_title_max_length()
    {
        // Arrange
        $user = User::factory()->create();
        $longTitle = str_repeat('a', 256); // 256 characters, max is 255

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => $longTitle,
                'content' => 'Valid content with more than 10 characters',
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_post_creation_validates_minimum_content_length()
    {
        // Arrange
        $user = User::factory()->create();
        $postData = [
            'title' => 'Valid Title',
            'content' => 'Short', // Less than 10 characters
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/posts', $postData);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
        
        $errorMessage = $response->json('errors.content.0');
        $this->assertStringContainsString('en az 10 karakter', $errorMessage);
    }

    public function test_post_creation_validates_status_values()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => 'Valid Title',
                'content' => 'Valid content with enough characters',
                'status' => 'invalid_status'
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_post_creation_accepts_valid_status_values()
    {
        // Arrange
        $user = User::factory()->create();

        // Test draft status
        $draftResponse = $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => 'Draft Post',
                'content' => 'This is a draft post content.',
                'status' => 'draft'
            ]);

        // Test published status
        $publishedResponse = $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => 'Published Post',
                'content' => 'This is a published post content.',
                'status' => 'published'
            ]);

        // Assert
        $draftResponse->assertStatus(201);
        $publishedResponse->assertStatus(201);
        
        $this->assertDatabaseHas('posts', [
            'title' => 'Draft Post',
            'status' => 'draft'
        ]);
        
        $this->assertDatabaseHas('posts', [
            'title' => 'Published Post',
            'status' => 'published'
        ]);
    }

    public function test_post_update_validates_fields()
    {
        // Arrange
        $user = User::factory()->create();
        $post = \App\Models\Post::factory()->create(['user_id' => $user->id]);

        // Act - Test with invalid title length
        $response = $this->actingAs($user)
            ->putJson("/api/posts/{$post->id}", [
                'title' => str_repeat('a', 256),
                'content' => 'Valid content'
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_post_update_validates_content_minimum_length()
    {
        // Arrange
        $user = User::factory()->create();
        $post = \App\Models\Post::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->putJson("/api/posts/{$post->id}", [
                'title' => 'Valid Title',
                'content' => 'Short'
            ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
    }

    public function test_post_validation_messages_are_in_turkish()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/posts', [
                'content' => 'Short'
            ]);

        // Assert
        $response->assertStatus(422);
        
        $errors = $response->json('errors');
        $this->assertStringContainsString('zorunludur', $errors['title'][0]);
        $this->assertStringContainsString('en az 10 karakter', $errors['content'][0]);
    }

    /**
     * @dataProvider validPostDataProvider
     */
    public function test_post_creation_with_valid_data($postData)
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/posts', $postData);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'title' => $postData['title'],
            'user_id' => $user->id
        ]);
    }

    public static function validPostDataProvider(): array
    {
        return [
            'minimum valid data' => [
                ['title' => 'Valid Title', 'content' => 'Valid content with enough characters']
            ],
            'with draft status' => [
                ['title' => 'Draft Post', 'content' => 'Draft content here', 'status' => 'draft']
            ],
            'with published status' => [
                ['title' => 'Published Post', 'content' => 'Published content here', 'status' => 'published']
            ],
            'with maximum title length' => [
                ['title' => str_repeat('a', 255), 'content' => 'Valid content here']
            ],
            'with long content' => [
                ['title' => 'Long Content Post', 'content' => str_repeat('This is content. ', 100)]
            ],
        ];
    }
}