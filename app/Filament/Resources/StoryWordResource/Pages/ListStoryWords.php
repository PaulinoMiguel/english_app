<?php

namespace App\Filament\Resources\StoryWordResource\Pages;

use App\Filament\Resources\StoryWordResource;
use App\Models\StoryWord;
use App\Models\Unit;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListStoryWords extends ListRecords
{
    protected static string $resource = StoryWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadTemplate')
                ->label('Descargar plantilla')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $headers = ['unit_id', 'order', 'text', 'is_core'];
                    $samples = [
                        [101, 0, 'A', 0],
                        [101, 1, 'cruel', 1],
                        [101, 2, 'lion', 0],
                    ];
                    return response()->streamDownload(function () use ($headers, $samples) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, $headers);
                        foreach ($samples as $row) fputcsv($out, $row);
                        fclose($out);
                    }, 'plantilla-cuentos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
                        ->helperText('UTF-8, primera fila con encabezados. Columnas: unit_id, order, text, is_core (0/1).'),
                    Forms\Components\Toggle::make('replace_unit')
                        ->label('Reemplazar cuento de cada unidad afectada')
                        ->default(true)
                        ->helperText('Si está activo, borra todas las palabras del cuento de cada unidad presente en el CSV antes de importar. Recomendado para evitar duplicados.'),
                ])
                ->action(function (array $data) {
                    $relativePath = is_array($data['csv']) ? $data['csv'][0] : $data['csv'];
                    $absolutePath = Storage::disk('local')->path($relativePath);

                    if (! file_exists($absolutePath)) {
                        Notification::make()->title('No se pudo leer el archivo')->danger()->send();
                        return;
                    }

                    $report = $this->importStoryWordsFromCsv($absolutePath, (bool) ($data['replace_unit'] ?? true));

                    @unlink($absolutePath);

                    $body = "Insertadas: {$report['inserted']} · Errores: {$report['errors']}";
                    if (! empty($report['units_replaced'])) {
                        $body .= " · Unidades reemplazadas: {$report['units_replaced']}";
                    }

                    Notification::make()->title('Importación finalizada')->body($body)->success()->send();
                }),

            Actions\CreateAction::make(),
        ];
    }

    /**
     * @return array{inserted:int, errors:int, units_replaced:int}
     */
    private function importStoryWordsFromCsv(string $path, bool $replaceUnit): array
    {
        $inserted = 0; $errors = 0;

        $handle = fopen($path, 'r');
        if (! $handle) return ['inserted' => 0, 'errors' => 0, 'units_replaced' => 0];

        $headers = fgetcsv($handle);
        if (! $headers) { fclose($handle); return ['inserted' => 0, 'errors' => 0, 'units_replaced' => 0]; }
        $headers = array_map(fn ($h) => trim(strtolower(preg_replace('/^\xEF\xBB\xBF/', '', $h ?? ''))), $headers);

        $unitIds = Unit::pluck('id')->flip();

        $rows = [];
        $unitsInCsv = [];
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

            $rows[] = [
                'unit_id' => $unitId,
                'order' => (int) ($assoc['order'] ?? 0),
                'text' => $text,
                'is_core' => filter_var($assoc['is_core'] ?? 0, FILTER_VALIDATE_BOOLEAN),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $unitsInCsv[$unitId] = true;
        }
        fclose($handle);

        $unitsReplaced = 0;
        if ($replaceUnit && ! empty($unitsInCsv)) {
            StoryWord::whereIn('unit_id', array_keys($unitsInCsv))->delete();
            $unitsReplaced = count($unitsInCsv);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StoryWord::insert($chunk);
            $inserted += count($chunk);
        }

        return ['inserted' => $inserted, 'errors' => $errors, 'units_replaced' => $unitsReplaced];
    }
}
