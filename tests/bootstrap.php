<?php
/**
 * PHPUnit bootstrap file
 */

// Include the composer autoloader
$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';

// Make sure the Shopware6Client\Tests namespace is loaded
$autoloader->addPsr4('Shopware6Client\\Tests\\', __DIR__);

// Check if autoloading works
if (!class_exists('Shopware6Client\Shopware6ApiClient')) {
    echo "Error: The Shopware6Client\\Shopware6ApiClient class could not be loaded!\n";
    echo "Check your autoloading configuration in composer.json\n";
    exit(1);
}

echo "PHPUnit bootstrap completed successfully.\n";