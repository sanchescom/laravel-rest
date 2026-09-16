<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Genrenator;

use Sanchescom\Rest\Model;

final class Genre extends Model
{
    protected ?string $endpoint = 'genre';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Genrenator',
    'docs' => 'https://binaryjazz.us/genrenator-api/',
    'base_uri' => 'https://binaryjazz.us/wp-json/genrenator/v1/',
    'traits' => [
        'response' => 'bare JSON string (a quoted scalar, not an object or array)',
    ],
    'scenarios' => [
        'scalar body' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'read.list'],
            'reason' => 'The body is a bare JSON string literal (curl-verified: "seattle panpipe reel", content-type application/json) — valid JSON but not an object or array; Json::decode() requires the decoded value to be an array and throws otherwise, so hydration never runs.',
            'attempt' => ['probe' => 'list', 'model' => Genre::class],
        ],
    ],
];
