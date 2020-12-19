<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\logger;

use froq\logger\LoggerException;
use froq\common\trait\OptionTrait;
use froq\util\Util;
use Throwable, Datetime;

/**
 * Logger.
 *
 * @package froq\logger
 * @object  froq\logger\Logger
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
class Logger
{
    /**
     * @see froq\common\trait\OptionTrait
     * @since 4.0
     */
    use OptionTrait;

    /**
     * Levels.
     * @const int
     */
    public const NONE  = 0, ALL   = 15, // Sum of all.
                 ERROR = 1, WARN  = 2,
                 INFO  = 4, DEBUG = 8;

    /** @var int */
    protected int $level;

    /** @var Datetime @since 4.2 */
    protected static Datetime $date;

    /** @var Datetime @since 5.0 */
    protected static string $dateFormat = 'D, d M Y H:i:s.u P';

    /** @var array */
    private static array $optionsDefault = [
        'level'           => 0,    // None. Moved as property at v/5.0
        'directory'       => null, // Must be given in constructor options.
        'tag'             => null, // Be used in write() as file name appendix.
        'file'            => null, // File with full path.
        'fileName'        => null, // Be used in write() or created.
        'utc'             => true, // Whether to use UTC date or local date.
        'separate'        => true, // Used for separating new lines.
        'json'            => false,
        'pretty'          => false,
        'rotate'          => false,
        'dateFormat'      => null,
    ];

    /**
     * Constructor.
     *
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $this->setOptions($options, self::$optionsDefault);

        [$level, $file, $tag, $utc] = $this->getOptions(['level', 'file', 'tag', 'utc']);

        $this->setLevel((int) $level);

        $file && $this->setOption('file', $file);
        $tag && $this->setOption('tag', ('-'. trim($tag, '-')));

        // Set date.
        self::$date = date_create('', timezone_open(
            $utc ? 'UTC' : date_default_timezone_get()
        ));
    }

    /**
     * Set log level.
     *
     * @param  int $level
     * @return self
     * @since  5.0
     */
    public final function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Get log level.
     *
     * @return int
     * @since  5.0
     */
    public final function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Get current log file.
     *
     * @note   Log file will be set in write() method, so this method returns null if any log*()
     *         method not yet called those run write() method.
     * @return string|null
     */
    public final function getFile(): string|null
    {
        return $this->getOption('file');
    }

    /**
     * Get current log directory that given in options.
     *
     * @return string|null
     */
    public final function getDirectory(): string|null
    {
        return $this->getOption('directory');
    }

    /**
     * Log a trivial message (this method may be used for skipping leveled log states).
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     * @since  3.2, 4.0 Renamed from logAny().
     */
    public final function log(string|Throwable $message, bool $separate = true): bool
    {
        return $this->write(-1, $message, $separate);
    }

    /**
     * Log an error message.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public final function logError(string|Throwable $message, bool $separate = true): bool
    {
        return $this->write(self::ERROR, $message, $separate);
    }

    /**
     * Log a warning message.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public final function logWarn(string|Throwable $message, bool $separate = true): bool
    {
        return $this->write(self::WARN, $message, $separate);
    }

    /**
     * Log an informational message.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public final function logInfo(string|Throwable $message, bool $separate = true): bool
    {
        return $this->write(self::INFO, $message, $separate);
    }

    /**
     * Log a debug message.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public final function logDebug(string|Throwable $message, bool $separate = true): bool
    {
        return $this->write(self::DEBUG, $message, $separate);
    }

    /**
     * Prepare a Throwable message.
     *
     * @param  Throwable $e
     * @param  bool      $pretty
     * @param  bool      $verbose
     * @return array
     * @since  4.1, 4.2 Replaced with prettify().
     */
    public static function prepare(Throwable $e, bool $pretty, bool $verbose): array
    {
        static $clean; // Dot all those PHP's ugly stuff..
        $clean ??= fn($s) => str_replace(['\\', '::', '->'], '.', $s);

        $type = get_class($e);
        if ($pretty) {
            $type = $clean($type);
        }

        [$code, $file, $line, $message] = [$e->getCode(), $e->getFile(), $e->getLine(), $e->getMessage()];

        if (!$verbose) {
            return [
                'string'  => sprintf('%s(%s): %s at %s:%s', $type, $code, $message, $file, $line),
                'trace'   => array_map(fn($s) => $clean($s), explode("\n", $e->getTraceAsString()))];
        } else {
            return [
                'type'    => $type, 'code' => $code,
                'file'    => $file, 'line' => $line,
                'message' => $message,
                'string'  => sprintf('%s(%s): %s at %s:%s', $type, $code, $message, $file, $line),
                'trace'   => array_map(fn($s) => preg_replace('~^#\d+ (.+)~', '\1', $clean($s)),
                    explode("\n", $e->getTraceAsString()))];
        }
    }

    /**
     * Check directory to ensure directory is created/exists, throw LoggerException if no
     * directory option given yet or cannot create that directory when not exists.
     *
     * @param  string $directory
     * @return void
     * @throws froq\logger\LoggerException
     */
    protected function directoryCheck(string $directory): void
    {
        $directory = trim($directory);
        if ($directory == '') {
            throw new LoggerException('Log directory is not defined yet, it must be given in'
                . ' constructor options or calling setOption() before log*() calls');
        }

        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new LoggerException('Cannot create log directory %s [error: %s]', [$directory, '@error']);
        }
    }

    /**
     * Write a trivial or leveled message to current log file, throw a LoggerException if error_log()
     * or "logrotate" process fails.
     *
     * @param  int              $level
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @throws froq\logger\LoggerException
     * @return bool
     * @since  4.0 Renamed to write() from log(), made private.
     */
    protected function write(int $level, string|Throwable $message, bool $separate = true): bool
    {
        // No log?
        if (!$level || !($level & $this->level)) {
            return false;
        }

        [$directory, $file, $fileName, $tag, $json, $pretty, $dateFormat] = $this->getOptions(
            ['directory', 'file', 'fileName', 'tag', 'json', 'pretty', 'dateFormat']
        );

        if (is_string($message)) {
            $message = trim($message);
        } else {
            if ($pretty || $json) {
                $message = self::prepare($message, !!$pretty, !!$json);
                $message = $json ? $message : $message['string'] . "\nTrace:\n" . join("\n", $message['trace']);
            } else {
                $message = trim((string) $message);
            }
        }

        // Use file's directory if given.
        $directory ??= $file ? dirname($file) : null;

        $this->directoryCheck((string) $directory);

        // Prepare if not given.
        if ($file == null) {
            $fileName ??= self::$date->format('Y-m-d');

            // Because permissions.
            $file = (PHP_SAPI != 'cli-server')
                ? sprintf('%s/%s%s.log', $directory, $fileName, $tag)
                : sprintf('%s/%s%s-cli-server.log', $directory, $fileName, $tag);

            // Store as option to speed up write process.
            $this->options['file'] = $file;
            $this->options['fileName'] = $fileName;
        }

        $type = match ($level) {
            self::ERROR => 'ERROR', self::WARN  => 'WARN',
            self::INFO  => 'INFO',  self::DEBUG => 'DEBUG',
                default => 'LOG'
        };

        // Use default date format if not given.
        $dateFormat = $this->getOption('dateFormat', self::$dateFormat);

        if (!$json) {
            // Eg: [ERROR] Sat, 31 Oct 2020 02:00:34.377367 +00:00 | 127.0.0.1 | Error(0): ..
            $log = sprintf("[%s] %s | %s | %s",
                $type, self::$date->format($dateFormat),
                Util::getClientIp() ?: '-', $message) . "\n";
            $separate && $log .= "\n";
        } else {
            // Eg: {"type":"ERROR", "date":"Sat, 07 Nov 2020 05:43:13.080835 +00:00", "ip":"127...", "message": {"type": ..
            $log = json_encode([
                'type' => $type, 'date' => self::$date->format($dateFormat),
                'ip' => Util::getClientIp() ?: '-', 'message' => $message,
            ], JSON_UNESCAPED_SLASHES) . "\n";
            $separate && $log .= "\n";
        }

        $this->commit($file, $log);
        $this->rotate($file);

        return true;
    }

    /** @internal */
    private function commit(string $file, string $log): void
    {
        // Fix non-binary-safe issue of error_log().
        if (str_contains($log, "\0")) {
            $log = str_replace("\0", "\\0", $log);
        }

        error_log($log, 3, $file)
            || throw new LoggerException('Log process failed [error: %s]', '@error');
    }

    /** @internal */
    private function rotate(string $file): void
    {
        // Mimic "logrotate" process.
        if ($this->options['rotate']) {
            $glob = $this->options['directory'] . '/*.log';
            foreach (glob($glob) as $gfile) {
                if ($gfile != $file) {
                    (copy($gfile, 'compress.zlib://' . $gfile . '.gz') && unlink($gfile))
                        || throw new LoggerException('Log rotate failed [error: %s]', '@error');
                }
            }
        }
    }
}
