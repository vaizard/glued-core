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
use Linfo\Linfo;

class StatusController extends AbstractController
{

    /**
     * Return system info via the Linfo library. NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function sysinfo(Request $request, Response $response, array $args = []): Response {
        $linfo = new Linfo(['temps'=> ['hwmon' => true]]);
        $parser = $linfo->getParser();
        $parser->determineCPUPercentage();
        $methods = ['Hostname', 'OS', 'Kernel', 'Distro', 'Uptime', 'Virtualization', 'CPU', 'HD', 'Ram', 'Load', 'Net', 'Temps'];
        foreach ($methods as $m) {
            $method = 'get' . $m;
            $data[strtolower($m)] = $parser->$method();
        }
        return $response->withJson($data);
    }

    /**
     * Return complete glued configuration. NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function config(Request $request, Response $response, array $args = []): Response {
        return $response->withJson($this->settings);
    }  

    /**
     * Returns PHP's get_defined_constants(true). NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function phpconst(Request $request, Response $response, array $args = []): Response {
        $arr = get_defined_constants(true);
        $response->getBody()->write(json_encode($arr, JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withHeader('Content-type', 'application/json');
    }

    /**
     * Get user's jwt token.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function token_fetch(Request $request, Response $response, array $args = []): Response {
        try {
            $data = $this->auth->fetch_token($request);
        }  catch (AuthJwtException | AuthTokenException $e) {
            $data['error'] = $e->getMessage();
            $data['message'] = "Login at ".$oidc['uri']['login'];
        }
        return $response->withJson($data);
    }

    /**
     * Decodes user's jwt token.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function token_decode(Request $request, Response $response, array $args = []): Response {
       try {
            // Get oidc config, jwk signing keys and the access token
            $oidc = $this->settings['oidc'];
            $certs = $this->auth->get_jwks($oidc);
            $accesstoken = $this->auth->fetch_token($request);
          
            // Authenticate user. Exceptions (i.e. invalid jwt, database 
            // errors etc.) are handled by the catch below.
            try {
                $jwt = Load::jws($accesstoken)   // Load and verify the token in $accesstoken
                    ->algs(['RS256', 'RS512'])   // Check if allowed The algorithms are used
                    ->exp()                      // Check if "exp" claim is present
                    ->iat(1000)                  // Check if "iat" claim is present and within 1000ms leeway
                    ->nbf(1000)                  // Check if "nbf" claim is present and within 1000ms leeway
                    ->iss($oidc['uri']['realm']) // Check if "nbf" claim is present and matches the realm
                    ->keyset(new JWKSet($certs)) // Key used to verify the signature
                    ->run();                     // Do it.
                $jwt_claims = $jwt->claims->all() ?? [];
                $jwt_header = $jwt->header->all() ?? [];
            } catch (\Exception $e) { throw new AuthJwtException($e->getMessage(), $e->getCode(), $e); }
        } 
        catch (AuthJwtException | AuthTokenException $e) {
            $jwt_claims['error'] = $e->getMessage();
            $jwt_claims['message'] = "Login at ".$oidc['uri']['login'];
        }
        catch (AuthOidcException $e) { echo $e->getMessage(); die(); }
        catch (DbException $e) { echo $e->getMessage(); die(); }
        catch (TransformException $e) { echo $e->getMessage(); die(); }
        catch (\Exception $e) { echo 'x'.$e->getMessage(); die(); }
        return $response->withJson($jwt_claims);
    }

    /**
     * Reflects a client request.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function reflect_request(Request $request, Response $response, array $args = []): Response {
        $data = getallheaders();
        return $response->withJson($data);
    }

}
