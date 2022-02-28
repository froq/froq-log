<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
declare(strict_types=1);

namespace froq\logger;

use froq\common\{Error, Exception, trait\OptionTrait};
use froq\util\{Util, Arrays, misc\System};
use Throwable, DateTime, DateTimeZone;

/**
 * Logger.
 *
 * @package froq\logger
 * @object  froq\logger\Logger
 * @author  Kerem Güneş
 * @since   1.0
 */
class Logger
{
    /** @see froq\common\trait\OptionTrait */
    use OptionTrait {
        setOptions as private _setOptions;
    }

    /**
     * Levels.
     * @const int
     */
    public const NONE  = 0, ALL   = -1,
                 ERROR = 1, WARN  = 2,
                 INFO  = 4, DEBUG = 8;

    /** @var int */
    protected int $level;

    /** @var DateTime */
    protected static DateTime $date;

    /** @var string */
    protected static string $dateFormat = 'D, d M Y H:i:s.u P';

    /** @var string */
    private string $lastLog = '';

    /** @var array */
    private static array $optionsDefault = [
        'level'      => -1,   // All. Moved as property in v/5.0.
        'directory'  => null, // Must be given in constructor options.
        'tag'        => null, // Be used in write() as file name appendix.
        'file'       => null, // File with full path.
        'fileName'   => null, // Be used in write() or created.
        'utc'        => true, // Using UTC date or local date.
        'full'       => true, // Using full logs with causes/previous.
        'separate'   => true, // Used for separating new lines.
        'json'       => false,
        'pretty'     => false,
        'rotate'     => false,
        'dateFormat' => null,
    ];

    /**
     * Constructor.
     *
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $options = Arrays::options($options, self::$optionsDefault);

        // Use default log directory when available.
        if (!$options['directory'] && defined('APP_DIR')) {
            $options['directory'] = APP_DIR . '/var/log';
        }

        // Regulate tag option.
        if ($options['tag'] != '') {
            $options['tag'] = '-' . trim($options['tag'], '-');
        }

        $this->setOptions($options);

        // Set date object.
        self::$date = new DateTime('', new DateTimeZone(
            $options['utc'] ? 'UTC' : System::defaultTimezone()
        ));
    }

    /**
     * Set options.
     *
     * @param  array      $options
     * @param  array|null $optionsDefault
     * @return self
     * @since  5.0
     */
    public final function setOptions(array $options, array $optionsDefault = null): self
    {
        // Special case of "level" option.
        if (isset($options['level'])) {
            $this->setLevel($options['level'] = (int) $options['level']);
        }

        return $this->_setOptions($options, $optionsDefault);
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
     * Note: Log file will be set in write() method, so this method returns null
     * if any log*() method not yet called those run write() method.
     *
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
     * Log a trivial message (this method may be used for skipping leveled calls).
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public final function log(string|Throwable $message, bool $separate = true): bool
    {
        return $this->write(-1, null, $message, $separate);
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
        return $this->write(self::ERROR, null, $message, $separate);
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
        return $this->write(self::WARN, null, $message, $separate);
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
        return $this->write(self::INFO, null, $message, $separate);
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
        return $this->write(self::DEBUG, null, $message, $separate);
    }

    /**
     * Prepare a Throwable message.
     *
     * @param  Throwable $e
     * @param  bool      $pretty
     * @param  bool      $verbose
     * @return array
     * @since  4.1, 4.2
     */
    public static function prepare(Throwable $e, bool $pretty, bool $verbose = false): array
    {
        static $clean; // Dot all those PHP's ugly stuff..
        $clean ??= fn($s, $p) => $p ? str_replace(['\\', '::', '->'], '.', $s) : $s;

        $type = get_class($e);
        if ($pretty) {
            $type = $clean($type, true);
        }

        [$code, $file, $line, $message] = [$e->getCode(), $e->getFile(), $e->getLine(), $e->getMessage()];

        if (!$verbose) {
            $ret = [
                'string'  => sprintf('%s(%s): %s at %s:%s', $type, $code, $message, $file, $line),
                'trace'   => array_map(fn($s) => $clean($s, $pretty), explode("\n", $e->getTraceAsString()))];
        } else {
            $ret = [
                'type'    => $type, 'code' => $code,
                'file'    => $file, 'line' => $line,
                'message' => $message,
                'string'  => sprintf('%s(%s): %s at %s:%s', $type, $code, $message, $file, $line),
                'trace'   => array_map(fn($s) => preg_replace('~^#\d+ (.+)~', '\1', $clean($s, $pretty)),
                    explode("\n", $e->getTraceAsString()))];
        }

        // Append "previous" stuff.
        if ($previous = $e->getPrevious()) {
            $ret += ['previous' => self::prepare($previous, $pretty, $verbose)];
        }
        if (($e instanceof Error || $e instanceof Exception) && ($cause = $e->getCause())) {
            $ret += ['cause' => self::prepare($cause, $pretty, $verbose)];
        }

        return $ret;
    }

    /**
     * Check directory to ensure directory is created/exists, throw `LoggerException`
     * if no directory option given yet or cannot create that directory when not exists.
     *
     * @param  string $directory
     * @return void
     * @throws froq\logger\LoggerException
     */
    protected function directoryCheck(string $directory): void
    {
        if (trim($directory) == '') {
            throw new LoggerException(
                'Log directory is empty yet, it must be given in constructor '.
                'options or calling setOption() before log*() calls'
            );
        }

        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new LoggerException(
                'Cannot create log directory %s [error: %s]',
                [$directory, '@error']
            );
        }
    }

