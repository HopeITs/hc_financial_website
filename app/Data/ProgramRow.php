<?php

namespace App\Data;

/**
 * One line of the programs page: a legacy program or a web one, with its active projects and their balances.
 * Money is a two-decimal string, never a float.
 */
final readonly class ProgramRow
{
    public function __construct(
        public string $source,
        public int $id,
        public string $name,
        public ?string $description,
        public ?string $status,
        public int $activeProjects,
        public string $syp,
        public string $usd,
    ) {}

    public function key(): string
    {
        return $this->source.':'.$this->id;
    }

    public function isWeb(): bool
    {
        return $this->source === 'web';
    }
}
