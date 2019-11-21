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

use froq\logger\LoggerException;
use froq\traits\OptionTrait;
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
     * Options trait.
     * @object froq\traits\OptionTrait
     */
    use OptionTrait;

    /**
     * Levels.
     * @const int
     */
    public const NONE  = 0,  FAIL  = 2,
                 WARN  = 4,  INFO  = 8,
                 DEBUG = 16, ALL   = 30,
                 ANY   = -1; // Just to skip NONE (0).

    /**
     * Options default.
     * @var array
     */
    private static array $optionsDefault = [
        'level'            => 0,    // None.
        'file'             => null, // Will be set in log().
        'directory'        => null,
        'fileNameAppendix' => null,
        'useLocalDate'     => true,
    ];

    /**
     * Constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $options = array_merge(self::$optionsDefault, $options ?? []);

        $this->setOptions($options);
    }

    /**
     * Log.
     * @param  int $level
     * @param  any $message
     * @throws froq\logger\LoggerException If error_log() fails.
     * @return bool
     */
    public function log(int $level, $message): bool
    {
        // No log.
        if (!$level || !($level & ((int) $this->options['level']))) {
            return false;
        }

        ['directory' => $directory, 'fileNameAppendix' => $fileNameAppendix,
         'useLocalDate' => $useLocalDate] = $this->options;

        // Ensure log directory.
        $this->checkDirectory($directory);

        $messageType = 'LOG'; // @default.
        $messageDate = $useLocalDate ? date('D, d M Y H:i:s O') : gmdate('D, d M Y H:i:s O');

        switch ($level) {
            case self::FAIL: $messageType = 'FAIL'; break;
            case self::INFO: $messageType = 'INFO'; break;
            case self::WARN: $messageType = 'WARN'; break;
            case self::DEBUG: $messageType = 'DEBUG'; break;
        }

        // Handle exception, object, array messages.
        if ($message instanceof Throwable) {
            $message = (string) $message;
        } elseif (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        $message = sprintf('[%s] %s | %s%s', $messageType, $messageDate,
            // Fix non-binary safe issue of error_log().
            str_replace(chr(0), 'NU??', trim((string) $message)), "\n\n"
        );

        $fileName = $useLocalDate ? date('Y-m-d') : gmdate('Y-m-d');
        $fileNameAppendix = $fileNameAppendix ?: '';

        // Because permissions.
        $messageFile = (PHP_SAPI != 'cli-server')
            ? sprintf('%s/%s%s.log', $directory, $fileName, $fileNameAppendix)
            : sprintf('%s/%s%s-cli-server.log', $directory, $fileName, $fileNameAppendix);

        // Store file.
        $this->options['file'] = $messageFile;

        $ok =@ error_log($message, 3, $messageFile);
        if (!$ok) {
            throw new LoggerException(sprintf('Log failed, error[%s]', error()));
        }

        return true;
    }

    /**
     * Log any.
     * @param  any $message
     * @return bool
     * @since  3.2
     */
    public function logAny($message): bool
    {
        return $this->log(self::ANY, $message);
    }

    /**
     * Log fail.
     * @param  any $message
     * @return bool
     */
    public function logFail($message): bool
    {
        return $this->log(self::FAIL, $message);
    }

    /**
     * Log warn.
     * @param  any $message
     * @return bool
     */
    public function logWarn($message): bool
    {
        return $this->log(self::WARN, $message);
    }

    /**
     * Log info.
     * @param  any $message
     * @return bool
     */
    public function logInfo($message): bool
    {
        return $this->log(self::INFO, $message);
    }

    /**
     * Log debug.
     * @param  any $message
     * @return bool
     */
    public function logDebug($message): bool
    {
        return $this->log(self::DEBUG, $message);
    }

    /**
     * Get file.
     * @return ?string
     * @since  4.0
     */
    public function getFile(): ?string
    {
        return $this->getOption('file');
    }

    /**
     * Get directory.
     * @return ?string
     */
    public function getDirectory(): ?string
    {
        return $this->getOption('directory');
    }

    /**
     * Check directory.
     * @param  ?string $directory
     * @return void
     * @throws froq\logger\LoggerException
     */
    private function checkDirectory(?string $directory): void
    {
        if ($directory == null) {
            throw new LoggerException('Log directory is not defined yet');
        }

        if (!is_dir($directory)) {
            $ok =@ mkdir($directory, 0644, true);
            if (!$ok) {
                throw new LoggerException(sprintf('Cannot make directory, error[%s]', error()));
            }
        }
    }
}
