<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'duration_days' => $this->duration_days,
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty,
            'highlights' => $this->highlights ?? [],
            'cover_image' => $this->cover_image,
            'categories' => $this->whenLoaded('categories', fn () =>
                $this->categories->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name, 'icon' => $c->icon])
            ),
            'photos' => $this->whenLoaded('photos', fn () =>
                $this->photos->map(fn ($p) => ['url' => $p->url, 'alt' => $p->alt])
            ),
            'dates' => $this->whenLoaded('dates', fn () =>
                $this->dates->map(fn ($d) => [
                    'id' => $d->id,
                    'start_date' => $d->start_date?->toDateString(),
                    'end_date' => $d->end_date?->toDateString(),
                    'price' => (float) $d->price,
                    'currency' => $d->currency,
                    'seats_total' => $d->seats_total,
                    'seats_available' => $d->seats_available,
                ])
            ),
            'route' => [
                'points' => $this->route_points ?? [],
                'center' => $this->route_center,
            ],
            'price_from' => $this->whenLoaded('dates', fn () => $this->price_from),
            'score' => $this->when(isset($this->score), fn () => (float) $this->score),
        ];
    }
}
