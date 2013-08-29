<?php
/**
 * Analyses social media accounts and get values
 *
 * @copyright  Robert Deutz Business solution
 * @author Robert Deutz <rdeutz@googlemail.com>
 * @license    WTFPL
 */

// Max out error reporting.
//error_reporting(-1);
//ini_set('display_errors', 1);

if (file_exists('log.php')) unlink('log.php');

// this is a valid entry point
define('_JEXEC',1);

// include facebook sdk
require realpath(__DIR__ . '/../vendor/facebook/php-sdk/src/facebook.php');

// Bootstrap the Joomla Framework.
require realpath(__DIR__ . '/../vendor/autoload.php');

// Define required paths
$dir = explode('/', dirname(__DIR__));

define('JPATH_BASE', implode('/', $dir));
define('JPATH_CONFIGURATION', JPATH_BASE);
define('JPATH_ROOT',          JPATH_BASE);
define('JPATH_SITE',          JPATH_BASE);
define('JPATH_FRAMEWORK', 	  JPATH_BASE . 'vendor/joomla/framework/src');

try
{
	$app = new Getsocialdata\Application;
	$app->execute();
}
catch (Exception $e)
{
	// An exception has been caught, just echo the message.
	fwrite(STDOUT, $e->getMessage() . "\n");
	exit($e->getCode());
}