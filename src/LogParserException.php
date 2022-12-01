<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
namespace froq\logger;

/**
 * @package froq\logger
 * @class   froq\logger\LogParserException
 * @author  Kerem Güneş
 * @since   7.0
 */
class LogParserException extends \froq\common\Exception
{
    /**
     * Create for empty file.
     *
     * @return static
     */
    public static function forEmptyFile(): static
    {
        return new static('No file given yet, call setFile()');
    }

    /**
     * Create for invalid file.
     *
     * @param  string $file
     * @param  string $type
     * @return static
     */
    public static function forInvalidFile(string $file, string $type): static
    {
        return new static('Invalid file %q [type: %s]', [$file, $type]);
    }

    /**
     * Create for caught throwable.
     *
     * @param  Throwable $e
     * @return static
     */
    public static function forCaughtThrowable(\Throwable $e): static
    {
        return new static($e, extract: true);
    }
}
