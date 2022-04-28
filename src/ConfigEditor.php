<?php

namespace App\FileEditors\ConfigEditor;

use App\FileEditors\Exceptions\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use Exception;
use App\FileEditors\ConfigEditor\ConfigFormatter;
use App\FileEditors\ConfigEditor\ConfigReader;
use App\FileEditors\ConfigEditor\ConfigWriter;
use App\FileEditors\Contracts\FileEditorContract;
use App\FileEditors\Exceptions\KeyNotFoundException;
use App\FileEditors\FileEditor;

class ConfigEditor extends FileEditor implements FileEditorContract
{
    /**
     * The formatter instance
     *
     * @var ConfigFormatter
     */
    protected $formatter;

    /**
     * The reader instance
     *
     * @var ConfigReader
     */
    protected $reader;

    /**
     * The writer instance
     *
     * @var ConfigWriter
     */
    protected $writer;
    /**
     * The path to the config file
     *
     * @var string
     */
    protected $file_path;


    /**
     * Create a new ConfigEditor instance
     *
     * @return void
     */
    public function __construct()
    {
        $this->backup_path = base_path('storage/config-editor/backups/');
        $this->backup_path = rtrim($this->backup_path, '\\/') . '/';
        if (App::environment('local')) {
            $this->backup_path = str_replace('/', '\\', $this->backup_path);
        }
        $this->createBackupFolder();
    }

    /**
     * Load config file
     *
     * @param  string       $file_path             The file path
     * @param  boolean      $restore_if_not_found  Restore this file from other file if it's not found
     * @param  string|null  $restore_path          The file path you want to restore from
     *
     * @return ConfigEditor
     */
    public function load(string $file_path, $restore_if_not_found = false, $restore_path = null)
    {
        $this->formatter  = new ConfigFormatter;
        $this->reader     = new ConfigReader($this->formatter);
        $this->writer     = new ConfigWriter($this->formatter);

        if (File::exists($file_path)) {
            $this->file_path = $file_path;
            $this->reader->load($this->file_path);
            $this->writer->setBuffer($this->getLines());

            return $this;
        }

        if ($restore_if_not_found) {
            return $this->restore($restore_path);
        }

        return $this;
    }

    /**
     * Reset content for editor
     *
     * @return void
     */
    public function resetContent()
    {
        $this->file_path = null;
        $this->reader->load(null);
        $this->writer->setBuffer(null);
    }

    /**
     * Save buffer to file
     *
     * @return ConfigEditor
     */
    public function save()
    {
        if (File::isFile($this->file_path)) {
            $this->backup();
        }

        $this->writer->save($this->file_path);

        return $this;
    }

    /**
     * Create a backup of the config file
     *
     * @return ConfigEditor
     */
    public function backup()
    {
        if (!File::isFile($this->file_path)) {
            throw new FileNotFoundException("File does not exist at path {$this->file_path}");

            return false;
        }

        // Make sure the backup directory exists
        $this->createBackupFolder();

        File::copy($this->file_path, $this->backup_path . self::BACKUP_FILENAME_PREFIX . date('Y_m_d_His'));

        return $this;
    }

    /**
     * Update an existing key and/or value
     *
     * @param string $dot_syntax
     * @param string|null $new_key
     * @param string|null $new_value
     * @param boolean $force_quotes
     * @return ConfigEditor
     */
    public function updateKey(string $dot_syntax, string $new_key=null, string $new_value=null, $force_quotes=false)
    {
        $line = $this->reader->getKey($dot_syntax, $this->writer->buffer);
        if (!$line) {
            throw new KeyNotFoundException('Unable to update key '.$dot_syntax.'. Does it exist?');
        }

        $this->writer->updateSetter($line, $new_key, $new_value, $force_quotes);

        return $this;
    }

    /**
     * Add a new key and value
     *
     * @param string $dot_syntax
     * @param string|array $value
     * @param boolean $force_quotes
     * @param string|null $after
     * @return ConfigEditor
     */
    public function addKey(string $dot_syntax, $value, bool $force_quotes=false, $after=null)
    {
        $line = $this->reader->getKey($dot_syntax, $this->writer->buffer);
        if ($line) {
            throw new Exception('The key already exists at '.$dot_syntax);
        }

        $insert_index = $this->reader->getInsertIndex($dot_syntax, $this->writer->getBuffer(), $after);

        $dot_syntax_array = explode('.', $dot_syntax);
        $key = array_pop($dot_syntax_array);

        $this->writer->appendSetter($key, $value, $insert_index, $force_quotes, $after);

        return $this;
    }

    /**
     * Checks the buffer content to make sure it can locate a given key
     *
     * @param string $dot_syntax
     * @return boolean
     */
    public function hasKey(string $dot_syntax): bool
    {
        $line = $this->reader->getKey($dot_syntax, $this->getBuffer());
        return is_array($line);
    }

    /**
     * Checks the buffer content and locates value based on a given key
     *
     * @param string $dot_syntax
     * @return string|null
     */
    public function getValue(string $dot_syntax)
    {
        $line = $this->reader->getKey($dot_syntax, $this->getBuffer());
        return $line['data']['value'];
    }

}
