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
            foreach (['ios', 'android', 'pc'] as $platform) {
                $body = file_get_contents(
                    "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=en-US&platform=$platform&osVersion=1"
                );

                self::assertIsString($body);
                self::assertStringContainsString('https://neko.harapeco.okinawa/event-update/assets/banner.webp', $body);
                self::assertStringNotContainsString('content="assets/banner.webp"', $body);
                self::assertStringNotContainsString('alt="App logo"', $body);
                self::assertStringContainsString('PurrfectSpirits event update', $body);
                self::assertStringContainsString('<html lang="en-US" dir="ltr">', $body);
                self::assertStringContainsString('<h1 dir="auto">Moonlit Night</h1>', $body);
                self::assertStringContainsString('<p class="description" dir="auto">A quiet veranda in the moonlight.', $body);
                self::assertStringContainsString('<meta property="og:title" content="Moonlit Night">', $body);
                self::assertStringContainsString('<meta property="og:description" content="A quiet veranda in the moonlight.', $body);
                self::assertStringContainsString('<p class="period" dir="auto">', $body);
            }

            $japaneseBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=ja-JP&platform=ios&osVersion=1"
            );
            self::assertIsString($japaneseBody);
            self::assertStringContainsString('<html lang="ja-JP" dir="ltr">', $japaneseBody);
            self::assertStringContainsString('<h1 dir="auto">おつきみ日和</h1>', $japaneseBody);
            self::assertStringContainsString('<p class="description" dir="auto">月あかりのさす、しずかな縁側。', $japaneseBody);
            self::assertStringContainsString('<meta property="og:title" content="おつきみ日和">', $japaneseBody);
            self::assertStringContainsString('<meta property="og:description" content="月あかりのさす、しずかな縁側。', $japaneseBody);
            self::assertStringContainsString('<p class="period" dir="auto">', $japaneseBody);
            self::assertStringNotContainsString('A new version is available.', $japaneseBody);
            self::assertStringNotContainsString('Event period:', $japaneseBody);
            self::assertStringNotContainsString('Current: V', $japaneseBody);

            $arabicBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=ar-SA&platform=ios&osVersion=1"
            );
            self::assertIsString($arabicBody);
            self::assertStringContainsString('<html lang="ar-SA" dir="rtl">', $arabicBody);
            self::assertStringContainsString('<h1 dir="auto">ليلة القمر</h1>', $arabicBody);
            self::assertStringContainsString('<meta property="og:description" content="شرفة هادئة في ضوء القمر.', $arabicBody);
            self::assertStringContainsString('<span><bdi dir="ltr">V2.9.0</bdi></span>', $arabicBody);

            $hebrewBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=he-IL&platform=ios&osVersion=1"
            );
            self::assertIsString($hebrewBody);
            self::assertStringContainsString('<html lang="he-IL" dir="rtl">', $hebrewBody);
            self::assertStringContainsString('<h1 dir="auto">ליל ירח</h1>', $hebrewBody);
            self::assertStringContainsString('<meta property="og:description" content="מרפסת שקטה לאור הירח.', $hebrewBody);
            $fallbackBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=ga-IE&platform=ios&osVersion=1"
            );
            self::assertIsString($fallbackBody);
            self::assertStringContainsString('<meta property="og:title" content="Moonlit Night">', $fallbackBody);
            self::assertStringContainsString('<p class="period" dir="auto">', $fallbackBody);
            self::assertContainsHeader('Content-Security-Policy:', $http_response_header ?? []);
            self::assertContainsHeader('X-Content-Type-Options: nosniff', $http_response_header ?? []);
            self::assertContainsHeader('Referrer-Policy: no-referrer', $http_response_header ?? []);
            self::assertContainsHeader('X-Robots-Tag: noindex, nofollow, noarchive', $http_response_header ?? []);

            $bodyWithoutParameters = file_get_contents("http://127.0.0.1:$port/");
            self::assertIsString($bodyWithoutParameters);
            self::assertStringContainsString('<html lang="en" dir="ltr">', $bodyWithoutParameters);
            self::assertStringContainsString('<span><bdi dir="ltr">V2.9.0</bdi></span>', $bodyWithoutParameters);

            $japaneseBrowserBody = file_get_contents(
                "http://127.0.0.1:$port/?platform=pc",
                false,
                $this->headerContext(['Accept-Language: ja-JP,ja;q=0.9,en;q=0.8']),
            );
            self::assertIsString($japaneseBrowserBody);
            self::assertStringContainsString('<html lang="ja-JP" dir="ltr">', $japaneseBrowserBody);
            self::assertStringContainsString('<h1 dir="auto">おつきみ日和</h1>', $japaneseBrowserBody);

            $explicitEnglishBody = file_get_contents(
                "http://127.0.0.1:$port/?locale=en&platform=pc",
                false,
                $this->headerContext(['Accept-Language: ja-JP,ja;q=0.9']),
            );
            self::assertIsString($explicitEnglishBody);
            self::assertStringContainsString('<html lang="en" dir="ltr">', $explicitEnglishBody);
            self::assertStringContainsString('<h1 dir="auto">Moonlit Night</h1>', $explicitEnglishBody);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /** @param list<string> $headers @return resource */
    private function headerContext(array $headers)
    {
        return stream_context_create(['http' => ['header' => implode("\r\n", $headers) . "\r\n"]]);
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
