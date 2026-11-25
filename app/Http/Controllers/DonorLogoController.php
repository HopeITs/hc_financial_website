<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A donor's logo, for the papers printed for that donor (finance request of 2026-11-28). Any signed-in person who
 * prints a donor's paper sees its logo; the file stays on the private disk.
 */
class DonorLogoController extends Controller
{
    public function __invoke(Donor $donor): BinaryFileResponse
    {
        $file = $donor->logoFile();
        abort_if($file === null, 404);

        return response()->file($file, ['Cache-Control' => 'private, max-age=86400']);
    }
}
