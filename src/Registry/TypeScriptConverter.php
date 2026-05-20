<?php

namespace Laravel\Wayfinder\Registry;

use DateTimeInterface;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use InvalidArgumentException;
use Laravel\Surveyor\Analyzer\ArrayableResolver;
use Laravel\Surveyor\Types;
use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Wayfinder\Langs\TypeScript;

class TypeScriptConverter extends AbstractConverter
{
    public function convert(Type $result): string
    {
        return match (get_class($result)) {
            Types\ArrayType::class => $this->convertArrayResult($result),
            Types\ArrayShapeType::class => $this->convertArrayShapeResult($result),
            Types\BoolType::class => $this->convertBoolResult($result),
            Types\ClassType::class => $this->convertClassResult($result),
            Types\Entities\ResourceResponse::class => $this->convertResourceResponse($result),
            Types\IntType::class, Types\FloatType::class, Types\NumberType::class => $this->convertNumberResult($result),
            Types\IntersectionType::class => $this->convertIntersectionResult($result),
            Types\MixedType::class => $this->convertMixedResult($result),
            Types\NullType::class => $this->convertNullResult($result),
            Types\StringType::class => $this->convertStringResult($result),
            Types\UnionType::class => $this->convertUnionResult($result),
            Types\CallableType::class => $this->convertCallableResult($result),
            default => throw new InvalidArgumentException('Unsupported result type: '.get_class($result)),
        };
    }

    protected function convertResourceResponse(Types\Entities\ResourceResponse $result): string
    {
        // Inertia/array contexts call ->toArray() directly without the JSON wrap,
        // so emit just the data shape.
        $shape = $result->data instanceof Types\ArrayType
            ? TypeScript::objectToTypeObject($result->data->value, false)
            : $this->convert($result->data);

        $shape = (string) $shape;

        return $result->isCollection ? "{$shape}[]" : $shape;
    }

    protected function convertCallableResult(Types\CallableType $result): string
    {
        return $this->convert($result->returnType);
    }

    protected function convertArrayResult(Types\ArrayType $result): string
    {
        if ($result->value instanceof Collection) {
            $value = $result->value->toArray();
        } else {
            $value = $result->value;
        }

        $nullSuffix = $result->isNullable() ? ' | null' : '';

        if (array_is_list($value)) {
            $types = TypeScript::union(array_map($this->convert(...), $value));

            if (str_contains($types, '|')) {
                return '('.$types.')[]'.$nullSuffix;
            }

            return $types.'[]'.$nullSuffix;
        }

        return TypeScript::objectToRecord($value, false).$nullSuffix;
    }

    protected function convertArrayShapeResult(Types\ArrayShapeType $result): string
    {
        $keyType = $this->convert($result->keyType);
        $valueType = $this->convert($result->valueType);

        if ($keyType === 'number') {
            return "{$valueType}[]";
        }

        if ($keyType === 'unknown') {
            $keyType = 'string';
        }

        return "Record<{$keyType}, {$valueType}>";
    }

    protected function convertBoolResult(Types\BoolType $result): string
    {
        $value = 'boolean';

        if ($result->value !== null) {
            $value = $result->value ? 'true' : 'false';
        }

        return $this->decorate($value, $result);
    }

    protected function convertClassResult(Types\ClassType $result): string
    {
        $class = ltrim($result->value, '\\');

        $matched = match (true) {
            $class === Stringable::class => 'string',
            is_a($class, DateTimeInterface::class, true) => 'string',
            is_a($class, Collection::class, true) => $this->convertCollectionType($result),
            default => null,
        };

        if ($matched === null) {
            $genericTypes = $result->genericTypes();

            if (! $genericTypes) {
                $resolved = app(ArrayableResolver::class)->resolve($result);

                if ($resolved) {
                    return $this->convert($resolved);
                }
            }

            $matched = str_replace('\\', '.', $class);

            if ($genericTypes) {
                $this->ensureFrameworkTypeDefinition($class);
                $converted = array_map(fn ($type) => $this->convert($type), $genericTypes);
                $matched .= '<'.implode(', ', $converted).'>';
            }
        }

        return $this->decorate($matched, $result);
    }

