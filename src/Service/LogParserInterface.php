<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Defines the contract for processing third-party log entries.
 * This abstraction allows for alternative parser implementations.
 */
interface LogParserInterface
{
    /**
     * @param array<string, string> $rawData
     * @param int $recordId
     * @return array<string, string|int>
     */
    public function processRow(array $rawData, int $recordId): array;
}