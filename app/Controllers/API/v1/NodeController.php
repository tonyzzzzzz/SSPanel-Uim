<?php
/**
 * Created by PhpStorm.
 * User: Z3205
 * Date: 2019/5/9
 * Time: 15:04
 */

namespace App\Controllers\API\v1;

use App\Middleware\API\v1\JwtToken as AuthService;
use App\Models\Node;
use App\Controllers\LinkController;
use App\Models\TrafficLog;
use App\Utils\URL;

class NodeController
{
    public function info($request, $response, $args){
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        $nodes = Node::where('type', 1)->where('node_class', '<=', $user->class)->where('sort', '!=', '9')->orderBy('name')->get();

        $fiveMinSumTraffic = TrafficLog::query()->where('log_time', '>', time()-5*60)->get();


        $ret['code'] = 0;
        $ret['data'] = array();
        foreach ($nodes as $node){
            $sum = 0;
            foreach ($fiveMinSumTraffic as $t){
                if((int)$t->node_id == (int)$node->id){
                    $sum += $t->u;
                    $sum += $t->d;
                }
            }
            $nodeLoad = $node->getNodeLoad();
            if (isset($nodeLoad[0]['load'])) {
                $nodeLoad = ((explode(" ", $nodeLoad[0]['load']))[0]) * 100;
            }
            else {
                $nodeLoad = 0;
            }

            $avgTraffic = $sum / (5*60);
            $node_item = URL::getItem($user, $node, $mu_port = 453);
            $url = URL::getItemUrl($node_item, 0);

            $region_pattern = '/(?![0-9a-zA-Z\_]+\s)[A-Z]{2}/';
            preg_match($region_pattern, $node->name,$matches);
            $region = strtolower(substr($node->name, 0, 2));

            array_push($ret['data'], array(
                'id' => $node->id,
                'link' => $node->server,
                'name' => $node->name,
                'load' => $nodeLoad,
                'mu_only' => $node->mu_only,
                'sort' => $node->sort,
                'online' => $node->isNodeOnline() ? $node->getOnlineUserCount() : -1,
                'level' => $node->node_class,
                'subscribelink' => $url,
                'region' => $region,
                'datausage' => 0,
                'traffic' => $avgTraffic
            ));
        }
        return $response->getBody()->write(json_encode($ret));
    }


}
