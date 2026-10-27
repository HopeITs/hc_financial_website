<?php

namespace App\Services\Assistant;

use InvalidArgumentException;

/**
 * A tool was called with input it cannot use; the message goes back to the model so it can correct the call.
 */
class ToolInputException extends InvalidArgumentException {}
