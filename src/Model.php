<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use JsonSerializable;
use Psr\SimpleCache\CacheInterface;
use Sanchescom\Rest\Cache\CacheKeys;
use Sanchescom\Rest\Cache\Memo;
use Sanchescom\Rest\Contracts\ClientInterface;
use Sanchescom\Rest\Contracts\ClientResolverInterface;
use Sanchescom\Rest\Contracts\PaginationConfigResolverInterface;
use Sanchescom\Rest\Pagination\PaginationConfig;
use Sanchescom\Rest\Query\ConfigurableGrammar;
use Sanchescom\Rest\Query\PlainGrammar;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 *
 * @method static Model|Collection<int, static> get(string|int|null $id = null)
 * @method static Collection<int, static> getMany(array<int, string|int|null> $ids)
 * @method static Model|null post(array<string, mixed> $data = [])
 * @method static Model|null put(string|int|null $id = null, array<string, mixed> $data = [])
 * @method static bool delete(string|int|null $id = null)
 * @method static Builder where(string $field, mixed $operator = null, mixed $value = null)
 * @method static Builder whereIn(string $field, array<int, mixed> $values)
 * @method static Builder orderBy(string $field, string $direction = 'asc')
 * @method static Builder limit(int $limit)
 * @method static Builder offset(int $offset)
 * @method static Builder page(int $page)
 * @method static Builder withQuery(array<string, mixed> $params)
 * @method static Model|null first()
 * @method static int count()
 * @method static LengthAwarePaginator<int, static> paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null)
 * @method static Paginator<int, static> simplePaginate(int $perPage = 15, string $pageName = 'page', ?int $page = null)
 * @method static LazyCollection<int, static> lazy(int $chunkSize = 100)
 * @method static Builder withCache(?int $ttl = null)
 * @method static Builder withoutCache()
 * @method static Builder withHeaders(array<string, string> $headers)
 *
 * @phpstan-consistent-constructor
 */
class Model implements Arrayable, ArrayAccess, JsonSerializable
{
    protected static ?ClientResolverInterface $resolver = null;

    /** @var array<class-string, array<string, list<callable>>> */
    protected static array $eventListeners = [];

    protected static ?object $eventDispatcher = null;

    protected static ?CacheInterface $cacheStore = null;

    protected static int $defaultCacheTtl = 300;

    protected ?string $client = null;

    protected ?int $cacheTtl = null;

    protected ?string $endpoint = null;

    protected ?string $dataKey = null;

    protected ?string $requestDataKey = null;

    /** @var class-string|null */
    protected ?string $grammar = null;

    /** @var array<string, mixed>|string|null */
    protected array|string|null $pagination = null;

    /** @var array<string, mixed> */
    protected array $options = [];

    /** @var array<string, string> */
    protected array $headers = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $loadedRelations = [];

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

    /**
     * @param  class-string<self>  $related
     */
    public function hasMany(string $related, ?string $foreignKey = null): Relations\HasMany
    {
        return new Relations\HasMany($this, $related, $foreignKey);
    }

    /**
     * @param  class-string<self>  $related
     */
    public function hasOne(string $related, ?string $foreignKey = null): Relations\HasOne
    {
        return new Relations\HasOne($this, $related, $foreignKey);
    }

    /**
     * @param  class-string<self>  $related
     */
    public function belongsTo(string $related, ?string $foreignKey = null): Relations\BelongsTo
    {
        return new Relations\BelongsTo($this, $related, $foreignKey);
    }

    protected function isRelationMethod(string $key): bool
    {
        if (! method_exists($this, $key)) {
            return false;
        }

        $returnType = (new \ReflectionMethod($this, $key))->getReturnType();

        return $returnType instanceof \ReflectionNamedType
            && ! $returnType->isBuiltin()
            && is_a($returnType->getName(), Relations\Relation::class, true);
    }

