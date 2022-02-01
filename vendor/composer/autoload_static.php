<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit643f4d2dbaa5e8f76649ac1d6d1673b3
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\Log\\' => 8,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
        ),
        'A' => 
        array (
            'Adyen\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
        'Adyen\\' => 
        array (
            0 => __DIR__ . '/..' . '/adyen/php-api-library/src/Adyen',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit643f4d2dbaa5e8f76649ac1d6d1673b3::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit643f4d2dbaa5e8f76649ac1d6d1673b3::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit643f4d2dbaa5e8f76649ac1d6d1673b3::$classMap;

        }, null, ClassLoader::class);
    }
}
