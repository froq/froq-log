<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\logger;

use froq\logger\LoggerException;
use froq\common\traits\OptionTrait;
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
final class Logger
{
    /**
     * Option trait.
     * @see froq\common\traits\OptionTrait
     * @since 4.0
     */
    use OptionTrait;

    /**
     * Levels.
     * @const int
     */
    public const NONE  = 0, ALL   = 30, // ALL is sum of all levels.
                 ERROR = 2, WARN  = 4,
                 INFO  = 8, DEBUG = 16;

    /**
     * Date.
     * @var Datetime
     * @since 4.2
     */
    private static Datetime $date;

    /**
     * Options default.
     * @var array
     */
    private static array $optionsDefault = [
        'level'           => 0,    // None.
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
        $options = array_merge(self::$optionsDefault, $options ?? []);
        if ($options['tag']) {
            $options['tag'] = '-' . trim($options['tag'], '-');
        }

        // Set date.
        self::$date = date_create('', timezone_open(
            $options['utc'] ? 'UTC' : date_default_timezone_get()
        ));

        $this->setOptions($options);
    }

    /**
     * Logs any trivial message to the log file. This method can be used for skipping leveled
     * log states.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     * @since  3.2, 4.0 Renamed from logAny().
     */
    public function log($message, bool $separate = true): bool
    {
        return $this->write(-1, $message, $separate);
    }

    /**
     * Logs error messages.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public function logError($message, bool $separate = true): bool
    {
        return $this->write(self::ERROR, $message, $separate);
    }

    /**
     * Logs warning messages.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public function logWarn($message, bool $separate = true): bool
    {
        return $this->write(self::WARN, $message, $separate);
    }

    /**
     * Logs informational messages.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public function logInfo($message, bool $separate = true): bool
    {
        return $this->write(self::INFO, $message, $separate);
    }

    /**
     * Logs debug messages.
     *
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @return bool
     */
    public function logDebug($message, bool $separate = true): bool
    {
        return $this->write(self::DEBUG, $message, $separate);
    }

    /**
     * Gets current log file. Note that log file is set in `write()` method and this method returns
     * null if any `log*()` method not yet called.
     *
     * @return ?string
     * @since  4.0
     */
    public function getFile(): ?string
    {
        return $this->getOption('file');
    }

    /**
     * Gets log directory that given in constructor options.
     *
     * @return ?string
     */
    public function getDirectory(): ?string
    {
        return $this->getOption('directory');
    }

    /**
     * Prepares a Throwable message.
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
                'string' => sprintf('%s(%s): %s at %s:%s',
                    $type, $code, $message, $file, $line),
                'trace' => array_map(fn($s) => $clean($s),
                    explode("\n", $e->getTraceAsString()))
            ];
        } else {
            return [
                'type' => $type, 'code' => $code,
                'file' => $file, 'line' => $line,
                'message' => $message,
                'string' => sprintf('%s(%s): %s at %s:%s',
                    $type, $code, $message, $file, $line),
                'trace' => array_map(fn($s) => preg_replace('~^#\d+ (.+)~', '\1', $clean($s)),
                    explode("\n", $e->getTraceAsString()))
            ];
        }
    }

    /**
     * Checks directory to ensure directory is created/exists, throws `LoggerException` if no
     * directory option given yet or cannot create that directory.
     *
     * @param  string $directory
     * @return void
     * @throws froq\logger\LoggerException
     */
    private function checkDirectory(string $directory): void
    {
        $directory = trim($directory);
        if ($directory == '') {
            throw new LoggerException('Log directory is not defined yet, it must be given in '.
                'constructor options or calling setOption() before log*() calls');
        }

        if (!is_dir($directory)) {
            $ok = mkdir($directory, 0755, true);
            if (!$ok) {
                throw new LoggerException('Cannot make directory [error: %s]', '@error');
            }
        }
    }

    /**
     * Writes any trivial or leveled message to the log file, throws a `LoggerException` if
     * no valid message given or internal `error_log()` function fails.
     *
     * @param  int              $level
     * @param  string|Throwable $message
     * @bool   bool             $separate
     * @throws froq\logger\LoggerException
     * @return bool
     * @since  4.0 Renamed to write() from log(), made private.
     */
    private function write(int $level, $message, bool $separate = true): bool
    {
        // No log..
        if (!$level || !($level & ((int) $this->options['level']))) {
            return false;
        }

        ['directory' => $directory, 'tag' => $tag, 'file' => $file, 'fileName' => $fileName,
          'json' => $json, 'pretty' => $pretty, 'dateFormat' => $dateFormat] = $this->options;

        if (is_string($message)) {
            $message = trim($message);
        } elseif ($message instanceof Throwable) {
            if ($pretty || $json) {
                $message = self::prepare($message, $pretty, $json);
                $message = $json ? $message : $message['string'] . "\nTrace:\n" . join("\n", $message['trace']);
            } else {
                $message = trim((string) $message);
            }
        } else {
            throw new LoggerException("Only string|Throwable messages are accepted, '%s' given",
                gettype($message));
        }

        // Use file's directory if given.
        $directory ??= $file ? dirname($file) : null;

        $this->checkDirectory((string) $directory);

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

        switch ($level) {
            case self::ERROR: $type = 'ERROR'; break;
            case self::INFO:  $type = 'INFO';  break;
            case self::WARN:  $type = 'WARN';  break;
            case self::DEBUG: $type = 'DEBUG'; break;
                     default: $type = 'LOG';
        }

        // Use default date format if not given.
        $dateFormat = $this->getOption('dateFormat', 'D, d M Y H:i:s.u P');

        if (!$json) {
            // Eg: [ERROR] Sat, 31 Oct 2020 02:00:34.377367 +00:00 | 127.0.0.1 | Error(0): ..
            $log = sprintf("[%s] %s | %s | %s",
                $type, self::$date->format($dateFormat),
                Util::getClientIp(), $message) . "\n";
            $separate && $log .= "\n";
        } else {
            // Eg: {"type":"ERROR", "date":"Sat, 07 Nov 2020 05:43:13.080835 +00:00", "ip":"127...", "message": {"type": ..
            $log = json_encode([
                'type' => $type, 'date' => self::$date->format($dateFormat),
                'ip' => Util::getClientIp(), 'message' => $message,
            ], JSON_UNESCAPED_SLASHES) . "\n";
            $separate && $log .= "\n";
        }

        // Fix non-binary-safe issue of error_log().
        if (strpos($log, "\0")) {
            $log = str_replace("\0", "\\0", $log);
        }

        $ok = error_log($log, 3, $file);
        if (!$ok) {
            throw new LoggerException('Log process failed [error: %s]', '@error');
        }

        // Mimic "logrotate" process.
        if ($this->options['rotate']) {
            foreach (glob($directory .'/*.log') as $gfile) {
                if ($gfile != $file) {
                    $ok = copy($gfile, 'compress.zlib://'. $gfile .'.gz') && unlink($gfile);
                    if (!$ok) {
                        throw new LoggerException('Log rotate failed [error: %s]', '@error');
                    }
                }
            }
        }

        return true;
    }
}
