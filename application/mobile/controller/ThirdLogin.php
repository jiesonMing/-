<?php
namespace app\mobile\controller;
use app\home\logic\UsersLogic;
use think\Controller;
use think\Session;
use think\Db;
/**
 *自定义第三方登录
 *
 * @author 邓小明 2017.05.11
 */
class ThirdLogin extends Controller{
    public $weixin_config;
    public $session_id;
    public function _initialize() {
        Session::start(); 
        $this->weixin_config = M('wx_user')->find(); //获取微信配置
        $this->session_id = session_id();
    }
    //微信公众号自定义菜单
    public function customMenu(){
        session('successUrl',I('url'));//获取成功后需要跳转的地址
        //1、调转微信接口获取code
        $appid=$this->weixin_config['appid'];//appid
        $rdurl='http://'.$_SERVER['HTTP_HOST'].U('Mobile/ThirdLogin/weixinLogin');
        $rdurl= urlencode($rdurl);
        $weixinUrl="https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$rdurl}&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect";
        //header("Location:{$weixinUrl}");
        $this->redirect($weixinUrl);
        //2、判断是否有openid,有则登录
    }
    //分享
    public function myShare(){
        session('shareUid',I('shareUid'));//获取成功后需要跳转的地址
        //获取分享等级
        $shareLevel=M('users')->where('user_id='.I('shareUid'))->field('shareLevel')->find();
        session ('shareLevel',$shareLevel['shareLevel']);
        //1、调转微信接口获取code
        $appid=$this->weixin_config['appid'];//appid
        $rdurl='http://'.$_SERVER['HTTP_HOST'].U('Mobile/ThirdLogin/weixinLogin').'?shareUid='.I('shareUid').'&goodsUrl='.I('goodsUrl');
        $rdurl= urlencode($rdurl);
        $weixinUrl="https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$rdurl}&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect";
        //header("Location:{$weixinUrl}");
        $this->redirect($weixinUrl);
    }


