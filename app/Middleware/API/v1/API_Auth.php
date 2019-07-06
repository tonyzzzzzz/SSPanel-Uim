<?php

namespace App\Middleware\API\v1;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Middleware\API\v1\JwtToken as AuthService;
use App\Services\Config;

use App\Services\Jwt;

class API_Auth
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        if (!$user->isLogin || $user->enable == 0) {
            $res['code'] = 403;
            $res['msg'] = '登录令牌已过期';
            $response->getBody()->write(json_encode($res));
            return $response;
        }

        $response = $next($request, $response, $user);
        return $response;
    }
}
