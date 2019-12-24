<?php
namespace App\Model;
use App\HttpController\User;
use EasySwoole\ORM\AbstractModel;

class ChatRecordModel extends AbstractModel{
    protected $tableName = 'cmf_chat_record';
    protected $primaryKey = 'id';


    /**
     * 分页
     * * @param $page        页码
     * @param $limit         分页大小
     * @param $callback      回调
     * @return array
     */
    public function Page($page,$limit = 20,$callback = null){
        $user_tablename = UserModel::create()->getTableName();
        $model = $this->limit($limit * ($page - 1), $limit)->withTotalCount();

        $list =   $model
            ->alias('cr')
            ->join($user_tablename.' as u','u.id = cr.user_id')
            ->order('cr.time','DESC')
            ->field(['u.user_nickname as username','u.id','u.avatar','cr.time as timestamp','cr.content'])
            ->all($callback, true);
        $total = $model->lastQueryResult()->getTotalCount();

        return [
            'data' => $list,                    //列表数据
            'last_page'=> $total > 0 ? ceil($total/$limit) : 0          //总页数
        ];


    }


}
