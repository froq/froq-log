<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-log
 */
namespace froq\log;

/**
 * Log parser class for parsing log and log archive files.
 *
 * @package froq\log
 * @class   froq\log\LogParser
 * @author  Kerem Güneş
 * @since   7.0
 */
class LogParser
{
    /** Log file to parse. */
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
     * @return Generator<array|null>
     * @throws froq\log\LogParserException
     * @causes froq\log\LogParserException
     */
    public function parse(): \Generator
    {
        $file = $this->getFile() ?? throw LogParserException::forEmptyFile();

        return self::parseFile($file);
    }

    /**
     * Parse given file.
     *
     * @return Generator<array|null>
     * @throws froq\log\LogParserException
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
            $type = ($type === 'dir') ? 'directory' : ($type ?: 'unknown');
            throw LogParserException::forInvalidFile((string) $file, $type);
        }

        try {
            $ofile = $file->openFile('rb');
            $entry = '';

            while (!$ofile->eof()) {
                $entry .= $line = $ofile->fgets();

                // Double "\n" is separator.
                if ($line === "\n") {
                    if ($result = self::parseFileEntry($entry)) {
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
        $entry = trim($entry);
        if (!$entry) {
            return null;
        }

        $ret = null;

        static $parseThrown;
        static $reNormalMatch = '~\[(?<type>.+?)\] (?<date>.+?) \| (?<ip>.+?) \| *(?<content>.*)~s';
        static $reThrownMatch = '~(?<class>.+?)(?:\((?<code>\d+?)\))?: *(?<message>.*?) @ *(?<file>.+?):(?<line>\d+)~s';

        // Regular log entry.
        if ($entry[0] === '[') {
            if (preg_match_names($reNormalMatch, $entry, $match)) {
                $ret = array_map('trim', $match);
                $ret['thrown'] = null;

                // Parse thrown recursively.
                $parseThrown ??= function ($content) use (&$parseThrown, $reThrownMatch) {
                    if (preg_match_names($reThrownMatch, $content, $match)) {
                        $thrown = array_apply($match, function ($v, $k) {
                            return match ($k) {
                                'code'  => is_numeric($v) ? (int) $v : $v,
                                'line'  => (int) $v,
                                default => $v
                            };
                        });

                        $lines = explode("\n", $content);
                        $subkey = null;

                        // Add trace as sub-thrown.
                        foreach ($lines as $i => $line) {
                            $line = ltrim($line);

                            if (preg_match('~^(Cause|Previous):~', $line, $match)) {
                                $subkey = strtolower($match[1]);
                                break;
                            }

                            if (preg_match('~^#\d+ ~', $line)) {
                                $thrown['trace'][] = $line;
                            }
                        }

                        if ($subkey) {
                            // Drop "Cause|Previous:" part & get rest.
                            $rest = array_slice($lines, $i + 1);
                            $rest = implode("\n", $rest);

                            if ($subthrown = $parseThrown($rest)) {
                                $thrown[$subkey] = $subthrown;
                            }
                        }

                        return $thrown;
                    }
                };

                $ret['thrown'] = $parseThrown($ret['content']);

                // @cancel
                // if (isset($ret['thrown']['file'], $ret['thrown']['line'])) {
                //     $ret['thrown'] = array_insert($ret['thrown'], 'message', ['path' => sprintf(
                //         '%s:%d', $ret['thrown']['file'], $ret['thrown']['line']
                //     )]);
                // }
            }
        }
        // JSON log entry.
        elseif ($entry[0] === '{') {
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
        if (!str_ends_with($file, '.gz')) {
            return null;
        }
        if (!file_exists($file)) {
            return null;
        }

        $tmpFile = null;

        if ($sfp = gzopen($file, 'rb')) {
            $tmp = sprintf('%s/%s.log', tmp(), uuid());
            if ($dfp = fopen($tmp, 'wb')) {
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
