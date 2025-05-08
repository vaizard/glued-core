<?php

namespace Glued\Controllers;

use Glued\Lib\Sql;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\Transformer\ArrayTransformer;


/**
 * Handles edge cases (exceptions, advesre events).n
 */
class AuthController extends ServiceController
{

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
        $route = $request->getQueryParams()['route'] ?? false;
        if (!$route) { throw new \Exception('Query parameter route mandatory. Try `?route=/`'); }
        $who = null;
        $bearer = null;
        $user = null;
        $bearer_type = 'unknown';
        try {
            $bearer = $this->auth->fetch_token($request);
        } catch (\Exception $e) {
            $bearer_type = 'none';
            $msg = "Unauthenticated (bearer token missing).";
        }

        if (!is_null($bearer)) {
            try {
                $who = $this->auth->validate_api_token($bearer);
                $bearer_type = 'api';
                $msg = "Authenticated (API).";
                $user = $who['user_uuid'];
            } catch (\Exception $e) {
                $msg = $e->getMessage();
            }
        }

        if (!is_null($bearer) and ($bearer_type == 'unknown')) {
            try {
                $oidc = $this->settings['oidc'];
                $certs = $this->auth->get_jwks($oidc);
                $who = $this->auth->validate_jwt_token($bearer, $certs);
                $bearer_type = 'jwt';
                $msg = "Authenticated (JWT).";
                $user = $this->auth->getuser($who['claims']['sub']);
            } catch (\Exception $e) {
                $msg = $e->getMessage();
            }
        }

        $routeName = '';
        $routeParsed = parse_url($route);
        $routeNormalized = $routeParsed['path'] !== '/' ? rtrim($routeParsed['path'], '/') : $routeParsed['path'];


        parse_str(isset($routeParsed['query']) ? $routeParsed['query'] : '', $routeParams);

        foreach ($this->settings['routes'] as $n=>$r) {
            if ($r['pattern'] === $routeNormalized) {
                $routeName = $n;
            }
        }

        $enf = $this->enforcer;
        try {
            $enf->addNamedGroupingPolicy('g2', ['system', '*']); // domain 'system' is parent to domain(s) '*'
            $enf->addNamedGroupingPolicy('g2', ['system', '*']); //
            //$b = $enf->savePolicy();
            $a = $enf->addNamedGroupingPolicy('g2', ['b', 'c']);
            //$b = $enf->savePolicy();
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
        ]);
    }


}

