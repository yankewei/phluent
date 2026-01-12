<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExceptionAssertions
{
    public static function assertRuntimeExceptionMessageContains(
        TestCase $test,
        callable $callable,
        string $expectedSubstring,
    ): void {
        try {
            $callable();
            $test->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $test->assertStringContainsString($expectedSubstring, $exception->getMessage());
        }
    }
}
