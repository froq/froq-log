<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-logger
 */
declare(strict_types=1);

namespace froq\logger;

/**
 * Log parser class for parsing log and log archive files.
 *
 * @package froq\logger
 * @object  froq\logger\LogParser
 * @author  Kerem Güneş
 * @since   7.0
 */
class LogParser
{
    /** The target file. */
    private string $file;

    /**
     * Constructor.
     *
     * @param string|null $file
     */
    public function __construct(string $file = null)
    {
        $file && $this->file = $file;
    }

    /**
     * Set file.
     *
     * @param  string $file
     * @return void
     */
    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    /**
     * Get file.
     *
     * @return string|null
     */
    public function getFile(): string|null
    {
        return $this->file ?? null;
    }

    /**
     * Parse self file.
     *
     * @return Generator
     * @throws froq\logger\LogParserException
     * @causes froq\logger\LogParserException
     */
    public function parse(): \Generator
    {
        $file = $this->getFile()
            ?? throw LogParserException::forEmptyFile();

        return self::parseFile($file);
    }

    /**
     * Parse given file.
     *
     * @return Generator
     * @throws froq\logger\LogParserException
     */
    public static function parseFile(string $file): \Generator
    {
        // Swap files temporarily.
        if ($tmpFile = self::copyGzFileAsTmpFile($file)) {
            $file = $tmpFile;
        }

        try {
            $file = new \SplFileInfo($file);
            $type = $file->getType();
        } catch (\Throwable $e) {
            throw LogParserException::forCaughtThrowable($e);
        }

        if (!$file->isFile()) {
            $type = ($type == 'dir') ? 'directory' : ($type ?: 'unknown');
            throw LogParserException::forInvalidFile((string) $file, $type);
        }

        try {
            $ofile = $file->openFile('rb');
            $entry = '';

            while (!$ofile->eof()) {
                $entry .= $line = $ofile->fgets();

                // Double "\n" is separator.
                if ($line == "\n") {
                    $result = self::parseFileEntry($entry);
                    if ($result != null) {
                        yield $result;
                    }

                    // Reset entry.
                    $entry = '';
                }
            }
        } catch (\Throwable $e) {
            throw LogParserException::forCaughtThrowable($e);
        }

        // Drop uncompressed file.
        $tmpFile && unlink($tmpFile);
    }

    /**
     * Parse a log entry.
     *
     * @param  string $entry
     * @return array|null
     */
    public static function parseFileEntry(string $entry): array|null
    {
        $ret = [];

        $entry = trim($entry);
        if (!$entry) {
            return $ret;
        }

        static $parseThrown;
        static $reNormalMatch = '~\[(?<type>.+)\] (?<date>.+) \| (?<ip>.+) \| *(?<content>.*)~s';
        static $reThrownMatch = '~(?<type>.+?)(?:\((?<code>\d+?)\))?: *(?<message>.*?) at (?<file>.+?):(?<line>\d+?)~s';

        // Regular log entry.
        if ($entry[0] == '[') {
            if (preg_match_names($reNormalMatch, $entry, $match)) {
                $ret = array_apply($match, 'trim');
                $ret['thrown'] = null;

                // Parse thrown recursively.
                $parseThrown ??= function ($content) use (&$parseThrown, $reThrownMatch) {
                    if (preg_match_names($reThrownMatch, $content, $match)) {
                        $thrown = array_apply($match, function ($v, $k) {
                            return ($k == 'code' || $k == 'line') ? (int) $v : $v;
                        });

                        $lines = explode("\n", $content);
                        $start = null;

                        // Add trace.
                        foreach ($lines as $i => $line) {
                            if (preg_match('~^(Cause|Previous):~', $line, $match)) {
                                $start = $match[1];
                                break;
                            }
                            if ($line && $line[0] == '#') {
                                $thrown['trace'][] = $line;
                            }
                        }

                        if ($start) {
                            // Drop "Cause|Previous:" part & get rest.
                            $rest = array_slice($lines, $i + 1);
                            $rest = implode("\n", $rest);

                            $subthrown = $parseThrown($rest);
                            if ($subthrown) {
                                $key = strtolower($start);
                                $thrown[$key] = $subthrown;
                            }
                        }

                        return $thrown;
                    }
                };

                $ret['thrown'] = $parseThrown($ret['content']);
            }
        }
        // JSON log.
        elseif ($entry[0] == '{') {
            $ret = json_decode($entry, true);
            if (json_last_error()) {
                $ret = null;
            }
        }

        return $ret;
    }

    /**
     * Copy file as a temporary file if it's a GZ file & return new file path.
     */
    private static function copyGzFileAsTmpFile(string $file): string|null
    {
        if (!file_exists($file)) {
            return null;
        }
        if (!str_ends_with($file, '.gz')) {
            return null;
        }

        $tmpFile = null;

        if ($sfp =@ gzopen($file, 'rb')) {
            $tmp = format('%s/%s.log', tmp(), uuid());
            if ($dfp =@ fopen($tmp, 'wb')) {
                $tmpFile = $tmp;
                while (!gzeof($sfp)) {
                    fwrite($dfp, gzread($sfp, 2048));
                }
                fclose($dfp);
            }
            gzclose($sfp);
        }

        return $tmpFile;
    }
}
