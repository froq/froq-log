<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-log
 */
namespace froq\log;

/**
 * Logger options with defaults.
 *
 * @package froq\log
 * @class   froq\log\LoggerOptions
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
     * @return froq\log\LoggerOptions
     */
    public static function create(array|null $options): LoggerOptions
    {
        static $optionsDefault = [
            'level'      => -1,   // All. Moved as property in v/5.0.
            'tag'        => null, // Be used in write() as file name appendix.
            'directory'  => null, // Must be given in constructor options.
            'file'       => null, // File with full path.
            'fileName'   => null, // Be used in write() or created.
            'timeZone'   => 'UTC',
            'timeFormat' => 'D, d M Y H:i:s.u P',
            'json'       => false,
            'rotate'     => false,
        ];

        // Create & filter base options.
        $that = (new LoggerOptions($options, $optionsDefault))
            ->filterDefaults($optionsDefault);

        // Special case of level.
        $that->level = (int) $that->level;

        // Use default log directory when available.
        if ($that->directory === null && defined('APP_DIR')) {
            $that->directory = APP_DIR . '/var/log';
        }

        // Regulate tag option.
        if ($that->tag !== null) {
            $that->tag = trim((string) $that->tag, '-');
        }

        return $that;
    }
}
