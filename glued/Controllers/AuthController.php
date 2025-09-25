<?php

namespace Glued\Controllers;

use Glued\Lib\Sql;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\Transformer\ArrayTransformer;
use Casbin\Enforcer;
use CasbinAdapter\Database\Adapter as DatabaseAdapter;
use Glued\Lib\Classes\Bearer\JWT;
use Glued\Lib\Classes\Bearer\PAT;
use Psr\Container\ContainerInterface;


/**
 * Handles edge cases (exceptions, advesre events).n
 */
class AuthController extends ServiceController
{
    protected $jwt;
    /**
     * @var mixed|void
     */

    protected $pat;
    /**
     * @var mixed|void
     */

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->jwt = new JWT($this->settings['oidc'], $this->apcuCache, $this->pg, $this->utils);
        $this->pat = new PAT($this->settings, $this->apcuCache, $this->pg, $this->logger);
    }

    public function postUsers(Request $request, Response $response, array $args = []): Response
    {
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $doc = $this->getValidatedRequestBody($request, $response);
        $db = new Sql($this->pg, 'core_users');
        //$this->auditAuthor($db, $request->getHeader('X-glued-auth-uuid')[0]);
        $res = $db->create((array)$doc, true, true);
        return $response->withJson($res);
    }

    public function getUsers(Request $request, Response $response, array $args = []): Response
    {
        $db = new Sql($this->pg, 'core_users');
        $res = $db->getAll();
        return $response->withJson($res);
    }

    public function getUser(Request $request, Response $response, array $args = []): Response
    {
        if (empty($args['uuid'])) { throw new \Exception('User not found', 404); }
        $db = new Sql($this->pg, 'core_users');
        $res = $db->get($args['uuid']);
        if (!$res) { throw new \Exception('User not found', 404); }
        return $response->withJson($res);
    }

    public function patchUser(Request $request, Response $response, array $args = []): Response
    {
        if (empty($args['uuid'])) { throw new \Exception('User not found', 404); }
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $db = new Sql($this->pg, 'core_users');
        $patch = (object) $this->getValidatedRequestBody($request, $response);
        $new = $db->patch($args['uuid'], $patch);
        return $response->withJson($new);
    }

    public function postPats(Request $request, Response $response, array $args = []): Response
    {
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $doc = $this->getValidatedRequestBody($request, $response);
        $db = new Sql($this->pg, 'core_pats');
        //$this->auditAuthor($db, $request->getHeader('X-glued-auth-uuid')[0]);
        $res = $db->create((array)$doc, true, true);
        return $response->withJson($res);
    }

    public function getPats(Request $request, Response $response, array $args = []): Response
    {
        $db = new Sql($this->pg, 'core_pats_ext');
        $res = $db->getAll();
        $tokenPrefix = $this->settings['glued']['patprefix'] ?? '';
        array_walk($res, fn(&$r) => $r['tokenPrefix'] = $tokenPrefix );
        return $response->withJson($res);
    }

    public function getPat(Request $request, Response $response, array $args = []): Response
    {
        if (empty($args['uuid'])) { throw new \Exception('PAT (Personal Access Token) not found', 404); }
        $db = new Sql($this->pg, 'core_pats');
        $res = $db->get($args['uuid']);
        if (!$res) { throw new \Exception('PAT (Personal Access Token) not found', 404); }
        return $response->withJson($res);
    }

    public function patchPat(Request $request, Response $response, array $args = []): Response
    {
        if (empty($args['uuid'])) { throw new \Exception('PAT (Personal Access Token) not found', 404); }
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $db = new Sql($this->pg, 'core_pats');
        $patch = (object) $this->getValidatedRequestBody($request, $response);
        $new = $db->patch($args['uuid'], $patch);
        return $response->withJson($new);
    }

    public function postRoles(Request $request, Response $response, array $args = []): Response
    {
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $doc = $this->getValidatedRequestBody($request, $response);
        $db = new Sql($this->pg, 'core_roles');
        //$this->auditAuthor($db, $request->getHeader('X-glued-auth-uuid')[0]);
        $res = $db->create((array)$doc, true, true);
        return $response->withJson($res);
    }

    public function getRoles(Request $request, Response $response, array $args = []): Response
    {
        $db = new Sql($this->pg, 'core_roles');
        $res = $db->getAll();
        return $response->withJson($res);
    }

    public function getRole(Request $request, Response $response, array $args = []): Response
    {
        if (empty($args['uuid'])) { throw new \Exception('Role not found', 404); }
        $db = new Sql($this->pg, 'core_roles');
        $res = $db->get($args['uuid']);
        if (!$res) { throw new \Exception('Role not found', 404); }
        return $response->withJson($res);
    }

    public function patchRole(Request $request, Response $response, array $args = []): Response
    {
        if (empty($args['uuid'])) { throw new \Exception('Role not found', 404); }
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $db = new Sql($this->pg, 'core_roles');
        $patch = (object) $this->getValidatedRequestBody($request, $response);
        $new = $db->patch($args['uuid'], $patch);
        return $response->withJson($new);
    }

    
    public function bootstrap(Request $request, Response $response, array $args = []): Response
    {
        // Instantiate casbin enforcer
        $config = [
            'type'     => 'pgsql',
            'hostname' => $this->settings['pgsql']['host'],
            'database' => $this->settings['pgsql']['database'],
            'username' => $this->settings['pgsql']['username'],
            'password' => $this->settings['pgsql']['password'],
            'hostport' => $this->settings['pgsql']['port'] ?? '5432',
        ];
        $adapter = DatabaseAdapter::newAdapter($config);
        $e = new Enforcer(__ROOT__ . '/glued/Config/Casbin/default.model', $adapter);

        // Get agent user (system agent user set up by database migrations, must be present)
        $db = new Sql($this->pg, 'core_users');
        $db->where('handle', '=', 'agent');
        $agentUuid = $db->getAll()[0]['uuid'];
        if (!$agentUuid) { throw new \Exception('Agent user UUID not found.', 503); }

        // Bootstrap domain hierarchy. Glued was initially designed with "multitenancy" in mind. This idea was surpassed
        // to avoid complexity, the RBAC multitenancy remains for now tho - it might find use to describe access rights
        // on other instances etc. Therefore, two "domains" are set up - universe (denoting everything) and deployment
        // which limits everything to the current deployment only.
        $e->addNamedGroupingPolicy('g2','universe','deployment');

        // Bootstrap user -> [role@domain] relation bindings. By default, the
        // - `agent` user is given membership to group `roots` in domain `universe`
        // - `agent` user is given membership to group `users` in domain `deployment`
        // - `anonymous` user is given membership to
        $e->addNamedGroupingPolicy('g',$agentUuid,'roots','universe');
        $e->addNamedGroupingPolicy('g',$agentUuid,'users','deployment');
        $e->addNamedGroupingPolicy('g','00000000-0000-0000-0000-000000000000','anonymous','deployment');

        // 3) Policies: role, domain, object-pattern, action
        $e->addPolicy('users','deployment','/*','read','allow');
        $e->addPolicy('anonymous','deployment','/api/core/:version/routes','read','deny');
        $e->addPolicy('anonymous','deployment','/api/core/:version/pats','read','deny');
        $e->addPolicy('anonymous','deployment','/*','read','allow');

        $e->savePolicy();

        return $response->withJson(['status' => $config]);
    }

    // TODO rework this
    /**
     * Return the authentication challenge response. Supported query parameters are `route` (mandatory)
     * and `user` (optional user UUID). If `user` is not set, `user` UUID will be deduced from the authentication
     * header. If the header is not set, a fallback zero-uuid will be used (anonymous user).
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */

    public function challenge(Request $request, Response $response, array $args = []): Response
    {
        $route = $request->getQueryParam('route') ?? false;
        $authenticated = true;
        $type = 'unknown';
        if (!$route) { throw new \Exception('Query parameter route mandatory. Try `?route=/`'); }

        try {
            $rawToken = $this->jwt->fetchToken($request); // Fetch Bearer token from Authorization header
            try {
                // Try to match the Bearer token against known PAT tokens
                $stored = $this->pat->matchToken($rawToken); // If succeeds, we're done
                $claims['sub'] = $stored['inherit']['uuid'];
                $type = 'pat';
            } catch (\Throwable $patEx) {
                // If the PAT check fails, attempt JWT logic
                $oidcConfiguration = $this->jwt->fetchOidcConfiguration();
                $oidcJwks = $this->jwt->fetchOidcJwks($oidcConfiguration['jwks_uri']);
                $oidcJwk = $this->jwt->processOidcJwks($oidcJwks);
                $this->jwt->parseToken($rawToken, $oidcJwk);
                $this->jwt->validateToken();
                $claims = $this->jwt->getJwtClaims();
                // Try to match the Bearer token against known JWT subjects
                $stored = $this->jwt->matchToken();
                $ex[] = $patEx->getMessage();
            }
            if (!$stored) { $authenticated = false; }
            else { $type = 'jwt'; }
        } catch (\Throwable $e) {
            // If fetchToken() or anything else blew up, fail
            $authenticated = false;
            $ex['message'] = $e->getMessage();
            //$ex['line'] = $e->getLine();
            //$ex['file'] = $e->getFile();
            //$ex['code'] = $e->getCode();
            //$ex['tr'] = $e->getTrace();
        }

        $user = $request->getQueryParam('impersonate') ?? $claims['sub'] ?? '00000000-0000-0000-0000-000000000000';
        $ret = [
            "route" => $route,
            "realUser" => $claims['sub'] ?? '00000000-0000-0000-0000-000000000000',
            "impersonatedUser" => $user,
            "authenticated" => $authenticated,
            "authorized" => null,
            "type" => $type,
            "rawToken" => $rawToken ?? '',
            "exception" => $ex ?? []
        ];


        $config = [
            'type'     => 'pgsql', // mysql,pgsql,sqlite,sqlsrv
            'hostname' => $this->settings['pgsql']['host'],
            'database' => $this->settings['pgsql']['database'],
            'username' => $this->settings['pgsql']['username'],
            'password' => $this->settings['pgsql']['password'],
            'hostport' => $this->settings['pgsql']['port'] ?? '5432',
        ];
        $adapter = DatabaseAdapter::newAdapter($config);
        $e = new Enforcer(__ROOT__ . '/glued/Config/Casbin/default.model', $adapter);


        // Register ABAC helpers
        $e->addFunction('explicitGrant', function($obj, $sub = null) {
            // $obj->private flag?
            $isPrivate = $obj->private ?? false;
            // if private but no subject passed → just “is private?”
            if ($sub === null) {
                return $isPrivate;
            }
            // if not private → we’re not doing subscriber checks here
            if (! $isPrivate) {
                return false;
            }
            // private → check subscriber list
            return in_array($sub, $obj->subscribers ?? [], true);
        });

        $e->addFunction('getPath', function ($obj) {
            return is_object($obj) && property_exists($obj, 'path')
                ? $obj->path
                : (string)$obj;
        });

        $routeObj = new \stdClass();
        $routeObj->path        = $ret['route'];    // e.g. "/api/corev/v2/routes"
        $routeObj->private     = false;            // or true, if it’s a private resource
        $routeObj->subscribers = ['55c2243f-735a-44be-b443-64b144280b56'];

        //$ret['authorized'] = $e->enforce('55c2243f-735a-44be-b443-64b144280b56','main','/api/core/v2/routes','read');


        //$user = '55c2243f-735a-44be-b443-64b144280b56';
        //$ret['route'];
        //$ret['authorized'] = $e->enforceWithMatcher('',$ret['impersonatedUser'],'deployment',$routeObj,'read');
        $ret['authorized'] = $e->enforceWithMatcher($matcher ?? '',$user,'deployment',$ret['route'],'read');
        //print_r($e);

/*
        try {
            $x = $e->enforceWithMatcher('', $user, 'deployment', $ret['route'], 'read');
        } catch (\Throwable $t) {
            print_r($t);
        }
        print_r($x);
        die();
*/
*/

        //$routeName = '';
        //$routeParsed = parse_url($route);
        //$routeNormalized = $routeParsed['path'] !== '/' ? rtrim($routeParsed['path'], '/') : $routeParsed['path'];


        //parse_str(isset($routeParsed
        //['query']) ? $routeParsed['query'] : '', $routeParams);
/*
        foreach ($this->settings['routes'] as $n=>$r) {
            if ($r['patte*rn'] === $routeNormalized) {
                $routeName = $n;
            }
        }

  */
        /*
        $enf = $this->enforcer;
        try {

            $a = $enf->addNamedGroupingPolicy('g2', ['c', (string) 'd']);
            $b = $enf->savePolicy();
        } catch (\Exception $e) {
            if ($e->getCode() == "23000" && get_class($e) == "PDOException") {}
            else throw new \Exception(previous: $e);
        }

        return $response->withJson([
            'auth-bearer' => $bearer,
            'auth-type' => $bearer_type,
            'auth-resp' => $who,
            'message' => 'Pass: '. $msg,
            'request' => $request->getMethod(),
            'route' => $route,
            'route-normalized' => $routeNormalized,
            'route-params' => $routeParams,
            'route-match' => $routeName,
            'user' => $user
        ]);*/

        return $response->withJson($ret);
    }


}

