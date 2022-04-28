<?php

namespace App\FileEditors\ConfigEditor;

use App\FileEditors\ConfigEditor\ConfigFormatter;
use App\FileEditors\Contracts\FileReaderContract;
use Illuminate\Support\Facades\File;
use App\FileEditors\Exceptions\UnableReadFileException;

class ConfigReader implements FileReaderContract
{
    /**
     * The file path
     *
     * @var string
     */
    protected $file_path;

    /**
     * The formatter instance
     *
     * @var ConfigFormatter
     */
    protected $formatter;

    /**
     * Create a new reader instance
     *
     * @param ConfigFormatter $formatter
     */
    public function __construct(ConfigFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Load file
     *
     * @param string|null $file_path
     * @return ConfigReader
     */
    public function load($file_path): ConfigReader
    {
        $this->file_path = $file_path;

        return $this;
    }

    /**
     * Ensures the config file is readable.
     *
     * @throws UnableReadFileException
     *
     * @return void
     */
    protected function ensureFileIsReadable()
    {
        if (!File::isReadable($this->file_path) || !File::isFile($this->file_path)) {
            throw new UnableReadFileException(sprintf('Unable to read the file at %s.', $this->file_path));
        }
    }

    /**
     * Get content of config file
     */
    public function content()
    {
        if (empty($this->file_path)) {
            return null;
        }

        $this->ensureFileIsReadable();

        return File::get($this->file_path);
    }

    /**
     * Get information about all lines from the file content
     *
     * @return array|null
     */
    public function lines()
    {
        if (empty($this->file_path)) {
            return null;
        }

        $lines = [];
        $content_lines   = $this->readLinesFromFile();

        foreach ($content_lines as $row => $line) {
            $lines[] = [
                'index' => $row,
                'raw_line' => $line,
                'data' => $this->formatter->parseLine($line)
            ];
        }

        return $lines;
    }

    /**
     * Read content into an array of lines with auto-detected line endings
     *
     * @return array
     */
    protected function readLinesFromFile()
    {
        $this->ensureFileIsReadable();

        $autodetect = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', '1');
        $lines = file($this->file_path, FILE_IGNORE_NEW_LINES);
        ini_set('auto_detect_line_endings', $autodetect);

        return $lines;
    }

    /**
     * Locate a key in the buffer based on the provided dot syntax
     *
     * @param string|null $dot_syntax
     * @param array $buffer
     * @return array|bool
     */
    public function getKey($dot_syntax, array $buffer)
    {
        $dot_syntax_array = explode('.', $dot_syntax);

        $target_key = end($dot_syntax_array);

        $target_key_located = false;

        foreach($dot_syntax_array as $key) {
            if (!$target_key_located) {
                $starting_index = array_key_first($buffer);
                foreach ($buffer as $line) {
                    if ($line['data']['type'] === 'setter' && $line['data']['key'] === $key) {
                        if ($key === $target_key) {
                            return $line;
                        }
                        // Calculate the new offset for the array_slice function
                        $offset = $starting_index > 0 ? $line['index'] - $starting_index : $line['index'];
                        $buffer = array_slice($buffer, $offset, null, true);
                        break;
                    }
                }
            }
        }

        return $target_key_located;
    }

    /**
     * Get the value of a key
     *
     * @param string $dot_syntax
     * @param array $buffer
     * @return string|bool
     */
    public function getValue(string $dot_syntax, array $buffer)
    {
        $line = $this->getKey($dot_syntax, $buffer);
        return $line ? $line['data']['value'] : false;
    }

    /**
     * Get the index where a new key and value can be inserted into the buffer
     *
     * @param string $dot_syntax
     * @param array $buffer
     * @param string|null $after
     * @return integer
     */
    public function getInsertIndex(string $dot_syntax, array $buffer, $after=null): int
    {
        // Remove the new key from the dot syntax
        $dot_syntax_array = explode('.', $dot_syntax);
        array_pop($dot_syntax_array);
        $dot_syntax = implode('.', $dot_syntax_array);

        // If there is no dot syntax, the key and value will be added at the first level
        if (empty($dot_syntax) && empty($after)) {
            foreach($buffer as $row => $line) {
                if ($line['data']['type'] !== 'header') {
                    return $row;
                }
            }
        }
        else {
            if (!empty($after)) {
                $dot_syntax .= '.'.$after;
            }

            // Get the line data for the parent key
            $parent_key_line = $this->getKey($dot_syntax, $buffer);

            // Check to see if the line's value is an array. If so, we need to find the end of the array.
            if (empty($after) || $parent_key_line['data']['value_type'] !== 'array_start') {
                // Add 1 to the parent key index
                return $parent_key_line['index'] + 1;
            }
            else {
                // Find the array end
                $indent = $parent_key_line['data']['lead_whitespace'];
                for ($i = $parent_key_line['index'] + 1; $i < count($buffer); $i++) {
                    if ($buffer[$i]['data']['lead_whitespace'] === $indent && $buffer[$i]['data']['type'] === 'array_end') {
                        return $i+1;
                    }
                }
            }
        }
    }

}
