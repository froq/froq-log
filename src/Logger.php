<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\logger;

use froq\common\traits\OptionTrait;
use froq\logger\LoggerException;
use Throwable;

/**
 * Logger.
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
     * Options default.
     * @var array
     */
    private static array $optionsDefault = [
        'level'           => 0,    // None.
        'directory'       => null, // Must be given in constructor options.
        'file'            => null, // File with full path.
        'fileName'        => null, // Be used in write() or created.
        'fileNameTag'     => null, // Be appended to file name.
        'useGmtDate'      => false,
        'usePrettyFormat' => true,
    ];

    /**
     * Constructor.
     *
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $options = array_merge(self::$optionsDefault, $options ?? []);

        $this->setOptions($options);
    }

    /**
     * Logs any trivial message to the log file. This method can be used for skipping leveled
     * log states.
     *
     * @param  string|Throwable $message
     * @return bool
     * @since  3.2, 4.0 Renamed to log() from logAny().
     */
    public function log($message): bool
    {
        return $this->write(-1, $message);
    }

    /**
     * Logs error messages.
     *
     * @param  string|Throwable $message
     * @return bool
     */
    public function logError($message): bool
    {
        return $this->write(self::ERROR, $message);
    }

    /**
     * Logs warning messages.
     *
     * @param  string|Throwable $message
     * @return bool
     */
    public function logWarn($message): bool
    {
        return $this->write(self::WARN, $message);
    }

    /**
     * Logs informational messages.
     *
     * @param  string|Throwable $message
     * @return bool
     */
    public function logInfo($message): bool
    {
        return $this->write(self::INFO, $message);
    }

    /**
     * Logs debug messages.
     *
     * @param  string|Throwable $message
     * @return bool
     */
    public function logDebug($message): bool
    {
        return $this->write(self::DEBUG, $message);
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
            $ok =@ mkdir($directory, 0755, true);
            if (!$ok) {
                throw new LoggerException('Cannot make directory [error: %s]', ['@error']);
            }
        }
    }

    /**
     * Writes any trivial or leveled message to the log file, throws a `LoggerException` if
     * no valid message given (`string|Throwable`) or internal `error_log()` function fails.
     *
     * @param  int              $level
     * @param  string|Throwable $message
     * @throws froq\logger\LoggerException
     * @return bool
     * @since  4.0 Renamed to write() from log(), made private.
     */
    private function write(int $level, $message): bool
    {
        // No log.
        if (!$level || !($level & intval($this->options['level']))) {
            return false;
        }

        if (is_string($message)) {
            $message = trim($message);
        } elseif ($message instanceof Throwable) {
            if ($this->options['usePrettyFormat']) {
                $message = self::prettify($message);
                $message = $message['string'] ."\nTrace:\n". join("\n", $message['trace']);
            } else {
                $message = trim($message);
            }
        } else {
            throw new LoggerException('Only string|Throwable messages are accepted, "%s" given',
                [gettype($message)]);
        }

        ['directory' => $directory, 'useGmtDate' => $useGmtDate,
         'file' => $file, 'fileName' => $fileName, 'fileNameTag' => $fileNameTag] = $this->options;

        // Use file's directory if given.
        $directory = strval($directory ?? ($file ? dirname($file) : null));

        $this->checkDirectory($directory);

        // Choose date function by option.
        $dater = $useGmtDate ? 'gmdate' : 'date';

        $type = 'LOG'; // @default
        $date = $dater('D, d M Y H:i:s O');

        switch ($level) {
            case self::ERROR: $type = 'ERROR'; break;
            case self::INFO:  $type = 'INFO';  break;
            case self::WARN:  $type = 'WARN';  break;
            case self::DEBUG: $type = 'DEBUG'; break;
        }

        $log = sprintf("[%s] %s | %s\n\n", $type, $date, $message);

        // Fix non-binary-safe issue of error_log().
        if (strpos($log, "\0")) {
            $log = str_replace("\0", "\\0", $log);
        }

        // Prepare if not given.
        if ($file == null) {
            $fileName = $fileName ? basename($fileName, '.log') : $dater('Y-m-d');
            $fileNameTag = $fileNameTag ? '-'. ltrim($fileNameTag, '-') : '';

            // Because permissions.
            $file = (PHP_SAPI != 'cli-server')
                  ? sprintf('%s/%s%s.log', $directory, $fileName, $fileNameTag)
                  : sprintf('%s/%s%s-cli-server.log', $directory, $fileName, $fileNameTag);

            // Store file as option for getFile() method.
            $this->options['file'] = $file;
        }


        $ok =@ error_log($log, 3, $file);
        if (!$ok) {
            throw new LoggerException('Log process failed [error: %s]', ['@error']);
        }

        return true;
    }

    /**
     * Prettify.
     * @param  Throwable $e
     * @return array
     * @since  4.1
     */
    public static function prettify(Throwable $e): array
    {
        // Dot all those PHP's ugly stuff..
        $clean = fn($s) => str_replace(['\\', '::', '->'], '.', $s);

        return [
            'string' => sprintf(
                '%s(%s): %s in %s:%s', $clean(get_class($e)),
                $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()
            ),
            'trace' => array_map($clean, explode("\n", $e->getTraceAsString()))
        ];
    }
}
