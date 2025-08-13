<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => $this->faker->paragraph(),
            'approved' => $this->faker->boolean(80), // 80% chance of being approved
            'post_id' => Post::factory(),
            'user_id' => User::factory()
        ];
    }

    public function approved()
    {
        return $this->state(function (array $attributes) {
            return [
                'approved' => true,
            ];
        });
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'approved' => false,
            ];
        });
    }
}
