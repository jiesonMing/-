<?php
/**
 * 微信交互类 jieson
 */ 
namespace app\home\controller;
use think\Controller;
use think\Db;
use think\Session;
class Weixin extends Controller {
    public $client;
    public $wechat_config;
    public function _initialize(){
        Session::start();
        //parent::_initialize();
        //获取微信配置信息
        $this->wechat_config = M('wx_user')->find();        
        $options = array(
 			'token'=>$this->wechat_config['w_token'], //填写你设定的key
 			'encodingaeskey'=>$this->wechat_config['aeskey'], //填写加密用的EncodingAESKey
 			'appid'=>$this->wechat_config['appid'], //填写高级调用功能的app id
 			'appsecret'=>$this->wechat_config['appsecret'], //填写高级调用功能的密钥
        		);

    }

    public function oauth(){

    }
    
    public function index(){
        if($this->wechat_config['wait_access'] == 0)        
            exit($_GET["echostr"]);
        else        
            $this->responseMsg();
    }    
    
    public function responseMsg()
    {
		//get post data, May be due to the different environments
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
      	//extract post data
	 if (empty($postStr))                 	
        	exit("");
         
                /* libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
                   the best way is to check the validity of xml by yourself */
                libxml_disable_entity_loader(true);
              	$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $fromUsername = $postObj->FromUserName;
                $toUsername = $postObj->ToUserName;
                $keyword = trim($postObj->Content);
                $time = time();
                
                //点击菜单拉取消息时的事件推送 
                /*
                 * 1、click：点击推事件
                 * 用户点击click类型按钮后，微信服务器会通过消息接口推送消息类型为event的结构给开发者（参考消息接口指南）
                 * 并且带上按钮中开发者填写的key值，开发者可以通过自定义的key值与用户进行交互；
                 */
                if($postObj->MsgType == 'event' && $postObj->Event == 'CLICK')
                {
                    $keyword = trim($postObj->EventKey);
                }
              
                
                if(empty($keyword))
                    exit("Input something...");
                
                // 图文回复
                $wx_img = M('wx_img')->where("keyword", "like", "%$keyword%")->find();
                if($wx_img)
                {
                    $textTpl = "<xml>
                                <ToUserName><![CDATA[%s]]></ToUserName>
                                <FromUserName><![CDATA[%s]]></FromUserName>
                                <CreateTime>%s</CreateTime>
                                <MsgType><![CDATA[%s]]></MsgType>
                                <ArticleCount><![CDATA[%s]]></ArticleCount>
                                <Articles>
                                    <item>
                                        <Title><![CDATA[%s]]></Title> 
                                        <Description><![CDATA[%s]]></Description>
                                        <PicUrl><![CDATA[%s]]></PicUrl>
                                        <Url><![CDATA[%s]]></Url>
                                    </item>                               
                                </Articles>
                                </xml>";                                        
                    $resultStr = sprintf($textTpl,$fromUsername,$toUsername,$time,'news','1',$wx_img['title'],$wx_img['desc']
                            , $wx_img['pic'], $wx_img['url']);
                    exit($resultStr);                   
                }
                
                
                // 文本回复
                $wx_text = M('wx_text')->where("keyword", "like","%$keyword%")->find();
                if($wx_text)
                {
                    $textTpl = "<xml>
                                <ToUserName><![CDATA[%s]]></ToUserName>
                                <FromUserName><![CDATA[%s]]></FromUserName>
                                <CreateTime>%s</CreateTime>
                                <MsgType><![CDATA[%s]]></MsgType>
                                <Content><![CDATA[%s]]></Content>
                                <FuncFlag>0</FuncFlag>
                                </xml>";                    
                    $contentStr = $wx_text['text'];
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, 'text', $contentStr);
                    exit($resultStr);                   
                }
                
                
                // 其他文本回复                
                    $textTpl = "<xml>
                                <ToUserName><![CDATA[%s]]></ToUserName>
                                <FromUserName><![CDATA[%s]]></FromUserName>
                                <CreateTime>%s</CreateTime>
                                <MsgType><![CDATA[%s]]></MsgType>
                                <Content><![CDATA[%s]]></Content>
                                <FuncFlag>0</FuncFlag>
                                </xml>";                    
                    $contentStr = '欢迎来到TPshop商城!';
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, 'text', $contentStr);
                    exit($resultStr);                   
      
    }
    //微信扫码登录 1,得到code
    public function codeBack(){
        //先验证是否是通过扫码登录进来的
        $state= session('state');
        if(empty($state)){
            $this->error('登录失效，请重新扫码登录！','http://'.$_SERVER['HTTP_HOST'].'/Home/User/login',null,3);
        }else{
            if(md5($state)!=I('get.state')){
                $this->error('非法登录，请重新扫码登录！','http://'.$_SERVER['HTTP_HOST'].'/Home/User/login',null,3);exit;
            }
        }
        $db_prex=PREFIX;
        if($db_prex == 'jia_'){
            $appid='wx824b0d5f71c3f19b';//微信开放平台上的应用appid,佳源
            $serect='785da0e0f4af5df71ac84e1f26a41589';//佳源
        }elseif($db_prex == 'vis_'){
            $appid='wxe50c6c813d9ac065';//安捷易
            $serect='641ccb10821c00c36c7450a0d9f1667c';//安捷易641ccb10821c00c36c7450a0d9f1667c
        }
        $code=I('get.code');//返回的code
        //通过code获取access_token
        $url1="https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$serect}&code={$code}&grant_type=authorization_code";
        $token= json_decode(file_get_contents($url1));
        $access_token=$token->access_token;
        $openid=$token->openid;
        //获取用户信息
        $url2="https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}";
        $userInfo=json_decode(file_get_contents($url2));
        if($userInfo->unionid){
            $wxuser=M('users')->where("oauth='weixin' and unionid='".$userInfo->unionid."'")->find();
        }
        if(!$wxuser){
            $wxuser=M('users')->where("oauth='weixin' and openid='".$userInfo->openid."'")->find();        
        }
        $data=array(
            'nickname'=>$userInfo->nickname,
            'sex'=>$userInfo->sex,
            'province'=>$userInfo->province,
            'city'=>$userInfo->city,
            'country'=>$userInfo->country,
            'head_pic'=>$userInfo->head_pic,
            'unionid'=>$userInfo->unionid,
            'openid'=>$userInfo->openid
        );
        //有微信用户，登录成功
        if($wxuser){
            //更新
            M('users')->where("user_id=".$wxuser['user_id'])->save($data);           
            //有绑定账号
            if($wxuser['bindId']!=''){
                $user=M('users')->where("user_id=".$wxuser['bindId'])->find();
                $wxuser=$user;
            }
            session('user', $wxuser);
            setcookie('user_id', $wxuser['user_id'], null, '/');
            setcookie('is_distribut', $wxuser['is_distribut'], null, '/');
            $nickname = empty($wxuser['nickname']) ? '微信用户' : $wxuser['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $this->success('登录成功','http://'.$_SERVER['HTTP_HOST']);
            header("Location:".'http://'.$_SERVER['HTTP_HOST'].U('Home/Index/index'));
        }else{
            //没有此微信用户，新增一个用户
            self::newUserLogin($data);
            $this->error('请你在微信客户端登录一下商城！','http://'.$_SERVER['HTTP_HOST'].'/Home/User/login',null,3);
        }
    }
    public function newUserLogin($data){
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        $myLevel= session('shareLevel')?session('shareLevel'):1;
        $baseData=array(
            'oauth'=>'weixin',
            'salt'=>rand(0,999999),
            'reg_time'=>time(),
            'pay_points'=>$pay_points,
            'token'=>md5(time().mt_rand(1,999999999)),
            'myLevel'=>$myLevel,
        );
        $userInfo= array_merge($data,$baseData);
        $res=M('users')->add($userInfo);
        if($res){
            session('user', $userInfo);
            setcookie('user_id', $res, null, '/');
            setcookie('is_distribut', $userInfo['is_distribut'], null, '/');
            $nickname = empty($userInfo['nickname']) ? '微信用户' : $userInfo['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $this->success('登录成功','http://'.$_SERVER['HTTP_HOST']);
            header("Location:".'http://'.$_SERVER['HTTP_HOST'].U('Home/Index/index'));
        }else{
            $this->error('登录失败，请重新扫码登录！','http://'.$_SERVER['HTTP_HOST'].'/Home/User/login',null,3);
        }
    }
}