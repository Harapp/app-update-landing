<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PublicIndexTest extends TestCase
{
    public function testPublicResponseUsesPurrfectSpiritsConfigurationForEveryPlatform(): void
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
            $destinations = [
                'ios' => 'https://itunes.apple.com/jp/app/id1269423920',
                'android' => 'https://play.google.com/store/apps/details?id=okinawa.harapeco.catRestaurant',
                'pc' => 'https://www.harapeco.okinawa/info/app/neko_boku.html',
            ];
            foreach ($destinations as $platform => $destination) {
                $body = file_get_contents(
                    "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=en-US&platform=$platform&osVersion=1"
                );

                self::assertIsString($body);
                self::assertStringContainsString('https://neko.harapeco.okinawa/event-update/assets/banner.webp', $body);
                self::assertStringNotContainsString('alt="App logo"', $body);
                self::assertStringContainsString('PurrfectSpirits event update', $body);
                self::assertStringContainsString(
                    'href="' . htmlspecialchars($destination, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
                    $body
                );
            }

            $japaneseBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=ja-JP&platform=ios&osVersion=1"
            );
            self::assertIsString($japaneseBody);
            self::assertStringContainsString('PurrfectSpirits イベントアップデート（仮コンテンツ）', $japaneseBody);
            self::assertStringContainsString('<small>更新してイベントを遊ぶ</small>', $japaneseBody);
            self::assertStringContainsString('バージョン2.9.0に更新してイベントを遊ぶ', $japaneseBody);
            self::assertStringContainsString('アップデートが反映されるまで、時間がかかる場合があります。', $japaneseBody);
            self::assertStringNotContainsString('A new version is available.', $japaneseBody);
            self::assertStringNotContainsString('Event period:', $japaneseBody);
            self::assertStringNotContainsString('Current: V', $japaneseBody);

            $fallbackBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=fr-FR&platform=ios&osVersion=1"
            );
            self::assertIsString($fallbackBody);
            self::assertStringContainsString('<small>Update and play the event</small>', $fallbackBody);
            self::assertStringContainsString('Updates may take some time to appear', $fallbackBody);
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
