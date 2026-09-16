<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Gbif;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;

final class Species extends Model
{
    protected ?string $endpoint = 'species';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'key';

    public function parentSpecies(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'parentKey');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SpeciesChild::class)->nested();
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'speciesKey');
    }
}

final class SpeciesChild extends Model
{
    protected ?string $endpoint = 'children';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'key';
}

final class Occurrence extends Model
{
    protected ?string $endpoint = 'occurrence/search';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'key';
}

final class DatasetList extends Model
{
    protected ?string $endpoint = 'dataset';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'key';
}

final class SpeciesSearchList extends Model
{
    protected ?string $endpoint = 'species/search';

    protected ?string $dataKey = 'results';

    protected string $primaryKey = 'key';

    public function parentSpecies(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'parentKey');
    }
}

return [
    'name' => 'GBIF API',
    'docs' => 'https://techdocs.gbif.org/en/openapi/',
    'base_uri' => 'https://api.gbif.org/v1/',
    'traits' => [
        'response' => 'results + offset/limit/endOfRecords/count',
        'pagination' => 'offset + limit; total in body:count (absent on /children)',
        'filters' => 'field=value; no sort parameters',
        'keys' => 'int key (species) or uuid key (dataset)',
        'relations' => 'parentKey -> species/{key}; species/{key}/children; occurrence/search?speciesKey=',
        'errors' => '404 JSON; 400 text/plain',
    ],
    'client' => [
        'pagination' => ['style' => 'offset', 'total' => 'count'],
    ],
    'scenarios' => [
        'list datasets' => ['probe' => 'list', 'model' => DatasetList::class, 'query' => fn (Builder $query) => $query->limit(20), 'min' => 20],
        'find species' => ['probe' => 'find', 'model' => Species::class, 'id' => 5231190, 'fields' => ['scientificName']],
        'get many species' => ['probe' => 'get-many', 'model' => Species::class, 'ids' => [5231190, 2492321, 212]],
        'filter species by rank' => ['probe' => 'filter', 'model' => SpeciesSearchList::class, 'field' => 'rank', 'value' => 'GENUS', 'min' => 10],
        'paginate datasets' => ['probe' => 'paginate', 'model' => DatasetList::class, 'per_page' => 10],
        'lazy walk datasets' => ['probe' => 'lazy', 'model' => DatasetList::class, 'chunk' => 20, 'take' => 60],
        'species parent' => ['probe' => 'belongs-to', 'model' => Species::class, 'id' => 5231190, 'relation' => 'parentSpecies', 'foreign_key' => 'parentKey'],
        'species children' => ['probe' => 'has-many', 'model' => Species::class, 'id' => 5231190, 'relation' => 'children', 'nested' => true, 'path_suffix' => 'species/5231190/children'],
        'species occurrences' => ['probe' => 'has-many', 'model' => Species::class, 'id' => 5231190, 'relation' => 'occurrences', 'foreign_key' => 'speciesKey'],
        'eager parents' => ['probe' => 'eager', 'model' => SpeciesSearchList::class, 'query' => fn (Builder $query) => $query->where('rank', 'GENUS')->limit(3), 'relation' => 'parentSpecies', 'mode' => 'concurrent', 'foreign_key' => 'parentKey'],
        'missing species' => ['probe' => 'not-found', 'model' => Species::class, 'id' => 999999999],
        'membership' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in', 'eager.batch'],
            'reason' => 'Membership needs repeated params for OR (rank=GENUS&rank=FAMILY works); a comma list is rejected outright for an enum field like rank (400 "Cannot parse GENUS,FAMILY into a known Rank"). dataset?key= has no filter at all (key=A, key=A&key=B, key=A,B all return the full corpus), so that endpoint cannot demonstrate the limitation.',
            'attempt' => ['probe' => 'where-in', 'model' => SpeciesSearchList::class, 'field' => 'rank', 'values' => ['GENUS', 'FAMILY']],
        ],
        'sorting' => [
            'probe' => 'unsupported',
            'features' => ['query.sort'],
            'reason' => 'No sort parameters on any list endpoint.',
        ],
    ],
];
