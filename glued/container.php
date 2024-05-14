<?php
declare(strict_types=1);

use Casbin\Enforcer;
use Casbin\Util\BuiltinOperations;
use CasbinAdapter\Database\Adapter as DatabaseAdapter;
use DI\Container;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Glued\Lib\Auth;
use Glued\Lib\Utils;
use GuzzleHttp\Client as Guzzle;
use Keycloak\Admin\KeycloakClient;
use Sabre\Event\Emitter;

require_once(__ROOT__ . '/vendor/vaizard/glued-lib/src/Includes/container.php');

$container->set('events', function () {
    return new Emitter();
});


$container->set('db', function (Container $c) {
    $mysqli = $c->get('my');
    return new MysqliDb($mysqli);
});

$container->set('enforcer', function (Container $c) {
    $s = $c->get('settings');
    $logger = $c->get('logger');

    $s['casbin']['adapter'] = 'database';
    if ($s['casbin']['adapter'] == 'database') {
        $adapter = DatabaseAdapter::newAdapter([
            'type' => 'mysql',
            'hostname' => $s['mysql']['host'],
            'database' => $s['mysql']['database'],
            'username' => $s['mysql']['username'],
            'password' => $s['mysql']['password'],
            'hostport' => '3306',
        ]);
    } elseif ($s['casbin']['adapter'] == 'file') {
        $adapter = $s['glued']['datapath'] . '/glued-core/cache/casbin.csv';
        if (!is_writable($adapter)) { $logger->error('Enforcer adapter not writable.', [$adapter]); }
    } else {
        $logger->error('Enforcer adapter misconfigured.', [$s['casbin']['adapter']]);
    }

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
        'baseUri' => $s['server'],
        'realm' => $s['realm'],
        'client_id' => $s['client']['admin']['id'],
        'username' => $s['client']['admin']['user'],
        'password' => $s['client']['admin']['pass']
    ]);
    return $client;
});

$container->set('oidc_cli', function (Container $c) {
    $s = $c->get('settings')['oidc'];
    $issuer = (new IssuerBuilder())->build($s['uri']['discovery']);
    $clientMetadata = ClientMetadata::fromArray([
        'client_id' => $s['client']['confidential']['id'],
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
        $c->get('utils'),
        $c->get('crypto')
    );
});

$container->set('utils', function (Container $c) {
    return new Utils($c->get('settings'), $c->get('routecollector'));
});

/*
$container->set('stor', function (Container $c) {
    return new Stor($c->get('db'));
});
*/


$container->set('guzzle', function () {
    return new Guzzle();
});
