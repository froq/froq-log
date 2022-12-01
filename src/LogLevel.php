<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
namespace froq\logger;

/**
 * Enum class for log levels.
 *
 * @package froq\logger
 * @class   froq\logger\LogLevel
 * @author  Kerem Güneş
 * @since   6.0
 */
class LogLevel
{
    /** Levels. */
    public const NONE  = 0, ALL   = -1,
                 ERROR = 1, WARN  = 2,
                 INFO  = 4, DEBUG = 8;
}
