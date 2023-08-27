<?php
declare(strict_types=1);
namespace Glued\Controllers;

use Glued\Lib\QuerySelect;
use mysql_xdevapi\Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends AbstractController
{

    /**
    * Provides an authentication and authorization endpoint for the nginx auth subrequest.
    * Enforces according to $_SERVER['HTTP_X_ORIGINAL_URI']
    * @param  Request  $request
    * @param  Response $response
    * @param  array    $args
    * @return Response with a 200 or 403 code (allow/deny). Additional response headers
    *                  X_GLUED_AUTH_UUID and X_GLUED_MESSAGE can be set as well.
    */






    public function enforce(Request $request, Response $response, array $args = []): Response
    {

        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // Log uri and method
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $this->logger->info("auth.enforce: start");
        $u = array_key_exists('HTTP_X_ORIGINAL_URI', $_SERVER) ? $_SERVER['HTTP_X_ORIGINAL_URI'] : 'undefined';
        $m = array_key_exists('HTTP_X_ORIGINAL_METHOD', $_SERVER) ? $_SERVER['HTTP_X_ORIGINAL_METHOD'] : 'undefined';
        $this->logger->debug("auth.enforce: orig", [
            "HTTP_X_ORIGINAL_URI" => $u,
            "HTTP_X_ORIGINAL_METHOD" => $m
        ]);


        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // Handle direct access / server misconfiguration
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        // This method's route must be private, available exclusively to nginx for internal auth subrequests. To
        // authorize a request to a resource nginx must be configured to perform a subrequest to this method with the
        // resource url passed in the HTTP_X_ORIGINAL_URI header. If HTTP_X_ORIGINAL_URI is not set, then nginx is
        // misconfigured or the client managed to access the private-only route (without nginx setting the
        // HTTP_X_ORIGINAL_URI header. In both cases, we just return a 403 response to achieve a `denied unless
        // explicitly allowed` behavior.

        if ((!array_key_exists('HTTP_X_ORIGINAL_URI', $_SERVER)) or (!array_key_exists('HTTP_X_ORIGINAL_METHOD', $_SERVER))) {
            $this->logger->error("auth.enforce: direct access to internal api.");
            return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X_GLUED_MESSAGE', 'Auth backend is mad.');
        }

        // Skip authorization on all OPTIONS requests (return a 200 response to achieve an `allow always` behavior).
        if ($_SERVER['HTTP_X_ORIGINAL_METHOD'] === 'OPTIONS') {
            $this->logger->debug("auth.enforce: pass (options)");
            return $response->withStatus(200)->withHeader('Content-Length', 0);
        }


        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // Authenticate
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        // Authentication tests the validity of the access token provided by the identity server. Token subject (user)
        // is used to query t_core_users for additional user account data. If t_core_users doesn't have a match,
        // subject's uuid is used to set up a valid user account.

        try {

            $token = $this->auth->validate_jwt_token(
                accesstoken: $this->auth->fetch_token($request),
                certs: $this->auth->get_jwks($this->settings['oidc'])
            );
            $this->logger->debug("auth.enforce: jwt", [
                "ALL" => $token,
                "SUB" => $token['claims']['sub']
            ]);
            $user = $this->auth->getuser($token['claims']['sub']);
            $this->logger->debug("auth.enforce: db", [ "USER" => $user ]);
            if ($user === false) {
                $this->logger->info( 'auth.enforce: adduser', [ "UUID" => $token['claims']['sub'] ]);
                // TODO consider updating user info from id server - on login only?, cron job?
                $this->auth->adduser($token['claims']);
            }

        } catch (\Exception $e) {
            $this->logger->error( 'auth.enforce authentication failed', [ $e->getFile(), $e->getLine(), $e->getMessage(), $this->db->getLastQuery() ]);
            //return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X_GLUED_MESSAGE', $e->getMessage());
        }

        $this->logger->error( 'auth.enforce authenticated as', [ "X-GLUED-AUTH-UUID" => $token['claims']['sub'] ?? 'anonymous' ]);


        // Authorization: provide hardcoded responses for test routes
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_pass_v1']['path']) {
            $this->logger->debug("auth.enforce hardcoded pass", [ "HTTP_X_ORIGINAL_METHOD" => $_SERVER['HTTP_X_ORIGINAL_METHOD'], "X_GLUED_AUTH_UUID" => $token['claims']['sub'] ?? 'anonymous' ]);
            //return $response->withStatus(200)->withHeader('Content-Length', 0)->withHeader('X-GLUED-AUTH-UUID', $token['claims']['sub'] ?? 'anonymous');
        }
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_fail_v1']['path']) {
            $this->logger->debug("auth.enforce hardcoded fail", [ "HTTP_X_ORIGINAL_METHOD" => $_SERVER['HTTP_X_ORIGINAL_METHOD'], "X_GLUED_AUTH_UUID" => $token['claims']['sub'] ?? 'anonymous' ]);
            return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X-GLUED-AUTH-UUID', $token['claims']['sub'] ?? 'anonymous');
        }

        // Authorization
        // TODO add CASBIN authorization code here
        // For now allow all.
        // TODO delete allow all when authorization code in place
        return $response->withStatus(200)->withHeader('Content-Length', 0)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub'] ?? 'anonymous')->withHeader('X_GLUED_AUTH_MSG', 'DEV CODE. DO NOT USE IN PRODUCTION.');

        // Fallback authorization response: DENY
        return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub'] ?? 'anonymous');
    }


    /**
     * Return the authentication status'. The AuthController::enforce() has a hardcoded
     * positive (200) response for the route with AuthController::say_pass() method.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function say_status(Request $request, Response $response, array $args = []): Response
    {
    }


   /**
     * Always returns 'pass'. The AuthController::enforce() has a hardcoded 
     * positive (200) response for the route with AuthController::say_pass() method.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function say_pass(Request $request, Response $response, array $args = []): Response {
        $route = $request->getQueryParams()['route'] ?? '';
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
        $routeNormalized = rtrim($routeParsed['path'],'/');
        parse_str(isset($routeParsed['query']) ? $routeParsed['query'] : '', $routeParams);

        foreach ($this->settings['routes'] as $n=>$r) {
            if ($r['path'] === $routeNormalized) {
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

   /**
     * Always returns 'fail'. The AuthController::enforce() has a hardcoded 
     * negative (403) response for the route with AuthController::say_fail() method.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function say_fail(Request $request, Response $response, array $args = []): Response {
        return $response->withJson([
            'message' => 'Fail: you should never see this message, a 403 error page should emit.',
            'request' => $request->getMethod()
        ]);
    }


    /**
     * Returns all users (administrative endpoint).
     * TODO minimize the number of authorized users able to see this endpoint.
     * TODO provide a "list users" endpoint meant for regular users
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function users_r0(Request $request, Response $response, array $args = []): Response {
        $rp = $request->getQueryParams();
        $qp = null;
        $qs = <<<EOT
        select 
                json_merge_preserve(
                    json_object("uuid", bin_to_uuid( `c_uuid`, true)),
                    json_object("handle",`c_handle`),
                    json_object("profile", `c_profile`),
                    json_object("attr",`c_attr`),
                    json_object("created",`c_ts_created`),
                    json_object("modified",`c_ts_updated`)
                ) as res_rows
        from `t_core_users`
        EOT;
        $qs = (new \Glued\Lib\QueryBuilder())->select($qs);
        $wm = [
            'handle' => '`c_handle` like ?'
        ];

        $this->utils->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        echo vsprintf(str_replace('?', "'%s'", $qs), $qp);
        return $response;
        $res = $this->db->rawQuery($qs, $qp);
        if (true) { $res['debug']['query'] = $this->db->getLastQuery(); }
        return $this->utils->mysqlJsonResponse($response, $res);
        /*return $response->withJson($this->auth->users());*/
    }


    function mysqlJsonQueryFromRequest(array $reqparams, QuerySelect &$qstring, &$qparams, array $wheremods = []) {

        // define fallback where modifier for the 'uuid' reqparam.
        if (!array_key_exists('uuid', $wheremods)) {
            $wheremods['uuid'] = 'c_uuid = uuid_to_bin( ? , true)';
        }

        foreach ($reqparams as $key => $val) {

            // if request parameter name ($key) doesn't validate, skip to next
            // foreach item, else replace _ with . in $key to get a valid jsonpath
            if ($this->reqParamToJsonPath($key) === false) { continue; }

            // to correctly construct the jsonpath, independent on the $key
            // containing a hypen or not, each $key must be encapsulated with quotes
            // 'some_hypen-path' -> 'some.hypen-path' -> '"some"."hypen-path"'
            $jsonpath = '\"'.str_replace('.', '\".\"', $key).'\"';
            // default where construct that transposes https://server/endpoint?mykey=myval
            // to sql query substring `where (`c_data`->>'$."mykey"' = ?)`
            $w = '(`c_data`->>"$.'.$jsonpath.'" = ?)';

            foreach ($wheremods as $wmk => $wmv) {
                if ($key === $wmk) { $w = $wmv; }
            }

            if (is_array($val)) {
                foreach ($val as $v) {
                    $qstring->where($w);
                    $qparams[] = $v;
                }
            } else {
                $qstring->where($w);
                $qparams[] = $val;
            }
        }

        // envelope in json_arrayagg to return a single row with the complete result
        $qstring = "select json_arrayagg(res_rows) from ( $qstring ) as res_json";
    }


    public function users_r1(Request $request, Response $response, array $args = []): Response {
        $rp = $request->getQueryParams();
        $qp = null;
        $qs = <<<EOT
        select 
                json_merge_preserve(
                    json_object("uuid", bin_to_uuid( `c_uuid`, true)),
                    json_object("handle",`c_handle`),
                    json_object("profile", `c_profile`),
                    json_object("attr",`c_attr`),
                    json_object("created",`c_ts_created`),
                    json_object("updated",`c_ts_updated`)
                ) as res_rows
        from `t_core_users`
        EOT;
        $qs = (new \Glued\Lib\QueryBuilder())->select($qs);
        $wm = [
            'handle' => '`c_handle` like ?'
        ];

        $this->utils->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        //echo vsprintf(str_replace('?', "'%s'", $qs), $qp);
        //return $response;
        $res = $this->db->rawQuery($qs, $qp);
        if (true) { $res['debug']['query'] = $this->db->getLastQuery(); }
        return $this->utils->mysqlJsonResponse($response, $res);
        /*return $response->withJson($this->auth->users());*/
    }

    // TODO users_c1() hardcode some basic security here in case rbac rules fail to exist
    public function users_c1(Request $request, Response $response, array $args = []): Response
    {
        $rp = $request->getQueryParams();
        // required params
        foreach (['handle', 'email', 'sub'] as $key) {
            if (!array_key_exists($key, $rp)) { throw new \Exception($key . ' is required.'); }
            if ($rp[$key] == '') { throw new \Exception($key . ' must not be empty.'); }
            if ($key == 'sub') {
                    $uuid = \Ramsey\Uuid\Uuid::fromString($rp[$key]);
                    if ($uuid->getVersion() !== \Ramsey\Uuid\Uuid::UUID_TYPE_RANDOM) { throw new \Exception('Only UUIDv4 is supported for `sub`.'); }
            }
        }
        // whitelist
        foreach (['handle', 'email', 'sub'] as $key) { $payload[$key] = $rp[$key]; }
        $res = $this->auth->adduser($payload);
        return $response->withJson($res);
    }

    // TODO domains_c1() hardcode some basic security here in case rbac rules fail to exist
    public function domains_c1(Request $request, Response $response, array $args = []): Response
    {
        $rp = $request->getQueryParams();
        // required params
        foreach (['owner', 'name'] as $key) {
            if (!array_key_exists($key, $rp)) { throw new \Exception($key . ' is required.'); }
            if ($rp[$key] == '') { throw new \Exception($key . ' must not be empty.'); }
        }
        if (!$this->auth->getuser($rp['owner'])) { throw new \Exception('owner uuid `'.$rp['owner'].'` not found.');}
        $res = $this->auth->adddomain($rp['name'], $rp['owner']);
        return $response->withJson($res);
    }

    public function roles_c1(Request $request, Response $response, array $args = []): Response
    {
        $rp = $request->getQueryParams();
        if (($rp['name'] ?? '') !== '' && is_string($rp['name'])) {
            $res = $this->auth->addrole($rp['name'], $rp['description'] ?? '');
            return $response->withJson($res);
        } else {
            throw new \Exception('Provide `name` and optionally `description`.', 400);
        }
    }


        /**
     * Returns all users (administrative endpoint).
     * TODO minimize the number of authorized users able to see this endpoint.
     * TODO provide a "list domain" endpoint meant for regular users
     * TODO join against users table to get username for primary_owner and all owners too.
     * TODO consider duplicating user-domain relation in a specialized table
     * TODO request https://glued/api/core/auth/domains/v1?owner=01462ec3-6fab-4111-bca5-970fc9029f9de&name=a fails
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function domains_r1(Request $request, Response $response, array $args = []): Response {
        $rp = $request->getQueryParams();
        $qp = null;
        $qs = <<<EOT
        select `c_attr` as res_rows
        from `t_core_domains`
        EOT;
        $qs = (new \Glued\Lib\QueryBuilder())->select($qs);
        $wm = [
            'root' => 'json_contains(`c_json`->>"$._root", ?)',
            'name' => '`c_name` like ?'
        ];
        $this->utils->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        $res = $this->db->rawQuery($qs, $qp);
        if (true) { $res['debug']['query'] = $this->db->getLastQuery(); }
        return $this->utils->mysqlJsonResponse($response, $res);
    }

}
