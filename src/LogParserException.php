<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-log
 */
namespace froq\log;

/**
 * @package froq\log
 * @class   froq\log\LogParserException
 * @author  Kerem Güneş
 * @since   7.0
 */
class LogParserException extends \froq\common\Exception
{
    public static function forEmptyFile(): static
    {
        return new static('No file given yet, call setFile()');
    }

    public static function forInvalidFile(string $file, string $type): static
    {
        return new static('Invalid file %q [type: %s]', [$file, $type]);
    }

    public static function forCaughtThrowable(\Throwable $e): static
    {
        return new static($e, extract: true);
    }
}
