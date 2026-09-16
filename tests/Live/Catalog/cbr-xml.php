<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\CbrXml;

use Sanchescom\Rest\Model;

final class DailyRates extends Model
{
    protected ?string $endpoint = 'XML_daily.asp';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Bank of Russia daily rates (XML)',
    'docs' => 'https://www.cbr.ru/development/SXML/',
    'base_uri' => 'https://www.cbr.ru/scripts/',
    'traits' => [
        'response' => 'xml (windows-1251)',
        'formats' => 'xml only',
    ],
    'scenarios' => [
        'xml only' => [
            'probe' => 'unsupported',
            'features' => ['read.list'],
            'reason' => 'The response is windows-1251 XML (curl-verified content-type application/xml; charset=windows-1251), not JSON; the package only decodes JSON bodies, so this needs a custom ClientInterface converting XML to arrays, which is outside a catalog file\'s scope.',
            'attempt' => ['probe' => 'list', 'model' => DailyRates::class],
        ],
    ],
];
