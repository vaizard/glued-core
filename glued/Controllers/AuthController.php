<?php
declare(strict_types=1);
namespace Glued\Controllers;

use Glued\Classes\QuerySelect;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Lib\Controllers\AbstractBlank;

class AuthController extends AbstractBlank
{

    /**
     * Constructs a SQL query against Glued's standard mysql collections with a minimal set of columns:
     * c_uuid - binary stored uuid with elements swapped for optimized storage, see `true` in `uuid_to_bin(? , true)`
     * c_data - the json data blob
     * c_stor_name - human-readable name for the object when accessed by the stor microservice.
     * The $qstring (i.e. SELECT) passed by reference will be appended by WHERE clauses constructed according to
     * $reqparams and $wheremods and finally enveloped by (changed into a subquery of) json_arrayagg().
     * This allows to return the whole response from mysql as a json string without further transformation of the data
     * in app logic.
     * @param array $reqparams (GET) request parameters that are converted them to SQL WHERE clauses
     * @param QueryBuilder $qstring Base SQL query string (i.e. select) passed as a reference to a QueryBuilder object.
     * @param array $qparams (GET) request parameter values to be used in WHERE clauses
     * @param array $wheremods $reqparam WHERE query modifiers. Since the JSON path of the parsed $reqparam vary,
     * a similar variability needs to be represented in the relevant WHERE query subelements.
     * @return void
     */
    protected function mysqlJsonQueryFromRequest(array $reqparams, QuerySelect &$qstring, &$qparams, array $wheremods = [], string $jsonkey = 'c_data') {

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
            // to sql query substring `where (`c_data`->>'$."mykey"' = ?)`, if $jsonkey is default
            $w = '('.$jsonkey.'->>"$.'.$jsonpath.'" = ?)';

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

    /**
     * Creates a metadata json header and appends $jsondata to path $.data (if $dataitem is kept default)
     * $jsondata would be typically acquired from db->rawQuery($qs, $qp) with the $qs and $gp parameters constructed
     * by mysqlJsonQueryFromRequest. Returns a PSR Response.
     * @param Response $response
     * @param array $jsondata
     * @param string $dataitem
     * @return Response
     */
    public function mysqlJsonResponse(Response $response, array $jsondata = [], string $dataitem = 'data', $meta = []): Response {
        // construct the response metadata json, remove last character (closing curly bracket)
        $meta['service']   = basename(__ROOT__);
        $meta['timestamp'] = microtime();
        $meta['code']      = 200;
        $meta['message']   = 'OK';
        $meta = json_encode($meta, JSON_UNESCAPED_SLASHES);
        $meta = mb_substr($meta, 0, -1);

        // get the json from a json_arrayagg() response
        $key = array_keys($jsondata[0])[0];
        $jsondata = $jsondata[0][$key];
        if (is_null($jsondata)) { $jsondata = '{}'; }

        // write the response body
        $body = $response->getBody();
        $body->write($meta.', "'.$dataitem.'": '.$jsondata."}");
        return $response->withBody($body)->withStatus(200)->withHeader('Content-Type', 'application/json');
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
        $qs = (new \Glued\Classes\QueryBuilder())->select($qs);
        $wm = [
            'handle' => '`c_handle` like ?'
        ];

        $this->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        echo vsprintf(str_replace('?', "'%s'", $qs), $qp);
        return $response;
        $res = $this->db->rawQuery($qs, $qp);
        if (true) { $res['debug']['query'] = $this->db->getLastQuery(); }
        return $this->mysqlJsonResponse($response, $res);
        /*return $response->withJson($this->auth->users());*/
    }


    public function tokens_c1(Request $request, Response $response, array $args = []): Response
    {
        $rp = $request->getQueryParams();
        $attr = json_decode($rp['attr'] ?? "{}", true);
        if (!isset($attr['consumer']['type']) || !in_array($attr['consumer']['type'], ['svc', 'app'])) {
            throw new \Exception("Invalid or missing 'consumer.type' attribute.");
        }
        if ($attr['consumer']['type'] === 'svc') {
            $requiredKeys = ['name', 'host'];
            foreach ($requiredKeys as $key) {
                if (!isset($attr['consumer'][$key])) { throw new \Exception("For 'svc' type, 'consumer.$key' must be set."); }
            }
        }
        $owner = $_SERVER['HTTP_X-GLUED-AUTH-UUID'] ?? '00000000-0000-0000-0000-000000000000';
        if ($owner === '00000000-0000-0000-0000-000000000000') { throw new \Exception("Only authorized users can add tokens.", 403); }
        $r = $this->auth->generate_api_token($owner, expiry: null, attributes: $attr);
        return $response->withJson($r);
    }


    public function tokens_r1(Request $request, Response $response, array $args = []): Response {
        $rp = $request->getQueryParams() ?? [];
        $qs = '
            SELECT 
                json_merge(
                json_object (
                    "uuid", bin_to_uuid(tok.c_uuid, true),
                    "exp", c_expired_at,
                    "owner_handle", u.c_handle,
                    "owner_uuid", bin_to_uuid(tok.c_inherit, true)
                ), tok.c_attr) as res_rows
            FROM t_core_tokens AS tok 
            LEFT JOIN t_core_users AS u ON tok.c_inherit = u.c_uuid
        ';
        $qp = null;
        $wm = [];
        $qs = (new \Glued\Classes\QueryBuilder())->select($qs);
        $this->mysqlJsonQueryFromRequest(reqparams: $rp, qstring: $qs, qparams: $qp, wheremods: $wm, jsonkey: 'tok.c_attr');
        $res = $this->db->rawQuery($qs, $qp);
        return $this->mysqlJsonResponse($response, $res);
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
        $qs = (new \Glued\Classes\QueryBuilder())->select($qs);
        $wm = [
            'handle' => '`c_handle` like ?'
        ];

        $this->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        //echo vsprintf(str_replace('?', "'%s'", $qs), $qp);
        //return $response;
        $res = $this->db->rawQuery($qs, $qp);
        if (true) { $res['debug']['query'] = $this->db->getLastQuery(); }
        return $this->mysqlJsonResponse($response, $res);
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
        $qs = (new \Glued\Classes\QueryBuilder())->select($qs);
        $wm = [
            'root' => 'json_contains(`c_json`->>"$._root", ?)',
            'name' => '`c_name` like ?'
        ];
        $this->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        $res = $this->db->rawQuery($qs, $qp);
        if (true) { $res['debug']['query'] = $this->db->getLastQuery(); }
        return $this->mysqlJsonResponse($response, $res);
    }

}