    /**
     * Write a trivial or leveled message to current log file, throw `LoggerException`
     * if error_log() or "logrotate" process fails.
     *
     * @param  int              $level
     * @param  string|null      $type
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @throws froq\logger\LoggerException
     * @return bool
     */
    protected function write(int $level, string|null $type, string|Throwable $message, bool $separate = true): bool
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
                $message = $prepared = self::prepare($message, !!$pretty, !!$json);
                $message = $json ? $message : $message['string'] . "\n" . join("\n", $message['trace']);
                if (isset($prepared['previous']) && !$json) {
                    $message .= "\nPrevious:\n" . $prepared['previous']['string']
                        . "\n" . join("\n", $prepared['previous']['trace']);
                }
                if (isset($prepared['cause']) && !$json) {
                    $message .= "\nCause:\n" . $prepared['cause']['string']
                        . "\n" . join("\n", $prepared['cause']['trace']);
                }
            } else {
                $message = $prepared = self::prepare($message, !!$pretty);
                $message = $message['string'] . "\n" . join("\n", $message['trace']);
                if (isset($prepared['previous'])) {
                    $message .= "\nPrevious:\n" . $prepared['previous']['string']
                        . "\n" . join("\n", $prepared['previous']['trace']);
                }
                if (isset($prepared['cause'])) {
                    $message .= "\nCause:\n" . $prepared['cause']['string']
                        . "\n" . join("\n", $prepared['cause']['trace']);
                }
            }
        }

        // Use file's directory if given.
        $directory ??= $file ? dirname($file) : null;

        $this->directoryCheck((string) $directory);

        // Prepare if not given.
        if (!$file) {
            $fileName ??= self::$date->format('Y-m-d');

            // Because permissions.
            $file = (PHP_SAPI != 'cli-server')
                  ? sprintf('%s/%s%s.log', $directory, $fileName, $tag)
                  : sprintf('%s/%s-cli-server%s.log', $directory, $fileName, $tag);

            // Store as option to speed up write process.
            $this->options['file']     = $file;
            $this->options['fileName'] = $fileName;
        }

        // Allowed override via write() calls from extender classes.
        $type = $type ?: match ($level) {
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

        $lastLog = md5($log);

        // Prevent duplications.
        if ($this->lastLog != $lastLog) {
            $this->lastLog = $lastLog;

            $this->commit($file, $log);
            $this->rotate($file);
        }

        return true;
    }

    /**
     * Run commit process.
     */
    private function commit(string $file, string $log): void
    {
        // Fix non-binary-safe issue of error_log().
        if (str_contains($log, "\0")) {
            $log = str_replace("\0", "\\0", $log);
        }

        error_log($log, 3, $file)
            || throw new LoggerException('Log process failed [error: @error]');
    }

    /**
     * Run rotate process.
     */
    private function rotate(string $file): void
    {
        // Mimic "logrotate" process.
        if ($this->options['rotate']) {
            $glob = $this->options['directory'] . '/*.log';
            foreach (glob($glob) as $gfile) {
                if ($gfile != $file) {
                    (copy($gfile, 'compress.zlib://' . $gfile . '.gz') && unlink($gfile))
                        || throw new LoggerException('Log rotate failed [error: @error]');
                }
            }
        }
    }
}
