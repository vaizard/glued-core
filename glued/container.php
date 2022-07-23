<?php
declare(strict_types=1);

use Alcohol\ISO4217;
use Casbin\Enforcer;
use Casbin\Util\BuiltinOperations;
use DI\Container;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Glued\Lib\Auth;
use Glued\Lib\Exceptions\InternalException;
use Glued\Lib\Utils;
use Goutte\Client;
use Grasmash\YamlExpander\YamlExpander;
use GuzzleHttp\Client as Guzzle;
use Http\Discovery\Psr17FactoryDiscovery;
use Keiko\Uuid\Shortener\Dictionary;
use Keiko\Uuid\Shortener\Shortener;
use Keycloak\Admin\KeycloakClient;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nyholm\Psr7\getParsedBody;
use Opis\JsonSchema\Validator;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Log\NullLogger;
use Sabre\Event\Emitter;
use Selective\Transformer\ArrayTransformer;
use Symfony\Component\Yaml\Yaml;
use voku\helper\AntiXSS;

/** @noinspection PhpUndefinedVariableInspection */
$container->set('events', function () {
    return new Emitter();
});

$container->set('fscache', function () {
    try {
        $path = $_ENV['datapath'] . '/' . basename(__ROOT__) . '/cache/psr16';
        CacheManager::setDefaultConfig(new ConfigurationOption([
            "path" => $path,
            "itemDetailedDate" => false,
        ]));
        return new Psr16Adapter('files');
    } catch (Exception $e) {
        throw new InternalException($e, "Path not writable - rerun composer configure", $e->getCode());
    }
});

$container->set('memcache', function () {
    CacheManager::setDefaultConfig(new ConfigurationOption([
        "defaultTtl" => 60,
    ]));
    return new Psr16Adapter('apcu');
});

$container->set('settings', function () {
    // Initialize
    $class_sy = new Yaml;
    $class_ye = new YamlExpander(new NullLogger());
    $ret      = [];
    $routes   = [];
    $seed     = [
        'hostname' => $_SERVER['SERVER_NAME'] ?? gethostbyname(php_uname('n')),
        'rootpath' => __ROOT__,
        'uservice' => basename(__ROOT__)
    ];

    // Load and parse the yaml configs. Replace yaml references with $_ENV and $seed ($_ENV has precedence)
    $files = __ROOT__ . '/glued/Config/defaults.yaml';
    $yaml = file_get_contents($files);
    $array = $class_sy->parse($yaml, $class_sy::PARSE_CONSTANT);
    $refs['env'] = array_merge($seed, $_ENV);
    $ret = $class_ye->expandArrayProperties($array, $refs);

    // Read the routes
    $files = glob($ret['glued']['datapath'] . '/*/cache/routes.yaml');
    foreach ($files as $file) {
        $yaml = file_get_contents($file);
        $array = $class_sy->parse($yaml);
        $routes = array_merge($routes, $class_ye->expandArrayProperties($array)['routes']);
    }

    $ret['routes'] = $routes;
    return $ret;
});

$container->set('logger', function (Container $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Logger($settings['name']);
    $processor = new UidProcessor();
    $logger->pushProcessor($processor);
    $handler = new StreamHandler($settings['path'], $settings['level']);
    $logger->pushHandler($handler);
    return $logger;
});

$container->set('mysqli', function (Container $c) {
    $db = $c->get('settings')['db'];
    $mysqli = new mysqli($db['host'], $db['username'], $db['password'], $db['database']);
    $mysqli->set_charset($db['charset']);
    $mysqli->query("SET collation_connection = " . $db['collation']);
    return $mysqli;
});

$container->set('db', function (Container $c) {
    $mysqli = $c->get('mysqli');
    $db = new MysqliDb($mysqli);
    return $db;
});

$container->set('transform', function () {
    return new ArrayTransformer();
});

$container->set('uuid_base62', function () {
    $shortener = Shortener::make(
        Dictionary::createAlphanumeric() // or pass your own characters set
    );
    return $shortener;
});

$container->set('uuid_base57', function () {
    $shortener = Shortener::make(
        Dictionary::createUnmistakable() // or pass your own characters set
    );
    return $shortener;
});

$container->set('antixss', function () {
    return new AntiXSS();
});

