<?php

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Files kept with a funding contract on the private disk, like voucher attachments: the signed contract, the reports
 * sent to the donor and the donor's approval of the closing. PDF, JPG or PNG up to 10 MB.
 */
class ContractFiles
{
    /** Validation rule for one contract file. */
    public const RULE = 'file|mimes:pdf,jpg,jpeg,png|max:10240';

    /**
     * Stores a file under the contract and returns its path and original name.
     *
     * @return array{path: string, name: string}
     */
    public function store(int $contractId, UploadedFile $file): array
    {
        $name = Str::uuid()->toString().'.'.($file->guessExtension() ?: 'bin');
        $path = $file->storeAs('contract-files/'.$contractId, $name, 'local');

        if ($path === false) {
            throw new RuntimeException('The contract file could not be stored.');
        }

        return ['path' => $path, 'name' => Str::limit(basename($file->getClientOriginalName()), 250, '')];
    }

    public function forget(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('local')->delete($path);
        }
    }
}
