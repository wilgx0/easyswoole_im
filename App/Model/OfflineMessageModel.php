<?php
namespace App\Model;
use EasySwoole\ORM\AbstractModel;

class OfflineMessageModel extends AbstractModel{
    protected $tableName = 'cmf_offline_message';
    protected $primaryKey = 'id';
}
