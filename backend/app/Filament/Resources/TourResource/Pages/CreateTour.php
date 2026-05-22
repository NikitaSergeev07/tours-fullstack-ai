<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use App\Models\Tour;
use App\Services\Tours\TourIndexer;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourResource::class;

    protected function afterCreate(): void
    {
        /** @var Tour $tour */
        $tour = $this->record;
        app(TourIndexer::class)->index($tour);
    }
}
