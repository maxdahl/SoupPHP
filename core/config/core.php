<?php

return [
    //actions have to be camelCase e.g. actionIndex or indexAction
    'action_prefix' => '',
    'action_suffix' => '',
    'base_directory' => '', //if your SoupPHP installation is not in the webroot, set the directory here
    'ajax_namespace' => '', //leave empty to handle ajax request not separately

    'core_namespaces' => [
        'Soup\\Core',
        'Soup\\DB',
        'Soup\\Helper',
        'Soup\\Exception',
    ],

    'class_paths' => [
        'Soup\\Core\\Asset' => CORE . 'classes',
        'Soup\\Core\\Autoloader' => CORE . 'classes',
        'Soup\\Core\\Controller' => CORE . 'classes',
        'Soup\\Core\\Input' => CORE . 'classes',
        'Soup\\Core\\Model' => CORE . 'classes',
        'Soup\\Core\\Module' => CORE . 'classes',
        'Soup\\Core\\Package' => CORE . 'classes',
        'Soup\\Core\\Request' => CORE . 'classes',
        'Soup\\Core\\Response' => CORE . 'classes',
        'Soup\\Core\\Router' => CORE . 'classes',
        'Soup\\Core\\Security' => CORE . 'classes',
        'Soup\\Core\\Session' => CORE . 'classes',
        'Soup\\Core\\Template' => CORE . 'classes',
        'Soup\\Core\\Uri' => CORE . 'classes',
        'Soup\\Core\\Validator' => CORE . 'classes',
        'Soup\\Core\\View' => CORE . 'classes',

        'Soup\\DB\\DB' => CORE . 'database',
        'Soup\\DB\\Query' => CORE . 'database',

        'Soup\\Helper\\Str' => CORE . 'helpers',

        'Soup\\Exception\\ValidationException' => CORE . 'classes' . DS . 'exceptions' . DS . 'Validation.php',
    ],

    'always_load' => [
        'packages' => [

        ],
    ]
];