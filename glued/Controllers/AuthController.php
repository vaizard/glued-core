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
        //return $response->withStatus(403)->withHeader('Content-Length', 0);
        $this->logger->info("auth.enforce: start");
        $val = array_key_exists('HTTP_X_ORIGINAL_URI', $_SERVER) ? $_SERVER['HTTP_X_ORIGINAL_URI'] : 'undefined';
        $this->logger->debug("auth.enforce: orig", [
            "HTTP_X_ORIGINAL_URI" => $val,
            "HTTP_X_ORIGINAL_METHOD" => $_SERVER['HTTP_X_ORIGINAL_METHOD']
        ]);

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
            $this->logger->error("auth.enforce: direct access to internal api.");
            return $response->withStatus(403)->withHeader('Content-Length', 0)->withHeader('X_GLUED_MESSAGE', 'Auth backend is mad.');
        }

        // Skip authorization on all OPTIONS requests,
        // return ALLOW (200).
        if ($_SERVER['HTTP_X_ORIGINAL_METHOD'] === 'OPTIONS') {
            $this->logger->debug("auth.enforce: pass (options)");
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
            $this->logger->debug("auth.enforce: jwt", [
                "ALL" => $token,
                "SUB" => $token['claims']['sub']
            ]);
            $user = $this->auth->getuser($token['claims']['sub']);
            $this->logger->debug("auth.enforce: db", [ "USER" => $user ]);
            if ($user === false) {
                $this->logger->error( 'auth.enforce: adduser', [ "UUID" => $token['claims']['sub'] ]);
                $this->auth->adduser($token['claims']);
            }

        } catch (\Exception $e) {
            $this->logger->error( 'auth.enforce authentication failed', [ $e->getMessage() ]);
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
     * Always returns 'pass'. The AuthController::enforce() has a hardcoded 
     * positive (200) response for the route with AuthController::say_pass() method.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function say_pass(Request $request, Response $response, array $args = []): Response {
        $uuid = "935ac614-96b2-4a80-8802-e2aee088dcae";

        //echo "usr";
        $user = $this->auth->getuser("935ac614-96b2-4a80-8802-e2aee088dcae");
        //print_r($user);
        $token = json_decode('{"claims":{"exp":1672585551,"iat":1672585251,"auth_time":1672584228,"jti":"9fc4698b-8292-4681-aec2-8b3b25a32a09","iss":"https://id.industra.space/auth/realms/t1","aud":["realm-management","new-client","account"],"sub":"935ac614-96b2-4a80-8802-e2aee088dcae","typ":"Bearer","azp":"new-client-2","session_state":"16f6fb40-8c7a-4d75-aa87-2bf33c622fd3","acr":"1","allowed-origins":["*"],"realm_access":{"roles":["offline_access","uma_authorization","realm-admin-role"]},"resource_access":{"realm-management":{"roles":["view-users","query-groups","query-users"]},"new-client":{"roles":["client-read-role"]},"account":{"roles":["manage-account","manage-account-links","view-profile"]}},"scope":"openid email profile","website":"https://vaizard.org","roles2":["offline_access","uma_authorization","realm-admin-role"],"email_verified":true,"name":"Pavel Stratl","groups":["/art","/art/bily-dum","/stage"],"preferred_username":"x","given_name":"Pavel","locale":"en","family_name":"Stratl","email":"pavel@industra.space"},"header":{"alg":"RS256","typ":"JWT","kid":"6EUClJ2T3fOE4LCGmrTrT7EPR8dzvEtIGBbuDkB8xME"}}', true);
        //print_r($x);
        $this->auth->adduser($token['claims']);
        die();
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
        ]);

    }

}
