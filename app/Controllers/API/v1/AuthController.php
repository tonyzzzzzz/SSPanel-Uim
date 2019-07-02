<?php
/**
 * Created by PhpStorm.
 * User: Z3205
 * Date: 2019/5/8
 * Time: 16:48
 */

namespace App\Controllers\API\v1;
use App\Models\User;
use App\Services\Config;
use App\Utils\Hash;
use App\Utils\Check;
use App\Services\Mail;
use App\Models\LoginIp;
use App\Utils\Tools;
use App\Models\EmailVerify;
use App\Middleware\API\v1\JwtToken as Auth;
use App\Models\InviteCode;
use voku\helper\AntiXSS;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Utils\GA;

class AuthController
{
    public function login($request, $response){
        $email = strtolower(trim($request->getParam('email')));
        $passwd = $request->getParam('passwd');
        $rememberMe = $request->getParam('rememberMe');
        $user = User::where('email', '=', $email)->first();

        if ($user == null) {
            $rs['code'] = -1;
            $rs['msg'] = "邮箱或者密码错误";
            return $response->getBody()->write(json_encode($rs));
        }

        if (!Hash::checkPassword($user->pass, $passwd)) {
            $rs['code'] = -1;
            $rs['msg'] = "邮箱或者密码错误.";
            $loginip = new LoginIp();
            $loginip->ip = $_SERVER["REMOTE_ADDR"];
            $loginip->userid = $user->id;
            $loginip->datetime = time();
            $loginip->type = 1;
            $loginip->save();
            return $response->getBody()->write(json_encode($rs));
        }

        $time = 86400;
        if ($rememberMe) {
            $time = 86400 * 7;
        }

        $token = Auth::login($user->id, $time);
        $res['code'] = 0;
        $res['data'] = array(
            'token' => $token,
            'time' => $time,
        );

        return $response->getBody()->write(json_encode($res));
    }

    public function getVerificationCode($request, $response){
        $email = trim($request->getParam('email'));

        if ($email == "" || !Check::isEmailLegal($email)) {
            $res['ret'] = -1;
            $res['msg'] = "无效邮箱";
            return $response->getBody()->write(json_encode($res));
        }

        $user = User::where('email', '=', $email)->first();
        if ($user != null) {
            $res['ret'] = -1;
            $res['msg'] = "此邮箱已注册";
            return $response->getBody()->write(json_encode($res));
        }

        $ipcount = EmailVerify::where('ip', '=', $_SERVER["REMOTE_ADDR"])->where('expire_in', '>', time())->count();
        if ($ipcount >= (int)Config::get('email_verify_iplimit')) {
            $res['ret'] = -1;
            $res['msg'] = "此IP请求次数过多";
            return $response->getBody()->write(json_encode($res));
        }

        $mailcount = EmailVerify::where('email', '=', $email)->where('expire_in', '>', time())->count();
        if ($mailcount >= 3) {
            $res['ret'] = -1;
            $res['msg'] = "此邮箱请求次数过多";
            return $response->getBody()->write(json_encode($res));
        }

        $ev = new EmailVerify();
        $ev->expire_in = time() + Config::get('email_verify_ttl');
        $ev->ip = $_SERVER["REMOTE_ADDR"];
        $ev->email = $email;
        $ev->code = Tools::genRandomNum(6);
        $ev->save();
        $subject = Config::get('appName') . "- 验证邮件";
        try {
            Mail::send($email, $subject, 'auth/verify.tpl', [
                "code" => $ev->code, "expire" => date("Y-m-d H:i:s", time() + Config::get('email_verify_ttl'))
            ], [
                //BASE_PATH.'/public/assets/email/styles.css'
            ]);
        } catch (\Exception $e) {
            $res['ret'] = -1;
            $res['msg'] = "邮件发送失败，请联系网站管理员。";
            return $response->getBody()->write(json_encode($res));
        }

        $res['ret'] = 1;
        $res['msg'] = "验证码发送成功，请查收邮件。";
        return $response->getBody()->write(json_encode($res));
    }

