<?php

namespace App\Services\Ledger;

use RuntimeException;

/** A city rate that cannot be proposed, approved or rejected; the message is ready to show. */
class TransferRateException extends RuntimeException {}
