<?php
declare(strict_types=1);
namespace Glued\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Classes\Auth;
use Jose\Component\Core\JWKSet;
use Jose\Easy\Load;
use Glued\Lib\Exceptions\DefaultException;
use Glued\Lib\Exceptions\AuthTokenException;
use Glued\Lib\Exceptions\AuthJwtException;
use Glued\Lib\Exceptions\AuthOidcException;
use Glued\Lib\Exceptions\DbException;
use Glued\Lib\Exceptions\TransformException;

class AuthController extends AbstractController
{

   /**
     * Provides enforce endpoint for the nginx auth subrequest. 
     * Enforces according to $_SERVER['HTTP_X_ORIGINAL_URI']
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response with 200 or 403 (allow/deny)
     */
    public function enforce(Request $request, Response $response, array $args = []): Response {
        // Initialize
        $data = [];

        // Handle direct access (nginx misconfiguration)
        if ((!array_key_exists('HTTP_X_ORIGINAL_URI', $_SERVER)) or (!array_key_exists('HTTP_X_ORIGINAL_URI', $_SERVER))) {
            $data['message'] = 'Unauthorized.';
            $data['code'] = 403;
            $data['hint'] = 'Authorization headers missing, endpoint accessed directly or proxy misconfigured.';
            return $response->withJson($data)->withStatus(403);
        }

        // Skip authorization on all OPTIONS requests
        if ($_SERVER['HTTP_X_ORIGINAL_METHOD'] === 'OPTIONS') {
            return $response->withStatus(200)->withHeader('Content-Length', 0);
        } 

        // Hardcoded replies 
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_pass_v1']['path']) { return $response->withJson($data)->withStatus(200); }
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_fail_v1']['path']) { return $response->withJson($data)->withStatus(403); }

        // TODO do casbin authorization here

        // Fallback authorization response: DENY
        return $response->withJson($data)->withStatus(403);
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
