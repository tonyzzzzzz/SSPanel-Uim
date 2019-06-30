<?php
/**
 * Created by PhpStorm.
 * User: Z3205
 * Date: 2019/5/8
 * Time: 16:50
 */

namespace App\Middleware\API\v1;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Middleware\API\v1\JwtToken as AuthService;

class API_Guest
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        if ($user->isLogin == 1) {
            $res['code'] = 302;
            $res['msg'] = '已登录';
            $response->getBody()->write(json_encode($res));
            return $response;
        }

        $response = $next($request, $response);
        return $response;
    }
}