$container->set('goutte', function () {
    return new Goutte\Client();
});

$container->set('jsonvalidator', function () {
    return new Validator;
});

/** @noinspection PhpUndefinedVariableInspection */
$container->set('routecollector', $app->getRouteCollector());
$container->set('responsefactory', $app->getResponseFactory());

/**
 * Casbin enforcer
 */
$container->set('enforcer', function (Container $c) {
    $s = $c->get('settings');
    $adapter = __ROOT__ . '/private/cache/casbin.csv';
    /*
    if ($s['casbin']['adapter'] == 'database')
        $adapter = DatabaseAdapter::newAdapter([
            'type'     => 'mysql',
            'hostname' => $s['db']['host'],
            'database' => $s['db']['database'],
            'username' => $s['db']['username'],
            'password' => $s['db']['password'],
            'hostport' => '3306',
        ]);*/
    $e = new Enforcer($s['casbin']['modelconf'], $adapter);

    $e->addNamedMatchingFunc('g', 'keyMatch2', function (string $key1, string $key2) {
        return BuiltinOperations::keyMatch2($key1, $key2);
    });
    $e->addNamedDomainMatchingFunc('g', 'keyMatch2', function (string $key1, string $key2) {
        return BuiltinOperations::keyMatch2($key1, $key2);
    });
    return $e;
});

$container->set('oidc_adm', function (Container $c) {
    $s = $c->get('settings')['oidc'];
    $client = KeycloakClient::factory([
        'baseUri'   => $s['server'],
        'realm'     => $s['realm'],
        'client_id' => $s['client']['admin']['id'],
        'username'  => $s['client']['admin']['user'],
        'password'  => $s['client']['admin']['pass']
    ]);
    return $client;
});

$container->set('oidc_cli', function (Container $c) {
    $s = $c->get('settings')['oidc'];
    $issuer = (new IssuerBuilder())->build($s['uri']['discovery']);
    $clientMetadata = ClientMetadata::fromArray([
        'client_id'     => $s['client']['confidential']['id'],
        'client_secret' => $s['client']['confidential']['secret'],
        'token_endpoint_auth_method' => 'client_secret_basic', // the auth method to the token endpoint
        'redirect_uris' => $s['uri']['redirect']
    ]);
    $client = (new ClientBuilder())
        ->setIssuer($issuer)
        ->setClientMetadata($clientMetadata)
        ->build();
    return $client;
});

$container->set('oidc_svc', function (Container $c) {
    $s = $c->get('settings')['oidc'];
    $service = (new AuthorizationServiceBuilder())->build();
    return $service;
});

$container->set('iso4217', function () {
    return new Alcohol\ISO4217();
});

$container->set('mailer', function (Container $c) {
    $smtp = $c->get('settings')['smtp'];
    $transport = (new Swift_SmtpTransport($smtp['addr'], $smtp['port'], $smtp['encr']))
        ->setUsername($smtp['user'])
        ->setPassword($smtp['pass'])
        ->setStreamOptions(array('ssl' => array('allow_self_signed' => true, 'verify_peer' => false)));
    $mailer = new Swift_Mailer($transport);
    $mailLogger = new Swift_Plugins_Loggers_ArrayLogger();
    $mailer->registerPlugin(new Swift_Plugins_LoggerPlugin($mailLogger));
    return $mailer;
});


// *************************************************
// GLUED CLASSES ***********************************
// ************************************************* 

$container->set('auth', function (Container $c) {
    return new Auth($c->get('settings'), 
                    $c->get('db'), 
                    $c->get('logger'), 
                    $c->get('events'),
                    $c->get('enforcer'),
                    $c->get('fscache'),
                    $c->get('utils')
                );
});

$container->set('utils', function (Container $c) {
    return new Utils($c->get('db'), $c->get('settings'), $c->get('routecollector'));
});

/*
$container->set('stor', function (Container $c) {
    return new Stor($c->get('db'));
});
*/

$container->set('crypto', function () {
    return new Glued\Classes\Crypto\Crypto();
});

$container->set('reqfactory', function () {
    return Psr17FactoryDiscovery::findUriFactory();
});

$container->set('urifactory', function () {
    return Psr17FactoryDiscovery::findRequestFactory();
});

$container->set('guzzle', function () {
    return new Guzzle();
});
