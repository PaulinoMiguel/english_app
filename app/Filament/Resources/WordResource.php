<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WordResource\Pages;
use App\Models\Word;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WordResource extends Resource
{
    protected static ?string $model = Word::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?string $modelLabel = 'palabra';

    protected static ?string $pluralModelLabel = 'palabras';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos básicos')
                    ->schema([
                        Forms\Components\Select::make('unit_id')
                            ->label('Unidad')
                            ->relationship('unit', 'title', fn ($query) => $query->orderBy('book_id')->orderBy('unit_number'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "Libro {$record->book_id} · Unidad {$record->unit_number}: {$record->title}")
                            ->searchable(['title', 'unit_number'])
                            ->preload()
                            ->required(),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('text')
                                ->label('Palabra (inglés)')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('type')
                                ->label('Tipo')
                                ->placeholder('adj, v, n, adv...')
                                ->maxLength(20),
                            Forms\Components\TextInput::make('phonetic')
                                ->label('Fonética')
                                ->placeholder('/əˈfreɪd/')
                                ->maxLength(255),
                        ]),

                        Forms\Components\Textarea::make('translation')
                            ->label('Traducción al español')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('definition')
                            ->label('Definición (inglés)')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('example')
                            ->label('Ejemplo (inglés)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Audios')
                    ->description('Sube archivos MP3 o introduce el nombre del archivo si ya está en /storage/audio/.')
                    ->schema([
                        Forms\Components\FileUpload::make('audio_file')
                            ->label('Audio de la palabra')
                            ->disk('public')
                            ->directory('audio')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])
                            ->preserveFilenames()
                            ->storeFileNamesIn('audio_file')
                            ->maxSize(5120),

                        Forms\Components\FileUpload::make('definition_audio')
                            ->label('Audio de la definición')
                            ->disk('public')
                            ->directory('audio')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])
                            ->preserveFilenames()
                            ->storeFileNamesIn('definition_audio')
                            ->maxSize(5120),

                        Forms\Components\FileUpload::make('example_audio')
                            ->label('Audio del ejemplo')
                            ->disk('public')
                            ->directory('audio')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])
                            ->preserveFilenames()
                            ->storeFileNamesIn('example_audio')
                            ->maxSize(5120),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unit.book.title')
                    ->label('Libro')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label('U#')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('text')
                    ->label('Palabra')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('translation')
                    ->label('Traducción')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('audio_file')
                    ->label('Audio')
                    ->boolean()
                    ->trueIcon('heroicon-o-speaker-wave')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->getStateUsing(fn ($record) => ! empty($record->audio_file)),
                Tables\Columns\IconColumn::make('definition_audio')
                    ->label('Def')
                    ->boolean()
                    ->trueIcon('heroicon-o-speaker-wave')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->getStateUsing(fn ($record) => ! empty($record->definition_audio)),
                Tables\Columns\IconColumn::make('example_audio')
                    ->label('Ej')
                    ->boolean()
                    ->trueIcon('heroicon-o-speaker-wave')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->getStateUsing(fn ($record) => ! empty($record->example_audio)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit')
                    ->relationship('unit', 'title')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('missing_audio')
                    ->label('Sin audio')
                    ->query(fn (Builder $query) => $query->where(function ($q) {
                        $q->whereNull('audio_file')
                          ->orWhereNull('definition_audio')
                          ->orWhereNull('example_audio');
                    })),
            ])
            ->defaultSort('unit_id')
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
            'index' => Pages\ListWords::route('/'),
            'create' => Pages\CreateWord::route('/create'),
            'edit' => Pages\EditWord::route('/{record}/edit'),
        ];
    }
}
