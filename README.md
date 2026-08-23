# MySaasPackage Hydrator

Hydrates DTOs from arrays. Controllers never build inputs by hand — a request body (or any array) goes in, a typed input object comes out, built by reflection over its public properties. Hydration is deliberately separate from validation: the hydrator fills whatever it can and never complains, so a half-filled request still produces a complete object that [`mysaaspackage/validation`](https://github.com/MySaasPackage/Validation) can turn into a full `ValidationResult` instead of a type error.

```php
$input = (new Hydrator())->hydrate($body, CreateInvoiceInput::class);
```

## Installation

```bash
composer require mysaaspackage/hydrator
```

## Quickstart

Declare a DTO with public, nullable, defaulted properties and hand the hydrator an array plus the class name:

```php
<?php

declare(strict_types=1);

use MySaasPackage\Hydrator\Hydrator;

class CreateInvoiceInput
{
    public ?string $type = null;

    public ?int $amount = null;

    public ?string $customerUuid = null;

    public ?DateTimeImmutable $dueAt = null;
}

$input = (new Hydrator())->hydrate([
    'type' => 'service',
    'amount' => 1500,
    'customerUuid' => '0e3f8a9c-6d4b-4f2a-9c1e-5b7d8e2a4f6c',
    'dueAt' => '2026-09-01',
], CreateInvoiceInput::class);

$input->amount; // 1500
$input->dueAt;  // DateTimeImmutable('2026-09-01')
```

## The contract

- **Property ↔ key by name.** Each public non-static property is filled from the array key with its name, overridable with `#[Map(source: 'q')]` (`MySaasPackage\Hydrator\Attribute\Map`) when the wire name differs from the property name.
- **Absent keys leave the default.** A key missing from the array never touches the property; `null` is never forced into a non-nullable property.
- **Nested hydration by type.** A property typed with any class is hydrated recursively from its sub-array; `#[ArrayOf(type: XInput::class)]` (the package's own `MySaasPackage\Hydrator\Attribute\ArrayOf`) hydrates each element of an array of sub-arrays. Any other attribute named `ArrayOf` exposing a public string `type` — such as `MySaasPackage\Validation\Assert\ArrayOf` — is honored too, so a DTO already annotated for validation hydrates without a second attribute.
- **Coercions.** `''` becomes `null` on nullable string properties; a `DateTimeImmutable`-typed property is constructed from the incoming string (empty string or non-string → `null`).
- **Instantiation.** Classes without a constructor — or whose constructor has no required parameters — are instantiated through it, so constructor-initialized properties survive; classes with required constructor parameters are instantiated without invoking it.

The hydrator does no validation — run the object through the `Validator` of [`mysaaspackage/validation`](https://github.com/MySaasPackage/Validation) as the use case's first step, so a malformed body produces violations as data, never an exception.

## License

MIT
