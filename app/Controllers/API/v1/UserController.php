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
    public function info($request, $response, $args)
    {
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        $trafficLogs_raw = UserTrafficDay::where("date", ">", time()-2678400)->where("userid", "=", $user->id)->get();

        $ssrSubLink = Config::get('subUrl') . LinkController::GenerateSSRSubCode($user->id, 0);
        $confLink = Config::get('subUrl') . LinkController::GeneratePCConfDownload($user->id);
        $iosLink = Config::get('subUrl') . LinkController::GenerateIosCode($user->id);

        $res['code'] = 0;
        $res['data'] = array(
            'id' => $user->id,
            'isAdmin' => $user->isAdmin(),
            'username' => $user->user_name,
            'trafficRemain' => $user->unusedTraffic(),
            'trafficTotal' => $user->enableTrafficInGB(),
            'balance' => $user->money,
            'accountExp' => strtotime($user->expire_in),
            'levelName' => ['游客', 'Platinum', 'Ultimate'][$user->class],
            'trafficLogs' => array(),
            'method' => $user->method,
            'obfs' => $user->obfs,
            'port' => $user->port,
            'protocol' => $user->protocol,
            'ssrSub' => $ssrSubLink,
            'confLink' => $confLink,
            'iosLink' => $iosLink
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

    public function getPac($request, $response, $args){
        $newResponse = $response->withHeader('Content-type', 'application/x-ns-proxy-autoconfig');
        $newResponse->getBody()->write(LinkController::get_pac($request->getParam('type'), $request->getParam('host'), $request->getParam('port'), $request->getParam('proxy_google'), ''));
        return $newResponse;
    }

    public function getNodeConfig($request, $response, $args){
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        $node_id = $request->getParam('id');
        $node = Node::where('id', '=', $node_id)->first();

        $ss_can = 0;

        $ss_can = URL::SSCanConnect($user);

        if($node->mu_only === 1){
            $mu_node = Node::where('sort', 9)->where('node_class', '<=', $user->class)->where("type", "1")->where(
                function ($query) use ($user) {
                    $query->where("node_group", "=", $user->node_group)
                        ->orWhere("node_group", "=", 0);
                }
            )->first();
            $mu_user = User::where('port', $mu_node->port)->first();
            $user = $mu_user;
            $ss_can = URL::SSCanConnect($user, $mu_node->port);
        }

        $ss_url = '';
        if($ss_can){
            $userinfo = base64_encode($user->method.':'.$user->passwd);
            $userinfo = str_replace(array('+','/','='),array('-','_',''),$userinfo);
            $ss_url = 'ss://'.$userinfo."@".$node->server.":".$user->port;

            $ss_obfs_list = Config::getSupportParam('ss_obfs');
            $obfs = "";
            $obfs_host = "";
            if(in_array($user->obfs, $ss_obfs_list)) {
                if(strpos($user->obfs, 'http') !== FALSE) {
                    $obfs = "http";
                }
                else {
                    $obfs = "tls";
                }
                if($user->obfs_param != '') {
                    $obfs_host = $user->obfs_param;
                }
                else {
                    $obfs_host = "wns.windows.com";
                }
            }

            if($obfs != ""){
                $ss_url .= urlencode('/?plugin=obfs-local;obfs='.$obfs.';obfs-host='.$obfs_host);
            }

        }

        $conf = array(
            'server' => $node->server,
            'server_port' => $user->port,
            'method' => $user->method,
            'password' => $user->passwd,
            'protocol' => $user->protocol,
            'protocol_param' => $user->protocol_param,
            'obfs' => $user->obfs,
            'obfs_param' => $user->obfs_param,
            "local_address" => "0.0.0.0",
            "local_port" => 1088,
            "over_tls_enable" => false,
            "udp" => true,
            "timeout" => 300
        );

        $ret = array(
            'code' => 0,
            'conf' => $conf,
            'ss' => $ss_url
        );

        return $response->getBody()->write(json_encode($ret));
    }

}
