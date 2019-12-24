<?php
namespace App\Model;
use EasySwoole\ORM\AbstractModel;

class UserModel extends AbstractModel{
    protected $tableName = 'cmf_user';
    protected $primaryKey = 'id';

    protected $token_table = 'cmf_user_token';      //关联的token表名称
    /**
     * 根据token获取用户信息
     * @param $token
     */
    public function getUserInfoByToken(string $token){

        $info = $this->alias("a")
            ->join("{$this->token_table} as b","a.id = b.user_id")
            ->field(['a.id','a.avatar','a.user_nickname as nickname','a.user_login as username','a.signature as sign','a.status'])
            ->findOne(['b.token'=>$token]);
        if (empty($info)) {
            return null;
        }
        return $info;
    }


    /**
     * 根据id获取用户信息
     */
    public function getUserById($id){
        $info = $this->where('id',$id)
            ->field(['id','avatar','user_nickname as nickname','user_login as username','signature as sign','status'])
            ->findOne();
        return $info ?: [];
    }
}
