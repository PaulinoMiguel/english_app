<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?string $modelLabel = 'unidad';

    protected static ?string $pluralModelLabel = 'unidades';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('book_id')
                    ->label('Libro')
                    ->relationship('book', 'title', fn ($query) => $query->orderBy('order'))
                    ->required(),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('unit_number')
                        ->label('Número de unidad')
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(255),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                Tables\Columns\TextColumn::make('book.title')
                    ->label('Libro')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unit_number')
                    ->label('U#')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('words_count')
                    ->label('Palabras')
                    ->counts('words'),
                Tables\Columns\TextColumn::make('story_words_count')
                    ->label('Palabras del cuento')
                    ->counts('storyWords'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('book')
                    ->relationship('book', 'title')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
