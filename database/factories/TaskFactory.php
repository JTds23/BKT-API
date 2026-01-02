<?php

namespace Database\Factories;

use App\Models\ServiceProvider;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'service_provider_id' => ServiceProvider::factory(),
            'name' => fake()->randomElement([
                'Lawn Mowing',
                'Tree Trimming',
                'Oven Cleaning',
                'Window Cleaning',
                'Carpet Cleaning',
                'Gutter Cleaning',
                'Pressure Washing',
                'Garden Maintenance',
                'Hedge Trimming',
                'Car Washing',
            ]),
            'price' => fake()->randomFloat(2, 15, 150),
        ];
    }
}