    protected function convertCollectionType(Types\ClassType $result): string
    {
        $genericTypes = array_values($result->genericTypes());

        return match (count($genericTypes)) {
            0 => 'unknown[]',
            1 => $this->convert($genericTypes[0]).'[]',
            default => $this->convert($genericTypes[1]).'[]',
        };
    }

    protected function ensureFrameworkTypeDefinition(string $class): void
    {
        match (true) {
            is_a($class, LengthAwarePaginator::class, true) => $this->registerLengthAwarePaginatorDefinition($class),
            is_a($class, Paginator::class, true) => $this->registerPaginatorDefinition($class),
            is_a($class, CursorPaginator::class, true) => $this->registerCursorPaginatorDefinition($class),
            default => null,
        };
    }

    protected function registerLengthAwarePaginatorDefinition(string $class): void
    {
        $name = class_basename($class);

        TypeScript::addFqnToNamespaced(
            $class,
            "export type {$name}<T> = { current_page: number, data: T[], first_page_url: string, from: number | null, last_page: number, last_page_url: string, links: { url: string | null, label: string, active: boolean }[], next_page_url: string | null, path: string | null, per_page: number, prev_page_url: string | null, to: number | null, total: number }",
        );
    }

    protected function registerPaginatorDefinition(string $class): void
    {
        $name = class_basename($class);

        TypeScript::addFqnToNamespaced(
            $class,
            "export type {$name}<T> = { current_page: number, current_page_url: string, data: T[], first_page_url: string, from: number | null, next_page_url: string | null, path: string | null, per_page: number, prev_page_url: string | null, to: number | null }",
        );
    }

    protected function registerCursorPaginatorDefinition(string $class): void
    {
        $name = class_basename($class);

        TypeScript::addFqnToNamespaced(
            $class,
            "export type {$name}<T> = { data: T[], path: string | null, per_page: number, next_cursor: string | null, next_page_url: string | null, prev_cursor: string | null, prev_page_url: string | null }",
        );
    }

    protected function convertNumberResult(Types\IntType|Types\FloatType|Types\NumberType $result): string
    {
        return $this->decorate('number', $result);
    }

    protected function decorate(string $type, Type $result): string
    {
        if ($result->isNullable()) {
            $type .= ' | null';
        }

        return $type;
    }

    protected function convertUnionResult(Types\UnionType $result): string
    {
        return $this->convertUnionOrIntersection($result->types, '|');
    }

    protected function convertIntersectionResult(Types\IntersectionType $result): string
    {
        return $this->convertUnionOrIntersection($result->types, '&');
    }

    protected function convertUnionOrIntersection(array $types, string $glue): string
    {
        $result = collect($types)
            ->map(function ($item) {
                if (is_array($item)) {
                    return collect($item)
                        ->filter()
                        ->map($this->convert(...))
                        ->unique()
                        ->implode(' | ');
                }

                if ($item === null) {
                    return null;
                }

                return $this->convert($item);
            })
            ->filter()
            ->unique();

        if ($result->count() > 1 && $result->contains(fn ($type) => $type === 'unknown')) {
            $newResult = $result->filter(fn ($type) => $type !== 'unknown');

            if ($newResult->count() === 1 && $newResult->first() === 'null') {
                return 'unknown';
            }

            $result = $newResult;
        }

        return $result->implode(' '.$glue.' ');
    }

    protected function convertMixedResult(Types\MixedType $result): string
    {
        return 'unknown';
    }

    protected function convertNullResult(Types\NullType $result): string
    {
        return 'null';
    }

    protected function convertStringResult(Types\StringType $result): string
    {
        return $this->decorate('string', $result);
    }

    protected function getType($type): string
    {
        if ($type instanceof Type) {
            return $this->convert($type);
        }

        return $type;
    }
}
