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
            //"uid" => $uid,
            "token" => ""
        ], time()-3600);
    }

    static public function getUser($token)
    {
        if($token){
            $tokenInfo = Jwt::decodeArray($token);
            $uid = $tokenInfo->uid;
            $expire_time = $tokenInfo->expire_time;

            if ($expire_time<time()) {
                $user = new User();
                $user->isLogin = false;
                return $user;
            }

            $user = User::find($uid);
            if ($user == null) {
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
