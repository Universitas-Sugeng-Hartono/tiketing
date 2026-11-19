<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateUploadsToObjectStorage extends Command
{
    protected $signature = 'uploads:migrate-to-object-storage
                            {--disk=s3 : Target disk (default s3)}
                            {--from=public : Source disk (default public)}
                            {--delete : Hapus file sumber setelah sukses disalin}
                            {--dry-run : Hanya tampilkan rencana, tidak menyalin}';

    protected $description = 'Salin lampiran/template/avatar/bukti dari disk lokal ke Object Storage (S3-compatible) tanpa mengubah path di database';

    /** @var list<string> */
    private array $folders = [
        'attachments',
        'completion-proofs',
        'category-templates',
        'avatars',
    ];

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('disk');
        $dryRun = (bool) $this->option('dry-run');
        $delete = (bool) $this->option('delete');

        if ($from === $to) {
            $this->error('Source dan target disk tidak boleh sama.');

            return self::FAILURE;
        }

        if ($to === 's3' && blank(config('filesystems.disks.s3.bucket'))) {
            $this->error('AWS_BUCKET belum diisi di .env. Minta akses Object Storage ke Pak Deny dulu.');
            if (! $dryRun) {
                return self::FAILURE;
            }
            $this->warn('Lanjut dry-run hanya untuk daftar file sumber.');
        }

        $source = Storage::disk($from);
        $targetReady = filled(config('filesystems.disks.'.$to.'.bucket'))
            || $to !== 's3';
        $target = $targetReady ? Storage::disk($to) : null;

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->folders as $folder) {
            $this->line("Folder: {$folder}/");

            try {
                $files = $source->allFiles($folder);
            } catch (\Throwable $e) {
                $this->warn("  Tidak bisa baca {$folder}: ".$e->getMessage());

                continue;
            }

            foreach ($files as $path) {
                if ($target) {
                    try {
                        if ($target->exists($path)) {
                            $this->line("  skip (sudah ada): {$path}");
                            $skipped++;

                            continue;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("  tidak bisa cek target {$path}: ".$e->getMessage());
                    }
                }

                if ($dryRun) {
                    $this->line("  [dry-run] salin: {$path}");
                    $copied++;

                    continue;
                }

                if (! $target) {
                    $this->error("  GAGAL: target disk {$to} belum siap (kredensial kosong)");
                    $failed++;

                    continue;
                }

                try {
                    $stream = $source->readStream($path);
                    if ($stream === false) {
                        throw new \RuntimeException('Gagal membuka stream sumber');
                    }
                    $target->writeStream($path, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    if ($delete) {
                        $source->delete($path);
                    }

                    $this->info("  OK: {$path}");
                    $copied++;
                } catch (\Throwable $e) {
                    $this->error("  GAGAL: {$path} — ".$e->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("Selesai. Disalin: {$copied}, dilewati: {$skipped}, gagal: {$failed}.");
        if (! $dryRun && $copied > 0) {
            $this->comment('Path di database tidak diubah. Set FILESYSTEM_UPLOAD_DISK='.$to.' lalu php artisan config:clear');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
