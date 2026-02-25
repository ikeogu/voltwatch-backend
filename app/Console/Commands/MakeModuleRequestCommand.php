<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\RequestMakeCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class MakeModuleRequestCommand extends RequestMakeCommand
{
    protected $name = 'make:request';
    protected $description = 'Create a new form request class inside a module';

    protected function getOptions()
    {
        return array_merge(
            parent::getOptions(),
            [
                ['module', 'mod', InputOption::VALUE_OPTIONAL, 'Create the request in the specified module']
            ]
        );
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        if ($this->option('module')) {
            $module = Str::studly($this->option('module'));
            return $rootNamespace . '\Modules\\' . $module . '\Requests';
        }

        return parent::getDefaultNamespace($rootNamespace);
    }

    protected function getPath($name)
    {
        if ($this->option('module')) {
            $module = Str::studly($this->option('module'));
            $name = Str::replaceFirst($this->rootNamespace(), '', $name);
            $name = Str::replaceFirst('Modules\\' . $module . '\\', '', $name);

            return $this->laravel['path'] . '/Modules/' . $module . '/' . str_replace('\\', '/', $name) . '.php';
        }

        return parent::getPath($name);
    }
}
