<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Relations;

use Closure;
use Sanchescom\Rest\Collection;
use Sanchescom\Rest\Model;

final class EagerLoader
{
    /**
     * @param  Collection<array-key, mixed>  $models
     * @param  array<int|string, string|Closure>  $relations  ['comments.author', 'author' => fn (Builder $query) => ...]
     */
    public function load(Collection $models, array $relations): void
    {
        $this->loadTree($models, self::parse($relations));
    }

    /**
     * @param  array<int|string, string|Closure>  $relations
     * @return array<string, array{constraint: ?Closure, children: array<string, mixed>}>
     */
    private static function parse(array $relations): array
    {
        $tree = [];

        foreach ($relations as $key => $value) {
            [$path, $constraint] = is_int($key)
                ? [(string) $value, null]
                : [$key, $value instanceof Closure ? $value : null];

            $tree = self::insert($tree, explode('.', $path), $constraint);
        }

        return $tree;
    }

    /**
     * @param  array<string, array{constraint: ?Closure, children: array<string, mixed>}>  $tree
     * @param  list<string>  $segments
     * @return array<string, array{constraint: ?Closure, children: array<string, mixed>}>
     */
    private static function insert(array $tree, array $segments, ?Closure $constraint): array
    {
        $segment = array_shift($segments);
        $node = $tree[$segment] ?? ['constraint' => null, 'children' => []];

        if ($segments === []) {
            $node['constraint'] = $constraint ?? $node['constraint'];
        } else {
            $node['children'] = self::insert($node['children'], $segments, $constraint);
        }

        $tree[$segment] = $node;

        return $tree;
    }

    /**
     * @param  Collection<array-key, mixed>  $models
     * @param  array<string, array{constraint: ?Closure, children: array<string, mixed>}>  $tree
     */
    private function loadTree(Collection $models, array $tree): void
    {
        $parents = $models->filter(fn (mixed $model) => $model instanceof Model)->values();

        if ($parents->isEmpty()) {
            return;
        }

        /** @var Model $first */
        $first = $parents->first();

        foreach ($tree as $name => $node) {
            $loaded = $first->relationFor($name)->eagerLoad($parents, $name, $node['constraint']);

            if ($node['children'] !== []) {
                $this->loadTree($loaded, $node['children']);
            }
        }
    }
}
