<?php
/**
 * PHPUnit bootstrap file
 */

// Include the composer autoloader
$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';

// Make sure the SchababerleDigital\Shopware6\ApiClient\Tests namespace is loaded
$autoloader->addPsr4('SchababerleDigital\\Shopware6\\ApiClient\\Tests\\', __DIR__);

// Check if autoloading works
if (!class_exists('SchababerleDigital\Shopware6\ApiClient\Shopware6ApiClient')) {
    echo "Error: The SchababerleDigital\\Shopware6\\ApiClient\\Shopware6ApiClient class could not be loaded!\n";
    echo "Check your autoloading configuration in composer.json\n";
    exit(1);
}

echo "PHPUnit bootstrap completed successfully.\n";