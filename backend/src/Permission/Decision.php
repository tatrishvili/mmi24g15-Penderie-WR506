<?php

declare(strict_types=1);

namespace App\Permission;

use App\Enum\AccessLevel;
use App\Enum\Capability;

/**
 * The answer of the resolver: an access level plus granted capabilities.
 */
final class Decision
{
    /**
     * @param list<Capability> $capabilities
     */
    public function __construct(
        public readonly AccessLevel $level,
        public readonly array $capabilities = [],
    ) {
    }

    public function allows(AccessLevel $level): bool
    {
        return $this->level->rank() >= $level->rank();
    }

    public function can(Capability $capability): bool
    {
        return $this->allows(AccessLevel::View) && \in_array($capability, $this->capabilities, true);
    }
}
