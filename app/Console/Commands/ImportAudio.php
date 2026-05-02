<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportAudio extends Command
{
    protected $signature = 'audio:import
                            {--source= : Carpeta de origen con los MP3 (por defecto: el proyecto Flutter)}
                            {--overwrite : Sobrescribe archivos existentes en destino}';

    protected $description = 'Copia los archivos MP3 al directorio storage/app/public/audio';

    public function handle(): int
    {
        $defaultSource = 'C:/Users/Home/Desktop/english_project/english_project/assets/audio';
        $source = rtrim($this->option('source') ?: $defaultSource, "/\\");
        $destination = storage_path('app/public/audio');

        if (! is_dir($source)) {
            $this->error("La carpeta de origen no existe: {$source}");
            return self::FAILURE;
        }

        if (! is_dir($destination)) {
            File::makeDirectory($destination, 0755, true);
            $this->info("Carpeta de destino creada: {$destination}");
        }

        $files = collect(File::files($source))
            ->filter(fn ($f) => strtolower($f->getExtension()) === 'mp3');

        $total = $files->count();
        if ($total === 0) {
            $this->warn('No se encontraron archivos MP3 en la carpeta de origen.');
            return self::SUCCESS;
        }

        $overwrite = (bool) $this->option('overwrite');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $copied = 0;
        $skipped = 0;
        foreach ($files as $file) {
            $target = $destination.DIRECTORY_SEPARATOR.$file->getFilename();
            if (! $overwrite && file_exists($target)) {
                $skipped++;
            } else {
                File::copy($file->getPathname(), $target);
                $copied++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Copiados: {$copied}");
        $this->info("Omitidos (ya existían): {$skipped}");
        $this->info("Total en origen: {$total}");

        $this->line('');
        $this->comment('Recuerda ejecutar: php artisan storage:link');

        return self::SUCCESS;
    }
}
