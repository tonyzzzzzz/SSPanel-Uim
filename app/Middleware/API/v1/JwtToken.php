<?php

namespace App\Middleware\API\v1;

use App\Utils;
use App\Services\Jwt;
use App\Models\User;

class JwtToken
{
    static public function login($uid, $time)
    {
        $expireTime = time() + $time;
        $ary = [
          "uid" => $uid,
          "expire_time" => $expireTime
        ];
        $encode = Jwt::encode($ary);
        return $encode;
    }

    public function logout()
    {
        Utils\Cookie::set([
            "token" => ""
        ], time()-3600);
    }

    static public function getUser($token)
    {
        if ($token) {
            $tokenInfo = Jwt::decodeArray($token);
            $user = User::find($tokenInfo->uid);
            $expire_time = $tokenInfo->expire_time;

            if ($expire_time < time() || $user == Null) {
                $user = new User();
                $user->isLogin = false;
                return $user;
            }

            $user->isLogin = true;
            return $user;
        } else {
            $user = new User();
            $user->isLogin = false;
            return $user;
        }
    }
}
