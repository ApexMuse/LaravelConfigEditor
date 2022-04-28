<?php

namespace App\FileEditors\ConfigEditor;

use App\FileEditors\Exceptions\UnableWriteToFileException;
use App\FileEditors\Contracts\FileWriterContract;
use App\FileEditors\ConfigEditor\ConfigFormatter;
use Illuminate\Support\Facades\File;

class ConfigWriter implements FileWriterContract
{
    /**
     * The content buffer
     *
     * @var array
     */
    public $buffer;

    /**
     * The formatter instance
     *
     * @var ConfigFormatter
     */
    protected $formatter;


    /**
     * Create a new writer instance
     *
     * @param ConfigFormatter   $formatter
     */
    public function __construct(ConfigFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Tests file for writability. If the file doesn't exist, check
     * the parent directory for writability so the file can be created.
     *
     * @throws UnableWriteToFileException
     *
     * @param string $file_path
     * @return void
     */
    protected function ensureFileIsWritable($file_path)
    {
        if ((File::isFile($file_path) && !File::isWritable($file_path)) || (!File::isFile($file_path) && !File::isWritable(dirname($file_path)))) {
            throw new UnableWriteToFileException('Unable to write to the file at '.$file_path);
        }
    }

    /**
     * Load current content into buffer
     *
     * @param  array $lines
     */
    public function setBuffer($lines)
    {
        $this->buffer = $lines;

        return $this;
    }

    /**
     * Return content in buffer
     */
    public function getBuffer()
    {
        return $this->buffer;
    }

    /**
     * Add an empty line
     *
     * @param integer $insert_index
     * @return array
     */
    public function appendEmptyLine(int $insert_index): array
    {
        $line = [];
        $line['index'] = $insert_index;
        $line['data']['lead_whitespace'] = 0;
        $line['data']['type'] = 'empty';
        $line['data']['key'] = null;
        $line['data']['value'] = null;
        $line['data']['value_type'] = null;
        $line['data']['value_quotes'] = "";
        $line['data']['value_terminates'] = false;
        $line['raw_line'] = "";
        return $line;
    }

    /**
     * Add a new key and value to the buffer
     *
     * @param string $key
     * @param string|array $value
     * @param integer $insert_index
     * @param boolean $force_quotes
     * @param string|null $after
     * @return ConfigWriter
     */
    public function appendSetter(string $key, $value, int $insert_index, bool $force_quotes=false, $after=null)
    {
        $current_index = $insert_index;

        $previous_line = $this->buffer[$insert_index - 1];
        $next_line = $this->buffer[$insert_index];

        $empty_line_before = false;
        $empty_line_after = false;

        // Calculate the lead whitespace and empty lines required for the new key and value
        $lead_whitespace = $previous_line['data']['lead_whitespace'] + 4;
        if (!empty($after)) {
            $lead_whitespace -= 4;
        }

        if ($previous_line['data']['type'] === 'header') { // The return line
            $empty_line_before = true;
            if ($next_line['data']['type'] !== 'empty') {
                $empty_line_after = true;
            }
        }
        elseif ($previous_line['data']['type'] === 'array_end') {
            $empty_line_before = true;
        }

        $new_content = [];

        // Check the previous line in the buffer before the insert index. If it isn't empty,
        //  add an empty line to the new content
        if ($empty_line_before) {
            array_push($new_content, $this->appendEmptyLine($current_index));
            ++$current_index;
        }

        // Add the new key and value to the new content
        if (!is_array($value)) {
            $line = [];
            $line['index'] = $current_index;
            $line['data']['lead_whitespace'] = $lead_whitespace;
            $line['data']['type'] = 'setter';
            $line['data']['key'] = $key;
            $line['data']['value'] = $value;
            $line['data']['value_type'] = 'value';
            $line['data']['value_quotes'] = $force_quotes ? "'" : '';
            $line['data']['value_terminates'] = true;
            $line['raw_line'] = $this->formatter->formatSetterLine($line, $key, $value, $force_quotes);
            array_push($new_content, $line);
            ++$current_index;
        }
        else {
            $line = [];
            $line['index'] = $current_index;
            $line['data']['lead_whitespace'] = $lead_whitespace;
            $line['data']['type'] = 'setter';
            $line['data']['key'] = $key;
            $line['data']['value'] = '[';
            $line['data']['value_type'] = 'array_start';
            $line['data']['value_quotes'] = '';
            $line['data']['value_terminates'] = false;
            $line['raw_line'] = $this->formatter->formatSetterLine($line, $key, '[', false);
            array_push($new_content, $line);
            ++$current_index;

            foreach ($value as $subkey => $subvalue) {
                $line = [];
                $line['index'] = $current_index;
                $line['data']['lead_whitespace'] = $lead_whitespace + 4;
                $line['data']['type'] = 'setter';
                $line['data']['key'] = $subkey;
                $line['data']['value'] = $subvalue;
                $line['data']['value_type'] = 'value';
                $line['data']['value_quotes'] = $force_quotes ? "'" : "";
                $line['data']['value_terminates'] = true;
                $line['raw_line'] = $this->formatter->formatSetterLine($line, $subkey, $subvalue, $force_quotes);
                array_push($new_content, $line);
                ++$current_index;
            }

            $line = [];
            $line['index'] = $current_index;
            $line['data']['lead_whitespace'] = $lead_whitespace;
            $line['data']['type'] = 'array_end';
            $line['data']['key'] = null;
            $line['data']['value'] = null;
            $line['data']['value_type'] = null;
            $line['data']['value_quotes'] = "";
            $line['data']['value_terminates'] = true;
            $line['raw_line'] = $this->formatter->formatSetterLine($line, null, null, false);
            array_push($new_content, $line);
            ++$current_index;
        }

        // Check the line in the buffer at the insert index. If it isn't empty,
        //  add another empty line to the new content
        if ($empty_line_after) {
            array_push($new_content, $this->appendEmptyLine($current_index));
            ++$current_index;
        }

        array_splice($this->buffer, $insert_index, null, $new_content);

        // Update the index attributes of each line in the buffer
        foreach ($this->buffer as $row => $line) {
            $this->buffer[$row]['index'] = $row;
        }

        return $this;
    }

    /**
     * Update a key and/or value in the buffer
     *
     * @param array|null $line
     * @param string|null $new_key
     * @param string|null $new_value
     * @param boolean $force_quotes
     * @return ConfigWriter
     */
    public function updateSetter($line=null, string $new_key = null, $new_value = null, $force_quotes = false)
    {
        if ($new_key) {
            $line['data']['key'] = $new_key;
        }
        else {
            $new_key = $line['data']['key'];
        }

        if ($new_value) {
            $line['data']['value'] = $new_value;
        }
        elseif (isset($line['data']['value'])) {
            $new_value = $line['data']['value'];
        }

        $line['raw_line'] = $this->formatter->formatSetterLine($line, $new_key, $new_value, $force_quotes);
        $this->buffer[$line['index']] = $line;

        return $this;
    }

    /**
     * Delete one setter in buffer
     *
     * @param  string $key
     *
     * @return object
     */
    public function deleteSetter($key)
    {

    }

    /**
     * Save buffer to file path
     *
     * @param  string $file_path
     */
    public function save(string $file_path)
    {
        $this->ensureFileIsWritable($file_path);

        // Build the content by using the 'raw_lines' in the buffer
        $content = [];
        foreach($this->buffer as $line) {
            $content[] = $line['raw_line'] . PHP_EOL;
        }

        File::put($file_path, $content);

        return $this;
    }
}
