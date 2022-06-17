<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
declare(strict_types=1);

namespace froq\logger;

/**
 * Logger options with defaults.
 *
 * @package froq\logger
 * @object  froq\logger\LoggerOptions
 * @author  Kerem Güneş
 * @since   6.0
 * @internal
 */
class LoggerOptions extends \Options
{
    /**
     * Create logger options with defaults.
     *
     * @param  array|null $options
     * @return froq\logger\LoggerOptions
     */
    public static function create(array|null $options): LoggerOptions
    {
        static $optionsDefault = [
            'level'      => -1,   // All. Moved as property in v/5.0.
            'tag'        => null, // Be used in write() as file name appendix.
            'directory'  => null, // Must be given in constructor options.
            'file'       => null, // File with full path.
            'fileName'   => null, // Be used in write() or created.
            'utc'        => true, // Using UTC date or local date.
            'json'       => false,
            'pretty'     => false,
            'rotate'     => false,
            'dateFormat' => null,
        ];

        // Create & filter base options.
        $that = (new LoggerOptions($options, $optionsDefault))
            ->filterDefaultKeys($optionsDefault);

        // Special case of level.
        $that->level = (int) $that->level;

        // Use default log directory when available.
        if ($that->directory == '' && defined('APP_DIR')) {
            $that->directory = APP_DIR . '/var/log';
        }

        // Regulate tag option.
        if ($that->tag != '') {
            $that->tag = '-' . trim((string) $that->tag, '-');
        }

        return $that;
    }
}
