<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Probes;

use InvalidArgumentException;

final class Probes
{
    public const FEATURES = [
        'read.list' => 'List a collection',
        'read.data-key' => 'Read items under a data key',
        'read.data-key.nested' => 'Read items under a nested (dot) data key',
        'read.find' => 'Find one model by key',
        'read.get-many' => 'Fetch several models concurrently (getMany)',
        'query.filter' => 'Filter on the server (where)',
        'query.where-in' => 'Membership filter (whereIn)',
        'query.sort' => 'Sort on the server (orderBy)',
        'grammar.plain' => 'Plain grammar',
        'grammar.jsonapi' => 'JSON:API grammar',
        'grammar.django' => 'Django grammar',
        'grammar.configurable' => 'Configurable grammar (names, sort styles, casing)',
        'paginate.total' => 'paginate() with a response total',
        'paginate.simple' => 'simplePaginate()',
        'paginate.lazy' => 'lazy() walk across pages',
        'paginate.style.page' => 'Page-number pagination style',
        'paginate.style.offset' => 'Offset pagination style',
        'relation.belongs-to' => 'belongsTo',
        'relation.has-many' => 'hasMany by foreign key',
        'relation.nested' => 'Nested URL relation',
        'eager.concurrent' => 'Eager loading, concurrent',
        'eager.batch' => 'Eager loading, batched whereIn',
        'write.create' => 'Create (POST)',
        'write.update' => 'Update (PUT)',
        'write.patch' => 'Update (PATCH)',
        'write.delete' => 'Delete',
        'write.envelope' => 'Request data envelope (requestDataKey)',
        'errors.not-found' => '404 → ModelNotFoundException',
        'errors.validation' => '422 → ValidationException',
        'errors.client' => 'Other 4xx → RequestException',
        'errors.server' => '5xx → ServerException',
        'auth.bearer' => 'Bearer auth',
        'auth.basic' => 'Basic auth',
        'auth.header' => 'Header auth',
        'headers.dynamic' => 'Dynamic headers (withHeaders)',
        'retry.status' => 'Retry on retryable status',
        'cache.response' => 'Response cache',
        'cache.memo' => 'Request memoization',
    ];

    /** Required scenario keys per probe, checked by the network-free catalog lint. */
    public const REQUIRED = [
        'list' => ['model'],
        'find' => ['model', 'id'],
        'get-many' => ['model', 'ids'],
        'filter' => ['model', 'field', 'value'],
        'where-in' => ['model', 'field', 'values'],
        'sort' => ['model', 'field'],
        'paginate' => ['model'],
        'simple-paginate' => ['model'],
        'lazy' => ['model', 'chunk', 'take'],
        'belongs-to' => ['model', 'id', 'relation', 'foreign_key'],
        'has-many' => ['model', 'id', 'relation'],
        'eager' => ['model', 'relation'],
        'write' => ['model', 'op'],
        'not-found' => ['model', 'id'],
        'status' => ['model', 'status'],
        'auth' => ['model', 'client'],
        'headers' => ['model', 'headers'],
        'retry' => ['model', 'status', 'attempts'],
        'cache' => ['model', 'kind'],
        'unsupported' => ['reason', 'features'],
        'custom' => ['run', 'features'],
    ];

    /** @var array<string, class-string<Probe>> */
    private const PROBES = [
        'list' => ListProbe::class,
        'find' => FindProbe::class,
        'get-many' => GetManyProbe::class,
        'filter' => FilterProbe::class,
        'where-in' => WhereInProbe::class,
        'sort' => SortProbe::class,
        'paginate' => PaginateProbe::class,
        'simple-paginate' => SimplePaginateProbe::class,
        'lazy' => LazyProbe::class,
        'belongs-to' => BelongsToProbe::class,
        'has-many' => HasManyProbe::class,
        'eager' => EagerProbe::class,
        'write' => WriteProbe::class,
        'not-found' => NotFoundProbe::class,
        'status' => StatusProbe::class,
        'auth' => AuthProbe::class,
        'headers' => HeadersProbe::class,
        'retry' => RetryProbe::class,
        'cache' => CacheProbe::class,
        'unsupported' => UnsupportedProbe::class,
        'custom' => CustomProbe::class,
    ];

    public static function for(string $name): Probe
    {
        $class = self::PROBES[$name] ?? throw new InvalidArgumentException("Unknown live probe [{$name}].");

        return new $class;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::PROBES);
    }
}
