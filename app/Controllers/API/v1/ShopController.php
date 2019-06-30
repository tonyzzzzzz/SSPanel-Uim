<?php
/**
 * Created by PhpStorm.
 * User: Z3205
 * Date: 2019/5/8
 * Time: 21:36
 */

namespace App\Controllers\API\v1;

use App\Models\Shop;
use App\Models\Coupon;
use App\Services\Payment;
use App\Models\Paylist;
use App\Middleware\API\v1\JwtToken as AuthService;

class ShopController
{
    public function index($request, $response, $args){
        $queryId = $request->getParam('id');
        $shops = Shop::where("status", 1)->orderBy("price")->where('id', 'LIKE', $queryId)->get();
        $ret['code'] = 0;
        $ret['data'] = array();
        foreach ($shops as $shop){
            array_push($ret['data'], array(
                'id' => $shop->id,
                'name' => $shop->name,
                'traffic' => $shop->bandwidth(),
                'nodeCount' => $shop->usableNodes(),
                'speed' => $shop->speedlimit(),
                'price' => $shop->price
            ));
        }
        return $response->getBody()->write(json_encode($ret));
    }

    public function buy($request, $response, $args){
        $token = explode(' ', $request->getHeaderLine('Authorization'));
        $token = isset($token[1]) ? $token[1] : '';
        $user = AuthService::getUser($token);

        $type = $request->getParam('type');
        $coupon = $request->getParam('coupon');
        $coupon = trim($coupon);
        $id = $request->getParam('id');

        $shop = Shop::where("id", $id)->where("status", 1)->first();

        if ($shop == null) {
            $res['ret'] = -1;
            $res['msg'] = "所请求商品不存在";
            return $response->getBody()->write(json_encode($res));
        }

        $credit = 0;
        if ($coupon == "") {
            $credit = 0;
        } else {
            $coupon = Coupon::where("code", $coupon)->first();
            if ($coupon == null) {
                $credit = 0;
            } else {
                $credit = $coupon->credit;
            }

        }

        $price = $shop->price * ((100 - $credit) / 100);

        $payInfo = Payment::purchase($user, $shop, $type, $price);
        $ret = array(
            'code' => 0,
            'data' => $payInfo
        );
        return $response->getBody()->write(json_encode($ret));
    }

    public function checkStatus($request, $response, $args){
        $p = Paylist::where("tradeno", $request->getParam('pid'))->first();
        $ret = array(
            'code' => 0,
            'data' => array(
                'paid' => $p->status
            )
        );
        return json_encode($ret);
    }
}
