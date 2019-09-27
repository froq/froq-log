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
     * Levels.
     * @const int
     */
    public const NONE  = 0,  FAIL  = 2,
                 WARN  = 4,  INFO  = 8,
                 DEBUG = 16, ALL   = 30,
                 ANY   = -1; // just to pass none (0)

    /**
     * Log level.
     * @var int
     */
    private $level = 0; // none

    /**
     * Directory.
     * @var string
     */
    private $directory;

    /**
     * Directory checked.
     * @var bool
     */
    private static $directoryChecked = false;

    /**
     * Constructor.
     * @param int         $level
     * @param string|null $directory
     */
    public function __construct(int $level = 0, string $directory = null)
    {
        $this->level = $level;
        $this->directory = $directory;
    }

    /**
     * Set level.
     * @param  int $level
     * @return void
     */
    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    /**
     * Get level.
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Set directory.
     * @param  string $directory
     * @return void
     */
    public function setDirectory(string $directory): void
    {
        $this->directory = $directory;
    }

    /**
     * Get directory.
     * @return ?string
     */
    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    /**
     * Check directory.
     * @return bool
     * @throws froq\logger\LoggerException
     */
    public function checkDirectory(): bool
    {
        if ($this->directory == null) {
            throw new LoggerException('Log directory is not defined yet');
        }

        self::$directoryChecked = self::$directoryChecked ?: is_dir($this->directory);
        if (!self::$directoryChecked) {
            self::$directoryChecked =@ (bool) mkdir($this->directory, 0644, true);
            if (self::$directoryChecked === false) {
                throw new LoggerException(sprintf('Cannot make directory, error[%s]',
                    error_get_last()['message'] ?? 'Unknown'));
            }
        }

        return self::$directoryChecked;
    }

    /**
     * Log.
     * @param  int  $level
     * @param  any  $message
     * @param  bool $separate
     * @throws froq\logger\LoggerException
     * @return ?bool
     */
    public function log(int $level, $message, bool $separate = false): ?bool
    {
        // no log
        if (!$level || !($level & $this->level)) {
            return null;
        }

        // ensure log directory
        $this->checkDirectory();

        // prepare message prepend
        $messageType = '';
        switch ($level) {
            case self::FAIL:
                $messageType = 'FAIL'; break;
            case self::INFO:
                $messageType = 'INFO'; break;
            case self::WARN:
                $messageType = 'WARN'; break;
            case self::DEBUG:
                $messageType = 'DEBUG'; break;
            default:
                $messageType = 'LOG';
        }

        $messageDate = date('D, d M Y H:i:s O');

        // handle exception, object, array messages
        if ($message instanceof \Throwable) {
            $message = sprintf("%s: '%s' in '%s:%s'.\n%s\n", get_class($message),
                $message->getMessage(), $message->getFile(), $message->getLine(),
                $message->getTraceAsString()
            );
        } elseif (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        $message = sprintf('[%s] %s | %s%s', $messageType, $messageDate,
            // fix non-binary safe issue of error_log()
            str_replace(chr(0), 'NU??', trim((string) $message)),
            // new lines
            $separate ? "\n\n" : "\n"
        );

        $messageFile = sprintf('%s/%s.log', $this->directory, date('Y-m-d'));
        // because permissions..
        if (PHP_SAPI == 'cli-server') {
            $messageFile = sprintf('%s/%s-cli-server.log', $this->directory, date('Y-m-d'));
        }

        $return = error_log($message, 3, $messageFile);
        if (!$return) {
            throw new LoggerException(sprintf('Log failed, error[%s]',
                error_get_last()['message'] ?? 'Unknown'));
        }

        return $return;
    }

    /**
     * Log any.
     * @param  any  $message
     * @param  bool $separate
     * @return ?bool
     * @since  3.2
     */
    public function logAny($message, bool $separate = false): ?bool
    {
        return $this->log(self::ANY, $message, $separate);
    }

    /**
     * Log fail.
     * @param  any  $message
     * @param  bool $separate
     * @return ?bool
     */
    public function logFail($message, bool $separate = false): ?bool
    {
        return $this->log(self::FAIL, $message, $separate);
    }

    /**
     * Log warn.
     * @param  any  $message
     * @param  bool $separate
     * @return ?bool
     */
    public function logWarn($message, bool $separate = false): ?bool
    {
        return $this->log(self::WARN, $message, $separate);
    }

    /**
     * Log info.
     * @param  any  $message
     * @param  bool $separate
     * @return ?bool
     */
    public function logInfo($message, bool $separate = false): ?bool
    {
        return $this->log(self::INFO, $message, $separate);
    }

    /**
     * Log debug.
     * @param  any  $message
     * @param  bool $separate
     * @return ?bool
     */
    public function logDebug($message, bool $separate = false): ?bool
    {
        return $this->log(self::DEBUG, $message, $separate);
    }
}
