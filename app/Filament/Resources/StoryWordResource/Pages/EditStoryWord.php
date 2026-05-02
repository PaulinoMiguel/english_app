<?php

namespace App\Filament\Resources\StoryWordResource\Pages;

use App\Filament\Resources\StoryWordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoryWord extends EditRecord
{
    protected static string $resource = StoryWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
