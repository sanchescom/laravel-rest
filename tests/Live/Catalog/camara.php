<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Camara;

use Sanchescom\Rest\Builder;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Relations\BelongsTo;
use Sanchescom\Rest\Relations\HasMany;

final class Deputado extends Model
{
    protected ?string $endpoint = 'deputados';

    protected ?string $dataKey = 'dados';

    public function legislatura(): BelongsTo
    {
        return $this->belongsTo(Legislatura::class, 'idLegislatura');
    }

    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'siglaPartido')->batch();
    }
}

final class Partido extends Model
{
    protected ?string $endpoint = 'partidos';

    protected ?string $dataKey = 'dados';

    protected string $primaryKey = 'sigla';
}

final class Legislatura extends Model
{
    protected ?string $endpoint = 'legislaturas';

    protected ?string $dataKey = 'dados';

    public function deputados(): HasMany
    {
        return $this->hasMany(Deputado::class, 'idLegislatura');
    }

    public function deputadosBatched(): HasMany
    {
        return $this->hasMany(Deputado::class, 'idLegislatura')->batch();
    }
}

final class Frente extends Model
{
    protected ?string $endpoint = 'frentes';

    protected ?string $dataKey = 'dados';

    public function legislatura(): BelongsTo
    {
        return $this->belongsTo(Legislatura::class, 'idLegislatura');
    }

    public function membros(): HasMany
    {
        return $this->hasMany(Membro::class)->nested();
    }
}

final class Membro extends Model
{
    protected ?string $endpoint = 'membros';

    protected ?string $dataKey = 'dados';
}

return [
    'name' => 'Câmara dos Deputados Dados Abertos',
    'docs' => 'https://dadosabertos.camara.leg.br/swagger/api.html',
    'base_uri' => 'https://dadosabertos.camara.leg.br/api/v2/',
    'throttle_ms' => 1200,
    'traits' => [
        'response' => 'dados + links[] ({rel,href} array), for both list and detail',
        'pagination' => 'pagina + itens; total in header X-Total-Count',
        'filters' => 'field=value, comma lists (siglaUf=SP,RJ)',
        'sort' => 'ordem=asc|desc + ordenarPor=field',
        'keys' => 'int id',
        'relations' => 'idLegislatura fk, siglaPartido -> partidos?sigla=, nested frentes/{id}/membros',
        'errors' => '404/400 problem+json',
        'rate_limit' => 'Retry-After: 30 on every response; slow (1-10 s)',
    ],
    'client' => [
        'query' => [
            'names' => ['page' => 'pagina', 'limit' => 'itens'],
            'sort' => 'separate',
            'sort_names' => ['field' => 'ordenarPor', 'direction' => 'ordem'],
        ],
        'options' => ['timeout' => 30],
    ],
    'scenarios' => [
        'list deputies' => ['probe' => 'list', 'model' => Deputado::class, 'query' => fn (Builder $query) => $query->limit(15), 'min' => 15, 'fields' => ['id', 'siglaUf']],
        'find deputy' => ['probe' => 'find', 'model' => Deputado::class, 'id' => 220593, 'fields' => ['nomeCivil']],
        'get many deputies' => ['probe' => 'get-many', 'model' => Deputado::class, 'ids' => [220593, 236518, 236423]],
        'filter by state' => ['probe' => 'filter', 'model' => Deputado::class, 'query' => fn (Builder $query) => $query->limit(15), 'field' => 'siglaUf', 'value' => 'SP', 'min' => 10],
        'where-in states' => ['probe' => 'where-in', 'model' => Deputado::class, 'query' => fn (Builder $query) => $query->limit(20), 'field' => 'siglaUf', 'values' => ['SP', 'RJ']],
        'sort by id desc' => ['probe' => 'sort', 'model' => Deputado::class, 'query' => fn (Builder $query) => $query->limit(20), 'field' => 'id', 'direction' => 'desc'],
        'simple paginate deputies' => ['probe' => 'simple-paginate', 'model' => Deputado::class, 'per_page' => 20],
        'lazy walk deputies' => ['probe' => 'lazy', 'model' => Deputado::class, 'chunk' => 100, 'take' => 300],
        'front legislature' => ['probe' => 'belongs-to', 'model' => Frente::class, 'id' => 54258, 'relation' => 'legislatura', 'foreign_key' => 'idLegislatura'],
        'legislature deputies' => ['probe' => 'has-many', 'model' => Legislatura::class, 'id' => 57, 'relation' => 'deputados', 'foreign_key' => 'idLegislatura'],
        'front members' => ['probe' => 'has-many', 'model' => Frente::class, 'id' => 54258, 'relation' => 'membros', 'nested' => true, 'path_suffix' => 'frentes/54258/membros'],
        // Fronts are paged newest-first by id; page 107 at 3/page (items 319-321) straddles the
        // legislatura 57/56 boundary (X-Total-Count for idLegislatura=57 was 320 on 2026-09-15),
        // so 3 fronts here carry 2 distinct idLegislatura values instead of all sharing the current one.
        'eager front legislature' => ['probe' => 'eager', 'model' => Frente::class, 'query' => fn (Builder $query) => $query->limit(3)->page(107), 'relation' => 'legislatura', 'mode' => 'concurrent', 'foreign_key' => 'idLegislatura'],
        'batched legislature deputies' => ['probe' => 'eager', 'model' => Legislatura::class, 'query' => fn (Builder $query) => $query->limit(2), 'relation' => 'deputadosBatched', 'mode' => 'batch', 'foreign_key' => 'idLegislatura'],
        'batched deputy party' => ['probe' => 'eager', 'model' => Deputado::class, 'query' => fn (Builder $query) => $query->limit(10), 'relation' => 'partido', 'mode' => 'batch', 'foreign_key' => 'siglaPartido'],
        'missing deputy' => ['probe' => 'not-found', 'model' => Deputado::class, 'id' => 1],
        'invalid page size' => ['probe' => 'status', 'model' => Deputado::class, 'query' => fn (Builder $query) => $query->withQuery(['itens' => 'abc']), 'status' => 400],
        'paginate with total' => [
            'probe' => 'unsupported',
            'features' => ['paginate.total'],
            'reason' => 'Total is only exposed in the X-Total-Count header (and next only via the Link header or a positional links[] array); paginate() reads totals from the response body.',
            'attempt' => ['probe' => 'paginate', 'model' => Deputado::class, 'per_page' => 15],
        ],
        'retry-after' => [
            'probe' => 'unsupported',
            'features' => ['retry.status'],
            'reason' => 'No synthetic status endpoint exists here to safely demonstrate a live retry, so client.retry is left absent rather than configured for a feature not actually exercised. (Every response also carries Retry-After: 30, which is why a retry config, if one were added, would need respect_retry_after=false to avoid a 30s wait per attempt.)',
        ],
    ],
];
