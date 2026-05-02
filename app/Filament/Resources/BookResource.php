<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?string $modelLabel = 'libro';

    protected static ?string $pluralModelLabel = 'libros';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('level')
                    ->label('Nivel')
                    ->options([
                        'Beginner' => 'Beginner',
                        'Elementary' => 'Elementary',
                        'Pre-Intermediate' => 'Pre-Intermediate',
                        'Intermediate' => 'Intermediate',
                        'Upper-Intermediate' => 'Upper-Intermediate',
                        'Advanced' => 'Advanced',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('total_units')
                        ->label('Total de unidades')
                        ->required()
                        ->numeric()
                        ->default(30),
                    Forms\Components\TextInput::make('order')
                        ->label('Orden')
                        ->required()
                        ->numeric()
                        ->default(0),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('level')
                    ->label('Nivel')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Beginner' => 'success',
                        'Elementary' => 'success',
                        'Pre-Intermediate' => 'info',
                        'Intermediate' => 'info',
                        'Upper-Intermediate' => 'warning',
                        'Advanced' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('units_count')
                    ->label('Unidades')
                    ->counts('units'),
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
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
