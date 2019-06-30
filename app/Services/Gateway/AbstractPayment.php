<?php
/**
 * Created by PhpStorm.
 * User: tonyzou
 * Date: 2018/9/24
 * Time: 下午4:23
 */

namespace App\Services\Gateway;

use App\Models\Paylist;
use App\Models\Payback;
use App\Models\User;
use App\Models\Bought;
use App\Models\Shop;
use App\Services\Config;
use App\Utils\Telegram;

abstract class AbstractPayment
{
    abstract public function purchase($user, $shop, $type, $price);
    abstract public function notify($request, $response, $args);
    abstract public function getPurchaseHTML();
    abstract public function getReturnHTML($request, $response, $args);
    abstract public function getStatus($request, $response, $args);

    public static function generateGuid() {
        mt_srand((double)microtime()*10000);
        $charid = strtoupper(md5(uniqid(rand() + time(), true)));
        $hyphen = chr(45);
        $uuid   = chr(123)
            .substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12)
            .chr(125);
        $uuid = str_replace(['}', '{', '-'],'',$uuid);
        $uuid = substr($uuid, 0, 8);
        return $uuid;
    }

    function postPayment($pid)
    {
        $p = Paylist::where("tradeno", $pid)->first();

        if($p->status==1){
            return 0;
        }

        $p->status=1;


        $user = User::find($p->userid);

        $shop = Shop::where('id', '=', $p->shopid)->first();

        $p->save();

        $bought = new Bought();
        $bought->userid = $user->id;
        $bought->shopid = $shop->id;
        $bought->datetime = time();

        if($shop->auto_renew > 0){
            $bought->renew = time() + $shop->auto_renew * 86400;
        } else {
            $bought->renew = 0;
        }

        $price = $p->total;
        $bought->price = $price;
        $bought->save();

        $shop->buy($user);

        if ($user->ref_by >= 1) {
            $gift_user=User::where("id", "=", $user->ref_by)->first();
            if($gift_user){
                // $gift_user->money=$gift_user->money+($p->total * ($gift_user->payback_r/100));
                $gift_user->money=$gift_user->money+($p->total * 0.5);
                $gift_user->save();
                $Payback=new Payback();
                $Payback->total=$p->total;
                $Payback->userid=$user->id;
                $Payback->ref_by=$user->ref_by;
                // $Payback->ref_get=$p->total * ($gift_user->payback_r/100);
                $Payback->ref_get=$p->total * 0.5;
                $Payback->datetime=time();
                $Payback->save();
            }
        }

        Telegram::Send(" - 购买提示 - ".PHP_EOL."用户ID: ".$user->id.PHP_EOL."用户名: ".$user->user_name.PHP_EOL."数额: ".$p->total.PHP_EOL."购买商品: ".$shop->name, $admin = 1);
        return 0;
    }


}
