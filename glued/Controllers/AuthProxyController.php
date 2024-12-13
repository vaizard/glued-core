<?php

namespace Glued\Controllers;

use Jose\Component\Core\JWK;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Lib\Controllers\AbstractBlank;

class AuthProxyController extends AbstractBlank
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

    public function oidcDiscovery(): array
    {
        $oidc = $this->settings['oidc'];
        $cacheKey = 'coreAuthOidcDiscovery';

        // Attempt to retrieve cached discovery data
        $res = $this->fscache->has($cacheKey)
            ? json_decode($this->fscache->get($cacheKey), true) ?? []
            : [];

        // If cache is empty or issuer doesn't match, fetch fresh data
        if (empty($res) || ($res['issuer'] ?? null) !== $oidc['issuer']) {
            $json = $this->utils->fetch_uri($oidc['discovery']) ?? '';
            $res = json_decode($json, true);
            if (empty($res)) { throw new \Exception("Identity server discovery {$oidc['discovery']} failed.", 502); }
            if (($res['issuer'] ?? null) !== $oidc['issuer']) { throw new \Exception("Configured OIDC issuer {$oidc['discovery']} doesn't match discovered issuer ({$res['issuer']}).", 500); }
            $this->fscache->set($cacheKey, $json, $oidc['ttl']); // Cache the new discovery data
        }

        return $res;
    }


    public function oidcJwks($jwksUri): array
    {
        $oidc = $this->settings['oidc'];
        $cacheKey = 'coreAuthOidcJwks';

        // Attempt to retrieve cached JWKS data
        $jwks = $this->fscache->has($cacheKey)
            ? json_decode($this->fscache->get($cacheKey), true) ?? []
            : [];

        // If cache is empty or 'keys' not found, fetch fresh data
        if (empty($jwks) || !isset($jwks['keys'])) {
            $json = $this->utils->fetch_uri($jwksUri) ?? '';
            $jwks = json_decode($json, true) ?? [];
            if (empty($jwks)) { throw new \Exception("Identity server returned empty Jwks response `{$jwksUri}`.", 502); }
            if (!isset($jwks['keys'])) { throw new \Exception("Identity server failed to return Jwks certificates.", 502); }
            $this->fscache->set($cacheKey, $json, $oidc['ttl']); // Cache the new discovery data
        }

        return $jwks;
    }

    public function oidcJwk($oidcJwks)
    {
        $oicdJwk = [];
        foreach ($oidcJwks['keys'] as $item) {
            $item = (array) $item;
            if ($item['use'] === 'sig') { $oicdJwk[] = new JWK($item); }
        }
        return $oicdJwk;
    }

    public function fetchToken($request)
    {
        $oidc = $this->settings['oidc'];

        // Check for token in header and in the cookie
        $header = $request->getHeaderLine($oidc['header']);
        if (!empty($header) && preg_match($oidc['regexp'], $header, $matches)) {
            return $matches[1];
        }

        $cookie = $request->getCookieParams()[$oidc['cookie']] ?? null;
        if ($cookie && preg_match($oidc['regexp'], $cookie, $matches)) {
            return $matches[1];
        }

        if ($cookie) {
            return $cookie;
        }

        throw new \Exception("Token not found.", 401);
    }


    public function health(Request $request, Response $response, array $args = []): Response
    {
        $authProxyConfig = $this->settings['oidc'];
        $oidcDiscovery = $this->oidcDiscovery();
        $oidcJwks = $this->oidcJwks($oidcDiscovery['jwks_uri']);
        $oidcJwk = $this->oidcJwk($oidcJwks);
        try { $rawToken = $this->fetchToken($request); } catch (\Exception $e) { $rawToken = $e->getMessage(); }


        //$certs = $this->getJwks($oidc);
        //$certs = $this->getJwks('https://glued');
        //$rawToken = $this->auth->fetch_token($request);
        //$parsedToken = $this->auth->validate_jwt_token($rawToken, $certs);
        //$arr['users'] = $this->auth->users();
        $ret = [
            'authProxyConfig' => $authProxyConfig,
            'oidcDiscovery' => $oidcDiscovery,
            'oidcJwks' => $oidcJwks,
            'oicdJwk' => $oidcJwk,
            'token' => $rawToken,
            //'parsedToken' => $parsedToken,
        ];
        return $response->withJson($ret, options: JSON_UNESCAPED_SLASHES);
    }


    public function challenge(Request $request, Response $response, array $args = []): Response
    {
        //$a = $this->getJwks('https://glued');
        // debug logging
        $rayId = microtime(true);
        $this->logger->debug("{$rayId} authChallengeResponse", [
            "HTTP_X_ORIGINAL_URI" => $_SERVER['HTTP_X_ORIGINAL_URI'] ?? 'undef',
            "HTTP_X_ORIGINAL_METHOD" => $_SERVER['HTTP_X_ORIGINAL_METHOD'] ?? 'undef'
        ]);

        // Handle server misconfiguration (missing X-ORIGINAL-URI and X-ORIGINAL-METHOD headers)
        if (empty($_SERVER['HTTP_X_ORIGINAL_URI']) || empty($_SERVER['HTTP_X_ORIGINAL_METHOD'])) {
            $this->logger->error("{$rayId} authChallengeResponse: X-ORIGINAL-URI or X-ORIGINAL-METHOD header missing.");
            return $response
                ->withStatus(403)
                ->withHeader('Content-Length', 0)
                ->withHeader('X_GLUED_MESSAGE', '500 Auth backend misconfigured.');
        }

        // Skip authorization on OPTIONS requests
        if ($_SERVER['HTTP_X_ORIGINAL_METHOD'] === 'OPTIONS') {
            $this->logger->debug("{$rayId} authChallengeResponse: OK (options)");
            return $response
                ->withStatus(200)
                ->withHeader('Content-Length', 0)
                ->withHeader('X_GLUED_MESSAGE', '200 OK (options).');
        }


        return $response->withStatus(200)
            ->withHeader('Content-Length', 0)
            ->withHeader('X-GLUED-MESSAGE', 'TEST')
            ->withHeader('X-GLUED-AUTH-UUID', $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000');
    }

    public function enforce2(Request $request, Response $response, array $args = []): Response
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

        $this->logger->error( 'auth.enforce authenticated as', [ "X-GLUED-AUTH-UUID" => $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000' ]);


        // Authorization: provide hardcoded responses for test routes
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_pass']['pattern']) {
            $this->logger->debug("auth.enforce hardcoded pass", [ "HTTP_X_ORIGINAL_METHOD" => $_SERVER['HTTP_X_ORIGINAL_METHOD'], "X_GLUED_AUTH_UUID" => $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000' ]);
            //return $response->withStatus(200)->withHeader('Content-Length', 0)->withHeader('X-GLUED-AUTH-UUID', $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000');
        }
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_fail']['pattern']) {
            $this->logger->debug("auth.enforce hardcoded fail", [ "HTTP_X_ORIGINAL_METHOD" => $_SERVER['HTTP_X_ORIGINAL_METHOD'], "X_GLUED_AUTH_UUID" => $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000' ]);
            return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X-GLUED-AUTH-UUID', $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000');
        }

        // Authorization
        // TODO add CASBIN authorization code here
        // For now allow all.
        // TODO delete allow all when authorization code in place
        return $response->withStatus(200)->withHeader('Content-Length', 0)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000')->withHeader('X_GLUED_AUTH_MSG', 'DEV CODE. DO NOT USE IN PRODUCTION.');

        // Fallback authorization response: DENY
        return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub'] ?? '00000000-0000-0000-0000-000000000000');
    }


}