    public function register($request, $response){
        $email = strtolower(trim($request->getParam('email')));
        $passwd = $request->getParam('passwd');
        $repasswd = $request->getParam('rpasswd');
        $code = trim($request->getParam('invitee_code'));
        $emailcode = trim($request->getParam('emailcode'));
        $c = InviteCode::where('code', $code)->first();
        if ($c == null) {
            if (Config::get('register_mode') == 'invite') {
                $res['ret'] = -1;
                $res['msg'] = "邀请码无效";
                return $response->getBody()->write(json_encode($res));
            }
        } else if ($c->user_id != 0) {
            $gift_user = User::where("id", "=", $c->user_id)->first();
            if ($gift_user == null) {
                $res['ret'] = -1;
                $res['msg'] = "邀请人不存在";
                return $response->getBody()->write(json_encode($res));
            } else if ($gift_user->class == 0) {
                $res['ret'] = -1;
                $res['msg'] = "邀请人尚未购买套餐";
                return $response->getBody()->write(json_encode($res));
            } else if ($gift_user->invite_num == 0) {
                $res['ret'] = -1;
                $res['msg'] = "邀请人可用邀请次数为0";
                return $response->getBody()->write(json_encode($res));
            }
        }

        if (!Check::isEmailLegal($email)) {
            $res['ret'] = -1;
            $res['msg'] = "邮箱无效";
            return $response->getBody()->write(json_encode($res));
        }
        // check email
        $user = User::where('email', $email)->first();
        if ($user != null) {
            $res['ret'] = -1;
            $res['msg'] = "邮箱已经被注册了";
            return $response->getBody()->write(json_encode($res));
        }
        if (Config::get('enable_email_verify') == 'true') {
            $mailcount = EmailVerify::where('email', '=', $email)->where('code', '=', $emailcode)->where('expire_in', '>', time())->first();
            if ($mailcount == null) {
                $res['ret'] = -1;
                $res['msg'] = "您的邮箱验证码不正确";
                return $response->getBody()->write(json_encode($res));
            }
        }
        // check pwd length
        if (strlen($passwd) < 8) {
            $res['ret'] = -1;
            $res['msg'] = "密码长度至少8位";
            return $response->getBody()->write(json_encode($res));
        }
        // check pwd re
        if ($passwd != $repasswd) {
            $res['ret'] = -1;
            $res['msg'] = "两次密码输入不符";
            return $response->getBody()->write(json_encode($res));
        }

        EmailVerify::where('email', '=', $email)->delete();

        $user = new User();
        $antiXss = new AntiXSS();
        $user->user_name = $antiXss->xss_clean($email);
        $user->email = $email;
        $user->pass = Hash::passwordHash($passwd);
        $user->passwd = Tools::genRandomChar(6);
        $user->port = Tools::getAvPort();
        $user->t = 0;
        $user->u = 0;
        $user->d = 0;
        $user->method = Config::get('reg_method');
        $user->protocol = Config::get('reg_protocol');
        $user->protocol_param = Config::get('reg_protocol_param');
        $user->obfs = Config::get('reg_obfs');
        $user->obfs_param = Config::get('reg_obfs_param');
        $user->forbidden_ip = Config::get('reg_forbidden_ip');
        $user->forbidden_port = Config::get('reg_forbidden_port');
        $user->transfer_enable = Tools::toGB(Config::get('defaultTraffic'));
        $user->invite_num = Config::get('inviteNum');
        $user->auto_reset_day = Config::get('reg_auto_reset_day');
        $user->auto_reset_bandwidth = Config::get('reg_auto_reset_bandwidth');
        $user->money = 0;
        //dumplin：填写邀请人，写入邀请奖励
        $user->ref_by = 0;
        if ($c != null) {
            if ($c->user_id != 0) {
                $gift_user = User::where("id", "=", $c->user_id)->first();
                $user->ref_by = $c->user_id;
                $user->money = Config::get('invite_get_money');
                $gift_user->transfer_enable = ($gift_user->transfer_enable + Config::get('invite_gift') * 1024 * 1024 * 1024);
                $gift_user->invite_num -= 1;
                $gift_user->save();
            }
        }
        $user->class_expire = date("Y-m-d H:i:s", time() + Config::get('user_class_expire_default') * 3600);
        $user->class = Config::get('user_class_default');
        $user->node_connector = Config::get('user_conn');
        $user->node_speedlimit = Config::get('user_speedlimit');
        $user->expire_in = date("Y-m-d H:i:s", time() + Config::get('user_expire_in_default') * 86400);
        $user->reg_date = date("Y-m-d H:i:s");
        $user->reg_ip = $_SERVER["REMOTE_ADDR"];
        $user->plan = 'A';
        $user->theme = Config::get('theme');
        $groups=explode(",", Config::get('ramdom_group'));
        $user->node_group=$groups[array_rand($groups)];
        $ga = new GA();
        $secret = $ga->createSecret();
        $user->ga_token = $secret;
        $user->ga_enable = 0;
        
        if ($user->save()) {
            $res['ret'] = 0;
            $res['msg'] = "注册成功！请您登录";
            return $response->getBody()->write(json_encode($res));
        }

        $res['ret'] = -1;
        $res['msg'] = "未知错误";
        return $response->getBody()->write(json_encode($res));
    }
} 