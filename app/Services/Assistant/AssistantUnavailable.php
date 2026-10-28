<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * The model could not answer: no API key, the API unreachable, or a refused call. The message is for the person.
 */
class AssistantUnavailable extends RuntimeException {}
