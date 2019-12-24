<?php
/**
 * Created by PhpStorm.
 * User: Double-jin
 * Date: 2019/6/19
 * Email: 605932013@qq.com
 */


namespace App\HttpController;


use EasySwoole\EasySwoole\Config;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Template\Render;
use App\Utility\PlatesRender;



use \EasySwoole\RedisPool\Redis as RedisPool;
/**
 * 基础控制器
 * Class Base
 * @package App\HttpController
 */
class Base extends Controller
{

    protected $basicAction = [                //无需验证权限的api

    ];

    protected  $userInfo = [];
    protected $token = '';
    const SERVER = "http://192.168.0.111:9501";

    public function onRequest(?string $action): ?bool
    {
        if (!parent::onRequest($action)) {
            return false;
        };

        $path = $this->request()->getUri()->getPath();
        if (!in_array($path, $this->basicAction)){
            $token = $this->request()->getRequestParam('token');

            if(!$token){
                $this->writeJson(10001,"token获取失败");
                return false;
            }

            $RedisPool = RedisPool::defer('redis');
            $user = $RedisPool->get('User_token_'.$token);
            if (empty($user)) {
                $this->writeJson(10001,"获取用户信息失败");
                return false;
            } else {
                $this->userInfo = json_decode($user,true);
                $this->token = $token;
            }
        }


        return true;

    }

    function index()
    {
        $this->actionNotFound('index');
    }


    /**
     * 分离式渲染
     * @param $template
     * @param $vars
     */
    function render($template, array $vars = [])
    {

        $vars = array_merge($vars,[
            'server'=>self::SERVER,
            'token' =>$this->token
        ]);
        $engine = new PlatesRender(EASYSWOOLE_ROOT . '/App/Views');
        $render = Render::getInstance();
        $render->getConfig()->setRender($engine);
        $content = $engine->render($template, $vars);
        $this->response()->write($content);
    }

    /**
     * 获取配置值
     * @param $name
     * @param null $default
     * @return array|mixed|null
     */
    function cfgValue($name, $default = null)
    {
        $value = Config::getInstance()->getConf($name);
        return is_null($value) ? $default : $value;
    }


    protected function writeJson($statusCode = 200, $msg = null,$result = null)
    {
        if (!$this->response()->isEndResponse()) {
            $data = Array(
                "code" => $statusCode,
                "data" => $result,
                "msg" => $msg
            );
            $this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            $this->response()->withStatus($statusCode);
            return true;
        } else {
            return false;
        }
    }
}
