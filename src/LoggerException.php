<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
declare(strict_types=1);

namespace froq\logger;

/**
 * @package froq\logger
 * @object  froq\logger\LoggerException
 * @author  Kerem Güneş
 * @since   1.0
 */
class LoggerException extends \froq\common\Exception
{
    /**
     * Create for empty directory.
     *
     * @return static
     */
    public static function forEmptyDirectory(): static
    {
        return new static(
            'Log directory is empty yet, it must be given in constructor '.
            'options or calling setOption() before log*() calls'
        );
    }

    /**
     * Create for create directory failed.
     *
     * @param  string $directory
     * @return static
     */
    public static function forCreateDirectoryFailed(string $directory): static
    {
        return new static(
            'Cannot create log directory %S [error: %s]',
            [$directory, '@error'], extract: true
        );
    }

    /**
     * Create for commit failed.
     *
     * @return static
     */
    public static function forCommitFailed(): static
    {
        return new static('Log commit failed [error: @error]', extract: true);
    }

    /**
     * Create for rotate failed.
     *
     * @return static
     */
    public static function forRotateFailed(): static
    {
        return new static('Log rotate failed [error: @error]', extract: true);
    }
}
