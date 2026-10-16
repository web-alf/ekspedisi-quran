<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $videoIds = ['dQw4w9WgXcQ', '3_mxkdlLL8Y', 'abc123def45', 'xyz789ghi01'];
        $videoId = $this->faker->randomElement($videoIds);

        return [
            'title' => $this->faker->sentence(4),
            'video_url' => "https://www.youtube.com/watch?v={$videoId}",
            'thumbnail' => null,
            'caption' => $this->faker->optional()->paragraph(),
            'video_type' => 'youtube',
            'category' => $this->faker->optional()->word(),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }

    /**
     * Indicate that the video is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the video is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the video uses youtu.be URL format.
     */
    public function shortUrl(): static
    {
        return $this->state(function (array $attributes) {
            $videoId = '3_mxkdlLL8Y';
            return [
                'video_url' => "https://youtu.be/{$videoId}?si=Qhr1VIjv78FYh7iP",
            ];
        });
    }
}
