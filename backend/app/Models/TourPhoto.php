<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TourPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['tour_id', 'path', 'alt', 'sort_order'];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function getUrlAttribute(): string
    {
        // Allow external URLs (seeded via Picsum) and uploads from the admin.
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }
        return Storage::disk('public')->url($this->path);
    }
}
