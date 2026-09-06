<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ConfigurableGrammar implements Grammar
{
    private const SORT_STYLES = ['dash', 'separate', 'suffix', 'array'];

    private const FILTER_STYLES = ['plain', 'brackets', 'django'];

    private const CASINGS = ['snake', 'camel'];

    private const KEYS = ['names', 'sort', 'sort_names', 'sort_suffix', 'filters', 'casing'];

    private const PRESETS = [
        'jsonapi' => [
            'filters' => 'brackets',
            'sort' => 'dash',
            'names' => ['limit' => 'page.size', 'page' => 'page.number', 'offset' => 'page.offset'],
        ],
        'django' => [
            'filters' => 'django',
            'sort' => 'dash',
            'names' => ['sort' => 'ordering', 'limit' => 'page_size'],
        ],
    ];

    /** @var array<string, string> */
    private array $names;

    private string $sortStyle;

    /** @var array{field: string, direction: string} */
    private array $sortNames;

    private string $sortSuffix;

    private string $filterStyle;

    private ?string $casing;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        foreach (array_keys($config) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw new InvalidArgumentException("Unknown query convention key [{$key}].");
            }
        }

        $this->names = (array) ($config['names'] ?? []);
        $this->sortStyle = (string) ($config['sort'] ?? 'dash');
        $this->sortNames = array_merge(
            ['field' => 'sort', 'direction' => 'direction'],
            (array) ($config['sort_names'] ?? []),
        );
        $this->sortSuffix = (string) ($config['sort_suffix'] ?? ':');
        $this->filterStyle = (string) ($config['filters'] ?? 'plain');
        $this->casing = $config['casing'] ?? null;

        if (! in_array($this->sortStyle, self::SORT_STYLES, true)) {
            throw new InvalidArgumentException("Unknown sort style [{$this->sortStyle}].");
        }

        if (! in_array($this->filterStyle, self::FILTER_STYLES, true)) {
            throw new InvalidArgumentException("Unknown filter style [{$this->filterStyle}].");
        }

        if ($this->casing !== null && ! in_array($this->casing, self::CASINGS, true)) {
            throw new InvalidArgumentException("Unknown casing [{$this->casing}].");
        }
    }

    /**
     * @param  array<string, mixed>|string  $config
     */
    public static function fromConfig(array|string $config): self
    {
        if (is_string($config)) {
            $config = ['preset' => $config];
        }

        if (isset($config['preset'])) {
            $preset = self::PRESETS[$config['preset']]
                ?? throw new InvalidArgumentException("Unknown query preset [{$config['preset']}].");

            unset($config['preset']);

            $config = array_replace_recursive($preset, $config);
        }

        return new self($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            $field = $this->cased($where['field']);

            match ($this->filterStyle) {
                'plain' => $query[$where['operator'] === '=' ? $field : "{$field}[{$where['operator']}]"] = $where['value'],
                'brackets' => $where['operator'] === '='
                    ? $query['filter'][$field] = $where['value']
                    : $query['filter'][$field][$where['operator']] = $where['value'],
                'django' => $query[$where['operator'] === '=' ? $field : "{$field}__{$where['operator']}"] = $where['value'],
                default => null,
            };
        }

        $query = $this->compileOrders($state, $query);

        foreach (['limit' => $state->limit, 'offset' => $state->offset, 'page' => $state->page] as $param => $value) {
            if ($value !== null) {
                Arr::set($query, $this->names[$param] ?? $param, $value);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function compileOrders(QueryState $state, array $query): array
    {
        if ($state->orders === []) {
            return $query;
        }

        $sortParam = $this->names['sort'] ?? 'sort';

        switch ($this->sortStyle) {
            case 'dash':
                Arr::set($query, $sortParam, implode(',', array_map(
                    fn (array $order) => ($order['direction'] === 'desc' ? '-' : '').$this->cased($order['field']),
                    $state->orders,
                )));
                break;

            case 'separate':
                $first = $state->orders[0];
                Arr::set($query, $this->sortNames['field'], $this->cased($first['field']));
                Arr::set($query, $this->sortNames['direction'], $first['direction']);
                break;

            case 'suffix':
                Arr::set($query, $sortParam, implode(',', array_map(
                    fn (array $order) => $this->cased($order['field']).$this->sortSuffix.$order['direction'],
                    $state->orders,
                )));
                break;

            case 'array':
                $sorts = [];
                foreach ($state->orders as $order) {
                    $sorts[$this->cased($order['field'])] = $order['direction'];
                }
                Arr::set($query, $sortParam, $sorts);
                break;
        }

        return $query;
    }

    private function cased(string $field): string
    {
        return match ($this->casing) {
            'snake' => Str::snake($field),
            'camel' => Str::camel($field),
            default => $field,
        };
    }
}
