<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PublicIndexTest extends TestCase
{
    private const IOS_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1';
    private const ANDROID_USER_AGENT = 'Mozilla/5.0 (Linux; Android 14; Pixel 8 Build/AP1A.240505.005) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Mobile Safari/537.36';

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
            $isBeforeEvent = new \DateTimeImmutable() < new \DateTimeImmutable('2026-09-05T00:00:00+09:00');
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
                self::assertStringNotContainsString('content="assets/banner.webp"', $body);
                self::assertStringNotContainsString('alt="App logo"', $body);
                self::assertStringContainsString('PurrfectSpirits event update', $body);
                self::assertStringContainsString('<html lang="en-US" dir="ltr">', $body);
                self::assertStringContainsString('<h1 dir="auto">Pampas grass, dumplings and a moonlit sky come to the room.</h1>', $body);
                self::assertStringContainsString('<p class="description" dir="auto">While the event runs, your room becomes a veranda', $body);
                self::assertStringContainsString('<meta property="og:title" content="Pampas grass, dumplings and a moonlit sky come to the room.">', $body);
                self::assertStringContainsString('<meta property="og:description" content="Update to V2.9.0 and play the event from Sep 5–Oct 4.">', $body);
                self::assertStringContainsString('<p class="period" dir="auto">', $body);
                if (str_contains($body, '<a class="update-link"')) {
                    self::assertStringContainsString(
                        'href="' . htmlspecialchars($destination, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
                        $body
                    );
                } else {
                    self::assertStringContainsString('<small dir="auto">Coming Soon</small>', $body);
                }
            }

            $japaneseBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=ja-JP&platform=ios&osVersion=1"
            );
            self::assertIsString($japaneseBody);
            self::assertStringContainsString('<html lang="ja-JP" dir="ltr">', $japaneseBody);
            self::assertStringContainsString('<h1 dir="auto">すすきとだんごと月あかりの空が、お部屋にやってきます。</h1>', $japaneseBody);
            self::assertStringContainsString('<p class="description" dir="auto">イベント期間中、お部屋は月あかりの縁側になります。', $japaneseBody);
            self::assertStringContainsString('<meta property="og:title" content="すすきとだんごと月あかりの空が、お部屋にやってきます。">', $japaneseBody);
            self::assertStringContainsString('<meta property="og:description" content="V2.9.0へ更新して、9月5日〜10月4日のイベントを遊ぼう。">', $japaneseBody);
            self::assertStringContainsString('<p class="period" dir="auto">', $japaneseBody);
            if (str_contains($japaneseBody, '<a class="update-link"')) {
                $expectedButtonText = $isBeforeEvent ? '更新してイベントに備える' : '更新してイベントを遊ぶ';
                self::assertStringContainsString('<small dir="auto">' . $expectedButtonText . '</small>', $japaneseBody);
                self::assertStringContainsString('バージョン2.9.0に' . $expectedButtonText, $japaneseBody);
                self::assertStringContainsString('アップデートが反映されるまで、時間がかかる場合があります。', $japaneseBody);
            } else {
                self::assertStringContainsString('<small dir="auto">近日開始</small>', $japaneseBody);
            }
            self::assertStringNotContainsString('A new version is available.', $japaneseBody);
            self::assertStringNotContainsString('Event period:', $japaneseBody);
            self::assertStringNotContainsString('Current: V', $japaneseBody);

            $arabicBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=ar-SA&platform=ios&osVersion=1"
            );
            self::assertIsString($arabicBody);
            self::assertStringContainsString('<html lang="ar-SA" dir="rtl">', $arabicBody);
            self::assertStringContainsString('<h1 dir="auto">يأتي عشب البامباس وحلوى الدانغو وسماء يضيئها القمر إلى الغرفة.</h1>', $arabicBody);
            self::assertStringContainsString('<meta property="og:description" content="حدّث إلى V2.9.0 والعب الفعالية من 5 سبتمبر إلى 4 أكتوبر.">', $arabicBody);
            if (str_contains($arabicBody, '<a class="update-link"')) {
                self::assertStringContainsString('<span><bdi dir="ltr">V2.9.0</bdi></span>', $arabicBody);
                self::assertStringContainsString(
                    '<small dir="auto">' . ($isBeforeEvent ? 'حدّث واستعد للفعالية' : 'حدّث والعب الفعالية') . '</small>',
                    $arabicBody,
                );
            }

            $hebrewBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=he-IL&platform=ios&osVersion=1"
            );
            self::assertIsString($hebrewBody);
            self::assertStringContainsString('<html lang="he-IL" dir="rtl">', $hebrewBody);
            self::assertStringContainsString('<h1 dir="auto">עשב פמפס, דנגו ושמיים באור ירח מגיעים אל החדר.</h1>', $hebrewBody);
            self::assertStringContainsString('<meta property="og:description" content="עדכנו לגרסה V2.9.0 ושחקו באירוע מ־5 בספטמבר עד 4 באוקטובר.">', $hebrewBody);
            if (str_contains($hebrewBody, '<a class="update-link"')) {
                self::assertStringContainsString(
                    '<small dir="auto">' . ($isBeforeEvent ? 'עדכנו והתכוננו לאירוע' : 'עדכנו ושחקו באירוע') . '</small>',
                    $hebrewBody,
                );
            }

            $fallbackBody = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=fr-FR&platform=ios&osVersion=1"
            );
            self::assertIsString($fallbackBody);
            self::assertStringContainsString('<meta property="og:title" content="Pampas grass, dumplings and a moonlit sky come to the room.">', $fallbackBody);
            self::assertStringContainsString('<p class="period" dir="auto">', $fallbackBody);
            if (str_contains($fallbackBody, '<a class="update-link"')) {
                self::assertStringContainsString(
                    '<small dir="auto">' . ($isBeforeEvent ? 'Update and get ready for the event' : 'Update and play the event') . '</small>',
                    $fallbackBody,
                );
                self::assertStringContainsString('Updates may take some time to appear', $fallbackBody);
            } else {
                self::assertStringContainsString('<small dir="auto">Coming Soon</small>', $fallbackBody);
            }
            self::assertContainsHeader('Content-Security-Policy:', $http_response_header ?? []);
            self::assertContainsHeader('X-Content-Type-Options: nosniff', $http_response_header ?? []);
            self::assertContainsHeader('Referrer-Policy: no-referrer', $http_response_header ?? []);
            self::assertContainsHeader('X-Robots-Tag: noindex, nofollow, noarchive', $http_response_header ?? []);

            $iosBodyFromSharedAndroidUrl = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=en-US&platform=android&osVersion=1",
                false,
                $this->userAgentContext(self::IOS_USER_AGENT),
            );
            self::assertIsString($iosBodyFromSharedAndroidUrl);
            self::assertStringContainsString('href="https://itunes.apple.com/jp/app/id1269423920"', $iosBodyFromSharedAndroidUrl);
            self::assertStringNotContainsString('play.google.com', $iosBodyFromSharedAndroidUrl);

            $androidBodyFromSharedIosUrl = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=en-US&platform=ios&osVersion=1",
                false,
                $this->userAgentContext(self::ANDROID_USER_AGENT),
            );
            self::assertIsString($androidBodyFromSharedIosUrl);
            self::assertStringContainsString('href="https://play.google.com/store/apps/details?id=okinawa.harapeco.catRestaurant"', $androidBodyFromSharedIosUrl);
            self::assertStringNotContainsString('itunes.apple.com', $androidBodyFromSharedIosUrl);
            self::assertStringContainsString('Updates may take some time to appear', $androidBodyFromSharedIosUrl);

            $androidBodyWithoutPlatform = file_get_contents(
                "http://127.0.0.1:$port/?appVersion=0.0.0&targetVersion=2.9.0&locale=en-US&osVersion=1",
                false,
                $this->userAgentContext(self::ANDROID_USER_AGENT),
            );
            self::assertIsString($androidBodyWithoutPlatform);
            self::assertStringContainsString('href="https://play.google.com/store/apps/details?id=okinawa.harapeco.catRestaurant"', $androidBodyWithoutPlatform);
            self::assertStringContainsString('Updates may take some time to appear', $androidBodyWithoutPlatform);

            $bodyWithoutParameters = file_get_contents("http://127.0.0.1:$port/");
            self::assertIsString($bodyWithoutParameters);
            self::assertStringContainsString('<html lang="en" dir="ltr">', $bodyWithoutParameters);
            self::assertStringContainsString('<span><bdi dir="ltr">V2.9.0</bdi></span>', $bodyWithoutParameters);
            self::assertStringContainsString('href="https://www.harapeco.okinawa/info/app/neko_boku.html"', $bodyWithoutParameters);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /** @return resource */
    private function userAgentContext(string $userAgent)
    {
        return stream_context_create(['http' => ['header' => "User-Agent: $userAgent\r\n"]]);
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
