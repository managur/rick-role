<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Exception\ConfigurationException;

/**
 * Unit tests for the ConfigurationException class.
 */
final class ConfigurationExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_with_default_message(): void
    {
        $exception = new ConfigurationException();

        self::assertInstanceOf(ConfigurationException::class, $exception);
        self::assertInstanceOf(\Exception::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function it_creates_with_custom_message(): void
    {
        $message = 'Invalid voter configuration';
        $exception = new ConfigurationException($message);

        self::assertSame($message, $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function it_creates_with_message_and_code(): void
    {
        $message = 'Configuration error';
        $code = 500;
        $exception = new ConfigurationException($message, $code);

        self::assertSame($message, $exception->getMessage());
        self::assertSame($code, $exception->getCode());
    }

    #[Test]
    public function it_creates_with_previous_exception(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new ConfigurationException('Configuration failed', 0, $previous);

        self::assertSame('Configuration failed', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function it_creates_with_all_parameters(): void
    {
        $message = 'Complete configuration error';
        $code = 404;
        $previous = new \InvalidArgumentException('Invalid argument');

        $exception = new ConfigurationException($message, $code, $previous);

        self::assertSame($message, $exception->getMessage());
        self::assertSame($code, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function it_handles_empty_message(): void
    {
        $exception = new ConfigurationException('');

        self::assertSame('', $exception->getMessage());
        self::assertEmpty($exception->getMessage());
    }

    #[Test]
    public function it_handles_null_previous(): void
    {
        $exception = new ConfigurationException('Test message', 0, null);

        self::assertNull($exception->getPrevious());
    }

    #[Test]
    public function it_handles_zero_code(): void
    {
        $exception = new ConfigurationException('Test', 0);

        self::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function it_handles_negative_code(): void
    {
        $exception = new ConfigurationException('Test', -1);

        self::assertSame(-1, $exception->getCode());
    }

    #[Test]
    public function it_handles_large_code(): void
    {
        $exception = new ConfigurationException('Test', 999999);

        self::assertSame(999999, $exception->getCode());
    }

    #[Test]
    public function it_preserves_stack_trace(): void
    {
        $exception = new ConfigurationException('Test exception');

        self::assertNotEmpty($exception->getTrace());
    }

    #[Test]
    public function it_has_correct_file_and_line(): void
    {
        $line = __LINE__ + 1;
        $exception = new ConfigurationException('Test exception');

        self::assertSame(__FILE__, $exception->getFile());
        self::assertSame($line, $exception->getLine());
    }

    #[Test]
    public function it_converts_to_string(): void
    {
        $exception = new ConfigurationException('Test message', 123);
        $string = (string) $exception;

        self::assertStringContainsString('ConfigurationException', $string);
        self::assertStringContainsString('Test message', $string);
        self::assertStringContainsString(__FILE__, $string);
    }

    #[Test]
    public function it_handles_long_message(): void
    {
        $longMessage = str_repeat('Long configuration error message. ', 100);
        $exception = new ConfigurationException($longMessage);

        self::assertSame($longMessage, $exception->getMessage());
    }

    #[Test]
    public function it_handles_special_characters_in_message(): void
    {
        $message = 'Config error: "invalid" value @#$%^&*()';
        $exception = new ConfigurationException($message);

        self::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function it_handles_unicode_in_message(): void
    {
        $message = 'Configuration 错误: 配置无效 🚫';
        $exception = new ConfigurationException($message);

        self::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function it_creates_chained_exceptions(): void
    {
        $root = new \RuntimeException('Root cause');
        $middle = new \InvalidArgumentException('Middle error', 0, $root);
        $final = new ConfigurationException('Final error', 0, $middle);

        self::assertSame($middle, $final->getPrevious());
        self::assertSame($root, $final->getPrevious()->getPrevious());
    }

    #[Test]
    public function it_maintains_exception_hierarchy(): void
    {
        $exception = new ConfigurationException();

        self::assertInstanceOf(\Exception::class, $exception);
        self::assertInstanceOf(\Throwable::class, $exception);
        self::assertInstanceOf(ConfigurationException::class, $exception);
    }

    #[Test]
    public function it_can_be_caught_as_base_exception(): void
    {
        try {
            throw new ConfigurationException('Test');
        } catch (\Exception $e) {
            self::assertInstanceOf(ConfigurationException::class, $e);
            return; // Test passes if we reach here
        }
    }

    #[Test]
    public function it_can_be_caught_as_throwable(): void
    {
        try {
            throw new ConfigurationException('Test');
        } catch (\Throwable $e) {
            self::assertInstanceOf(ConfigurationException::class, $e);
            return; // Test passes if we reach here
        }
    }

    #[DataProvider('exceptionVariousData')]
    #[Test]
    public function it_creates_exception_with_various_data(string $message, int $code, string $description): void
    {
        $exception = new ConfigurationException($message, $code);

        self::assertSame($message, $exception->getMessage());
        self::assertSame($code, $exception->getCode());
        self::assertInstanceOf(ConfigurationException::class, $exception);
    }

    /** @return array<string, array{message: string, code: int, description: string}> */
    public static function exceptionVariousData(): array
    {
        return [
            'basic message' => [
                'message' => 'Configuration error',
                'code' => 0,
                'description' => 'Basic configuration error'
            ],
            'with error code' => [
                'message' => 'Invalid voter setup',
                'code' => 100,
                'description' => 'Configuration error with specific code'
            ],
            'empty message' => [
                'message' => '',
                'code' => 404,
                'description' => 'Empty message with error code'
            ],
            'long message' => [
                'message' => str_repeat('Very long error message. ', 50),
                'code' => 500,
                'description' => 'Very long error message'
            ],
            'special chars' => [
                'message' => 'Error: "config" <script>alert("test")</script>',
                'code' => 999,
                'description' => 'Message with special characters'
            ],
            'unicode message' => [
                'message' => '错误配置',
                'code' => 1001,
                'description' => 'Unicode error message'
            ],
        ];
    }
}
