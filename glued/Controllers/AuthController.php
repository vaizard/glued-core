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
        $data = [];
        if (!isset($_SERVER['HTTP_X_ORIGINAL_URI'])) {
            return $response->withJson($_SERVER)->withStatus(403);
            throw new \Exception('Internal resource accessed externally or proxy misconfigured.');
        }
        $data = [ 'enforcer' => $_SERVER['HTTP_X_ORIGINAL_URI'] ?? 'bad request' ];
        $data = $_SERVER;

        // hardcoded testcases
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_pass_v1']['path']) {
           if ($request->getMethod() === 'OPTIONS') { 
              return $response->withStatus(204)
                ->withHeader('Access-Control-Allow-Origin', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Headers', 'GET, POST, PUT, DELETE, PATCH')
                ->withHeader('Access-Control-Allow-Methods', 'true')
                ->withHeader('Access-Control-Allow-Credentials', '*');
           }
           return $response->withJson($data)->withStatus(200);
        }
       
        if ($_SERVER['HTTP_X_ORIGINAL_URI'] == $this->settings['routes']['be_core_auth_test_fail_v1']['path']) { 
           if ($request->getMethod() === 'OPTIONS') { 
              return $response->withStatus(204)
                ->withHeader('Access-Control-Allow-Origin', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Headers', 'GET, POST, PUT, DELETE, PATCH')
                ->withHeader('Access-Control-Allow-Methods', 'true')
                ->withHeader('Access-Control-Allow-Credentials', '*');
           }
           return $response->withJson($data)->withStatus(401);
        }
       
        return $response->withJson($data);
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
        return $response->withJson(['message' => 'pass']);
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
        return $response->withJson(['message' => 'fail']);
    }

}
