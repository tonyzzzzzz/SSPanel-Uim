<?php
/**
 * Created by PhpStorm.
 * User: Z3205
 * Date: 2019/5/8
 * Time: 12:30
 */
namespace App\Controllers\API\v1;

use App\Models\User;
use App\Models\Node;
use App\Models\UserTrafficDay;
use App\Middleware\API\v1\JwtToken as AuthService;
use App\Services\Config;
use App\Controllers\LinkController;
use App\Utils\Tools;
use App\Utils\URL;

class UserController
{
    public function info($request, $response, $args) {
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        $trafficLogs_raw = UserTrafficDay::where("date", ">", time()-2678400)->where("userid", "=", $user->id)->get();

        $ssrSubLink = Config::get('subUrl') . LinkController::GenerateSSRSubCode($user->id, 0);

        $res['code'] = 0;
        $res['data'] = array(
            'id' => $user->id,
            'isAdmin' => $user->isAdmin(),
            'username' => $user->user_name,
            'trafficRemain' => $user->unusedTraffic(),
            'trafficTotal' => $user->enableTrafficInGB(),
            'balance' => $user->money,
            'accountExp' => strtotime($user->expire_in),
            'levelName' => 'Level. ' . $user->class,
            'trafficLogs' => array(),
            'ssrSub' => $ssrSubLink
        );

        foreach ($trafficLogs_raw as $trafficLog){
            $raw = array(
                'day' => (int)date('d', $trafficLog->date),
                'd' => Tools::flowToGB($trafficLog->traffic)
            );
            array_push($res['data']['trafficLogs'], $raw);
        }

        return $response->getBody()->write(json_encode($res));
    }

    public function getNodeConfig($request, $response, $args) {
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        $ssrEnable = 1;
        $ssrEnable = URL::SSRCanConnect($user);

        if ($ssrEnable == 1) {
            $muUsers = User::where("is_multi_user", "<>", 0)->get();
            if ($muUsers != Null) {
                $i = 0;
                $muInfo = array();
                foreach ($muUsers as $muUser) {
                    $muInfo[$i] = array(
                        'port' => $muUser->port,
                        'method' => $muUser->method,
                        'password' => $muUser->passwd,
                        'protocol' => $muUser->protocol,
                        'protocol_param' => $user->id.":".$user->passwd,
                        'obfs' => $muUser->obfs，
                        'obfs_param' => $user->getMuMd5(),
                        'ssr' => true
                    );

                    $i++; // Warning: the value will be a correct number after done. The client should use '$muInfo[ 0 ~ $i-1]' as condition.
                }
            }
        }

        $userInfo = array(
            'port' => $user->port,
	        'method' => $user->method,
	        'password' => $user->passwd,
	        'protocol' => $user->protocol,
	        'protocol_param' => $user->protocol_param,
	        'obfs' => $user->obfs,
	        'obfs_param' => $user->obfs_param,
            'ssr' => (bool)$ssrEnable
        );
        
        $ret = array(
            'muInfo' => $muInfo,
            'muCount' => $i,
            'userInfo' => $userInfo
        );

        return $response->getBody()->write(json_encode($ret));
    }

}
