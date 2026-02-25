<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\NotificationMakeCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class MakeModuleNotificationCommand extends NotificationMakeCommand
{
    protected $name = 'make:notification';
    protected $description = 'Create a new notification class inside a module';

    protected function getOptions()
    {
        return array_merge(
            parent::getOptions(),
            [
                ['module', 'mod', InputOption::VALUE_OPTIONAL, 'Create the notification in the specified module']
            ]
        );
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        if ($this->option('module')) {
            $module = Str::studly($this->option('module'));
            return $rootNamespace . '\Modules\\' . $module . '\Notifications';
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
