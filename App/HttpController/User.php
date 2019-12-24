<?php
/**
 * Created by PhpStorm.
 * User: Double-jin
 * Date: 2019/6/19
 * Email: 605932013@qq.com
 */

namespace App\HttpController;


use \EasySwoole\RedisPool\Redis as RedisPool;
use EasySwoole\FastCache\Cache;
use EasySwoole\Mysqli\QueryBuilder;

use App\Model\ChatRecordModel;
use App\Model\GroupMemberModel;
use App\Model\SystemMessageModel;
use App\Model\UserModel;
use App\Model\GroupModel;
use App\Model\FriendModel;
use App\Model\FriendGroupModel;



/**
 * Class Index
 * @package App\HttpController
 */
class User extends Base
{



    public function test(){
//        $RedisPool = RedisPool::defer('redis');
//        $RedisPool->set('test',666);
//        var_dump($RedisPool->get('test'));
//        $res = UserModel::create()->get(1);
//        return $this->writeJson(0,'success',$res);
        //$this->render('index');
    }


    /**
     *  初始化用户信息
     */
    public function userinfo()
    {
        $user = $this->userInfo;

        //群组列表
        $groups = GroupMemberModel::create()->getGPlistByUserId($user['id']);


        //好友列表
        $friend_groups = FriendGroupModel::create()
            ->where('user_id', $user['id'])
            ->field(['id','groupname'])
            ->select();
        if($friend_groups){
            foreach ($friend_groups as $k => $v) {
                $friend_groups[$k]['list'] = FriendModel::create()->getFriendListByGroupId($v['id']);
            }
        }

        //我的信息
        $data = [
            'mine'      => [
                'username'  => $user['nickname'],
                'id'        => $user['id'],
                'status'    => $user['status'],
                'sign'      => $user['sign'],
                'avatar'    => $user['avatar']
            ],
            "friend"    => $friend_groups,
            "group"     => $groups
        ];

       // var_dump($data);
        return $this->writeJson(0,'success',$data);
   }


    /**
     * 获取群成员
     */
   public function groupMembers(){
       $params =  $this->request()->getRequestParam();
       $id =  $params['id'];

       $list =  GroupMemberModel::create()->getGroupMemeberListByGroupId($id);
       if (!count($list)) {
           return $this->writeJson(10001,"获取群成员失败");
       }
       return $this->writeJson(0,"",['list' => $list]);
   }



    /**
     * 消息盒子页面
     */
    public function messageBox(){

        $user = $this->userInfo;
        SystemMessageModel::create()
            ->where('user_id',$user['id'])
            ->update(['read' => 1]);      //设置为已读

        $list = SystemMessageModel::create()->getMessageListByUserId($user['id']);

        foreach ($list as $k => $v) {
            $list[$k]['time'] = $this->__time_tranx($v['time']);
        }

        $this->render('message_box',['list' => $list]);

    }


    /**
     * 聊天记录页面
     */
    public function chatLog(){

        if($this->request()->getMethod() == 'POST'){
            $params =  $this->request()->getRequestParam();

            $user = $this->userInfo;

            $id = $params['id'];
            $type = $params['type'];
            $page = $params['page'];


            $charRecordModel = new ChatRecordModel();

            $pageSize = 20;         //分页大小
            if ($type == 'group') {

                $list = $charRecordModel->page($page,$pageSize,function(QueryBuilder $queryBuilder) use ($id){
                    $queryBuilder->where('cr.group_id',$id);
                });

            } else {

                $list = $charRecordModel->page($page,$pageSize,function(QueryBuilder $queryBuilder) use ($user,$id){
                    $queryBuilder->where("(cr.user_id = {$user['id']} and cr.friend_id = {$id}) or (cr.user_id = {$id} and cr.friend_id = {$user['id']})");
                });
            }
            foreach ($list["data"] as $k=>$v){
                $list[$k]['timestamp'] = $v['timestamp'] * 1000;
            }

            return $this->writeJson(0,'',$list);
        }else{
            $params =  $this->request()->getRequestParam();
            //var_dump($params);
            $id = $params['id'];
            $type = $params['type'];
            $this->render('chat_log',['id' => $id,'type' => $type]);
        }
    }


