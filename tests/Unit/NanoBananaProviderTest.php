<?php

namespace Tests\Unit;

use App\Services\ImageProviders\NanoBananaProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NanoBananaProviderTest extends TestCase
{
    public function test_status_aliases_are_normalized_for_async_polling(): void
    {
        $provider = new NanoBananaProvider();

        $normalize = $this->method('normalizeStatus');
        $isSucceeded = $this->method('isSucceededStatus');
        $isFailed = $this->method('isFailedStatus');

        $this->assertSame('success', $normalize->invoke($provider, ' SUCCESS '));
        $this->assertTrue($isSucceeded->invoke($provider, 'success'));
        $this->assertTrue($isSucceeded->invoke($provider, 'finished'));
        $this->assertTrue($isSucceeded->invoke($provider, 'done'));
        $this->assertTrue($isFailed->invoke($provider, 'failed'));
        $this->assertTrue($isFailed->invoke($provider, 'cancelled'));
    }

    public function test_extract_image_items_finds_nested_gemini_result_urls(): void
    {
        $provider = new NanoBananaProvider();
        $extract = $this->method('extractImageItems');

        $items = $extract->invoke($provider, [
            'code' => 200,
            'data' => [
                'state' => 'succeeded',
                'data' => [
                    'images' => [
                        ['url' => 'https://cdn.example.com/results/a.png'],
                    ],
                ],
            ],
        ]);

        $this->assertSame([['url' => 'https://cdn.example.com/results/a.png']], $items);
    }

    private function method(string $name): ReflectionMethod
    {
        $method = new ReflectionMethod(NanoBananaProvider::class, $name);
        $method->setAccessible(true);

        return $method;
    }
}
