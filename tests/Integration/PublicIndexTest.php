<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PublicIndexTest extends TestCase
{
    public function testPublicResponseContainsSecurityHeadersAndConfiguredBanner(): void
    {
        $port = $this->freePort();
        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname(__DIR__, 2) . '/public'],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'a'],
                2 => ['file', '/dev/null', 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );
        self::assertIsResource($process);

        try {
            $this->waitForServer($port);
            $body = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=1.0.0&targetVersion=2.2.0&locale=en-US&platform=ios&osVersion=18.0"
            );

            self::assertIsString($body);
            self::assertStringContainsString('https://neko.harapeco.okinawa/event-update/assets/banner.webp', $body);
            self::assertStringContainsString('A new version is available.', $body);
            self::assertContainsHeader('Content-Security-Policy:', $http_response_header ?? []);
            self::assertContainsHeader('X-Content-Type-Options: nosniff', $http_response_header ?? []);
            self::assertContainsHeader('Referrer-Policy: no-referrer', $http_response_header ?? []);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /** @param list<string> $headers */
    private static function assertContainsHeader(string $expected, array $headers): void
    {
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), strtolower($expected))) {
                self::assertTrue(true);
                return;
            }
        }

        self::fail("Expected response header was not found: $expected");
    }

    private function waitForServer(int $port): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            usleep(10000);
        }

        self::fail('PHP development server did not start.');
    }

    private function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($server);
        $name = stream_socket_get_name($server, false);
        fclose($server);

        self::assertIsString($name);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
