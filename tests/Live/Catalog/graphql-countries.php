<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\GraphqlCountries;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Live\Support\LiveContext;

final class Country extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = 'data.country';

    protected string $primaryKey = 'code';
}

return [
    'name' => 'Countries GraphQL API',
    'docs' => 'https://github.com/trevorblades/countries',
    'base_uri' => 'https://countries.trevorblades.com/',
    'traits' => [
        'response' => 'graphql {data} / {errors}',
        'pagination' => 'none',
        'deviations' => 'single POST endpoint',
        'errors' => '200 with errors[]',
    ],
    'scenarios' => [
        'query via post workaround' => [
            'probe' => 'custom',
            'features' => [],
            'run' => function (LiveContext $context) {
                $country = (new Country)->newBuilder()->post([
                    'query' => '{ country(code: "BR") { code name } }',
                ]);

                expect($country)->not->toBeNull()
                    ->and($country->getAttribute('code'))->toBe('BR')
                    ->and($country->getAttribute('name'))->toBe('Brazil');
            },
        ],
        'graphql only' => [
            'probe' => 'unsupported',
            'features' => ['read.list', 'read.find', 'query.filter', 'errors.not-found'],
            'reason' => 'Every read is a POST {query} to one endpoint; find()/list() always issue a GET, which the server answers with an empty 204 body, so hydration always yields an attribute-less model — GraphQL query errors also come back as HTTP 200 with errors[], not a REST-style status code.',
            'attempt' => ['probe' => 'find', 'model' => Country::class, 'id' => 'BR'],
        ],
    ],
];
