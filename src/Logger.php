<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
declare(strict_types=1);

namespace froq\logger;

use froq\common\{Error, Exception};
use froq\util\Util;
use Stringable, Throwable, DateTime, DateTimeZone;

/**
 * A logger class for logging and optionally rotating logs.
 *
 * @package froq\logger
 * @object  froq\logger\Logger
 * @author  Kerem Güneş
 * @since   1.0
 */
class Logger
{
    /** @var int */
    private int $level;

    /** @var DateTime */
    private static DateTime $date;

    /** @var froq\logger\LoggerOptions */
    private LoggerOptions $options;

    /** @var string */
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
     * Set option.
     *
     * @param  string $option
     * @param  mixed  $value
     * @return self
     * @since  6.0
     */
    public final function setOption(string $option, mixed $value): self
    {
        // Special case of "level" option.
        if ($option == 'level') {
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
    public final function getOption(string $option, mixed $default = null): mixed
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
     * @param  string|Stringable $message
     * @return bool
     */
    public final function log(string|Stringable $message): bool
    {
        return $this->write(LogLevel::ALL, null, $message);
    }

    /**
     * Log an error message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public final function logError(string|Stringable $message): bool
    {
        return $this->write(LogLevel::ERROR, null, $message);
    }

    /**
     * Log a warning message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public final function logWarn(string|Stringable $message): bool
    {
        return $this->write(LogLevel::WARN, null, $message);
    }

    /**
     * Log an info message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public final function logInfo(string|Stringable $message): bool
    {
        return $this->write(LogLevel::INFO, null, $message);
    }

    /**
     * Log a debug message.
     *
     * @param  string|Stringable $message
     * @return bool
     */
    public final function logDebug(string|Stringable $message): bool
    {
        return $this->write(LogLevel::DEBUG, null, $message);
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

        // Works if "options.json=true" only.
        if ($verbose) {
            $ret = [
                'type'    => $type, 'code' => $code,
                'file'    => $file, 'line' => $line,
                'message' => $message,
                'string'  => sprintf('%s(%s): %s at %s:%s', $type, $code, $message, $file, $line),
                'trace'   => array_map(fn($s) => preg_replace('~^#\d+ (.+)~', '\1', $clean($s, $pretty)),
                    explode("\n", $e->getTraceAsString()))];
        } else {
            $ret = [
                'string'  => sprintf('%s(%s): %s at %s:%s', $type, $code, $message, $file, $line),
                'trace'   => array_map(fn($s) => $clean($s, $pretty), explode("\n", $e->getTraceAsString()))];
        }

        // Append previous/cause stuff.
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

        if (!dirmake($directory)) {
            throw new LoggerException(
                'Cannot create log directory %S [error: %s]',
                [$directory, '@error']
            );
        }
    }

    /**
     * Write a trivial or leveled message to current log file, cause `LoggerException`
     * if error_log() or "logrotate" process fails.
     *
     * @param  int               $level
     * @param  string|null       $type
     * @param  string|Stringable $message
     * @causes froq\logger\LoggerException
     * @return bool
     */
    protected function write(int $level, string|null $type, string|Stringable $message): bool
    {
        // No log?
        if (($level > -1) && (!$level || !($level & $this->level))) {
            return false;
        }

        [$directory, $file, $fileName, $tag, $json, $pretty, $format] = $this->options->select(
            ['directory', 'file', 'fileName', 'tag', 'json', 'pretty', 'timeFormat']
        );

        $messageOrig = $message;

        if ($message instanceof Throwable) {
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
        } else {
            $message = trim((string) $message);
        }

        // Use file's directory if file given but not directory given.
        $directory ??= $file ? dirname($file) : null;

        $this->directoryCheck((string) $directory);

        // Prepare file if not given.
        if (!$file) {
            $fileName ??= self::$date->format('Y-m-d');
            if ($tag != '') {
                $fileName .= '-' . $tag;
            }

            // Because of permissions.
            $file = (PHP_SAPI != 'cli-server')
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
        $date = self::$date->format($format);

        if (!$json) {
            // Eg: [ERROR] Sat, 31 Oct 2020 .. | 127.0.0.1 | Error(0): ..
            $log = sprintf(
                "[%s] %s | %s | %s",
                $type, $date, $ip, $message
            );
        } else {
            // Regulate fields for LogParser parsing rules (@see LogParser.parseFileEntry()).
            [$content, $thrown] = ($messageOrig instanceof Throwable) ? [null, $message] : [$message, null];

            // Eg: {"type":"ERROR", "date":"Sat, 07 Nov 2020 ..", "ip":"127...", "content":null, "thrown":{"type": ..
            $log = json_encode(
                [
                    'type' => $type, 'date' => $date, 'ip' => $ip,
                    'content' => $content, 'thrown' => $thrown
                ],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        // Separator for the LogParser.
        $log .= "\n\n";

        // Prevent duplications.
        if ($this->lastLog != $lastLog = md5($log)) {
            $this->lastLog = $lastLog;

            $this->commit($file, $log);
            $this->rotate($file);
        }

        return true;
    }

    /**
     * Run commit process.
     *
     * @throws froq\logger\LoggerException
     */
    private function commit(string $file, string $log): void
    {
        // Fix non-binary-safe issue of error_log().
        if (str_contains($log, "\0")) {
            $log = str_replace("\0", "\\0", $log);
        }

        error_log($log, 3, $file)
            || throw new LoggerException('Log commit failed [error: @error]');
    }

    /**
     * Run rotate process.
     *
     * @throws froq\logger\LoggerException
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
