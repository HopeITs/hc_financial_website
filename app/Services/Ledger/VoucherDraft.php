<?php

namespace App\Services\Ledger;

use App\Enums\VoucherSource;
use App\Enums\VoucherType;
use Carbon\CarbonImmutable;

/**
 * A voucher about to be posted: the header and its lines, before any rule has looked at it.
 * `preparedBy` and the other user columns hold the legacy `user`.`ID`.
 */
final readonly class VoucherDraft
{
    /** @param  list<LineDraft>  $lines */
    public function __construct(
        public VoucherType $type,
        public CarbonImmutable $date,
        public int $preparedBy,
        public array $lines,
        public ?string $description = null,
        public VoucherSource $source = VoucherSource::Web,
        public ?int $legacyActionId = null,
        public bool $isCarryForward = false,
        public bool $isTemporary = false,
        public ?int $requestId = null,
        public ?string $transferKind = null,
    ) {}

    /** The month the voucher belongs to, as in `gl_month_closes` (YYYY-MM). */
    public function month(): string
    {
        return $this->date->format('Y-m');
    }

    /** @param  list<LineDraft>  $lines */
    public function withLines(array $lines): self
    {
        return new self(
            $this->type, $this->date, $this->preparedBy, $lines, $this->description,
            $this->source, $this->legacyActionId, $this->isCarryForward, $this->isTemporary, $this->requestId, $this->transferKind,
        );
    }
}
