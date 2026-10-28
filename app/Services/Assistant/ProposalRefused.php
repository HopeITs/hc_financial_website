<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * An entry the assistant prepared could not be saved; `messages` say why, in the person's language.
 */
class ProposalRefused extends RuntimeException
{
    /** @param  list<string>  $messages */
    public function __construct(public readonly array $messages)
    {
        parent::__construct(implode(' ', $messages));
    }
}
