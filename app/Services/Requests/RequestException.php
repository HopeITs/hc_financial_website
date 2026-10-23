<?php

namespace App\Services\Requests;

use RuntimeException;

/** A request action that the rules refuse: a wrong state, a missing quote, a bad line. Carries one message per problem. */
class RequestException extends RuntimeException
{
    /** @param  non-empty-list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct($problems[0].(count($problems) > 1 ? ' (+'.(count($problems) - 1).')' : ''));
    }
}
