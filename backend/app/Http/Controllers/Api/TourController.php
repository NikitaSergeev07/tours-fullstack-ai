<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourResource;
use App\Http\Resources\TourSummaryResource;
use App\Models\Category;
use App\Models\Tour;
use App\Services\Tours\TourSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TourController extends Controller
{
    public function __construct(private readonly TourSearch $search)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:200',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:64',
            'duration_min' => 'nullable|integer|min:1|max:60',
            'duration_max' => 'nullable|integer|min:1|max:60',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'difficulty' => 'nullable|in:easy,moderate,hard',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|in:newest,price_asc,price_desc,duration_asc,duration_desc',
            'per_page' => 'nullable|integer|min:1|max:48',
        ]);

        $perPage = (int) ($filters['per_page'] ?? 12);
        $paginator = $this->search->paginate($filters, $perPage);

        return TourSummaryResource::collection($paginator);
    }

    public function show(Tour $tour): TourResource|JsonResponse
    {
        if (! $tour->is_published) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $tour->load(['photos', 'categories', 'dates']);
        return new TourResource($tour);
    }

    public function filters(): JsonResponse
    {
        // Static-ish reference data used to build the filter UI on the frontend.
        $categories = Category::query()
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'icon']);

        return response()->json([
            'categories' => $categories,
            'difficulties' => [
                ['value' => 'easy', 'label' => 'Лёгкий'],
                ['value' => 'moderate', 'label' => 'Средний'],
                ['value' => 'hard', 'label' => 'Сложный'],
            ],
            'sort_options' => [
                ['value' => 'newest', 'label' => 'Новинки'],
                ['value' => 'price_asc', 'label' => 'Сначала дешевле'],
                ['value' => 'price_desc', 'label' => 'Сначала дороже'],
                ['value' => 'duration_asc', 'label' => 'Короче по дням'],
                ['value' => 'duration_desc', 'label' => 'Дольше по дням'],
            ],
            'duration' => [
                'min' => 1,
                'max' => (int) (Tour::query()->max('duration_days') ?? 14),
            ],
            'price' => [
                'min' => 0,
                'max' => (int) (\DB::table('tour_dates')->max('price') ?? 200_000),
            ],
        ]);
    }
}
