<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class NumericConversion
{
    /**
     * @template T of \Throwable
     * @param callable(string):bool $validator
     * @param callable(string, \InvalidArgumentException):T $exceptionFactory
     */
    public static function bytesFromDecimal(
        string $id,
        int $length,
        callable $validator,
        string $validatorMessage,
        string $errorMessage,
        callable $exceptionFactory,
    ): string {
        try {
            return NumericIdCodec::bytesFromDecimal(
                $id,
                $length,
                $validator,
                $validatorMessage,
            );
        } catch (\InvalidArgumentException $exception) {
            throw $exceptionFactory($errorMessage, $exception);
        }
    }

    /**
     * @template T of \Throwable
     * @param callable(string, \InvalidArgumentException):T $exceptionFactory
     */
    public static function decimalFromBase(
        string $encoded,
        int $base,
        int $length,
        callable $exceptionFactory,
    ): string {
        try {
            return NumericIdCodec::decimalFromBase($encoded, $base, $length);
        } catch (\InvalidArgumentException $exception) {
            throw $exceptionFactory($exception->getMessage(), $exception);
        }
    }

    /**
     * @template T of \Throwable
     * @param callable(string, \InvalidArgumentException):T $exceptionFactory
     */
    public static function decimalFromBytes(
        string $bytes,
        int $length,
        string $errorMessage,
        callable $exceptionFactory,
    ): string {
        try {
            return NumericIdCodec::decimalFromBytes($bytes, $length);
        } catch (\InvalidArgumentException $exception) {
            throw $exceptionFactory($errorMessage, $exception);
        }
    }
}