    public function __get(string $key): mixed
    {
        if (! array_key_exists($key, $this->attributes) && $this->isRelationMethod($key)) {
            if (array_key_exists($key, $this->loadedRelations)) {
                return $this->loadedRelations[$key];
            }

            return $this->loadedRelations[$key] = $this->{$key}()->getResults();
        }

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

    public static function setCacheStore(?CacheInterface $store, int $defaultTtl = 300): void
    {
        static::$cacheStore = $store;
        static::$defaultCacheTtl = $defaultTtl;
    }

    public static function getCacheStore(): ?CacheInterface
    {
        return static::$cacheStore;
    }

    public static function getDefaultCacheTtl(): int
    {
        return static::$defaultCacheTtl;
    }

    public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    public function getClientName(): ?string
    {
        return $this->client;
    }

    public static function flushCache(): void
    {
        Memo::forget(static::class);

        $store = static::getCacheStore();

        if ($store === null) {
            return;
        }

        $key = CacheKeys::version(static::class);

        $store->set($key, (int) $store->get($key, 0) + 1);
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param  array<string, mixed>  $extraOptions
     */
    public function getClient(array $extraOptions = []): ClientInterface
    {
        return static::$resolver->client(
            $this->client,
            $extraOptions === [] ? $this->options : array_replace_recursive($this->options, $extraOptions),
        );
    }

    public function getEndpoint(): string
    {
        return $this->endpoint ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    public function getDataKey(): ?string
    {
        return $this->dataKey;
    }

    public function getRequestDataKey(): ?string
    {
        return $this->requestDataKey;
    }

    public function getPaginationConfig(): ?PaginationConfig
    {
        $config = $this->pagination;

        if ($config === null && static::$resolver instanceof PaginationConfigResolverInterface) {
            $config = static::$resolver->paginationConfig($this->client);
        }

        return $config === null ? null : PaginationConfig::fromConfig($config);
    }

    public function newBuilder(): Builder
    {
        $grammarClass = $this->grammar
            ?? (static::$resolver?->grammar($this->client));

        if ($grammarClass !== null) {
            return new Builder($this, new $grammarClass);
        }

        $queryConfig = static::$resolver?->queryConfig($this->client);

        if ($queryConfig !== null) {
            return new Builder($this, ConfigurableGrammar::fromConfig($queryConfig));
        }

        return new Builder($this, new PlainGrammar);
    }

    /**
     * @param  array<int, static>  $models
     * @return Collection<int, static>
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    public static function creating(callable $listener): void
    {
        static::registerModelEvent('creating', $listener);
    }

    public static function created(callable $listener): void
    {
        static::registerModelEvent('created', $listener);
    }

    public static function updating(callable $listener): void
    {
        static::registerModelEvent('updating', $listener);
    }

    public static function updated(callable $listener): void
    {
        static::registerModelEvent('updated', $listener);
    }

    public static function deleting(callable $listener): void
    {
        static::registerModelEvent('deleting', $listener);
    }

    public static function deleted(callable $listener): void
    {
        static::registerModelEvent('deleted', $listener);
    }

    protected static function registerModelEvent(string $event, callable $listener): void
    {
        static::$eventListeners[static::class][$event][] = $listener;
    }

    public static function flushEventListeners(): void
    {
        unset(static::$eventListeners[static::class]);
    }

    public static function setEventDispatcher(?object $dispatcher): void
    {
        static::$eventDispatcher = $dispatcher;
    }

    public function fireModelEvent(string $event): bool
    {
        foreach (static::$eventListeners[static::class][$event] ?? [] as $listener) {
            if ($listener($this) === false) {
                return false;
            }
        }

        $bridge = match ($event) {
            'created' => Events\ModelCreated::class,
            'updated' => Events\ModelUpdated::class,
            'deleted' => Events\ModelDeleted::class,
            default => null,
        };

        if ($bridge !== null && static::$eventDispatcher !== null && method_exists(static::$eventDispatcher, 'dispatch')) {
            static::$eventDispatcher->dispatch(new $bridge($this));
        }

        return true;
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