    public function weixinLogin(){
        /*
        * 微信第三方登录，微信授权
        */
        $appid=$this->weixin_config['appid'];//"wx3afde4250377223f";//appid
        $secret=$this->weixin_config['appsecret'];//"7cfe416f46f1fb0fd6c43c49a70155f4";//秘钥
        if($_GET['code']){
                $code=$_GET['code'];
        }else{
                $erro='没有获取到code<br/>';
                echo $erro;
        }
        //通过code换取网页授权access_token
        //https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code
        $url='https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$secret.'&code='.$code.'&grant_type=authorization_code';
        $token = json_decode(file_get_contents($url));
        if (!empty($token->errcode)) {
         echo '<h1>错误：</h1>'.$token->errcode;
         echo '<br/><h2>错误信息：</h2>'.$token->errmsg;
         exit;
        }
        // $access_token_url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$appid.'&grant_type=refresh_token&refresh_token='.$token->refresh_token;
        // //转成对象
        // $access_token = json_decode(file_get_contents($access_token_url));
        // if (!empty($access_token->errcode)) {
        //  echo '<h1>错误：</h1>'.$access_token->errcode;
        //  echo '<br/><h2>错误信息：</h2>'.$access_token->errmsg;
        //  exit;
        // }
        $user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$token->access_token.'&openid='.$token->openid; 
        //转成对象
        $user_info = json_decode(file_get_contents($user_info_url));
        if (!empty($user_info->errcode)) {
         echo '<h1>错误：</h1>'.$user_info->errcode;
         echo '<br/><h2>错误信息：</h2>'.$user_info->errmsg;
         exit;
        }
        $resOpenid=M('users')->where('openid',$user_info->openid)->find();//是否有此微信用户
        if($user_info->subscribe)
            session('subscribe', $user_info->subscribe);// 当前这个用户是否关注了微信公众号//subscribe
        $userInfo=array(
                'openid'=>$user_info->openid,
                'nickname'=>$user_info->nickname,
                'sex'=>$user_info->sex,
                'province'=>$user_info->province,
                'city'=>$user_info->city,
                'head_pic'=>$user_info->headimgurl,
            );
        $userInfo['unionid']=empty($user_info->unionid)?'':$user_info->unionid;
        if($resOpenid){ 
            //已有用户
            M('users')->where('openid',$user_info->openid)->save($userInfo);//更新用户信息
            $resOpenid=M('users')->where('openid',$user_info->openid)->find();//获取用户信息
            session('user', $resOpenid);
            //dump(session('user'));exit;
            setcookie('user_id', $resOpenid['user_id'], null, '/');
            setcookie('is_distribut', $resOpenid['is_distribut'], null, '/');
            $nickname = empty($resOpenid['nickname']) ? '微信用户' : $resOpenid['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            // if($resOpenid['mobile']=='')
            //     $this->redirect('Mobile/ThirdLogin/setMobile');//验证手机号
            if(!empty(I('goodsUrl'))){
                    header("Location:".I('goodsUrl'));exit;
            }
            if(empty(session('successUrl'))){               
                header("Location:".'http://'.$_SERVER['HTTP_HOST'].U('Mobile/Index/index'));exit;
            }else{
                $url=session('successUrl');
                header("Location:".'http://'.$_SERVER['HTTP_HOST'].U($url));exit;
            }
            
        }else{
            //把用户信息用于注册会员
            $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
            $myLevel= session('shareLevel')?session('shareLevel'):1;
            $userInfo['oauth']='weixin';
            $userInfo['salt']=rand(0,999999);
            $userInfo['reg_time']=time();
            $userInfo['pay_points']=$pay_points;
            $userInfo['token']=md5(time().mt_rand(1,999999999));
            $userInfo['myLevel']=$myLevel;

            //isset($user_info->unionid)?$userInfo['unionid']=$user_info->unionid:'';
            $userInfo['parentUser']=session('shareUid')?session('shareUid'):I('shareUid');
            //session('wxUserInfo',$userInfo);
            // if(!empty(I('goodsUrl'))){
            //     session('goodsUrl',I('goodsUrl'));
            // }  
            //$this->redirect('Mobile/ThirdLogin/setMobile');//验证手机号
            //exit;
            $res=M('users')->add($userInfo);
            $user_id=M('users')->getLastInsId();
            if($res){
                //登录成功，跳转到首页
                $user = M('users')->where("user_id", $user_id)->find();
                session('user', $user);
                //dump(session('user'));exit;
                setcookie('user_id', $user['user_id'], null, '/');
                setcookie('is_distribut', $user['is_distribut'], null, '/');
                $nickname = empty($user['nickname']) ? '微信用户' : $user['nickname'];
                setcookie('uname', urlencode($nickname), null, '/');
                setcookie('cn', 0, time() - 3600, '/');
                if(!empty(I('goodsUrl'))){
                    header("Location:".I('goodsUrl'));exit;
                }  
                header("Location:".'http://'.$_SERVER['HTTP_HOST'].U('Mobile/Index/index'));exit;                
            }else{
                //添加失败，跳转首页
                header("Location:".'http://'.$_SERVER['HTTP_HOST'].U('Mobile/Index/index')); exit;              
            }
        }
        
    }
    //设置手机号
    public function setMobile(){
        if(IS_GET){
            return $this->fetch('User/setMobile');
        }else{
            $userLogic = New UsersLogic();//
            $mobile = I('post.mobile');
            $code = I('post.mobile_code');
            $password=I('post.password');
            //return array('status'=>-1,'msg'=>$this->session_id,'result'=>'');
            //先验证验证码是否正常
            $check_code = $userLogic->check_validate_code1($code,$mobile,'mobile',$this->session_id,6);
            if ($check_code['status'] != 1)
                return array('status'=>-1,'msg'=>$check_code['msg'],'result'=>'');
            //return array('status'=>-1,'msg'=>'111','result'=>'');           
            $result=$userLogic->setMobile($mobile,$password);
            if(session('goodsUrl')){
                $result['goodsUrl']=session('goodsUrl');
            } 
            return $result;
        }
    }
}
