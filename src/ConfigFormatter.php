<?php

namespace App\FileEditors\ConfigEditor;

use App\FileEditors\Contracts\FileFormatterContract;
use Illuminate\Support\Str;

class ConfigFormatter implements FileFormatterContract
{
    protected $return_line_found = false;

    /**
     * Format key for writing
     *
     * @param 	string	$key
     */
    public function formatKey($key)
    {
        return "'".$key."'";
    }

    /**
     * Format value for writing
     *
     * @param string $value
     * @param string $quotes
     * @param boolean $terminates
     * @return string
     */
    public function formatValue($value, $quotes = '', $terminates = true)
    {
        $formatted_value = $quotes.$value.$quotes;
        $formatted_value = $terminates ? $formatted_value.',' : $formatted_value;

        return $formatted_value;
    }

    /**
     * Build a key line from the individual components
     *
     * @param array $line
     * @param string|null $key
     * @param string|null $value
     * @param boolean $force_quotes
     * @return string
     */
    public function formatSetterLine(array $line, string $key = null, string $value = null, bool $force_quotes=false): string
    {
        $indent = str_repeat(' ', $line['data']['lead_whitespace']);
        if ($line['data']['type'] === 'array_end') {
            return $indent.'],';
        }
        $key = $this->formatKey($key);
        $quotes = $line['data']['value_quotes'];
        if (empty($quotes) && $force_quotes) {
            $quotes = "'";
        }
        $terminates = $line['data']['value_terminates'];
        $value = $this->formatValue($value, $quotes, $terminates);

        return $indent.$key.' => '.$value;
    }

    /**
     * Normalize the key for reading
     *
     * @param  string $key
     *
     * @return string
     */
    public function normalizeKey($key)
    {
        return Str::replace(['\'', '"'], '', $key);
    }

    /**
     * Normalize the value for reading
     *
     * @param  string $value
     * @return string
     */
    public function normalizeValue($value)
    {
        $formatted_value = Str::replaceLast(',', '', trim($value));

        if (Str::startsWith($formatted_value, ['\'', '"'])) {
            $formatted_value = substr($formatted_value, 1);
        }

        if (Str::endsWith($formatted_value, ['\'', '"'])) {
            $formatted_value = substr($formatted_value, 0, strlen($formatted_value) -1);
        }

        return $formatted_value;
    }

    /**
     * Parse a line into an array of type, key, value and comment
     *
     * @param  string $line
     */
    public function parseLine($line): array
    {
        $output = [
            'lead_whitespace' => Str::length($line) - Str::length(ltrim($line)),
            'type' => null,
            'key' => null,
            'value' => null,
            'value_type' => null,
            'value_quotes' => '',
            'value_terminates' => true,
        ];

        $line = ltrim($line);

        if (!$this->return_line_found) {
            if (Str::startsWith($line, 'return [')) {
                $this->return_line_found = true;
            }

            $output['type'] = 'header';
            return $output;
        }

        if ($this->isEmpty($line)) {
            $output['type'] = 'empty';
        }
        elseif ($this->isComment($line)) {
            $output['type'] = 'comment';
        }
        elseif ($this->looksLikeSetter($line)) {
            list($key, $value) = array_map('trim', explode('=>', $line, 2));
            $quotes = $this->beginsWithAQuote($value);
            $terminates = $this->valueTerminates($value);
            $key    = $this->normalizeKey($key);
            $value  = $this->normalizeValue($value);

            if ($this->looksLikeArrayStart($value)) {
                $value_type = 'array_start';
            }
            else {
                $value_type = 'value';
            }

            $output['type'] = 'setter';
            $output['key'] = $key;
            $output['value'] = $value;
            $output['value_type'] = $value_type;
            $output['value_quotes'] = $quotes;
            $output['value_terminates'] = $terminates;
        }
        elseif ($this->looksLikeArrayEnd($line)) {
            $output['type'] = 'array_end';
        }
        else {
            $output['type'] = 'value';
            $output['value_type'] = 'value';
            $output['value_quotes'] = $this->beginsWithAQuote($line);
            $output['value_terminates'] = $this->valueTerminates($line);
        }

        return $output;
    }

    /**
     * Determine if the line in the file is an empty line
     *
     * @param string $line
     *
     * @return bool
     */
    protected function isEmpty($line)
    {
        return strlen(trim($line)) == 0;
    }

    /**
     * Determine if the line in the file is a comment, e.g. begins with a /, |, *, or #.
     *
     * @param string $line
     *
     * @return bool
     */
    protected function isComment($line)
    {
        return Str::startsWith(ltrim($line), ['/', '|', '*', '#']);
    }

    /**
     * Determine if the given line looks like it's setting a key.
     *
     * @param string $line
     *
     * @return bool
     */
    protected function looksLikeSetter(string $line): bool
    {
        return Str::contains($line, '=>');
    }

    /**
     * Check value of key for opening array bracket
     *
     * @param string $data
     * @return boolean
     */
    protected function looksLikeArrayStart(string $data): bool
    {
        return Str::startsWith($data, '[');
    }

    /**
     * Check line to see if it starts with a closing array bracket
     *
     * @param string $data
     * @return boolean
     */
    protected function looksLikeArrayEnd(string $data): bool
    {
        return Str::startsWith($data, [']', '],', '];']);
    }

    /**
     * Determine if the given string begins with a quote.
     *
     * @param string $data
     * @return string|null
     */
    protected function beginsWithAQuote(string $data)
    {
        if (Str::startsWith($data, ['\'', '"'])) {
            return $data[0];
        }

        return '';
    }

    /**
     * Determine if the given string ends with a quote.
     *
     * @param string $data
     *
     * @return bool
     */
    protected function endsWithAQuote($data)
    {
        return Str::endsWith($data, ['\'', '"', '\',', '",']);
    }

    /**
     * Determine if the given config value terminates with a comma.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function valueTerminates($value)
    {
        return Str::endsWith(trim($value), [',', ';']);
    }
}
