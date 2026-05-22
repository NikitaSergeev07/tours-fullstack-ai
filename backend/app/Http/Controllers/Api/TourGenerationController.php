<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LLM\TourGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TourGenerationController extends Controller
{
    public function generate(Request $request, TourGenerator $generator): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'prompt' => 'required|string|min:5|max:1000',
        ]);

        try {
            $draft = $generator->generate($data['prompt']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Tour generation failed',
                'error' => $e->getMessage(),
            ], 502);
        }

        return response()->json(['draft' => $draft]);
    }
}
