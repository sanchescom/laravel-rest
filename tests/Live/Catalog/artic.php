<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Artic;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;

final class Artwork extends Model
{
    protected ?string $endpoint = 'artworks';

    protected ?string $dataKey = 'data';

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'artist_id');
    }

    public function artistBatched(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'artist_id')->batch();
    }
}

final class Agent extends Model
{
    protected ?string $endpoint = 'agents';

    protected ?string $dataKey = 'data';
}

return [
    'name' => 'Art Institute of Chicago API',
    'docs' => 'https://api.artic.edu/docs/',
    'base_uri' => 'https://api.artic.edu/api/v1/',
    'traits' => [
        'response' => 'data envelope (list and detail) + pagination/info/config siblings',
        'pagination' => 'page + limit, total/next_url under pagination.*',
        'filters' => 'ids=a,b only on GET; field filters and sort exist only on /artworks/search (ES DSL)',
        'keys' => 'int id',
        'relations' => 'fk artist_id -> agents/{id}',
        'errors' => '404 {"status","error","detail"}',
    ],
    'client' => [
        'pagination' => ['style' => 'page', 'total' => 'pagination.total', 'next' => 'pagination.next_url'],
    ],
    'scenarios' => [
        'list artworks' => ['probe' => 'list', 'model' => Artwork::class, 'query' => fn (Builder $query) => $query->withQuery(['fields' => 'id,title,artist_id']), 'min' => 12, 'fields' => ['id', 'title']],
        'find artwork' => ['probe' => 'find', 'model' => Artwork::class, 'id' => 27992, 'fields' => ['title']],
        'get many artworks' => ['probe' => 'get-many', 'model' => Artwork::class, 'ids' => [27992, 28560, 129884]],
        'where-in ids' => ['probe' => 'where-in', 'model' => Artwork::class, 'field' => 'ids', 'attribute' => 'id', 'values' => [27992, 28560]],
        'paginate artworks' => ['probe' => 'paginate', 'model' => Artwork::class, 'per_page' => 10],
        'simple paginate artworks' => ['probe' => 'simple-paginate', 'model' => Artwork::class, 'per_page' => 10],
        'lazy walk agents' => ['probe' => 'lazy', 'model' => Agent::class, 'chunk' => 20, 'take' => 60],
        'artwork artist' => ['probe' => 'belongs-to', 'model' => Artwork::class, 'id' => 27992, 'relation' => 'artist', 'foreign_key' => 'artist_id'],
        'eager artists' => ['probe' => 'eager', 'model' => Artwork::class, 'query' => fn (Builder $query) => $query->withQuery(['ids' => '27992,28560,28067,129884', 'fields' => 'id,artist_id']), 'relation' => 'artist', 'mode' => 'concurrent', 'foreign_key' => 'artist_id'],
        'missing artwork' => ['probe' => 'not-found', 'model' => Artwork::class, 'id' => 999999999],
        'response cache' => ['probe' => 'cache', 'kind' => 'response', 'model' => Artwork::class, 'id' => 27992],
        'search filters and sort' => [
            'probe' => 'unsupported',
            'features' => ['query.filter', 'query.sort'],
            'reason' => 'Field filters and sorting exist only on /artworks/search as Elasticsearch params (query[term][field]=, sort[field][order]=); a plain field=value filter on /artworks is silently ignored (title=Starry still returns the default unfiltered page). Workaround: from(\'artworks/search\')->withQuery([...]).',
            'attempt' => ['probe' => 'filter', 'model' => Artwork::class, 'field' => 'title', 'value' => 'Starry Night and the Astronauts', 'min' => 1],
        ],
        'batched artists' => [
            'probe' => 'unsupported',
            'features' => ['eager.batch'],
            'reason' => 'Membership param is ids while the primary key is id; BelongsTo batch sends whereIn(<primary key name>) as id=a,b, which artic ignores (returns the default unfiltered page), so no related agent resolves.',
            'attempt' => ['probe' => 'eager', 'model' => Artwork::class, 'query' => fn (Builder $query) => $query->limit(5)->withQuery(['fields' => 'id,artist_id']), 'relation' => 'artistBatched', 'mode' => 'batch'],
        ],
    ],
];
