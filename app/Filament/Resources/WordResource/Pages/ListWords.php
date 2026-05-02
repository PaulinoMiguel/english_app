<?php

namespace App\Filament\Resources\WordResource\Pages;

use App\Filament\Resources\WordResource;
use App\Models\Unit;
use App\Models\Word;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListWords extends ListRecords
{
    protected static string $resource = WordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadTemplate')
                ->label('Descargar plantilla')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $headers = ['unit_id', 'text', 'type', 'phonetic', 'translation', 'definition', 'example', 'audio_file', 'definition_audio', 'example_audio'];
                    $sample = [101, 'afraid', 'adj', 'əˈfreɪd', 'tener miedo', 'When someone is afraid they feel fear', 'The woman was afraid of what she saw', 'afraid.mp3', 'afraid_D.mp3', 'afraid_E.mp3'];
                    return response()->streamDownload(function () use ($headers, $sample) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM (Excel-friendly)
                        fputcsv($out, $headers);
                        fputcsv($out, $sample);
                        fclose($out);
                    }, 'plantilla-palabras.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),

            Actions\Action::make('importCsv')
                ->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('csv')
                        ->label('Archivo CSV')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->helperText('UTF-8, primera fila con encabezados. Columnas requeridas: unit_id, text. El resto son opcionales.'),
                    Forms\Components\Toggle::make('update_existing')
                        ->label('Actualizar palabras existentes')
                        ->default(true)
                        ->helperText('Si una palabra (mismo text + unit_id) ya existe, actualiza sus campos. Si está apagado, se omite.'),
                ])
                ->action(function (array $data) {
                    $relativePath = is_array($data['csv']) ? $data['csv'][0] : $data['csv'];
                    $absolutePath = Storage::disk('local')->path($relativePath);

                    if (! file_exists($absolutePath)) {
                        Notification::make()
                            ->title('No se pudo leer el archivo')
                            ->danger()
                            ->send();
                        return;
                    }

                    $report = $this->importWordsFromCsv($absolutePath, (bool) ($data['update_existing'] ?? true));

                    @unlink($absolutePath);

                    $body = "Creadas: {$report['created']} · Actualizadas: {$report['updated']} · Omitidas: {$report['skipped']}";
                    if ($report['errors'] > 0) {
                        $body .= " · Errores: {$report['errors']}";
                    }

                    Notification::make()
                        ->title('Importación finalizada')
                        ->body($body)
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }

    /**
     * @return array{created:int, updated:int, skipped:int, errors:int}
     */
    private function importWordsFromCsv(string $path, bool $updateExisting): array
    {
        $created = 0; $updated = 0; $skipped = 0; $errors = 0;

        $handle = fopen($path, 'r');
        if (! $handle) return compact('created', 'updated', 'skipped', 'errors');

        // Skip BOM if present
        $first = fgets($handle);
        if ($first === false) { fclose($handle); return compact('created', 'updated', 'skipped', 'errors'); }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        rewind($handle);
        if (str_starts_with(fread($handle, 3), "\xEF\xBB\xBF") === false) {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (! $headers) { fclose($handle); return compact('created', 'updated', 'skipped', 'errors'); }
        $headers = array_map(fn ($h) => trim(strtolower($h ?? '')), $headers);

        $unitIds = Unit::pluck('id')->flip();

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) continue;

            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = isset($row[$i]) ? trim($row[$i]) : null;
            }

            $unitId = (int) ($assoc['unit_id'] ?? 0);
            $text = $assoc['text'] ?? '';

            if ($unitId === 0 || $text === '' || ! $unitIds->has($unitId)) {
                $errors++;
                continue;
            }

            $payload = [
                'type' => $assoc['type'] ?: null,
                'phonetic' => ($assoc['phonetic'] ?? null) === 'none' ? null : ($assoc['phonetic'] ?: null),
                'translation' => $assoc['translation'] ?: null,
                'definition' => $assoc['definition'] ?: null,
                'example' => $assoc['example'] ?: null,
                'audio_file' => $assoc['audio_file'] ?: null,
                'definition_audio' => $assoc['definition_audio'] ?: null,
                'example_audio' => $assoc['example_audio'] ?: null,
            ];

            $existing = Word::where('unit_id', $unitId)->where('text', $text)->first();

            if ($existing) {
                if ($updateExisting) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                Word::create(array_merge($payload, ['unit_id' => $unitId, 'text' => $text]));
                $created++;
            }
        }

        fclose($handle);
        return compact('created', 'updated', 'skipped', 'errors');
    }
}
