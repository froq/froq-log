<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-log
 */
namespace froq\log;

use froq\util\{Util, Debugger};
use DateTime, DateTimeZone, Stringable, Throwable;

/**
 * A logger class for logging and optionally rotating logs.
 *
 * @package froq\log
 * @class   froq\log\Logger
 * @author  Kerem Güneş
 * @since   1.0
 */
class Logger
{
    /** Log level. */
    private int $level;

    /** Date/time instance. */
    private static DateTime $date;

    /** Logger options with defaults. */
    private LoggerOptions $options;

    /** Last log hash to prevent double logs. */
    private string $lastLog = '';

    /**
     * Constructor.
     *
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $this->options = LoggerOptions::create($options);

        $this->setLevel($this->options['level']);

        self::$date = new DateTime('', new DateTimeZone($this->options['timeZone']));
    }

    /**
     * Set log level.
     *
     * @param  int $level
     * @return self
     * @since  5.0
     */
    public function setLevel(int $level): self
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
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Set option.
     *
     * @param  string $option
     * @param  mixed  $value
     * @return self
     * @since  6.0
     */
    public function setOption(string $option, mixed $value): self
    {
        // Special case of "level" option.
        if ($option === 'level') {
            $this->setLevel($value = (int) $value);
        }

        $this->options[$option] = $value;

        return $this;
    }

    /**
     * Get option.
     *
     * @param  string     $option
     * @param  mixed|null $default
     * @return mixed
     * @since  6.0
     */
    public function getOption(string $option, mixed $default = null): mixed
    {
        return $this->options[$option] ?? $default;
    }

    /**
     * Get current log file.
     *
     * Note: Log file will be set in write() method, so this method returns null
     * if any log*() method not yet called those run write() method.
     *
     * @return string|null
     */
    public function getFile(): string|null
    {
        return $this->getOption('file');
    }

    /**
     * Get current log directory that given in options.
     *
     * @return string|null
     */
    public function getDirectory(): string|null
    {
        return $this->getOption('directory');
    }

    /**
     * Log a trivial message (this method may be used for skipping leveled calls).
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public function log(string|Stringable $message): bool
    {
        return $this->write(LogLevel::ALL, null, $message);
    }

    /**
     * Log an error message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public function logError(string|Stringable $message): bool
    {
        return $this->write(LogLevel::ERROR, null, $message);
    }

    /**
     * Log a warning message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public function logWarn(string|Stringable $message): bool
    {
        return $this->write(LogLevel::WARN, null, $message);
    }

    /**
     * Log an info message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public function logInfo(string|Stringable $message): bool
    {
        return $this->write(LogLevel::INFO, null, $message);
    }

    /**
     * Log a debug message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public function logDebug(string|Stringable $message): bool
    {
        return $this->write(LogLevel::DEBUG, null, $message);
    }

    /**
     * Check directory to ensure directory is created/exists, throw `LoggerException`
     * if no directory option given yet or cannot create that directory when not exists.
     *
     * @param  string $directory
     * @return void
     * @throws froq\log\LoggerException
     */
    protected function directoryCheck(string $directory): void
    {
        if (trim($directory) === '') {
            throw LoggerException::forEmptyDirectory();
        }

        if (!@dirmake($directory)) {
            throw LoggerException::forMakeDirectoryError($directory);
        }
    }

    /**
     * Prepare a `Throwable` message.
     *
     * @param  array|Throwable $e
     * @param  bool            $json
     * @return array
     */
    protected static function prepare(array|Throwable $e, bool $json = false): array
    {
        $debug = is_array($e) ? $e : Debugger::debug(
            $e, withTracePath: $json, withTraceString: !$json
        );

        if ($json) {
            $ret = [
                'class'   => $debug['class'],   'code'   => $debug['code'],
                'file'    => $debug['file'],    'line'   => $debug['line'],
                'message' => $debug['message'], 'string' => $debug['string'],
                'trace'   => $debug['tracePath']
            ];
        } else {
            // Escape line feeds (for LogParser).
            $debug['string'] = addcslashes($debug['string'], "\r\n");

            $ret = ['string' => $debug['string'], 'trace' => $debug['traceString']];
        }

        $ret['cause'] = $debug['cause'] ? self::prepare($debug['cause'], $json) : null;
        $ret['previous'] = $debug['previous'] ? self::prepare($debug['previous'], $json) : null;

        return $ret;
    }

