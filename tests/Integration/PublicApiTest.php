<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublicApiTest extends TestCase
{
    public function testPublicApiReturnsTheCurrentReleaseWithoutAuthentication(): void
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
            $body = file_get_contents("http://127.0.0.1:$port/api/");
            self::assertIsString($body);
            $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);

            $now = new DateTimeImmutable();
            $startAt = new DateTimeImmutable($payload['eventPeriod']['startAt']);
            $endAt = new DateTimeImmutable($payload['eventPeriod']['endAt']);
            $expectedEventPhase = $now < $startAt ? 'upcoming' : ($now > $endAt ? 'ended' : 'active');

            self::assertSame(1, $payload['schemaVersion']);
            self::assertSame('2.9.0', $payload['releaseVersion']);
            self::assertTrue($payload['enabled']);
            self::assertSame($expectedEventPhase, $payload['eventPeriod']['phase']);
            self::assertTrue($payload['platforms']['ios']['released']);
            self::assertSame(
                'https://itunes.apple.com/jp/app/id1269423920',
                $payload['platforms']['ios']['targetUrl'],
            );
            self::assertContainsHeader('Content-Type: application/json; charset=UTF-8', $http_response_header ?? []);
            self::assertContainsHeader('Access-Control-Allow-Origin: *', $http_response_header ?? []);
            self::assertContainsHeader('X-Robots-Tag: noindex, nofollow, noarchive', $http_response_header ?? []);
            self::assertContainsHeader('Cache-Control: no-store', $http_response_header ?? []);
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
