<?php

namespace App\FileEditors\ConfigEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The ConfigEditor facade.
 */
class ConfigEditor extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'config-editor';
    }
}
