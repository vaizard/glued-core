<?php
declare(strict_types=1);
namespace Glued\Controllers;

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
    public function enforce(Request $request, Response $response, array $args = []): Response {
        // Initialize
        $data = [];

        $this->logger->warning("core.auth.enforce start");
        //$this->logger->warn("Auth subrequest start");
        // Handle direct access / server misconfiguration
        // This method's route must be private, available exclusively to nginx
        // for internal auth subrequests. To authorize a request to a resource
        // nginx must be configured to subrequest this method with the resource
        // url passed in the HTTP_X_ORIGINAL_URI header. If HTTP_X_ORIGINAL_URI
        // then the nginx is misconfigured or the client managed to access the
        // private-only route without nginx setting the HTTP_X_ORIGINAL_URI header
        // In both cases, we just return a 403 response to ensure a "denied
        // unless explicitly allowed" behavior.

        if ((!array_key_exists('HTTP_X_ORIGINAL_URI', $_SERVER)) or (!array_key_exists('HTTP_X_ORIGINAL_METHOD', $_SERVER))) {
            return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X_GLUED_MESSAGE', 'Auth backend is mad.');
            $this->logger->error("core.auth.enforce / Direct access to internal API.");
        }

        // Skip authorization on all OPTIONS requests,
        // return ALLOW (200).
        if ($_SERVER['HTTP_X_ORIGINAL_METHOD'] === 'OPTIONS') {
            return $response->withStatus(200)->withHeader('Content-Length', 0);
        }

        // Authenticate
        // Authentication tests the validity of the access token provided by the
        // identity server. Token subject (user) is used to query t_core_users
        // for additional user account data. If t_core_users doesn't have a match,
        // subject's uuid is used to set up a valid user account.

        try {
            $token = $this->auth->decode_token(
                accesstoken: $this->auth->fetch_token($request),
                certs: $this->auth->get_jwks($this->settings['oidc'])
            );
            $user = $this->auth->getuser($token['claims']['sub']);
            if (!$user) { $this->auth->adduser($token['claims']); }

        } catch (\Exception $e) {
            $this->logger->error( 'core.auth.enforce / authentication failed with exception:' . $e->getMessage() );
        }


        // Authorization: provide hardcoded responses for test routes
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_pass_v1']['path']) {
            return $response->withJson($data)->withStatus(200)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub']);
        }
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_fail_v1']['path']) {
            return $response->withJson($data)->withStatus(403)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub']);
        }

        // Authorization
        // TODO add CASBIN authorization code here
        // For now allow all.
        // TODO delete allow all when authorization code in place
        return $response->withStatus(200)->withHeader('Content-Length', 0)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub'] ?? 'prd')->withHeader('X_GLUED_AUTH_MSG', 'DEV CODE. DO NOT USE IN PRODUCTION.');;

        // Fallback authorization response: DENY
        return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X_GLUED_AUTH_UUID', $token['claims']['sub'] ?? '');
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
        return $response->withJson([
            'message' => 'pass',
            'request' => $request->getMethod()
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
            'message' => 'fail',
            'request' => $request->getMethod()
        ]);    }

}
