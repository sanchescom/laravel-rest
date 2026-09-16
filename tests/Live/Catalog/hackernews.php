<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Hackernews;

use Sanchescom\Rest\Model;

final class Item extends Model
{
    protected ?string $endpoint = 'item';

    protected ?string $dataKey = null;
}

final class TopStories extends Model
{
    protected ?string $endpoint = 'topstories.json';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Hacker News (Firebase)',
    'docs' => 'https://github.com/HackerNews/API',
    'base_uri' => 'https://hacker-news.firebaseio.com/v0/',
    'traits' => [
        'response' => 'bare int arrays (topstories) / bare-object item',
        'pagination' => 'none',
        'keys' => 'int id',
        'deviations' => '.json suffix required on every path',
        'errors' => 'unknown id -> 200 literal null',
    ],
    'scenarios' => [
        'suffix and null id' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'errors.not-found'],
            'reason' => 'Items live at item/{id}.json — a .json suffix appended after the id, not a bare path segment; find() renders item/8863 (no suffix), which 301-redirects (curl-verified Location: console.firebase.google.com/...) to the Firebase console\'s HTML UI, and decoding that HTML fails outright — that redirect-and-decode failure is what the attempt reproduces. errors.not-found is the second half of the same shape and is not separately exercised: even with the suffix, an unknown id answers HTTP 200 with the literal body null, which fails to decode as an array rather than triggering a 404.',
            'attempt' => ['probe' => 'find', 'model' => Item::class, 'id' => 8863],
        ],
        'id lists hydrate empty' => [
            'probe' => 'unsupported',
            'features' => ['read.list', 'relation.has-many'],
            'reason' => 'topstories.json (and every kids[] list) is a bare array of integers, not objects; hydrate() only keeps array items as attributes, so each bare int becomes an attribute-less model — the same shape would break a nested hasMany relation onto kids.',
            'attempt' => ['probe' => 'list', 'model' => TopStories::class, 'min' => 1, 'fields' => ['id']],
        ],
    ],
];
