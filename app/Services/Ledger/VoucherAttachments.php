<?php

namespace App\Services\Ledger;

use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;

/** Files kept with a voucher, on the private disk: PDF, JPG or PNG up to 5 MB, at most ten at a time. */
class VoucherAttachments
{
    public const MAX_FILES = 10;

    /** @return array<string, list<string>> validation rules for a list of uploads under the given property name */
    public function rules(string $property = 'attachments', int $max = self::MAX_FILES): array
    {
        return [
            $property => ['array', 'max:'.$max],
            $property.'.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(string $property = 'attachments'): array
    {
        return [
            $property.'.max' => __('ledger.builder.files_count'),
            $property.'.*.file' => __('ledger.builder.files_type'),
            $property.'.*.mimes' => __('ledger.builder.files_type'),
            $property.'.*.max' => __('ledger.builder.files_size'),
        ];
    }

    /**
     * Stores the files for a voucher and records them. Returns the paths written, so the caller can delete them if the
     * rest of its work is rolled back.
     *
     * @param  iterable<TemporaryUploadedFile>  $files
     * @return list<string>
     */
    public function store(string $voucherId, iterable $files, int $uploadedBy): array
    {
        $stored = [];

        foreach ($files as $file) {
            $name = Str::uuid()->toString().'.'.($file->guessExtension() ?: 'bin');
            $path = $file->storeAs('voucher-attachments/'.$voucherId, $name, 'local');

            if ($path === false) {
                $this->forget($stored);

                throw new RuntimeException('The attachment could not be stored.');
            }

            $stored[] = $path;

            VoucherAttachment::query()->create([
                'voucher_id' => $voucherId,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                'stored_path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                'uploaded_by' => $uploadedBy,
            ]);
        }

        return $stored;
    }

    /** The voucher a file belongs to, from the start of its name (CPV-2026-0012_invoice.pdf), or null. */
    public function voucherOf(string $fileName): ?string
    {
        return preg_match('/^(CRV|CPV|TRF|ALC|JV|OPN)-(\d{4})-(\d{4,})/i', basename($fileName), $m) === 1
            ? strtoupper($m[1]).'-'.$m[2].'-'.$m[3]
            : null;
    }

    /**
     * Attaches each file to its voucher, all or none: a file whose voucher is missing, rejected or unknown refuses the lot.
     * Every voucher gets an audit row. Returns the number of files attached, or 0 with nothing written.
     *
     * @param  iterable<TemporaryUploadedFile>  $files
     */
    public function attachByName(iterable $files, int $uploadedBy): int
    {
        $pairs = [];

        foreach ($files as $file) {
            $id = $this->voucherOf($file->getClientOriginalName());

            if ($id === null || ! Voucher::query()->whereKey($id)->exists()) {
                return 0;
            }

            $pairs[] = [$id, $file];
        }

        if ($pairs === []) {
            return 0;
        }

        $stored = [];

        try {
            DB::transaction(function () use ($pairs, $uploadedBy, &$stored) {
                foreach ($pairs as [$id, $file]) {
                    array_push($stored, ...$this->store($id, [$file], $uploadedBy));
                    VoucherAudit::query()->create([
                        'voucher_id' => $id, 'action' => 'attach', 'field' => 'attachment',
                        'new_value' => Str::limit(basename($file->getClientOriginalName()), 250, ''), 'changed_by' => $uploadedBy,
                    ]);
                }
            });
        } catch (Throwable $e) {
            $this->forget($stored);

            throw $e;
        }

        return count($pairs);
    }

    /** @param  list<string>  $paths */
    public function forget(array $paths): void
    {
        Storage::disk('local')->delete($paths);
    }
}
