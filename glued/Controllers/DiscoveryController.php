<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Glued\Lib\Classes\Bearer\JWT;
use Glued\Lib\Classes\Bearer\PAT;
use Glued\Lib\Controllers\AbstractService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * ServiceController
 */
class DiscoveryController extends AbstractService
{

    protected JWT $jwt;
    protected PAT $pat;

    /** @var list<string> */
    private array $anonAllowlist = [
        '/',                           // FE fallback
        '/api',                        // API home
        '/api/core/v1/auth-bootstrap',  // if you want it public
        // regex rules
        '~^/api/[^/]+/v\d+/health$~',
        '~^/api/[^/]+/health$~',
        '~^/api/[^/]+/$~',             // OpenAPI ingress endpoints like /api/catalogue/
    ];

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->jwt = new JWT($this->settings['oidc'], $this->apcuCache, $this->pg, $this->utils);
        $this->pat = new PAT($this->settings, $this->apcuCache, $this->pg, $this->logger);
    }

    /**
     * Discovery endpoint (PAT only):
     * Lists all applicable roles for each route+method.
     *
     * roles are currently only users; public endpoints return an empty list.
     */
    public function getDiscovery(Request $request, Response $response, array $args = []): Response
    {
        if (!$this->requireValidPat($request)) {
            // keep it strict: this endpoint itself is not for anonymous/JWT users
            return $response->withStatus(401)->withJson(
                ['error' => '401 Unauthorized (PAT required)'],
                options: JSON_UNESCAPED_SLASHES
            );
        }

        $data = $this->utils->get_routes();
        if (!is_array($data)) {
            return $response->withJson([], options: JSON_UNESCAPED_SLASHES);
        }

        $baseUrl = $this->detectBaseUrl($request);

        $out = [];
        foreach ($data as $r) {
            if (!is_array($r)) continue;

            $pattern = (string)($r['pattern'] ?? '');
            $name    = (string)($r['name'] ?? '');
            $methods = $r['methods'] ?? [];

            if ($pattern === '' || $name === '' || !is_array($methods) || $methods === []) {
                continue;
            }

            // Hide internal endpoints from discovery (your call; safer default)
            if (str_starts_with($pattern, '/internal/')) {
                continue;
            }

            $url = $r['url'] ?? null;
            if (!is_string($url) || $url === '') {
                // If pattern contains placeholders or [] optionals, treat as template anyway.
                $url = rtrim($baseUrl, '/') . $pattern;
            }

            $rolesByMethod = [];
            foreach ($methods as $m) {
                $m = strtoupper((string)$m);
                if ($m === '') continue;
                $rolesByMethod[$m] = $this->rolesForRouteMethod($pattern, $m);
            }

            $out[] = [
                'url'   => $url,
                'name'  => $name,
                'roles' => $rolesByMethod,
            ];
        }

        return $response->withJson($out, options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * PAT gate: validate that Authorization: Bearer <token> is a known PAT.
     * Do NOT evaluate UUID, claims, JWT, etc.
     */
    private function requireValidPat(Request $request): bool
    {
        try {
            $rawToken = $this->jwt->fetchToken($request); // just to extract Bearer safely
            $storedPat = $this->pat->matchToken($rawToken);
            return (bool)$storedPat;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return all roles applicable for a given route+method.
     * Public endpoints return no required roles; authenticated endpoints require users.
     *
     * @return list<string>
     */
    private function rolesForRouteMethod(string $pattern, string $method): array
    {
        if ($method === 'OPTIONS') {
            return [];
        }

        if ($this->isAnonAllowed($pattern)) {
            return [];
        }

        return ['users'];
    }

    private function isAnonAllowed(string $pattern): bool
    {
        foreach ($this->anonAllowlist as $rule) {
            $isRegex = (strlen($rule) >= 2 && $rule[0] === '~' && substr($rule, -1) === '~');
            if ($isRegex) {
                if (@preg_match($rule, $pattern) === 1) return true;
            } else {
                if ($rule === $pattern) return true;
            }
        }
        return false;
    }

    private function detectBaseUrl(Request $request): string
    {
        $cfg = $this->settings['public_base_url'] ?? null;
        if (is_string($cfg) && $cfg !== '') return rtrim($cfg, '/');

        $baseUri = $this->settings['glued']['baseuri'] ?? null;
        if (is_string($baseUri) && $baseUri !== '') return rtrim($baseUri, '/');

        $uri = $request->getUri();
        $base = $uri->withPath('')->withQuery('')->withFragment('');
        return rtrim((string)$base, '/');
    }
}
