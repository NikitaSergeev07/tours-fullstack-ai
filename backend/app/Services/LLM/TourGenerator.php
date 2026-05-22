<?php

namespace App\Services\LLM;

interface TourGenerator
{
    /**
     * Generate a draft tour from a free-form admin prompt.
     *
     * Must return a structured array matching the GenerateTourRequest schema
     * so the admin form can pre-fill its inputs.
     *
     * @return array{
     *   title: string,
     *   short_description: string,
     *   description: string,
     *   duration_days: int,
     *   difficulty: string,
     *   highlights: list<string>,
     *   categories: list<string>,
     *   route_points: list<array{lat: float, lon: float, label?: string}>,
     *   route_center: array{lat: float, lon: float, zoom?: int},
     *   dates: list<array{start_date: string, end_date: string, price: float, currency: string, seats_total: int}>
     * }
     */
    public function generate(string $prompt): array;
}
