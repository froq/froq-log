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

namespace Froq\Logger;

/**
 * @package    Froq
 * @subpackage Froq\Logger
 * @object     Froq\Logger\Logger
 * @author     Kerem Güneş <k-gun@mail.com>
 */
final class Logger
{
    /**
     * None.
     * @const int
     */
    public const NONE = 0;

    /**
     * Fail.
     * @const int
     */
    public const FAIL = 2;

    /**
     * Warn.
     * @const int
     */
    public const WARN = 4;

    /**
     * Info.
     * @const int
     */
    public const INFO = 8;

    /**
     * Debug.
     * @const int
     */
    public const DEBUG = 16;

    /**
     * All.
     * @const int
     */
    public const ALL = 30;

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
     * @param int    $level
     * @param string $directory
     */
    public function __construct(int $level = self::NONE, string $directory = null)
    {
        $this->level = $level;
        $this->directory = $directory;
    }

    /**
     * Set level.
     * @param  int $level
     * @return self
     */
    public function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
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
     * @return self
     */
    public function setDirectory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
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
     * @throws Froq\Logger\LoggerException
     */
    public function checkDirectory(): bool
    {
        if (empty($this->directory)) {
            throw new LoggerException('Log directory is not defined yet');
        }

        self::$directoryChecked = self::$directoryChecked ?: is_dir($this->directory);
        if (!self::$directoryChecked) {
            self::$directoryChecked = (bool) @mkdir($this->directory, 0644, true);
            if (self::$directoryChecked === false) {
                throw new LoggerException(sprintf('Cannot make directory, error[%s]',
                    error_get_last()['message'] ?? 'Unknown'));
            }
        }

        return self::$directoryChecked;
    }

    /**
     * Log.
     * @param  int $level
     * @param  any $message
     * @throws Froq\Logger\LoggerException
     * @return ?bool
     */
    public function log(int $level, $message): ?bool
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
            case self::FAIL: $messageType = 'FAIL'; break;
            case self::INFO: $messageType = 'INFO'; break;
            case self::WARN: $messageType = 'WARN'; break;
            case self::DEBUG: $messageType = 'DEBUG'; break;
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

        $message = sprintf("[%s] %s ~ %s\n\n", $messageType, $messageDate,
            // fix non-binary safe issue of error_log()
            str_replace(chr(0), 'NU??', trim((string) $message))
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
     * Log fail.
     * @param  any $message
     * @return ?bool
     */
    public function logFail($message): ?bool
    {
        return $this->log(self::FAIL, $message);
    }

    /**
     * Log warn.
     * @param  any $message
     * @return ?bool
     */
    public function logWarn($message): ?bool
    {
        return $this->log(self::WARN, $message);
    }

    /**
     * Log info.
     * @param  any $message
     * @return ?bool
     */
    public function logInfo($message): ?bool
    {
        return $this->log(self::INFO, $message);
    }

    /**
     * Log debug.
     * @param  any $message
     * @return ?bool
     */
    public function logDebug($message): ?bool
    {
        return $this->log(self::DEBUG, $message);
    }
}
