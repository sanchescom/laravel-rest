<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use JsonSerializable;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;
use Sanchescom\Rest\Query\PlainGrammar;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 *
 * @method static Model|Collection<int, static> get(string|int|null $id = null)
 * @method static Collection<int, static> getMany(array<int, string|int|null> $ids)
 * @method static Model post(array<string, mixed> $data = [])
 * @method static Model put(string|int|null $id = null, array<string, mixed> $data = [])
 * @method static bool delete(string|int|null $id = null)
 * @method static Builder where(string $field, mixed $operator = null, mixed $value = null)
 * @method static Builder orderBy(string $field, string $direction = 'asc')
 * @method static Builder limit(int $limit)
 * @method static Builder offset(int $offset)
 * @method static Builder page(int $page)
 * @method static Builder withQuery(array<string, mixed> $params)
 * @method static Model|null first()
 * @method static int count()
 *
 * @phpstan-consistent-constructor
 */
class Model implements Arrayable, ArrayAccess, JsonSerializable
{
    protected static ?ClientResolverInterface $resolver = null;

    protected ?string $client = null;

    protected ?string $endpoint = null;

    protected ?string $dataKey = null;

    /** @var class-string|null */
    protected ?string $grammar = null;

    /** @var array<string, mixed> */
    protected array $options = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<int, string> */
    protected array $fillable = [];

    /** @var array<string, string> */
    protected array $casts = [];

    protected string $primaryKey = 'id';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    protected function isFillable(string $key): bool
    {
        return $this->fillable === [] || in_array($key, $this->fillable, true);
    }

    public function getAttribute(string $key): mixed
    {
        return $this->castAttribute($key, $this->attributes[$key] ?? null);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || ! isset($this->casts[$key])) {
            return $value;
        }

        return match ($this->casts[$key]) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach (array_keys($this->attributes) as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newInstance(array $attributes = []): static
    {
        return new static($attributes);
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function setClientResolver(ClientResolverInterface $resolver): void
    {
        static::$resolver = $resolver;
    }

    public static function getClientResolver(): ?ClientResolverInterface
    {
        return static::$resolver;
    }

    public static function unsetClientResolver(): void
    {
        static::$resolver = null;
    }

    public function getClient(): ClientInterface
    {
        return static::$resolver->client($this->client, $this->options);
    }

    public function getEndpoint(): string
    {
        return $this->endpoint ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    public function getDataKey(): ?string
    {
        return $this->dataKey;
    }

    public function newBuilder(): Builder
    {
        $grammarClass = $this->grammar
            ?? (static::$resolver !== null ? static::$resolver->grammar($this->client) : null)
            ?? PlainGrammar::class;

        return new Builder($this, new $grammarClass);
    }

    /**
     * @param  array<int, static>  $models
     * @return Collection<int, static>
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->newBuilder()->{$method}(...$parameters);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->{$method}(...$parameters);
    }
}
