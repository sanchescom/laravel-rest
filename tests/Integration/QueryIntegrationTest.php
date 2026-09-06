<?php

declare(strict_types=1);

use Sanchescom\Rest\ClientResolver;
use Sanchescom\Rest\Clients\GuzzleClient;
use Sanchescom\Rest\Model;
use Sanchescom\Rest\Tests\Support\FixtureServer;

beforeAll(fn () => FixtureServer::start());

class EchoItem extends Model
{
    protected ?string $endpoint = 'echo';

    protected ?string $dataKey = 'query';
}

it('sends compiled query over real http', function () {
    $resolver = new ClientResolver([
        'fixture' => GuzzleClient::fromConfig(['base_uri' => FixtureServer::$baseUri]),
    ]);
    $resolver->setDefaultClient('fixture');
    Model::setClientResolver($resolver);

    $echoed = EchoItem::where('userId', 1)->orderBy('date', 'desc')->limit(5)->get();

    expect($echoed)->not->toBeNull();

    // assert exact wire format via raw echo
    $raw = json_decode(file_get_contents(FixtureServer::$baseUri.'echo?userId=1&sort=-date&limit=5'), true);
    expect($raw['query'])->toBe(['userId' => '1', 'sort' => '-date', 'limit' => '5']);
})->group('integration');
