<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Equipment>
 */
class EquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $photos = [
            fake()->image('public/images/equipments', 360, 360, 'animals', true, true, 'cats', true, 'jpg'),
            fake()->image('public/images/equipments', 360, 360, 'animals', true, true, 'cats', true, 'jpg'),
            fake()->image('public/images/equipments', 360, 360, 'animals', true, true, 'cats', true, 'jpg'),        ];
        return [
            'name' => fake()->name(),
            'photos' => json_encode($photos),
            'description' => fake()->paragraph(),
            'availabilities' => fake()->sentence(5),
            'availablequantity' => fake()->randomDigit(),
            'price' => fake()->sentence(1),
        ];
    }
}