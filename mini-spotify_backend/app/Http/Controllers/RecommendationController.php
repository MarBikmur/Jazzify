<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request, RecommendationService $recommendationService)
    {
        $validated = $request->validate([
            'genre' => ['required', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $tracks = $recommendationService->getRecommendationsForUser(
            $request->user(),
            $validated['genre'],
            (int) ($validated['limit'] ?? 5),
        );

        return response()->json($tracks->values());
    }
}
