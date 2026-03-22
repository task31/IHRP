<?php

namespace App\Services;

/**
 * @param  list<array<string, string>>  $records
 * @param  list<array<string, mixed>>  $consultantRows
 * @param  list<string>  $warnings
 */
final class PayrollParseResult
{
    /**
     * @param  list<array<string, string>>  $records
     * @param  list<array<string, mixed>>  $consultantRows
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly ?string $ownerName,
        public readonly array $records,
        public readonly array $consultantRows,
        public readonly array $warnings = [],
    ) {}
}
