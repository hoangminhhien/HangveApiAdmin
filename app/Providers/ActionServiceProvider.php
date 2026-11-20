<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ActionServiceProvider extends ServiceProvider
{
    public function register()
    {
        $delimiter = "Actions";
        $mutualNameSpace = "App\\{$delimiter}\\";

        $directories = File::directories(app_path($delimiter));

        if (! count($directories)) {
            return;
        }

        // check với mục đích ngăn lỗi do directory path của Xampp/Laragon khác Vagrant/Valet/Docker/Unix-based OS
        // Xampp\Laragon: C::\xampp\htdocs\hangve-adminapi\App\Providers\ActionServiceProvider.php
        // Others: /var/www/hangve-adminapi/app/Providers/ActionServiceProvider.php
        $specificEnvDelimeter = str_contains($directories[0], '/') ? '/' : '\\';

        foreach ($directories as $directory) {
            $directoryName = explode("{$delimiter}{$specificEnvDelimeter}", $directory)[1];
            $namespace = "{$mutualNameSpace}{$directoryName}\\";

            $files = File::files($directory);

            foreach ($files as $file) {
                $fileClassName = $namespace . $file->getFilenameWithoutExtension();
                $this->app->bind($fileClassName, $fileClassName);
            }
        }
    }
}
