<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use App\Models\Tour;
use App\Services\Tours\TourIndexer;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Belt-and-braces: the model already hides `embedding`, but if a
        // future change reverses that we still want EditTour to be safe.
        unset($data['embedding']);

        // `highlights` is a Repeater::simple() — Filament expects a flat
        // list of scalars, which is exactly how the model stores them.
        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Tour $tour */
        $tour = $this->record;
        app(TourIndexer::class)->index($tour);
    }
}
