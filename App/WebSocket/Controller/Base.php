<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:54
 */

namespace App\WebSocket\Controller;

use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\RedisPool\Redis as RedisPool;
/**
 * 基础控制器
 * Class Base
 * @package App\WebSocket\Controller
 */
class Base extends Controller
{
    protected $basicAction = [                //无需验证权限的方法

    ];

    protected  $userInfo = [];              //当前连接用户信息

    public function onRequest(?string $actionName):bool
    {

        $path = $this->caller()->getControllerClass()."\\".$this->caller()->getAction();
        $path = ltrim($path,"\\App\\WebSocket\\Controller\\");
        //var_dump($path);
        if (!in_array($path, $this->basicAction)){
            $info = $this->caller()->getArgs();

            if(!isset($info['token']) || empty($info['token']) ){
                $this->response()->setMessage(json_encode([
                    "type" => "token empty"
                ]));
                return false;
            }
            $RedisPool = RedisPool::defer('redis');
            $user = $RedisPool->get('User_token_' . $info['token']);
            $user = json_decode($user, true);
            if ($user == null) {
                $this->response()->setMessage(json_encode([
                    "type" => "token expire"
                ]));
                return false;
            }

            $this->userInfo = $user;

        }


        return true;
    }


    /**
     * 获取当前的用户
     * @return array|string
     * @throws Exception
     */
    protected function currentUser()
    {
        return  $this->userInfo;
    }

}
