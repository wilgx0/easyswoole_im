<?php
namespace App\Model;
use EasySwoole\ORM\AbstractModel;

class SystemMessageModel extends AbstractModel{
    protected $tableName = 'cmf_system_message';
    protected $primaryKey = 'id';


    /**
     * 获取消息盒子的信息
     */
    public function getMessageListByUserId($user_id){
        $user_table = UserModel::create()->getTableName();
        $result = $this->alias('sm')
            ->join($user_table.' as f','f.id = sm.from_id')
            ->where('user_id',$user_id)
            ->order('id', 'DESC')
            ->limit(50)
            ->field(['sm.id','f.id as uid','f.avatar','f.user_nickname as nickname','sm.remark','sm.time','sm.type','sm.group_id','sm.status'])
            ->select();

        return $result ?: [];
    }

}