    /**
     * 查找页面
     */
    public function find(){
        $params =  $this->request()->getRequestParam();

        $type = isset($params['type']) ?$params['type'] :'';
        $wd = isset($params['wd'])? $params['wd']:'';
        $user_list = [];
        $group_list = [];


        $key = '%'.$wd.'%';

        switch ($type) {
            case "user" :
                $userModel = new UserModel();

                $user_list = $userModel
                    ->where('id',$key,'like','OR')
                    ->where('user_nickname',$key,'like','OR')
                    ->where('user_login',$key,'like','OR')
                    ->field(['id','user_nickname as nickname','avatar'])
                    ->select();
                break;
            case "group" :
                $group_list = GroupModel::create()
                    ->where('id',$key,'like','OR')
                    ->where('groupname',$key,'like','OR')
                    ->field(['id','groupname','avatar'])
                    ->select();
                break;
            default :
                break;
        }

        $this->render('find', ['user_list' => $user_list,'group_list' => $group_list,'type' => $type,'wd' => $wd]);
    }




    /**
     * 时间格式化
     * @param $the_time
     * @return false|string
     */
    private function  __time_tranx($the_time)
    {
        $now_time = time();
        $dur = $now_time - $the_time;
        if ($dur <= 0) {
            $mas =  '刚刚';
        } else {
            if ($dur < 60) {
                $mas =  $dur . '秒前';
            } else {
                if ($dur < 3600) {
                    $mas =  floor($dur / 60) . '分钟前';
                } else {
                    if ($dur < 86400) {
                        $mas =  floor($dur / 3600) . '小时前';
                    } else {
                        if ($dur < 259200) { //3天内
                            $mas =  floor($dur / 86400) . '天前';
                        } else {
                            $mas =  date("Y-m-d H:i:s",$the_time);
                        }
                    }
                }
            }
        }
        return $mas;
    }



    /**
     * 添加好友界面
     */
    public function addFriend()
    {
        $params =  $this->request()->getRequestParam();
        $id = $params['id'];

        $system_message = SystemMessageModel::create()->findOne($id);

        $isFriend = FriendModel::create()->where('user_id',$system_message['user_id'])
            ->where('friend_id',$system_message['from_id'])
            ->findOne();

        if ($isFriend) {
            return $this->writeJson(10001,'已经是好友了');
        }

        $data = [
            [
                'user_id' => $system_message['user_id'],
                'friend_id' =>$system_message['from_id'],
                'friend_group_id' => $params['groupid']
            ],
            [
                'user_id' =>$system_message['from_id'],
                'friend_id' => $system_message['user_id'],
                'friend_group_id' => $system_message['group_id']
            ]
        ];
        $res = FriendModel::create()->saveAll($data);                //相互添加为好友
        if (!$res) {
            return $this->writeJson(10001,'添加失败');
        }

        SystemMessageModel::create()->where('id',$id)->update(['status' => 1]);         //标记为同意添加
        $user = UserModel::create()->getUserById($system_message['from_id']);

        $system_message_data = [
            'user_id'   => $system_message['from_id'],
            'from_id'   => $system_message['user_id'],
            'type'      => 1,
            'status'    => 1,
            'time'      => time()
        ];

        SystemMessageModel::create()->data($system_message_data,false)->save();         //请求结果通知

        $data = [
            "type"  => "friend",
            "avatar"    => $user['avatar'],
            "username" => $user['nickname'],
            "groupid" => $params['groupid'],
            "id"        => $user['id'],
            "sign"    => $user['sign']
        ];
        return $this->writeJson(200,'添加成功',$data);
    }

