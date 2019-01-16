<?php

define('ENVIRONMENT', 'HOME'); //Set this to PRODUCTION when the website goes live
//define('ENVIRONMENT', 'TESTING'); //Set this to PRODUCTION when the website goes live
//define('ENVIRONMENT', 'PRODUCTION');

////TODO: Make Debug Class
//// Call this at each point of interest, passing a descriptive string
//function prof_flag($str)
//{
//    global $prof_timing, $prof_names, $prof_memory;
//    $prof_timing[] = microtime(true);
//    $prof_names[] = $str;
//
//    $mem_usage = memory_get_usage(true);
//    if ($mem_usage < 1024)
//        $prof_memory[] = $mem_usage." bytes";
//    elseif ($mem_usage < 1048576)
//        $prof_memory[] = round($mem_usage/1024,2)." kilobytes";
//    else
//        $prof_memory[] = round($mem_usage/1048576,2)." megabytes";
//}
//
//// Call this when you're done and want to see the results
//function prof_print()
//{
//    global $prof_timing, $prof_names, $prof_memory;
//    $size = count($prof_timing);
//    for($i=0;$i<$size - 1; $i++)
//    {
//        echo "<b>{$prof_names[$i]}</b><br>";
//        echo sprintf("&nbsp;&nbsp;&nbsp;%f<br>", $prof_timing[$i+1]-$prof_timing[$i]);
//        echo 'Memory allocated: ' . $prof_memory[$i] . '<br><br><br>';
//    }
//    echo "<b>{$prof_names[$size-1]}</b><br><br><br>";
//}

function debug($data, $trace = true) {
    if($trace)
        { echo '<pre>'; var_dump(debug_backtrace()); echo '</pre>'; }
    echo '<pre>'; var_dump($data); echo '</pre>';
}

function csrf($asInputField = true) {
    if($asInputField === false)
        return \Session::read(Config::get('security.csrf_token_key', 'csrf_token'));
    $html = '<input type="hidden" name="'
        .Config::get('security.csrf_token_key', 'csrf_token')
        .'" value="' . \Session::read(Config::get('security.csrf_token_key', 'csrf_token')) . '">';

    return $html;
}

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', realpath(__DIR__ . DS . '..') . DS);
define('PKGPATH', ROOT . 'packages' . DS);
define('CORE', ROOT . 'core' . DS);
define('APP', ROOT . 'app' . DS);

require(CORE . 'classes/Config.php');
require(CORE . 'classes/Autoloader.php');

class_alias('\\Soup\\Core\\Config', 'Config');

\Soup\Core\Autoloader::register();

$request = new Request();

if($mod = $request->moduleRequested()) {
    define('MODPATH', $mod . DS);

    $modName = explode(DS, $mod);
    $modName = array_pop($modName);
    define('LOADEDMOD', ucfirst($modName));

    \Soup\Core\Autoloader::setUserPath($mod);
    \Soup\Core\Autoloader::addNamespace(LOADEDMOD . '\\Controller', MODPATH . DS . 'classes' . DS . 'controllers');
    \Soup\Core\Autoloader::addNamespace(LOADEDMOD . '\\Model', MODPATH . DS . 'classes' . DS . 'models', MODPATH);

    \Config::setUserPath(MODPATH);
} else
    \Config::setUserPath(APP);

\Config::flush();

foreach (\Config::get('core.always_load.packages', []) as $pkg)
    \Package::load($pkg);

\Asset::init();
\Security::init();

$response = $request->execute();
$response->send();

