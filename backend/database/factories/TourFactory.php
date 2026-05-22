<?php

namespace Database\Factories;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Tour>
 */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);
        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'duration_days' => fake()->numberBetween(1, 14),
            'difficulty' => fake()->randomElement(['easy', 'moderate', 'hard']),
            'route_points' => [
                ['lat' => 55.75, 'lon' => 37.61, 'label' => 'Старт'],
                ['lat' => 55.78, 'lon' => 37.65, 'label' => 'Финиш'],
            ],
            'route_center' => ['lat' => 55.76, 'lon' => 37.63, 'zoom' => 10],
            'highlights' => [fake()->sentence(4), fake()->sentence(4)],
            'is_published' => true,
        ];
    }
}
