<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Contracts;

interface OpsAuditWriter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function write(string $eventKey, array $context = []): void;
}
