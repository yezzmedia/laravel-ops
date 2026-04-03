<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Ops\Contracts\OpsAuditWriter;

final class NullOpsAuditWriter implements OpsAuditWriter
{
    public function write(string $eventKey, array $context = []): void {}
}
