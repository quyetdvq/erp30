<?php

declare(strict_types=1);

$_SERVER['CI_ENVIRONMENT'] = 'testing';
defined('ENVIRONMENT') || define('ENVIRONMENT', 'testing');
defined('CI_DEBUG') || define('CI_DEBUG', true);

define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('HOMEPATH', ROOTPATH);
define('CONFIGPATH', APPPATH . 'Config' . DIRECTORY_SEPARATOR);
define('PUBLICPATH', FCPATH);
define('TESTPATH', ROOTPATH . 'tests' . DIRECTORY_SEPARATOR);
define('CIPATH', ROOTPATH . 'vendor/codeigniter4/framework' . DIRECTORY_SEPARATOR);
define('SUPPORTPATH', TESTPATH . '_support' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('VENDORPATH', ROOTPATH . 'vendor' . DIRECTORY_SEPARATOR);

require ROOTPATH . 'vendor/autoload.php';
require ROOTPATH . 'app/Config/Paths.php';

$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::bootTest($paths);
