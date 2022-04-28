<?php

namespace Tests\Feature;

use App\FileEditors\ConfigEditor\ConfigEditor;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConfigEditorTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        File::copy(base_path('config/database.php'), base_path('config/database-test.php'));
        Artisan::call('config:cache');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        File::delete(base_path('config/database-test.php'));
    }

    public function test_config_editor()
    {
        $this->setUp();

        $file_path = base_path('config\database-test.php');

        // The ConfigEditor can be instantiated properly
        $config_editor = new ConfigEditor;
        $this->assertDirectoryExists(base_path('storage/config-editor/backups/'));

        // Config file can be loaded
        $config_editor->load($file_path);
        $file_content = $config_editor->getContent();
        $this->assertIsString($file_content);
        $lines = $config_editor->getLines();
        $this->assertIsArray($lines);
        $this->assertIsArray($config_editor->getBuffer());

        // Editor content can be reset
        $config_editor->resetContent();
        $this->assertNull($config_editor->getContent());
        $this->assertNull($config_editor->getLines());
        $this->assertNull($config_editor->getBuffer());

        // A first-level key can be replaced
        $config_editor->load($file_path)->updateKey('connections', 'DB_CONNECTIONS');
        $this->assertTrue($config_editor->hasKey('DB_CONNECTIONS'));

        // A nested key can be replaced
        $config_editor->updateKey('DB_CONNECTIONS.atom.username', 'ATOM_USERNAME');
        $this->assertTrue($config_editor->hasKey('DB_CONNECTIONS.atom.ATOM_USERNAME'));

        // A first-level value can be replaced
        $config_editor->updateKey('default', null, 'ATOM', true);
        $this->assertSame('ATOM', $config_editor->getValue('default'));

        // A nested value can be replaced
        $config_editor->updateKey('DB_CONNECTIONS.atom.ATOM_USERNAME', null, 'GIGGITY', true);
        $this->assertSame('GIGGITY', $config_editor->getValue('DB_CONNECTIONS.atom.ATOM_USERNAME'));

        // A first level key and string value can be added
        $config_editor->addKey('NEW_STRING_KEY', 'NEW_STRING_VALUE', true);
        $this->assertTrue($config_editor->hasKey('NEW_STRING_KEY'));
        $this->assertSame('NEW_STRING_VALUE', $config_editor->getValue('NEW_STRING_KEY'));

        // A first level key and array value can be added
        $config_editor->addKey('NEW_ARRAY_KEY', [
            'subkey1' => 'subvalue1',
            'subkey2' => 'subvalue2',
            'subkey3' => 'subvalue3',
        ], true);
        $this->assertTrue($config_editor->hasKey('NEW_ARRAY_KEY'));

        // A nested key and string value can be added
        $config_editor->addKey('NEW_ARRAY_KEY.NEW_NESTED_KEY', 'NEW_NESTED_VALUE', true);
        $this->assertTrue($config_editor->hasKey('NEW_ARRAY_KEY.NEW_NESTED_KEY'));
        $this->assertSame('NEW_NESTED_VALUE', $config_editor->getValue('NEW_ARRAY_KEY.NEW_NESTED_KEY'));

        // A nested key and array value can be added
        $config_editor->addKey('DB_CONNECTIONS.sqlite.NEW_NESTED_KEY', [
            'subkey1' => 'subvalue1',
            'subkey2' => 'subvalue2',
            'subkey3' => 'subvalue3',
        ], true);
        $this->assertTrue($config_editor->hasKey('DB_CONNECTIONS.sqlite.NEW_NESTED_KEY'));

        // A first level key and string value can be added with "after" set to a key with a string value
        $config_editor->addKey('AFTER_MIGRATIONS_KEY', 'AFTER_MIGRATIONS_VALUE', true, 'migrations');
        $this->assertTrue($config_editor->hasKey('AFTER_MIGRATIONS_KEY'));
        $this->assertSame('AFTER_MIGRATIONS_VALUE', $config_editor->getValue('AFTER_MIGRATIONS_KEY'));

        // A first level key and string value can be added with "after" set to a key with an array value
        $config_editor->addKey('AFTER_REDIS_KEY', 'AFTER_REDIS_VALUE', true, 'redis');
        $this->assertTrue($config_editor->hasKey('AFTER_REDIS_KEY'));
        $this->assertSame('AFTER_REDIS_VALUE', $config_editor->getValue('AFTER_REDIS_KEY'));

        // A nested key and string value can be added with "after" set to a key with a string value
        $config_editor->addKey('DB_CONNECTIONS.sqlite.NEW_NESTED_KEY.AFTER_SUBKEY2', 'AFTER_SUBKEY2_VALUE', true, 'subkey2');
        $this->assertTrue($config_editor->hasKey('DB_CONNECTIONS.sqlite.NEW_NESTED_KEY.AFTER_SUBKEY2'));
        $this->assertSame('AFTER_SUBKEY2_VALUE', $config_editor->getValue('DB_CONNECTIONS.sqlite.NEW_NESTED_KEY.AFTER_SUBKEY2'));

        // A nested level key and string value can be added with "after" set to a key with an array value
        $config_editor->addKey('redis.AFTER_OPTIONS_KEY', 'AFTER_OPTIONS_VALUE', true, 'options');
        $this->assertTrue($config_editor->hasKey('redis.AFTER_OPTIONS_KEY'));
        $this->assertSame('AFTER_OPTIONS_VALUE', $config_editor->getValue('redis.AFTER_OPTIONS_KEY'));

        // A first level key and array value can be added with "after" set to a key with a string value
        $config_editor->addKey('AFTER_MIGRATIONS_KEY2', [
            'subkey1' => 'subvalue1',
            'subkey2' => 'subvalue2',
            'subkey3' => 'subvalue3',
        ], true, 'migrations');
        $this->assertTrue($config_editor->hasKey('AFTER_MIGRATIONS_KEY2'));

        // A first level key and array value can be added with "after" set to a key with an array value
        $config_editor->addKey('AFTER_REDIS_KEY2', [
            'subkey1' => 'subvalue1',
            'subkey2' => 'subvalue2',
            'subkey3' => 'subvalue3',
        ], true, 'redis');
        $this->assertTrue($config_editor->hasKey('AFTER_REDIS_KEY2'));

        // A nested key and array value can be added with "after" set to a key with a string value
        $config_editor->addKey('DB_CONNECTIONS.sqlite.NEW_NESTED_KEY.AFTER_SUBKEY2_AGAIN', [
            'subkey1' => 'subvalue1',
            'subkey2' => 'subvalue2',
            'subkey3' => 'subvalue3',
        ], true, 'subkey2');
        $this->assertTrue($config_editor->hasKey('DB_CONNECTIONS.sqlite.NEW_NESTED_KEY.AFTER_SUBKEY2_AGAIN'));

        // A nested level key and array value can be added with "after" set to a key with an array value
        $config_editor->addKey('redis.AFTER_OPTIONS_KEY2', [
            'subkey1' => 'subvalue1',
            'subkey2' => 'subvalue2',
            'subkey3' => 'subvalue3',
        ], true, 'options');
        $this->assertTrue($config_editor->hasKey('redis.AFTER_OPTIONS_KEY2'));

        // The buffer can be written to the config file
        $config_editor->save();
        $this->assertFileExists($file_path);

        // The file backups can be deleted
        $config_editor->deleteBackups();
    }

}
