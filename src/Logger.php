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
     * None.
     * @const int
     */
    const NONE = 0;

    /**
     * Fail.
     * @const int
     */
    const FAIL = 2;

    /**
     * Warn.
     * @const int
     */
    const WARN = 4;

    /**
     * Info.
     * @const int
     */
    const INFO = 8;

    /**
     * Debug.
     * @const int
     */
    const DEBUG = 16;

    /**
     * All.
     * @const int
     */
    const ALL = 30;

    /**
     * Log level.
     * @var int
     */
    private $level = self::NONE;

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
     * @return string
     */
    public function getDirectory(): string
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
     * Log.
     * @param  int $level
     * @param  any $message
     * @return bool|null
     */
    public function log(int $level, $message)
    {
        // no log
        if (!$level || ($level & $this->level) == 0) {
            return null;
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
            $message = sprintf("%s: '%s' in '%s:%s'.\n%s\n", get_class($message),
                $message->getMessage(), $message->getFile(), $message->getLine(),
                $message->getTraceAsString()
            );
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
    public function logFail($message)
    {
        return $this->log(self::FAIL, $message);
    }

    /**
     * Log warn.
     * @param  any $message
     * @return bool|null
     */
    public function logWarn($message)
    {
        return $this->log(self::WARN, $message);
    }

    /**
     * Log info.
     * @param  any $message
     * @return bool|null
     */
    public function logInfo($message)
    {
        return $this->log(self::INFO, $message);
    }

    /**
     * Log debug.
     * @param  any $message
     * @return bool|null
     */
    public function logDebug($message)
    {
        return $this->log(self::DEBUG, $message);
    }
}
