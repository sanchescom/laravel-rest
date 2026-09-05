<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Support;

use RuntimeException;

final class FixtureServer
{
    public static string $baseUri = 'http://127.0.0.1:8937/';

    /** @var resource|null */
    private static $process = null;

    public static function start(): void
    {
        if (self::$process !== null) {
            return;
        }

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:8937', __DIR__.'/../Fixtures/server.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if ($process === false) {
            throw new RuntimeException('Could not spawn fixture server.');
        }

        self::$process = $process;
        register_shutdown_function(static fn () => self::stop());

        for ($i = 0; $i < 50; $i++) {
            if (@file_get_contents(self::$baseUri.'ping') !== false) {
                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException('Fixture server did not become ready.');
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            self::$process = null;
        }
    }
}
