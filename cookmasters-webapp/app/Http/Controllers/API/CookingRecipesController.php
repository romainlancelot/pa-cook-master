<?php

namespace App\Http\Controllers\API;

use App\Models\CookingRecipes;
use App\Models\Transactions;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CookingRecipesController extends Controller
{
    /**
     * Get list of cooking recipes.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCookingRecipes()
    {
        $data = CookingRecipes::all();
        foreach ($data as $key => $value) {
            $data[$key]['average_rating'] = $value->averageRating();
            $data[$key]['nb_orders'] = Transactions::where('cooking_recipe_id', $value->id)->count();
            $data[$key]['ingredients'] = $value->ingredients()->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Cooking Recipes List',
            'data' => $data
        ]);
    }
}
