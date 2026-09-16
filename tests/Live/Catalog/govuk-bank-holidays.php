<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\GovukBankHolidays;

use Sanchescom\Rest\Model;

final class DivisionEvents extends Model
{
    protected ?string $endpoint = 'bank-holidays.json';

    protected ?string $dataKey = 'england-and-wales.events';

    protected string $primaryKey = 'date';
}

final class Division extends Model
{
    protected ?string $endpoint = 'bank-holidays.json';

    protected ?string $dataKey = null;

    protected string $primaryKey = 'division';
}

return [
    'name' => 'GOV.UK Bank Holidays',
    'docs' => 'https://www.api.gov.uk/gds/bank-holidays/',
    'base_uri' => 'https://www.gov.uk/',
    'traits' => [
        'response' => 'top-level object keyed by division {england-and-wales:{division,events[]}}',
        'pagination' => 'none',
        'deviations' => '.json suffix',
    ],
    'scenarios' => [
        'list events of one division' => [
            'probe' => 'list',
            'model' => DivisionEvents::class,
            'min' => 50,
            'fields' => ['title', 'date'],
        ],
        'list divisions (top-level keyed object)' => [
            'probe' => 'list',
            'model' => Division::class,
            'min' => 3,
            'fields' => ['division', 'events'],
        ],
    ],
];
