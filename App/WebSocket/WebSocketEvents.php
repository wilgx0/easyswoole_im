<?php

namespace App\WebSocket;
use \EasySwoole\RedisPool\Redis as RedisPool;
use EasySwoole\FastCache\Cache;


use App\Model\UserModel;
use App\Model\FriendModel;
use App\Model\SystemMessageModel;
use App\Model\OfflineMessageModel;
use EasySwoole\EasySwoole\Task\TaskManager;

use EasySwoole\EasySwoole\ServerManager;

/**
 * WebSocket Events
 * Class WebSocketEvents
 * @package App\WebSocket
 */
class WebSocketEvents
{
    /**
     * 打开了一个链接
     * @param swoole_websocket_server $server
     * @param swoole_http_request $request
     */
    static function onOpen(\swoole_websocket_server $server, \swoole_http_request $request)
    {
        $token = $request->get["token"];

        if(!isset($token)){
            $data = [
                "type" => "token expire"
            ];
            $server->push($request->fd, json_encode($data));
            return;
        }

        //根据token获取用户信息
        $user = UserModel::create()->getUserInfoByToken($token);

        if(empty($user)){
            $data = [
                "type" => "token expire"
            ];
            $server->push($request->fd, json_encode($data));
            return;
        }

        //记录用户信息
        $RedisPool = RedisPool::defer('redis');
        $RedisPool->set('User_token_'.$token,json_encode($user));
        //绑定fd变更状态
        Cache::getInstance()->set('uid'.$user['id'], ["value"=>$request->fd],3600);
        Cache::getInstance()->set('fd'.$request->fd, ["value"=>$user['id']],3600);

        //将用户标记为在线
        UserModel::create()->where(['id'=>$user['id']])->update(['status' => 'online']);
        //var_dump(UserModel::create()->lastQuery()->getLastQuery());

        TaskManager::getInstance()->async(function () use ($user){

            $server = ServerManager::getInstance()->getSwooleServer();
            //给好友发送上线通知，用来标记头像去除置灰
            $friend_list = FriendModel::create()->where(['user_id'=>$user['id']])->select();
            $data = [
                "type"  => "friendStatus",
                "uid"   => $user['id'],
                "status"=> 'online'
            ];

            foreach ($friend_list as $k => $v) {
                $fd = Cache::getInstance()->get('uid'.$v['friend_id']);//获取接受者fd
                if ($fd && $server->exist($fd['value'])){
                    $server->push($fd['value'], json_encode($data));//发送消息
                }
            }

            //获取未读离线消息
            $offline_messgae = OfflineMessageModel::create()
                ->where('user_id', $user['id'])
                ->where('`status`', 0)
                ->select();
            if ($offline_messgae){
                foreach ($offline_messgae as $k=>$v) {
                    $fd = Cache::getInstance()->get('uid'.$user['id']);//获取接受者fd
                    if ($fd){
                        $server->push($fd['value'], $v['data']);//发送消息
                        OfflineMessageModel::create()
                            ->where(['id'=>$v['id']])
                            ->update(['status' => 1]);
                    }
                }
            }


        });


        //获取添加好友消息数量
        $count = SystemMessageModel::create()
            ->where('user_id',$user['id'])
            ->where('`read`',0)
            ->count();
        $data = [
            "type"      => "msgBox",
            "count"     => $count
        ];
        $server->push($request->fd, json_encode($data));

    }

    /**
     * 链接被关闭时
     * @param swoole_server $server
     * @param int $fd
     * @param int $reactorId
     * @throws Exception
     */
    static function onClose(\swoole_server $server, int $fd, int $reactorId)
    {
        $uid = Cache::getInstance()->get('fd'.$fd);


        TaskManager::getInstance()->async(function () use ($uid){

            $server = ServerManager::getInstance()->getSwooleServer();
            //通知好友本人下线即头像置灰
            $friend_list = FriendModel::create()->where('user_id',$uid['value'])->select();
            $data = [
                "type"  => "friendStatus",
                "uid"   => $uid['value'],
                "status"=> 'offline'
            ];

            if ($friend_list){
                foreach ($friend_list as $k => $v) {
                    $result = Cache::getInstance()->get('uid'.$v['friend_id']);//获取接受者fd
                    if ($result && $server->exist($result['value'])){
                        $server->push($result['value'], json_encode($data));//发送消息
                    }
                }
            }
        });


        if ($uid !== false) {
            Cache::getInstance()->unset('uid'.$uid['value']);// 解绑uid映射
        }
        Cache::getInstance()->unset('fd' . $fd);// 解绑fd映射
        UserModel::create()->where('id',$uid['value'])->update(['status' => 'offline']);        //下线状态

    }
}
