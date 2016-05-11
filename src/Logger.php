<?php
/**
 * Copyright (c) 2016 Kerem Güneş
 *     <k-gun@mail.com>
 *
 * GNU General Public License v3.0
 *     <http://www.gnu.org/licenses/gpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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
     * Log only error events.
     * @const int
     */
    const FAIL = 2;

    /**
     * Log only warning events.
     * @const int
     */
    const WARN = 4;

    /**
     * Log only informal events.
     * @const int
     */
    const INFO = 8;

    /**
     * Log only debugging events.
     * @const int
     */
    const DEBUG = 16;

    /**
     * Log all events (FAIL | WARN | INFO | DEBUG).
     * @const int
     */
    const ALL = 30;

    /**
     * No log.
     * @const int
     */
    const NONE = 0;

    /**
     * Log level, disabled as default.
     * @var int
     */
    protected $level = 0;

    /**
     * Log directory.
     * @var string
     */
    protected $directory;

    /**
     * Aims some performance, escaping to call "is_dir" function.
     * @var bool
     */
    protected static $directoryChecked = false;

    /**
     * Set log level.
     *
     * @param  int $level
     * @return self
     */
    final public function setLevel(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Get log level.
     *
     * @return int
     */
    final public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Set log directory.
     *
     * @param  string $directory
     * @return self
     */
    final public function setDirectory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Get log directory.
     *
     * @return string
     */
    final public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Check log directory, if not exists create it.
     *
     * @throws \RuntimeException
     * @return bool
     */
    public function checkDirectory(): bool
    {
        if (empty($this->directory)) {
            throw new LoggerException(
                'Log directory is not defined in given configuration! '.
                'Define it using `query_log_directory` key to activate logging.');
        }

        self::$directoryChecked = self::$directoryChecked ?: is_dir($this->directory);
        if (!self::$directoryChecked) {
            self::$directoryChecked = mkdir($this->directory, 0644, true);
            if (self::$directoryChecked === false) {
                throw new LoggerException('Cannot create log directory!');
            }

            // !!! NOTICE !!!
            // set your log dir secure
            file_put_contents($this->directory .'/index.php',
                "<?php header('HTTP/1.1 403 Forbidden'); ?>");
            // this action is for only apache, see nginx configuration here:
            // http://nginx.org/en/docs/http/ngx_http_access_module.html
            file_put_contents($this->directory .'/.htaccess',
                "Order deny,allow\r\nDeny from all");
        }

        return self::$directoryChecked;
    }

    /**
     * Log given message by level.
     * @param  int $level
     * @param  any $message
     * @return bool|null
     */
    final public function log(int $level, $message)
    {
        // no log
        if (!$level || ($level & $this->level) == 0) {
            return;
        }

        // ensure log directory
        $this->checkDirectory();

        // prepare message prepend
        $messageType = '';
        switch ($level) {
            case self::FAIL:
                $messageType = 'FAIL';
                break;
            case self::INFO:
                $messageType = 'INFO';
                break;
            case self::WARN:
                $messageType = 'WARN';
                break;
            case self::DEBUG:
                $messageType = 'DEBUG';
                break;
        }

        $messageDate = date('D, d M Y H:i:s O');

        // handle exception, object, array messages
        if ($message instanceof \Throwable) {
            $message = get_class($message) ." thrown in '". $message->getFile() .":".
                $message->getLine() ."' with message '". $message->getMessage() ."'.\n".
                $message->getTraceAsString() ."\n";
        } elseif (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        // prepare message & message file
        $message = sprintf("[%s] %s >> %s\n\n",
            $messageType, $messageDate, trim((string) $message));
        $messageFile = sprintf('%s/%s.log', $this->directory, date('Y-m-d'));

        return error_log($message, 3, $messageFile);
    }

    /**
     * Log fail.
     * @param  any $message
     * @return bool|null
     */
    final public function logFail($message)
    {
        return $this->log(self::FAIL, $message);
    }

    /**
     * Log warn.
     * @param  any $message
     * @return bool|null
     */
    final public function logWarn($message)
    {
        return $this->log(self::WARN, $message);
    }

    /**
     * Log info.
     * @param  any $message
     * @return bool|null
     */
    final public function logInfo($message)
    {
        return $this->log(self::INFO, $message);
    }

    /**
     * Log debug.
     * @param  any $message
     * @return bool|null
     */
    final public function logDebug($message)
    {
        return $this->log(self::DEBUG, $message);
    }
}
