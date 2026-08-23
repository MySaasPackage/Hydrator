<?php

declare(strict_types=1);

namespace MySaasPackage\Hydrator\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Map
{
    public function __construct(
        public string $source,
    ) {
    }
}
