<?php

namespace App\Console\Commands;


use Illuminate\Routing\Console\ControllerMakeCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class MakeModuleControllerCommand extends ControllerMakeCommand
{
    protected $name = 'make:controller';
    protected $description = 'Create a new controller class inside the specified module';

    protected function getOptions()
    {
        return array_merge(
            parent::getOptions(),
            [
                ['module', 'mod', InputOption::VALUE_OPTIONAL, 'Create a controller in the specified module']
            ]
        );
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        if ($this->option('module')) {
            $module = Str::studly($this->option('module'));
            return $rootNamespace . '\Modules\\' . $module . '\Controllers';
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

    protected function getStub()
    {
        if ($this->option('module')) {
            return base_path('stubs/controller.module.stub');
        }

        return parent::getStub();
    }
}
