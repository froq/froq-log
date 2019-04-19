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

use froq\logger\Logger;

/**
 * Logger.
 * @return froq\logger\Logger.
 */
function logger(): Logger
{
    return app()->logger();
}

/**
 * Log fail.
 * @param  any  $message
 * @param  bool $separate
 * @return ?bool
 */
function log_fail($message, bool $separate = true): ?bool
{
    return logger()->logFail($message, $separate);
}

/**
 * Log warn.
 * @param  any  $message
 * @param  bool $separate
 * @return ?bool
 */
function log_warn($message, bool $separate = true): ?bool
{
    return logger()->logWarn($message, $separate);
}

/**
 * Log info.
 * @param  any  $message
 * @param  bool $separate
 * @return ?bool
 */
function log_info($message, bool $separate = true): ?bool
{
    return logger()->logInfo($message, $separate);
}

/**
 * Log debug.
 * @param  any  $message
 * @param  bool $separate
 * @return ?bool
 */
function log_debug($message, bool $separate = true): ?bool
{
    return logger()->logDebug($message, $separate);
}
