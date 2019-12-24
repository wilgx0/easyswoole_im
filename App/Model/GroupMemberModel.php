<?php
namespace App\Model;
use EasySwoole\ORM\AbstractModel;

class GroupMemberModel extends AbstractModel{
    protected $tableName = 'cmf_group_member';
    protected $primaryKey = 'id';

    /**
     * 根据群组id获取成员列表
     */
    public function getGroupMemeberListByGroupId($id){

        $user_tablename = UserModel::create()->getTableName();
        $list =  $this->alias('gm')
            ->join($user_tablename .' as u','u.id=gm.user_id')
            ->where('gm.group_id', $id)
            ->field(['u.user_nickname as username','u.id','u.avatar','u.signature as sign'])
            ->select();

        return $list ?: [];

    }


    /**
     * 根据用户id获取群组列表
     */
    public function getGPlistByUserId($user_id){
        $group_tablename = GroupModel::create()->getTableName();
        $groups = $this
            ->alias('gm')
            ->join($group_tablename.' as g','g.id = gm.group_id')
            ->where('gm.user_id', $user_id)
            ->field(['g.id,g.groupname,g.avatar'])
            ->select();
        return $groups ?: [];
    }



}
