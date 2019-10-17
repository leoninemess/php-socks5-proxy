<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitd506204fd2e5eafad02fe1e8547b104c
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Workerman\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Workerman\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/workerman',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitd506204fd2e5eafad02fe1e8547b104c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitd506204fd2e5eafad02fe1e8547b104c::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}