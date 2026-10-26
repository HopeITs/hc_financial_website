<?php

namespace App\Data;

/**
 * Which projects and cash accounts of the Windows program a person may see.
 * `unrestricted` means everything; otherwise the id lists are the whole of what is allowed, and an empty list means nothing.
 */
final readonly class DataScope
{
    /**
     * @param  list<int>  $projectAccountIds  Project accounts (`account.ID`)
     * @param  list<int>  $cashAccountIds  Cash accounts (`cashaccount.ID`)
     * @param  list<int>  $exchangeAccountIds
     * @param  list<int>  $personalAccountIds
     */
    public function __construct(
        public bool $unrestricted,
        public array $projectAccountIds = [],
        public array $cashAccountIds = [],
        public array $exchangeAccountIds = [],
        public array $personalAccountIds = [],
    ) {}

    public static function everything(): self
    {
        return new self(true);
    }

    public function allowsProject(int $accountId): bool
    {
        return $this->unrestricted || in_array($accountId, $this->projectAccountIds, true);
    }

    public function allowsCashAccount(int $cashAccountId): bool
    {
        return $this->unrestricted || in_array($cashAccountId, $this->cashAccountIds, true);
    }

    /**
     * Project account ids to filter by, or null when nothing is filtered.
     *
     * @return list<int>|null
     */
    public function projectLimit(): ?array
    {
        return $this->unrestricted ? null : $this->projectAccountIds;
    }

    /**
     * @return list<int>|null
     */
    public function cashLimit(): ?array
    {
        return $this->unrestricted ? null : $this->cashAccountIds;
    }
}
