<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoryWordResource\Pages;
use App\Models\StoryWord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoryWordResource extends Resource
{
    protected static ?string $model = StoryWord::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?string $modelLabel = 'palabra del cuento';

    protected static ?string $pluralModelLabel = 'palabras del cuento';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('unit_id')
                    ->label('Unidad')
                    ->relationship('unit', 'title', fn ($query) => $query->orderBy('book_id')->orderBy('unit_number'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "Libro {$record->book_id} · Unidad {$record->unit_number}: {$record->title}")
                    ->searchable(['title', 'unit_number'])
                    ->preload()
                    ->required(),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('order')
                        ->label('Orden')
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('text')
                        ->label('Texto')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_core')
                        ->label('Es palabra clave (hueco)')
                        ->helperText('Si está activo, esta palabra será un hueco en el ejercicio Complete Story.'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                Tables\Columns\TextColumn::make('unit.title')
                    ->label('Unidad')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('text')
                    ->label('Palabra')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_core')
                    ->label('Hueco')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit')
                    ->relationship('unit', 'title')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_core')
                    ->label('Es hueco'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoryWords::route('/'),
            'create' => Pages\CreateStoryWord::route('/create'),
            'edit' => Pages\EditStoryWord::route('/{record}/edit'),
        ];
    }
}
