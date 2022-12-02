<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
namespace froq\logger;

/**
 * @package froq\logger
 * @class   froq\logger\LoggerException
 * @author  Kerem Güneş
 * @since   1.0
 */
class LoggerException extends \froq\common\Exception
{
    public static function forEmptyDirectory(): static
    {
        return new static(
            'Log directory is empty yet, it must be given in constructor '.
            'options or calling setOption() before log*() calls'
        );
    }

    public static function forMakeDirectoryError(string $directory): static
    {
        return new static(
            'Cannot create log directory %S [error: @error]',
            $directory, extract: true
        );
    }

    public static function forCommitError(): static
    {
        return new static('Log commit failed [error: @error]', extract: true);
    }

    public static function forRotateError(): static
    {
        return new static('Log rotate failed [error: @error]', extract: true);
    }
}
