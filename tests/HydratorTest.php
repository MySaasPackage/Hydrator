<?php

declare(strict_types=1);

namespace MySaasPackage\Hydrator\Tests;

use DateTimeImmutable;
use MySaasPackage\Hydrator\Attribute\ArrayOf;
use MySaasPackage\Hydrator\Attribute\Map;
use MySaasPackage\Hydrator\Hydrator;
use MySaasPackage\Hydrator\Tests\Fixture\Assert\ArrayOf as ForeignArrayOf;
use PHPUnit\Framework\TestCase;

class Address
{
    public ?string $street = null;
    public ?string $city = null;
}

class Tag
{
    public ?string $label = null;
}

class Profile
{
    public ?string $name = null;
    public ?int $age = null;

    #[Map(source: 'q')]
    public ?string $search = null;

    public int $limit = 10;
    public ?string $nickname = 'anon';
    public ?string $bio = null;
    public string $code = 'initial';

    public ?Address $address = null;

    /**
     * @var Tag[]|null
     */
    #[ArrayOf(type: Tag::class)]
    public ?array $tags = null;

    public ?DateTimeImmutable $startsAt = null;

    public static ?string $registry = null;
}

class Catalog
{
    /**
     * @var Tag[]|null
     */
    #[ForeignArrayOf(type: Tag::class)]
    public ?array $tags = null;
}

class Pagination
{
    public int $page = 1;
    public int $limit = 10;
}

class Listing
{
    public ?string $search = null;
    public ?Pagination $pagination = null;

    public function __construct()
    {
        $this->pagination = new Pagination();
    }
}

class WithRequiredConstructor
{
    public ?string $marker = null;

    public function __construct(public string $name)
    {
        $this->marker = 'constructed';
    }
}

class HydratorTest extends TestCase
{
    public function testPropertyIsHydratedFromTheKeyMatchingItsName(): void
    {
        $profile = (new Hydrator())->hydrate(['name' => 'Alef', 'age' => 30], Profile::class);

        self::assertSame('Alef', $profile->name);
        self::assertSame(30, $profile->age);
    }

    public function testMapAttributeOverridesTheSourceKey(): void
    {
        $profile = (new Hydrator())->hydrate(['q' => 'term', 'search' => 'ignored'], Profile::class);

        self::assertSame('term', $profile->search);
    }

    public function testAbsentKeyPreservesTheDefault(): void
    {
        $profile = (new Hydrator())->hydrate([], Profile::class);

        self::assertSame(10, $profile->limit);
        self::assertSame('anon', $profile->nickname);
        self::assertNull($profile->name);
    }

    public function testExplicitNullIsAssignedOnNullableProperty(): void
    {
        $profile = (new Hydrator())->hydrate(['nickname' => null], Profile::class);

        self::assertNull($profile->nickname);
    }

    public function testNullIsNeverForcedIntoNonNullableProperty(): void
    {
        $profile = (new Hydrator())->hydrate(['limit' => null], Profile::class);

        self::assertSame(10, $profile->limit);
    }

    public function testClassTypedPropertyIsHydratedRecursivelyFromSubArray(): void
    {
        $profile = (new Hydrator())->hydrate(['address' => ['street' => 'Main St', 'city' => 'Lisbon']], Profile::class);

        self::assertInstanceOf(Address::class, $profile->address);
        self::assertSame('Main St', $profile->address->street);
        self::assertSame('Lisbon', $profile->address->city);
    }

    public function testArrayOfHydratesEachElement(): void
    {
        $profile = (new Hydrator())->hydrate(['tags' => [['label' => 'a'], ['label' => 'b']]], Profile::class);

        self::assertIsArray($profile->tags);
        self::assertCount(2, $profile->tags);
        self::assertInstanceOf(Tag::class, $profile->tags[0]);
        self::assertInstanceOf(Tag::class, $profile->tags[1]);
        self::assertSame('a', $profile->tags[0]->label);
        self::assertSame('b', $profile->tags[1]->label);
    }

    public function testArrayOfShapedAttributeFromAnotherNamespaceIsHonored(): void
    {
        $catalog = (new Hydrator())->hydrate(['tags' => [['label' => 'a'], ['label' => 'b']]], Catalog::class);

        self::assertIsArray($catalog->tags);
        self::assertCount(2, $catalog->tags);
        self::assertInstanceOf(Tag::class, $catalog->tags[0]);
        self::assertSame('b', $catalog->tags[1]->label);
    }

    public function testDateTimeImmutableIsConstructedFromString(): void
    {
        $profile = (new Hydrator())->hydrate(['startsAt' => '2026-01-15 10:30:00'], Profile::class);

        self::assertInstanceOf(DateTimeImmutable::class, $profile->startsAt);
        self::assertSame('2026-01-15 10:30:00', $profile->startsAt->format('Y-m-d H:i:s'));
    }

    public function testEmptyStringBecomesNullOnDateTimeImmutableProperty(): void
    {
        $profile = (new Hydrator())->hydrate(['startsAt' => ''], Profile::class);

        self::assertNull($profile->startsAt);
    }

    public function testNonStringBecomesNullOnDateTimeImmutableProperty(): void
    {
        $profile = (new Hydrator())->hydrate(['startsAt' => 1700000000], Profile::class);

        self::assertNull($profile->startsAt);
    }

    public function testEmptyStringBecomesNullOnNullableStringProperty(): void
    {
        $profile = (new Hydrator())->hydrate(['bio' => ''], Profile::class);

        self::assertNull($profile->bio);
    }

    public function testEmptyStringIsKeptOnNonNullableStringProperty(): void
    {
        $profile = (new Hydrator())->hydrate(['code' => ''], Profile::class);

        self::assertSame('', $profile->code);
    }

    public function testConstructorWithoutRequiredParametersRuns(): void
    {
        $listing = (new Hydrator())->hydrate(['search' => 'invoices'], Listing::class);

        self::assertSame('invoices', $listing->search);
        self::assertInstanceOf(Pagination::class, $listing->pagination);
        self::assertSame(1, $listing->pagination->page);
        self::assertSame(10, $listing->pagination->limit);
    }

    public function testClassWithRequiredConstructorParametersIsInstantiatedWithoutConstructor(): void
    {
        $instance = (new Hydrator())->hydrate(['name' => 'direct'], WithRequiredConstructor::class);

        self::assertSame('direct', $instance->name);
        self::assertNull($instance->marker);
    }

    public function testStaticPropertiesAreUntouched(): void
    {
        (new Hydrator())->hydrate(['registry' => 'boom'], Profile::class);

        self::assertNull(Profile::$registry);
    }
}
