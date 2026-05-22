<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'duration_days' => $this->duration_days,
            'difficulty' => $this->difficulty,
            'cover_image' => $this->cover_image ?? optional($this->photos->first())->url,
            'categories' => $this->whenLoaded('categories', fn () =>
                $this->categories->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name, 'icon' => $c->icon])
            ),
            'price_from' => $this->price_from,
            'score' => $this->when(isset($this->score), fn () => round((float) $this->score, 4)),
            'photos' => $this->whenLoaded('photos', fn () =>
                $this->photos->take(3)->map(fn ($p) => ['url' => $p->url, 'alt' => $p->alt])
            ),
        ];
    }
}
