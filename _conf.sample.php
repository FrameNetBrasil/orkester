<?php
return [
    'name' => 'App',
    'options' => [
        'app' => 'app',
        'debug' => true,
        'charset' => 'UTF-8',
        'timezone' => "America/Sao_Paulo",
        'separatorDate' => '/',
        'formatDate' => 'd/m/Y',
        'formatTimestamp' => 'd/m/Y H:i:s',
        'csv' => ';',
        'mode' => 'DEV',
        'dispatch' => 'index.php',
        'tmpPath' => sys_get_temp_dir(),
        'locale' => array("pt_BR.utf8", "ptb"), // linux: check installed locales - "locale -a"
        'fetchStyle' => \FETCH_ASSOC,
        'language' => 'en',
        'defaultPassword' => 'default',
        'pageTitle' => '',
        'mainTitle' => 'Title App',
        'idLanguage' => 2,
        'datasource' => 'app',
        'db' => 'app',
        'templatePath' => [
            '/var/www/html/app/UI/Templates/Page',
            '/var/www/html/app/UI/Templates/Controls',
            '/var/www/html/app/UI/Components',
        ],
        'painter' => 'app\ui\MPainter' // Maestro compatibility
    ],
    'login' => [
        'handler' => 'login', // or 'auth0'
        'AUTH0_CLIENT_ID' => '',
        'AUTH0_DOMAIN' => '',
        'AUTH0_ISSUER' => '',
        'AUTH0_AUDIENCE' => '',
        'AUTH0_CLIENT_SECRET' => '',
        'AUTH0_CALLBACK_URL' => '',
        'logout' => '',
        'class' => "Orkester\\Security\\MAuth",
        'check' => false
    ],
    'mailer' => [
        'smtpServer' => 'smtp.gmail.com',
        'smtpFrom' => 'x@gmail.com',
        'smtpFromName' => 'Project Name',
        'smtpTo' => '',
        'smtpAuth' => true,
        'smtpPass' => '',
        'smtpPort' => 587,
    ],
    'session' => [
        'handler' => "file",
        'timeout' => "30",
        'exception' => false,
        'check' => true,
        'dbsession' => false,
    ],
    'logs' => [
        'channel' => 'orkester',
        'path' => __DIR__ . '/../var/log',
        'level' => 2,
        'handler' => "socket", // for e.g. mtrace
        'peer' => isset($_SERVER['REMOTE_ADDR']) ? 'host.docker.internal' : 'localhost',
        //'strict' => '127.0.0.1',
        'port' => 0,
        'console' => 1,
        'errorCodes' => [
            E_ERROR,
            E_WARNING,
            E_PARSE,
            E_RECOVERABLE_ERROR,
            E_USER_ERROR,
            E_COMPILE_ERROR,
            E_CORE_ERROR
        ],
    ],
    'cache' => [
        'type' => "apcu", // php, java, apc, apcu, memcache
        'path' => __DIR__ . '/../var/cache',
        'memcache' => [
            'host' => "127.0.0.1",
            'port' => "11211",
            'default.ttl' => 0
        ],
        'apc' => [
            'default.ttl' => 0
        ]
    ],

];
