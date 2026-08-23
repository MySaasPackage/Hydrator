<?php

declare(strict_types=1);

namespace MySaasPackage\Hydrator;

use DateTimeImmutable;
use MySaasPackage\Hydrator\Attribute\ArrayOf;
use MySaasPackage\Hydrator\Attribute\Map;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class Hydrator
{
    /**
     * @template T of object
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $class
     *
     * @return T
     */
    public function hydrate(array $data, string $class): object
    {
        /** @var ReflectionClass<T> $reflection */
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        /** @var T $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        if (null === $constructor || 0 === $constructor->getNumberOfRequiredParameters()) {
            $instance = $reflection->newInstance();
        }

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            $sourceKey = $this->resolveSourceKey($property, $name);
            if (!array_key_exists($sourceKey, $data)) {
                continue;
            }

            $value = $this->resolveValue($property, $data[$sourceKey]);

            $type = $property->getType();
            if (null === $value && $type instanceof ReflectionNamedType && !$type->allowsNull()) {
                continue;
            }

            $instance->{$name} = $value;
        }

        return $instance;
    }

    private function resolveSourceKey(ReflectionProperty $property, string $default): string
    {
        $mapAttrs = $property->getAttributes(Map::class);
        if ([] === $mapAttrs) {
            return $default;
        }

        return $mapAttrs[0]->newInstance()->source;
    }

    private function resolveValue(ReflectionProperty $property, mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        $type = $property->getType();
        $typeName = null;
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
        }

        if (DateTimeImmutable::class === $typeName) {
            if (!is_string($value) || '' === $value) {
                return null;
            }

            return new DateTimeImmutable($value);
        }

        if (null !== $typeName && $type instanceof ReflectionNamedType && !$type->isBuiltin() && is_array($value) && class_exists($typeName)) {
            return $this->hydrate($value, $typeName);
        }

        if (is_array($value)) {
            $elementClass = $this->resolveElementClass($property);
            if (null !== $elementClass && class_exists($elementClass)) {
                return array_map(
                    function (mixed $item) use ($elementClass): mixed {
                        if (!is_array($item)) {
                            return $item;
                        }

                        return $this->hydrate($item, $elementClass);
                    },
                    $value,
                );
            }
        }

        if ('string' === $typeName && '' === $value && $type instanceof ReflectionNamedType && $type->allowsNull()) {
            return null;
        }

        return $value;
    }

    private function resolveElementClass(ReflectionProperty $property): ?string
    {
        $own = $property->getAttributes(ArrayOf::class);
        if ([] !== $own) {
            return $own[0]->newInstance()->type;
        }

        // any ArrayOf-shaped attribute counts (e.g. mysaaspackage/validation's),
        // so a DTO annotated for validation hydrates without a second attribute
        foreach ($property->getAttributes() as $attribute) {
            $attributeClass = $attribute->getName();
            if (!str_ends_with('\\'.$attributeClass, '\\ArrayOf') || !class_exists($attributeClass)) {
                continue;
            }

            $instance = $attribute->newInstance();
            if (property_exists($instance, 'type') && is_string($instance->type)) {
                return $instance->type;
            }
        }

        return null;
    }
}
