<?php
namespace App\Model;
use EasySwoole\ORM\AbstractModel;

class FriendModel extends AbstractModel{
    protected $tableName = 'cmf_friend';
    protected $primaryKey = 'id';

    /**
     * 根据好友分组id获取好友列表
     * @param $grounp_id
     */
    public function getFriendListByGroupId($grounp_id){
        $user_tablename = UserModel::create()->getTableName();
        $result  = $this->alias('f')
            ->join($user_tablename.' as u','u.id = f.friend_id')
            ->where('f.friend_group_id',$grounp_id)
            ->order('u.status','DESC')
            ->field(['u.user_nickname as username','u.id','u.avatar','u.signature as sign','u.status'])
            ->select();
        return $result ?: [];
    }
}
