<?php

declare(strict_types=1);

namespace MySaasPackage\Hydrator\Tests\Fixture\Assert;

use Attribute;

// mimics the shape of MySaasPackage\Validation\Assert\ArrayOf without depending on it
#[Attribute(Attribute::TARGET_PROPERTY)]
class ArrayOf
{
    public function __construct(
        public string $type,
        public string $code = 'array_of',
    ) {
    }
}
