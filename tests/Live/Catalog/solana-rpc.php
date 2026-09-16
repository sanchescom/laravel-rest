<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\SolanaRpc;

use Sanchescom\Rest\Model;

final class RpcResponse extends Model
{
    protected ?string $endpoint = '';

    protected ?string $dataKey = null;
}

return [
    'name' => 'Solana JSON-RPC (mainnet-beta)',
    'docs' => 'https://solana.com/docs/rpc',
    'base_uri' => 'https://api.mainnet-beta.solana.com/',
    'throttle_ms' => 2000,
    'traits' => [
        'response' => 'JSON-RPC 2.0 {jsonrpc,id,result|error}',
        'deviations' => 'all-POST',
        'errors' => '200 with error object',
        'rate_limit' => 'public endpoint rate-limited; one request per run',
    ],
    'scenarios' => [
        'json-rpc' => [
            'probe' => 'unsupported',
            'features' => ['read.find', 'read.list'],
            'reason' => 'Every call is POST {"jsonrpc":"2.0",...} to one endpoint; find()/list() always issue a GET, which the server rejects with HTTP 405 "Bad method" (curl-verified {"jsonrpc":"2.0","error":{"code":405,"message":"Bad method"}}), so no read ever reaches a result. The package raises a correct typed client error for that 405 — errors.client is therefore not claimed unsupported; what stays untested here is the JSON-RPC application-error shape (HTTP 200 carrying an error member), which needs a POST read path the package does not offer.',
            'attempt' => ['probe' => 'find', 'model' => RpcResponse::class, 'id' => 'getHealth'],
        ],
    ],
];
