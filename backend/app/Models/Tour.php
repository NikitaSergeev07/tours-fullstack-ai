<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;

class Tour extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'description',
        'duration_days',
        'duration_hours',
        'difficulty',
        'cover_image',
        'route_points',
        'route_center',
        'highlights',
        'is_published',
        'embedding',
    ];

    protected $casts = [
        'route_points' => 'array',
        'route_center' => 'array',
        'highlights' => 'array',
        'is_published' => 'boolean',
        'embedding' => Vector::class,
    ];

    /**
     * Hide the raw vector from JSON/array conversions: the API resource
     * doesn't need it, and Livewire can't serialize the Vector object
     * (Filament's EditTour fills the form from $record->toArray() and
     * would crash on dehydration otherwise).
     */
    protected $hidden = ['embedding'];

    protected static function booted(): void
    {
        static::saving(function (Tour $tour) {
            if (empty($tour->slug)) {
                $tour->slug = Str::slug($tour->title).'-'.Str::lower(Str::random(5));
            }
        });
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TourPhoto::class)->orderBy('sort_order');
    }

    public function dates(): HasMany
    {
        return $this->hasMany(TourDate::class)->orderBy('start_date');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'tour_category');
    }

    /** Cheapest current/future price across linked dates, or null if no dates. */
    protected function priceFrom(): Attribute
    {
        return Attribute::make(get: function () {
            $upcoming = $this->dates->filter(fn ($d) => $d->start_date >= now()->toDateString());
            $set = $upcoming->isNotEmpty() ? $upcoming : $this->dates;
            return $set->min('price');
        });
    }

    /** Concatenated string used to compute embeddings — keep in sync with the indexer. */
    public function embeddingText(): string
    {
        $cats = $this->relationLoaded('categories')
            ? $this->categories->pluck('name')->implode(', ')
            : $this->categories()->pluck('name')->implode(', ');
        $highlights = is_array($this->highlights) ? implode('. ', $this->highlights) : '';

        return collect([
            $this->title,
            $this->short_description,
            $this->description,
            $highlights,
            $cats ? "Категории: {$cats}" : null,
            $this->duration_days ? "Длительность: {$this->duration_days} дн." : null,
        ])->filter()->implode("\n");
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