    /**
     * Write a trivial or leveled message to current log file, cause `LoggerException`
     * if error_log() or "logrotate" process fails.
     *
     * @param  int               $level
     * @param  string|null       $type
     * @param  string|Stringable $message
     * @return bool
     * @causes froq\log\LoggerException
     */
    protected function write(int $level, string|null $type, string|Stringable $message): bool
    {
        // If no log by level, just skip write action.
        if (($level > -1) && (!$level || !($level & $this->level))) {
            return false;
        }

        [$directory, $file, $fileName, $tag, $json, $jsonIndent, $timeFormat] = $this->options->select(
            ['directory', 'file', 'fileName', 'tag', 'json', 'jsonIndent', 'timeFormat']
        );

        $isThrowable = $message instanceof Throwable;

        if ($isThrowable) {
            $prepared = self::prepare($message, !!$json);

            if (!$json) {
                // Escape line feeds (for LogParser).
                $message = addcslashes($prepared['string'], "\r\n");

                $message .= "\n" . $prepared['trace'];

                if ($prepared['cause']) {
                    $current = $prepared['cause'];
                    while ($current) {
                        $message .= "\nCause:\n" . $current['string']
                                 . "\n" . $current['trace'];

                        $current = $current['cause'];
                    }
                }

                if ($prepared['previous']) {
                    $current = $prepared['previous'];
                    while ($current) {
                        $message .= "\nPrevious:\n" . $current['string']
                                 . "\n" . $prepared['previous']['trace'];

                        $current = $current['previous'];
                    }
                }
            }
        } else {
            $message = trim((string) $message);

            // Escape line feeds (for LogParser).
            $message = addcslashes($message, "\r\n");
        }

        // Use file's directory if file given but not directory given.
        $directory ??= $file ? dirname($file) : null;

        $this->directoryCheck((string) $directory);

        // Prepare file if not given.
        if (!$file) {
            $fileName ??= self::$date->format('Y-m-d');
            if ($tag !== null) {
                $fileName .= '-' . $tag;
            }

            // Because of permissions.
            $file = (PHP_SAPI !== 'cli-server')
                  ? sprintf('%s/%s.log', $directory, $fileName)
                  : sprintf('%s/%s-cli-server.log', $directory, $fileName);

            // Store as option to speed up write process.
            $this->options['file']     = $file;
            $this->options['fileName'] = $fileName;
        }

        // Allowed override via write() calls from extender classes.
        $type = $type ?: match ($level) {
            LogLevel::ERROR => 'ERROR', LogLevel::WARN  => 'WARN',
            LogLevel::INFO  => 'INFO',  LogLevel::DEBUG => 'DEBUG',
            default         => 'LOG',
        };

        $ip   = Util::getClientIp() ?: '-';
        $date = self::$date->format($timeFormat);

        if (!$json) {
            // Eg: [ERROR] Sat, 31 Oct 2020 .. | 127.0.0.1 | Error(0): ..
            $log = sprintf(
                "[%s] %s | %s | %s",
                $type, $date, $ip, $message
            );
        } else {
            // Regulate fields for LogParser parsing rules (@see LogParser.parseFileEntry()).
            [$content, $thrown] = $isThrowable ? [null, self::prepare($message, !!$json)] : [$message, null];

            // Eg: {"type":"ERROR", "date":"Sat, 07 Nov 2020 ..", "ip":"127...", "content":null, "thrown":{"type": ..
            $log = json_serialize(
                ['type' => $type, 'date' => $date, 'ip' => $ip, 'content' => $content, 'thrown' => $thrown],
                indent: is_bool($jsonIndent) || is_int($jsonIndent) ? $jsonIndent : null
            );
        }

        // Separator for the LogParser.
        $log .= "\n\n";

        // Prevent duplications.
        if ($this->lastLog !== ($lastLog = md5($log))) {
            $this->lastLog = $lastLog;

            $this->commit($file, $log);
            $this->rotate($file);
        }

        return true;
    }

    /**
     * Run commit process.
     *
     * @throws froq\log\LoggerException
     */
    private function commit(string $file, string $log): void
    {
        // Fix non-binary-safe issue of error_log().
        if (str_contains($log, "\0")) {
            $log = str_replace("\0", "\\0", $log);
        }

        error_log($log, 3, $file) || throw LoggerException::forCommitError();
    }

    /**
     * Run rotate process.
     *
     * @throws froq\log\LoggerException
     */
    private function rotate(string $file): void
    {
        // Mimic "logrotate" process.
        if ($this->options['rotate']) {
            $now = gmdate('H');

            // Possible between 22 pm as default.
            $time = intval($this->options['rotateTime']) ?: 22;

            if ($now < $time || $now > $time) {
                return;
            }

            $pattern = $this->options['directory'] . '/*.log';

            foreach (glob($pattern, GLOB_NOSORT) as $gfile) {
                if ($gfile === $file) {
                    continue;
                }

                $gzfile = $gfile . '.gz';

                // If GZ file exists, copy() will fail.
                if (is_file($gzfile) && !unlink($gzfile)) {
                    continue;
                }

                $okay = copy($gfile, 'compress.zlib://' . $gzfile) && unlink($gfile);
                $okay || throw LoggerException::forRotateError();
            }
        }
    }
}
