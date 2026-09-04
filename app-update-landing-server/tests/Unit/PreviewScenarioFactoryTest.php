<?php

declare(strict_types=1);

namespace Tests\Unit;

use AppUpdateLanding\Development\PreviewScenarioFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/dev/PreviewScenarioFactory.php';

final class PreviewScenarioFactoryTest extends TestCase
{
    #[DataProvider('scenarioCases')]
    public function testCreatesEveryPreviewScenario(string $scenario, bool $showsStatusMessage): void
    {
        $view = (new PreviewScenarioFactory(dirname(__DIR__, 2)))->create($scenario, 'ja', 'ios');

        self::assertSame($scenario, $view->state);
        self::assertSame($showsStatusMessage, $view->statusMessage !== '');
    }

    /** @return array<string, array{string, bool}> */
    public static function scenarioCases(): array
    {
        return [
            'available' => ['available', false],
            'unreleased' => ['unreleased', false],
            'ended' => ['ended', false],
            'up-to-date' => ['up-to-date', true],
            'disabled' => ['disabled', true],
            'unsupported-os' => ['unsupported-os', true],
            'missing-destination' => ['missing-destination', true],
            'unavailable' => ['unavailable', true],
        ];
    }

    public function testUsesConfiguredLocalesAndSelectedPlatform(): void
    {
        $factory = new PreviewScenarioFactory(dirname(__DIR__, 2));

        self::assertContains('en', $factory->locales());
        self::assertContains('ar', $factory->locales());
        self::assertContains('ja', $factory->locales());
        self::assertSame('rtl', $factory->create('available', 'ar', 'android')->textDirection);
        self::assertStringEndsWith('/android', (string) $factory->create('available', 'en', 'android')->destinationUrl);
    }

    public function testRejectsUnknownSelections(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PreviewScenarioFactory(dirname(__DIR__, 2)))->create('unknown', 'ja', 'ios');
    }
}