    /**
     * 拒绝添加好友
     */
    public function refuseFriend()
    {
        $params =  $this->request()->getRequestParam();

        $id = $params['id'];
        $systemMessageModel = new SystemMessageModel();
        $system_message = $systemMessageModel->where('id',$id)->findOne();

        $res =  $systemMessageModel->where('id',$id)->update(['status' => 2]);

        $data = [
            'user_id'   => $system_message['from_id'],
            'from_id'   => $system_message['user_id'],
            'type'      => 1,       //0好友请求 1请求结果通知
            'status'    => 2,       //0未处理 1同意 2拒绝
            'time'      => time()
        ];
        $res1 = $systemMessageModel->data($data)->save();

        if ($res && $res1){
            return $this->writeJson(200,"已拒绝");
        } else {
            return $this->writeJson(10001,"操作失败");
        }
    }


    /**
     * 通过添加面板加入群
     * @return bool
     */
    public function joinGroup()
    {
        $params =  $this->request()->getRequestParam();

        $user = $this->userInfo;

        $id = $params['groupid'];

        $GroupModel = new GroupModel();
        $GroupMemeberModel = new GroupMemberModel();

        $isIn = $GroupMemeberModel
            ->where('group_id',$id)
            ->where('user_id', $user['id'])
            ->findOne();

        if ($isIn) {
            return $this->writeJson(10001,"您已经是该群成员");
        }

        $group = $GroupModel
            ->where('id',$id)
            ->findOne();

        $res = $GroupMemeberModel
            ->data([
                'group_id' => $id,
                'user_id' => $user['id']
            ])
            ->save();

        if (!$res) {
            return $this->writeJson(10001,"加入群失败");
        }
        $data = [
            "type" => "group",
            "avatar"    => $group['avatar'],
            "groupname" => $group['groupname'],
            "id"        => $group['id']
        ];
        return $this->writeJson(200,"加入成功",$data);

    }


    /**
     * 创建群
     */
    public function createGroup()
    {
        if($this->request()->getMethod() == 'POST'){
            $params =  $this->request()->getRequestParam();

            $user = $this->userInfo;

            $data = [
                'groupname' => $params['groupname'],
                'user_id'   => $user['id'],
                'avatar'    => $params['avatar']
            ];

            $group_id = GroupModel::create($data)->save();

            if(!$group_id){
                return $this->writeJson(10001,"创建失败！");
            }


            $res_join =  GroupMemberModel::create(['group_id' => $group_id,'user_id' => $user['id']])->save();

            if ($group_id && $res_join) {
                $data = [
                    "type" => "group",
                    "avatar"    => $params['avatar'],
                    "groupname" => $params['groupname'],
                    "id"        => $group_id
                ];
                return $this->writeJson(200,"创建成功！",$data);
            } else {
                return $this->writeJson(10001,"创建失败！");
            }
        } else {
            $this->render('create_group');
        }
    }


    /**
     * 上传
     */
    public function upload(){
        $request = $this->request();
        $img_file = $request->getUploadedFile('file');

        if (!$img_file) {
            $this->writeJson(500, '请选择上传的文件');
        }

        if ($img_file->getSize() > 1024 * 1024 * 5) {
            $this->writeJson(500, '图片不能大于5M！');
        }

        $MediaType = explode("/", $img_file->getClientMediaType());
        $MediaType = $MediaType[1] ?? "";
        if (!in_array($MediaType, ['png', 'jpg', 'gif', 'jpeg', 'pem', 'ico'])) {
            $this->writeJson(500, '文件类型不正确！');
        }

        $path =  '/static/upload/';
        $dir =  EASYSWOOLE_ROOT.'/static/upload/';
        $fileName = uniqid().$img_file->getClientFileName();

        if(!is_dir($dir)) {
            mkdir($dir, 0777 , true);
        }

        $flag = $img_file->moveTo($dir.$fileName);

        $data = [
            'name' => $fileName,
            'src' => $path.$fileName,
        ];

        if($flag) {
            $this->writeJson(0, '上传成功', $data);
        } else {
            $this->writeJson(500, '上传失败');
        }
    }



}
