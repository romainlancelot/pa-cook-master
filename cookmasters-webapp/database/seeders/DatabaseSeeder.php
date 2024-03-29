<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            UsersSeeder::class,
            CookingRecipesSeeder::class,
            RecipesStepsSeeder::class,
            IngredientsSeeder::class,
            IngredientsRecipesSeeder::class,
            CommentsSeeder::class,
            TransactionsSeeder::class,
            CoursesSeeder::class,
            CoursesModuleSeeder::class,
            RoomSeeder::class,
            EquipmentSeeder::class,
            WorkshopSeeder::class,
        ]);
    }
}
