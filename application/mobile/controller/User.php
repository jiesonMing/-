<?php
/**
* 邓小明 2017.05.02
 */
namespace app\mobile\controller;

use app\home\logic\UsersLogic;
use app\home\model\Message;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();
    public $db_prex='';
    public $weixin_config;
    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express',
        );
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            header("location:" . U('Mobile/User/login'));
            exit;
        }
        $this->weixin_config = M('wx_user')->find(); //获取微信配置
        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->db_prex=C('db_prex');
        $this->assign('order_status_coment', $order_status_coment);
        
        $user_role=M('user_role')->select();
        $this->assign('user_role',$user_role);
        
        $weixin_config = M('wx_user')->find(); //获取微信配置
        $tpshop_config=M('config')->where("inc_type='shop_info' and name='store_title'")->find();
        $appid=$weixin_config['appid'];//appid
        $appSecret=$weixin_config['appsecret'];      
        if($appSecret!=0||$appSecret!=''){
            $jssdk=new JSSDK($appid, $appSecret);//获取jssdk
            //$signPackage = $jssdk->GetSignPackage();//配置信息，签名
        }
        $url='http://'.$_SERVER['HTTP_HOST'].U('Mobile/ThirdLogin/myShare').'?shareUid='.$this->user_id;
        $imgurl=M('users')->where('user_id', $this->user_id)->field('head_pic')->find();
        $this->assign('title',$tpshop_config['value']);
        $this->assign('imgurl',$imgurl['head_pic']);
        $this->assign('link',$url);
        $this->assign('signPackage',$signPackage);
        
        
    }

    /*
     * 用户中心首页
     */
    public function  index()
    {        
        $goods_collect_count = M('goods_collect')->where("user_id", $this->user_id)->count(); // 我的商品收藏
        $comment_count = M('comment')->where("user_id", $this->user_id)->count();   // 我的评论数
        $coupon_count = M('coupon_list')->where("uid", $this->user_id)->count(); // 我的优惠券数量
        $level_name = M('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        $userInfo=M('users')->where("user_id", $this->user_id)->field('myLevel,oauth')->find();
        $order_count = M('order')->where("user_id", $this->user_id)->count(); //我的全部订单 (改)
        $count_return = M('return_goods')->where("user_id=$this->user_id and status<2")->count();   //退换货数量
        $wait_pay = M('order')->where("user_id=$this->user_id and pay_status =0 and order_status = 0  and pay_code != 'cod'")->count(); //我的待付款 (改)
        $wait_receive = M('order')->where("user_id=$this->user_id and order_status= 1 and shipping_status= 1")->count(); //我的待收货 (改)
        $comment = DB::query("select COUNT(1) as comment from __PREFIX__order_goods as og left join __PREFIX__order as o on o.order_id = og.order_id where o.user_id = $this->user_id and og.is_send = 1 and og.is_comment = 0 ");  //我的待评论订单
        $wait_comment = $comment[0][comment];
        $count_sundry_status = array($wait_pay, $wait_receive, $wait_comment, $count_return);
        $bindId=M('users')->where("mobile='' and oauth='weixin' and user_id=".$this->user_id)->field('bindId')->find();//是否有绑定pc商城
        if(empty($bindId))
            $bindId['bindId']=1;
        $this->assign('bindId',$bindId);
        $this->assign('user_id',$this->user_id);//user_id
        $this->assign('userInfo',$userInfo);//用户的会员级别，来源
        $this->assign('level_name', $level_name);
        $this->assign('order_count', $order_count); // 我的订单数 （改）
        $this->assign('goods_collect_count', $goods_collect_count);
        $this->assign('comment_count', $comment_count);
        $this->assign('coupon_count', $coupon_count);
        $this->assign('count_sundry_status', $count_sundry_status);  //各种数量       
        return $this->fetch();
    }
    public function checkPcAccount(){
        $PCuser = M('users')->where("password !='' and mobile='".$mobile."' and openid =''")->find();//pc账号
        if($PCuser){
            return array('status'=>-1,'msg'=>'存在pc账户');
        }else{
            return array('status'=>1,'msg'=>'没有pc账号,请为该手机号设置一个登录密码');
        }
    }
    //绑定PC商城账号
    public function bindingUser(){
        if(IS_POST){
            set_time_limit(0);
            $mobile=trim(I('post.username'));
            $password=trim(I('post.password'));//没有pc账号才使用该密码
            $userLogic = new UsersLogic();
            $check_code = $userLogic->check_validate_code(I('mobile_code'), $mobile, $this->session_id);
            //手机验证码
            if($check_code['status'] != 1)
                return array('status'=>-1,'msg'=>$check_code['msg']);
            //手机号是否存在PC账户
            $PCuser = M('users')->where("mobile='".$mobile."' and openid =''")->find();//pc账号
            $checkMobile=M('users')->where("oauth='weixin' and mobile='".$mobile."' and openid !=''")->find();
            if($PCuser){
                $PCuser_Id=$PCuser['user_id'];
                //存在了，把该手机号绑定到微信号
                if($checkMobile){
                    return array('status'=>-2,'msg'=>'此手机号已被占用！');
                }else{
                    //实现两个账户的绑定***
                    $WXuser = M('users')->where("oauth='weixin' and user_id=".$this->user_id)->find();//微信账号
                    $wxdata=$sqlArr=array();
                    if($WXuser['myLevel']>$PCuser['myLevel'])   $wxdata['myLevel']=$WXuser['myLevel'];
                    if($WXuser['parentUser']>0)    $wxdata['parentUser']=$WXuser['parentUser'];
                    if($WXuser['openid'])    $wxdata['openid']=$WXuser['openid'];
                    if($PCuser['head_pic']=='')      $wxdata['head_pic']=$WXuser['head_pic'];
                    if($PCuser['address_id']=='')     $wxdata['address_id']=$WXuser['address_id'];
                    if($PCuser['nickname']=='')   $wxdata['nickname']=$WXuser['nickname'];
                    
                    //修改PC账号的其他信息                    
                    $sqlArr[]="update __PREFIX__users set user_money=user_money+".$WXuser['user_money'].",pay_points=pay_points+".$WXuser['pay_points'].",frozen_money=frozen_money+".$WXuser['frozen_money'].",distribut_money=+".$WXuser['distribut_money'].",total_amount=total_amount+".$WXuser['total_amount'].",withdrawaling=withdrawaling+".$WXuser['withdrawaling']." where user_id=".$PCuser['user_id'];
                    //微信账号的所有user_id更改为pc的id***
                    $databaseArr=['account_log','cart','comment','delivery_doc','distribut_goods','feedback','goods_collect','order','rebate_log','recharge','remittance','return_goods','user_address','user_message','withdrawals'];//paylog->userId                  
                    foreach ($databaseArr as $v) {
                        $sqlArr[]="update __PREFIX__{$v} set user_id={$PCuser_Id} where user_id>0 and user_id=".$this->user_id;
                    }                   
                    M('users')->startTrans();
                    //修改微信账号的bindId
                    $resB=M('users')->where('user_id',$this->user_id)->save(array('bindId'=>$PCuser['user_id']));
                    foreach ($sqlArr as $sql) {                       
                        $resP=M('users')->execute($sql);
                    }
                    if($resB||$resP){
                        M('users')->commit();
                        M('users')->where('user_id',$PCuser['user_id'])->save($wxdata);
                        //修改下级用户
                        M('users')->where('parentUser='.$this->user_id)->save(array('parentUser'=>$PCuser['user_id']));
                        //日志信息
                        M('account_log')->add(array('user_id'=>$this->user_id,'change_time'=>time(),'desc'=>'会员绑定账号，被绑定user_id='.$PCuser_Id));
                        $user = M('users')->where('user_id='.$PCuser['user_id'])->find();
                        session('user', $user);
                        setcookie('user_id', $user['user_id'], null, '/');
                        $unionUser_id=$PCuser['user_id'].','.$this->user_id;
                        setcookie('unionUser_id',$unionUser_id);//被绑定的两个账户之间的联合id
                        setcookie('is_distribut', $user['is_distribut'], null, '/');
                        $nickname = empty($user['nickname']) ? I('post.username') : $user['nickname'];
                        setcookie('uname', $nickname, null, '/');
                        setcookie('cn', 0, time() - 3600, '/');
                        return array('status'=>1,'msg'=>'恭喜你绑定成功','result'=>$user);
                    }else{
                        M('users')->rollback();
                        return array('status'=>-3,'msg'=>'绑定失败,请稍等重试','result'=>'');
                    }                        
                }                                                                      
            }
            else{                             
                //手机号是否给微信账号绑定               
                if($checkMobile){
                    return array('status'=>-2,'msg'=>'此手机号已被占用,请更换手机号');
                }else{
                    //更新此手机号到该微信号
                    $salt=rand(100000,999999);
                    $password=md5($password.$salt);
                    $token = md5(time().mt_rand(1,99999));
                    $res=M('users')->where("oauth='weixin' and user_id=".$this->user_id)->save(array('mobile'=>$mobile,'password'=>$password,'salt'=>$salt,'token'=>$token));
                    if($res){
                        //日志信息
                        M('account_log')->add(array('user_id'=>$this->user_id,'change_time'=>time(),'desc'=>'会员绑定手机号，手机号为'.$mobile));
                        return array('status'=>1,'msg'=>'已为你微信绑定此手机号,感谢使用');
                    }else{
                        M('account_log')->add(array('user_id'=>$this->user_id,'desc'=>'会员绑定手机号，手机号为'.$mobile));
                        $sql=M('account_log')->getLastSql();
                        return array('status'=>-4,'msg'=>'绑定失败,请刷新重试');
                    }
                    
                }                    
            }

            

            // $userLogic = new UsersLogic();
            // $wxuser = M('users')->where("oauth='weixin' and user_id=".$this->user_id)->find();//微信账号
            // $user = M('users')->where("oauth='' and mobile='".trim(I('post.username'))."'")->find();//pc账号
            // $checkMobile=M('users')->where("oauth='weixin' and mobile=".trim(I('post.username')))->find();//判断此手机号是否已被使用
            // //$newPass=md5(trim(I('post.password')).$user['salt']);
            // //验证手机验证码
            // $check_code = $userLogic->check_validate_code(I('mobile_code'), trim(I('post.username')), $this->session_id);
            // if(empty($user)){
            //    M('users')->where("oauth='weixin' and user_id=".$this->user_id)->update(array('mobile'=>trim(I('post.username'))));
            //    $result = array('status'=>-1,'msg'=>'已为你微信绑定此手机号,感谢使用！');
            // }elseif($checkMobile){
            //     $result = array('status'=>-4,'msg'=>'此手机号已被占用！');
            // }
            // elseif($check_code['status'] != 1){
            //    $result = array('status'=>-2,'msg'=>'手机验证码错误!');
            // }elseif($user['is_lock'] == 1){
            //    $result = array('status'=>-3,'msg'=>'账号异常已被锁定！！！');
            // }else{
            //     //查询用户信息之后, 查询用户的登记昵称    账户等级，下级用户，微信账号订单
            //     $data1=array('bindId'=>$user['user_id']);
            //     M('users')->where('user_id='.$this->user_id)->save($data1);//修改微信账号
            //     //修改下级用户
            //     M('users')->where('parentUser='.$this->user_id)->save(array('parentUser'=>$user['user_id']));
            //    //修改微信账号订单
            //     M('order')->where('user_id='.$this->user_id)->save(array('user_id'=>$user['user_id']));
                
            //     $data2=array('openid'=>$wxuser['openid']);//获取微信openid
            //     //修改主账号等级
            //     if($wxuser['myLevel']>$user['myLevel']){
            //         $data2['myLevel']=$wxuser['myLevel']; 
            //     }
            //     if($user['head_pic']==''){//pc账号头像没设置
            //         $data2['head_pic']=$wxuser['head_pic'];
            //     }
            //     if($user['address_id']=='')
            //         $data2['address_id']=$wxuser['address_id'];
            //     if($user['is_distribut']=='')
            //         $data2['is_distribut']=$wxuser['is_distribut'];
            //     //修改PC账号的其他信息
            //     $sql="update __PREFIX__users set user_money=user_money+".$wxuser['user_money'].",pay_points=pay_points+".$wxuser['pay_points'].",frozen_money=frozen_money+".$wxuser['frozen_money'].",distribut_money=+".$wxuser['distribut_money'].
            //             ",total_amount=total_amount+".$wxuser['total_amount']." where user_id=".$user['user_id'];
            //     M('users')->where('user_id='.$user['user_id'])->save($data2);//修改PC账号
            //     M('users')->execute($sql);
            //     //绑定账号成功后，更新用户的信息
            //     $user = M('users')->where('user_id='.$user['user_id'])->find();
            //     session('user', $user);
            //     setcookie('user_id', $user['user_id'], null, '/');
            //     setcookie('is_distribut', $user['is_distribut'], null, '/');
            //     $nickname = empty($user['nickname']) ? I('post.username') : $user['nickname'];
            //     setcookie('uname', $nickname, null, '/');
            //     setcookie('cn', 0, time() - 3600, '/');
                               
            //    $result = array('status'=>1,'msg'=>'恭喜你绑定成功','result'=>$user);
            // }
            //$result['url'] = urldecode(I('post.referurl'));
            //exit(json_encode($result));
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");// $_SERVER['HTTP_REFERER']获取前一页面的url
        $this->assign('referurl', $referurl);
        return $this->fetch();
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }
    /*
     * 新开的下级账号
     */
    public function myUser(){
        $where['parentUser']=$this->user_id;
        if(!empty(session('unionUser_id')))
            $where['parentUser']="in (".session('unionUser_id').")";
        $myUser=M('users')->where($where)->order('user_id desc')->select();
        $this->assign('myUser',$myUser);
        return $this->fetch();
    }
    /*
     * 提成订单
     */
    public function mySonOrder(){
        if(IS_POST){
            //下单计算预计提成
            if(is_array(I('order_id'))){
                foreach (I('order_id') as $v){
                    $yujiTicheng=self::calculateTicheng($v);
                    if($yujiTicheng<0)
                        $yujiTicheng=0;
                    //更新预计提成
                    M('order')->where('order_id',$v)->save(array('yujTicheng'=>$yujiTicheng));
                }
            }else{
                $yujiTicheng=self::calculateTicheng(I('order_id'));
                if($yujiTicheng<0)
                    $yujiTicheng=0;
                //更新预计提成
                M('order')->where('order_id',I('order_id'))->save(array('yujTicheng'=>$yujiTicheng));
            }
            echo 1;
        }else{
            $sql="select o.order_status,o.yujTicheng,o.order_id,u.nickname,o.consignee,o.order_Sn,o.add_time from __PREFIX__order o left join __PREFIX__users u on u.user_id=o.user_id where u.parentUser=".$this->user_id." order by order_id desc";
            $sonOrder=M('order')->query($sql);
            foreach ($sonOrder as $k=>$v){
                if($v['yujTicheng']==0){
                    $yujiTicheng=self::calculateTicheng($v['order_id']);
                    $sonOrder[$k]['yujTicheng']=$yujiTicheng;
                    //更新预计提成
                    M('order')->where('order_id',$v['order_id'])->save(array('yujTicheng'=>$yujiTicheng));
                }
            }
            //dump($sonOrder);exit;
            $this->assign('sonOrder',$sonOrder);
            return $this->fetch();
        }
    }
    //计算提成
    public function calculateTicheng($order_id){
        set_time_limit(0);       
        $sql="select o.*,u.myLevel,u.parentUser from __PREFIX__order o left join __PREFIX__users u on u.user_id=o.user_id where o.order_id=".$order_id;
        $orderData=M('order')->query($sql);
        //return $amount=$orderData[0]['parentUser'];
        if($orderData[0]['parentUser']!=''){
            $current_level=$orderData[0];//当前订单会员等级
            $parent_level=M('users')->where('user_id',$orderData[0]['parentUser'])->field('myLevel')->find();//当前订单上级会员的等级
            //查找当前订单下所有的商品
            $amount=0;
            $order_goods = M('order_goods')->where("order_id",$order_id)->select();
            foreach ($order_goods as $v){
                $goods=M('goods')->where("goods_id",$v['goods_id'])->find();
                //是否是同一级别
                if(($current_level['myLevel']==$parent_level['myLevel']) || ($current_level['myLevel']!='' && $parent_level['myLevel']!=3)){
                    //级别价
                    if($current_level['myLevel']==0){ 
                        if($v['goodsRate']>0)
                            $amount+=($goods['shop_price']*$goods['commissionPoint'])*$v['goods_num']*(1-$v['goodsRate']);
                        else
                            $amount+=($goods['shop_price']*$goods['commissionPoint'])*$v['goods_num'];
                    }elseif($current_level['myLevel']==1){
                        if($v['goodsRate']>0)
                            $amount+=($goods['thirdMemberPrice']*$goods['commissionPoint'])*$v['goods_num']*(1-$v['goodsRate']);
                        else
                            $amount+=($goods['thirdMemberPrice']*$goods['commissionPoint'])*$v['goods_num'];
                    }elseif ($current_level['myLevel']==2) {
                        if($v['goodsRate']>0)
                            $amount+=($goods['secondMemberPrice']*$goods['commissionPoint'])*$v['goods_num']*(1-$v['goodsRate']);
                        else
                            $amount+=($goods['secondMemberPrice']*$goods['commissionPoint'])*$v['goods_num'];
                    }elseif ($current_level['myLevel']==3) {
                        if($v['goodsRate']>0)
                            $amount+=($goods['firstMemberPrice']*$goods['commissionPoint'])*$v['goods_num']*(1-$v['goodsRate']);
                        else
                            $amount+=($goods['firstMemberPrice']*$goods['commissionPoint'])*$v['goods_num'];
                    }
                    //$amount=1;
                //主账号为一级会员
                }elseif($parent_level['myLevel']==3 && $current_level['myLevel']!=3){
                    //级别价
                    if($current_level['myLevel']==0){
                        if($v['goodsRate']>0)
                            $amount+=($goods['shop_price']-$goods['firstMemberPrice'])*$v['goods_num']*(1-$v['goodsRate']);
                        else
                            $amount+=($goods['shop_price']-$goods['firstMemberPrice'])*$v['goods_num'];
                    }elseif($current_level['myLevel']==1){
                        if($v['goodsRate']>0)
                            $amount+=($goods['thirdMemberPrice']-$goods['firstMemberPrice'])*$v['goods_num']*(1-$v['goodsRate']);
                        else
                            $amount+=($goods['thirdMemberPrice']-$goods['firstMemberPrice'])*$v['goods_num'];
                    }elseif ($current_level['myLevel']==2) {
                        if($v['goodsRate']>0)
                            $amount+=($goods['secondMemberPrice']-$goods['firstMemberPrice'])*$v['goods_num']*(1-$v['goodsRate']);
                        else
                            $amount+=($goods['secondMemberPrice']-$goods['firstMemberPrice'])*$v['goods_num'];
                    }
                    //$amount=2;
                }
            }
            return $amount;
        }
    }
    /*
     * 添加下级账号
     */
    public function add_myUser(){
        if(IS_POST){
            $salt=rand(0,999999);
            $password=strip_tags(trim(I('post.password')));
            $data=array(
                'nickname'  => strip_tags(trim(I('post.nickname'))),
                'password'  => md5($password.$salt),
                'mobile'    => strip_tags(trim(I('post.mobile'))),
                'email'     => strip_tags(trim(I('post.email'))),
                'myLevel'   => strip_tags(trim(I('post.myLevel'))),
                'parentUser'=> $this->user_id,
                'salt'      => $salt,
                'time'      => time(),
                'pay_points'=> '100'
            );
            if(!empty(I('post.user_id'))){
                $res=M('users')->where('user_id='.I('post.user_id'))->save($data);
            }else{
                $res=M('users')->add($data);
            }
            if($res){
                $this->success('添加成功', U('/Mobile/User/myUser'));
                exit();
            }
        }
        if(I('id')){
            $myUser=M('users')->where('user_id='.I('id'))->find();
            $this->assign('myUser',$myUser);
        }
        $role=M('users')->where('user_id',$this->user_id)->field('myLevel')->find();
        $user_role=M('user_role')->where("role_level<=".$role['myLevel'])->select();
        $this->assign('user_role',$user_role);
        return $this->fetch();
    }
    /*
     * 我的分享
     */
    public function myShare(){
        $tpshop_config=M('config')->where("inc_type='shop_info' and name='store_title'")->find();
        $appid=$this->weixin_config['appid'];//appid
        $appSecret=$this->weixin_config['appsecret'];
        $rdurl='http://'.$_SERVER['HTTP_HOST'].U('Mobile/ThirdLogin/weixinLogin').'?shareUid='.$this->user_id;//"http://www.whole-shougi.com/mobile/User/thirdLogin?shareUid=".$this->user_id;//$_SERVER['HTTP_HOST'].U('Mobile/User/index');
        $rdurl= urlencode($rdurl);
        $weixinUrl="https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$rdurl}&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect";
        $jssdk=new JSSDK($appid, $appSecret);//获取jssdk
        $signPackage = $jssdk->GetSignPackage();//配置信息，签名
        $url='http://'.$_SERVER['HTTP_HOST'].U('Mobile/ThirdLogin/myShare').'?shareUid='.$this->user_id;
        $imgurl=M('users')->where('user_id', $this->user_id)->field('head_pic')->find();
        //分享了几个下级用户
        $shereNum=M('users')->where("parentUser=".$this->user_id)->count();
        $this->assign('shareNum',$shereNum); 
        $this->assign('title',$tpshop_config['value']);
        $this->assign('imgurl',$imgurl['head_pic']);
        $this->assign('link',$url);
        $this->assign('signPackage',$signPackage);
        $this->assign('url',$weixinUrl);
        return $this->fetch();
    }
    //设置分享等级
    public function settingShareLevel(){
        if(IS_POST){
            $res=M('users')->where('user_id',$this->user_id)->save(array('shareLevel'=>I('post.role_level')));
            if($res){
                echo '成功';die;
            }
        }else{
            $Level=M('users')->where('user_id',$this->user_id)->field('myLevel,shareLevel')->find();
            $level=M('user_role')->where('role_level <='.$Level['myLevel'])->select();
            $this->assign('level',$level);
            $this->assign('mylevel',$Level['myLevel']);
            $this->assign('shareLevel',$Level['shareLevel']);
            return $this->fetch();
        }
        
    }

    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     * 确定订单的使用优惠券
     * @author lxl
     * @time 2017
     */
    public function checkcoupon()
    {
        $cartLogic = new \app\home\logic\CartLogic();
        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        $result = $cartLogic->cartList($this->user, $this->session_id,1,1); // 获取购物车商品
        if(I('type') == ''){
            $where = " c2.uid = {$this->user_id} and ".time()." < c1.use_end_time and c1.condition <= {$result['total_price']['total_fee']} ";
        }
        if(I('type') == '1'){
           $where = " c2.uid = {$this->user_id} and c1.use_end_time < ".time()." or {$result['total_price']['total_fee']}  < c1.condition ";
        }

        $coupon_list = DB::name('coupon')
            ->alias('c1')
            ->field('c1.name,c1.money,c1.condition,c1.use_end_time, c2.*')
            ->join('coupon_list c2','c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0','LEFT')
            ->where($where)
            ->select();
        $this->assign('coupon_list', $coupon_list); // 优惠券列表
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
//            header("Location: " . U('Mobile/User/index'));
        }
        //获取微信授权登录
        $appid=$this->weixin_config['appid'];//appid
        $rdurl='http://'.$_SERVER['HTTP_HOST'].U('Mobile/ThirdLogin/weixinLogin');//"http://www.whole-shougi.com/mobile/User/thirdLogin?shareUid=".$this->user_id;//$_SERVER['HTTP_HOST'].U('Mobile/User/index');
        //$rdurl= urlencode($rdurl);
        $weixinUrl="https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$rdurl}&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect";
        $this->assign('weixinUrl',$weixinUrl);
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");// $_SERVER['HTTP_REFERER']获取前一页面的url
        $this->assign('referurl', $referurl);
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = strip_tags(trim(I('post.username')));
        $password = strip_tags(trim(I('post.password')));

        //验证码验证
        $verify_code = I('post.verify_code');
        $verify = new Verify();
        if (!$verify->check($verify_code, 'user_login')) {
            $res = array('status' => 0, 'msg' => '验证码错误');
            exit(json_encode($res));
        }
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            if($res['result']['bindId']){
                $user=M('users')->where("user_id=".$res['result']['bindId'])->find();
                $res['result']=$user;
            }
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', $nickname, null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new \app\home\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $res['result']['user_id']);  //用户登录后 需要对购物车 一些操作
        }

        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

    	if($this->user_id > 0) header("Location: ".U('Mobile/User/index'));
        // $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            //是否开启注册验证码机制
            // $code = I('post.mobile_code', '');
            // $session_id = session_id();
            
            // if(check_mobile($username)){
            //     $check_code = $logic->check_validate_code($code, $username, $session_id);
            //     if($check_code['status'] != 1){
            //         $this->error($check_code['msg']);
            //     }
            // }
            //是否开启注册邮箱验证码机制
            // if(check_email($username)){
            //     $check_code = $logic->check_validate_code($code, $username);
            //     if($check_code['status'] != 1){
            //         $this->error($check_code['msg']);
            //     }
            // }

            $data = $logic->reg($username, $password, $password2);
            if ($data['status'] != 1)
                $this->error($data['msg']);
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new \app\home\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
            $this->success($data['msg'], U('Mobile/User/index'));
            exit;
        }
        // $this->assign('regis_sms_enable',$reg_sms_enable); // 注册启用短信：
        // $this->assign('regis_smtp_enable',$reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    /*
     * 订单列表
     */
    public function order_list()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索
        if (in_array(strtoupper(I('type')), array('WAITCCOMMENT', 'COMMENTED'))) {
            $where .= " AND order_status in(2,4) "; //代评价 和 已评价
        } elseif (I('type')) {
            $where .= C(strtoupper(I('type')));
        }
        $count = M('order')->where($where)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $order_str = "order_id DESC";       
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            //self::checkGoodsPriceChange($v['order_id']);//商品价格与订单商品的价格不同则订单作废
            self::checkGoodsPriceChange($v['order_id']);
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }
        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = '';
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
        }
        //计算 待付款、待发货、待收货
        $waitpayNum=M('order')->where('(pay_status=0 or pay_status=2) and order_status=0 and deleted=0 and user_id='.$this->user_id)->count();
        $waitsendNum=M('order')->where('pay_status=1 and shipping_status=0 and deleted=0 and order_status=0 and user_id='.$this->user_id)->count();
        $waitrecviveNum=M('order')->where('shipping_status=1 and order_status=1 and deleted=0 and user_id='.$this->user_id)->count();
        $this->assign('waitpayNum',$waitpayNum);
        $this->assign('waitsendNum',$waitsendNum);
        $this->assign('waitrecviveNum',$waitrecviveNum);
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('page', $show);
        $this->assign('lists', $order_list);
        $this->assign('active', 'order_list');
        $this->assign('active_status', I('get.type'));
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_order_list');
            exit;
        }
        return $this->fetch();
    }
    //查询订单的商品价格是否变化
    public function checkGoodsPriceChange($order_id){
        $model=M('order');
        //先查询用户的等级
        $sql="select u.myLevel,o.add_time from __PREFIX__order o left join __PREFIX__users u on u.user_id=o.user_id left join __PREFIX__order_goods ogs on ogs.order_id=o.order_id where ogs.prom_type=0 and o.order_status=0 and o.deleted=0 and o.pay_status not in (1,2) and o.order_id={$order_id}";
        $resUserLevel=$model->query($sql);
        if($resUserLevel){
            if($resUserLevel[0]['myLevel']==0){
                $userGoodsPrice="shop_price";
            }elseif($resUserLevel[0]['myLevel']==1){
                $userGoodsPrice="thirdMemberPrice";
            }
            elseif($resUserLevel[0]['myLevel']==2){
                $userGoodsPrice="secondMemberPrice";
            }
            elseif($resUserLevel[0]['myLevel']==3){
                $userGoodsPrice="firstMemberPrice";
            }
            $sql="select gs.{$userGoodsPrice} as goods_price,ogs.member_goods_price from __PREFIX__order_goods ogs left join __PREFIX__goods gs on gs.goods_id=ogs.goods_id where ogs.prom_type=0 and ogs.order_id={$order_id}";
            $resGoodsPrice=$model->query($sql);
            if(!empty($resGoodsPrice)){
                foreach ($resGoodsPrice as $k => $v) {
                    if($v['goods_price'] != $v['member_goods_price']){
                        $res1=$model->where("pay_status not in (1,2) and order_status=0 and order_id=".$order_id)->save(array('order_status'=>5));
                    }
                }
                //记录
                if($res1)
                    M('action')->add(array('username'=>'mobile订单作废','tabName'=>'order','tabField'=>'order_id='.$order_id,'notes'=>'mobile订单作废'));
            }
            
        }
        //超时处理
        if(!empty($resUserLevel[0]['add_time']) && ($resUserLevel[0]['add_time']+(60*60*24))<time()){
            $res2=M('order')->where("pay_status not in (1,2) and order_status=0 and order_id=".$order_id)->save(array('order_status'=>7));
            //记录
            if($res2)
                M('action')->add(array('username'=>'mobile订单超时','tabName'=>'order','tabField'=>'order_id='.$order_id,'notes'=>'mobile订单超时'));
        }
        return true;
    }

    /*
     * 订单列表
     */
    public function ajax_order_list()
    {

    }

    /*
     * 订单详情
     */
    public function order_detail()
    {
        $id = I('get.id/d');
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];

        $region_list = get_region_list();
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $id))->select();
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('region_list', $region_list);
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);

        if (I('waitreceive')) {  //待收货详情
            return $this->fetch('wait_receive_detail');
        }
        return $this->fetch();
    }

    public function express()
    {
        $order_id = I('get.order_id/d', 195);
        $order_goods = M('order_goods')->where("order_id", $order_id)->select();
        $delivery = M('order')->where("order_id", $order_id)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('delivery', $delivery);
        return $this->fetch();
    }

    /*
     * 取消订单
     */
    public function cancel_order()
    {
        $id = I('get.id/d');
        //检查是否有积分，余额支付
        $logic = new UsersLogic();
        $data = $logic->cancel_order($this->user_id, $id);
        if ($data['status'] < 0)
            $this->error($data['msg']);
        $this->success($data['msg']);
    }
    //申请退款
    public function refund_order(){
        $res=M('order')->where('order_id',I('order_id'))->save(array('order_status'=>6));
        if($res){
            $this->success('申请成功');
        }else{
            $this->error('申请失败');
        }
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, I('post.'));
            if ($data['status'] != 1)
                $this->error($data['msg']);
            elseif (I('post.source') == 'cart2') {
                header('Location:' . U('/Mobile/Cart/cart2', array('address_id' => $data['result'])));
                exit;
            }

            $this->success($data['msg'], U('/Mobile/User/address_list'));
            exit();
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        //return $this->fetch('edit_address');
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, I('post.'));
            if ($_POST['source'] == 'cart2') {
                header('Location:' . U('/Mobile/Cart/cart2', array('address_id' => $id)));
                exit;
            } else
                $this->success($data['msg'], U('/Mobile/User/address_list'));
            exit();
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }

    /*
     * 评论晒单
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status');
        $logic = new UsersLogic();
        $result = $logic->get_comment($user_id, $status); //获取评论列表
        $this->assign('comment_list', $result['result']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_comment_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *添加评论
     */
    public function add_comment()
    {
        if (IS_POST) {
            // 晒图片
            $files = request()->file('comment_img_file');
            $save_url = 'public/upload/comment/' . date('Y', time()) . '/' . date('m-d', time());
            foreach ($files as $file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $info = $file->rule('uniqid')->validate(['size' => 1024 * 1024 * 3, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img[] = '/'.$save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }
            }
            if (!empty($comment_img)) {
                $add['img'] = serialize($comment_img);
            }

            $user_info = session('user');
            $logic = new UsersLogic();
            $add['goods_id'] = I('goods_id');
            $add['email'] = $user_info['email'];
            $hide_username = I('hide_username');
            if (empty($hide_username)) {
                $add['username'] = $user_info['nickname'];
            }
            $add['order_id'] = I('order_id');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            $add['is'] = I('goods_rank');
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = getIP();
            $add['user_id'] = $this->user_id;

            //添加评论
            $row = $logic->add_comment($add);
            if ($row['status'] == 1) {
                //评论完成 结算分销提成
                calculateTicheng(4,I('order_id'));
                $this->success('评论成功', U('/Mobile/Goods/goodsInfo', array('id' => $add['goods_id'])));
                exit();
            } else {
                $this->error($row['msg']);
            }
        }
        $rec_id = I('rec_id');
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        $this->assign('order_goods', $order_goods);
        return $this->fetch();
    }

    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机

            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, $this->session_id);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            $this->success("操作成功");
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
            exit;
        }
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('message');

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }

    /*
     * 密码修改
     */
    public function password()
    {
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if ($user['mobile'] == '' && $user['email'] == '')
            $this->error('请先到电脑端绑定手机', U('/Mobile/User/index'));
        if (IS_POST) {
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password')); // 获取用户信息
            if ($data['status'] == -1)
                $this->error($data['msg']);
            $this->success($data['msg']);
            exit;
        }
        return $this->fetch();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
//            header("Location: " . U('User/Index'));`
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                $this->verifyHandle('forget');
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field));
                    header("Location: " . U('User/find_pwd'));
                    exit;
                } else {
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
//            header("Location: " . U('User/Index'));
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        if (empty($check)) {
            header("Location:" . U('User/forget_pwd'));
        } elseif ($check['is_check'] == 0) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            if ($check['is_check'] == 1) {
                //$user = get_user_info($check['sender'],1);
                $user = M('users')->where("mobile", $check['sender'])->whereOr('email', $check['sender'])->find();
                M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($password)));
                session('validate_code', null);
                //header("Location:".U('User/set_pwd',array('is_set'=>1)));
                $this->success('新密码已设置行牢记新密码', U('User/index'));
                exit;
            } else {
                $this->error('验证码还未验证通过', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    //发送验证码
    public function send_validate_code()
    {
        $type = I('type');
        $send = I('send');
        $logic = new UsersLogic();
        $res = $logic->send_validate_code($send, $type);
        $this->ajaxReturn($res);

    }

    public function check_validate_code()
    {
        $code = I('post.code');
        $send = I('send');
        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $send);
        $this->ajaxReturn($res);
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    /**
     * 确定收货成功
     */
    public function order_confirm()
    {
        $id = I('get.id/d', 0);
        $data = confirm_order($id, $this->user_id);
        if (!$data['status']) {
            $this->error($data['msg']);
        } else {
            $model = new UsersLogic();
            $order_goods = $model->get_order_goods($id);
            $this->assign('order_goods', $order_goods);
            return $this->fetch();
            exit;
        }
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {
        $order_id = I('order_id/d', 0);
        $order_sn = I('order_sn', 0);
        $goods_id = I('goods_id/d', 0);
        $good_number = I('good_number', 0); //申请数量
        $spec_key = I('spec_key');
        $c = M('order')->where(['order_id' => $order_id, 'user_id' => $this->user_id])->count();
        if ($c == 0) {
            $this->error('非法操作');
            exit;
        }

        $return_goods = M('return_goods')
            ->where(['order_id' => $order_id, 'goods_id' => $goods_id, 'spec_key' => $spec_key])
            ->find();
        if (!empty($return_goods)) {
            $this->success('已经提交过退货申请!', U('Mobile/User/return_goods_info', array('id' => $return_goods['id'])));
            exit;
        }
        if (IS_POST) {
            // 晒图片
            if (count($_FILES['return_imgs']['tmp_name'])>0) {
                $files = request()->file('return_imgs');
                $save_url = 'public/upload/return_goods/' . date('Y', time()) . '/' . date('m-d', time());
                foreach ($files as $file) {
                    // 移动到框架应用根目录/public/uploads/ 目录下
                    $info = $file->rule('uniqid')->validate(['size' => 1024 * 1024 * 3, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                    if ($info) {
                        // 成功上传后 获取上传信息
                        $return_imgs[] = '/'.$save_url . '/' . $info->getFilename();
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }
                if (!empty($return_imgs)) {
                    $data['imgs'] = implode(',', $return_imgs);
                }
            }
            $data['order_id'] = $order_id;
            $data['order_sn'] = $order_sn;
            $data['goods_id'] = $goods_id;
            $data['addtime'] = time();
            $data['user_id'] = $this->user_id;
            $data['type'] = I('type'); // 服务类型  退货 或者 换货
            $data['reason'] = I('reason'); // 问题描述     
            $data['spec_key'] = I('spec_key'); // 商品规格						       
            $res = M('return_goods')->add($data);
            $data['return_id'] = $res;  //退换货id
            $this->assign('data',$data);
            return $this->fetch('return_good_success'); //申请成功
//            $this->success('申请成功,客服第一时间会帮你处理', U('Mobile/User/order_list'));
            exit;
        }

//        $goods = M('goods')->where("goods_id", $goods_id)->find();
        $goods = M('order_goods')->where("goods_id", $goods_id)->find();
        //查找订单收货地址
        $region = M('order')->field('consignee,country,province,city,district,twon,address,mobile')->where("order_id = $order_id")->find();
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('region', $region);
        $this->assign('goods', $goods);
        $this->assign('order_id', $order_id);
        $this->assign('order_sn', $order_sn);
        $this->assign('goods_id', $goods_id);

        return $this->fetch();
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        //退换货商品信息
        $count = M('return_goods')->where("user_id", $this->user_id)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('return_goods')->where("user_id", $this->user_id)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');  //获取商品ID
        if (!empty($goods_id_arr)){
            $goodsList = M('goods')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        }

        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('page', $page->show());// 赋值分页输出
        if (I('is_ajax')) {
            return $this->fetch('ajax_return_goods_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id = I('id', 0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
        $this->assign('goods', $goods);
        $this->assign('return_goods', $return_goods);
        return $this->fetch();
    }


    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment_where = array(
            'status'=>1,
            'isMobile'=>1,
            'scene'=>array('in',array(0,2))
        );      
        $paymentList = M('Pay_type')->where($payment_where)->select();
        $this->assign('paymentList',$paymentList); 
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {

        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('withdrawals');
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min = tpCache('basic.min'); // 最少提现额度
            if ($data['money'] < $distribut_min) {
                $this->error('每次最少提现额度' . $distribut_min);
                exit;
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->error("你最多可提现{$this->user['user_money']}账户余额.");
                exit;
            }
            $withdrawal = M('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('money');
            if ($this->user['user_money'] < ($withdrawal + $data['money'])) {
                $this->error('您有提现申请待处理，本次提现余额不足');
            }
            if (M('withdrawals')->add($data)) {
                //把用户余额变为提现中金额
                $sql="update __PREFIX__users set user_money=user_money-{$data['money']},withdrawaling=withdrawaling+{$data['money']} where user_id=". $this->user_id;
                M('users')->execute($sql);
                $this->success("已提交申请");
                exit;
            } else {
                $this->error('提交失败,联系客服!');
                exit;
            }
        }

        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
            exit;
        }
        $where["user_id"]=$this->user_id;
        $where["status"]=1;
        $moneyed=M('withdrawals')->where($where)->field('sum(money) as money')->find();
        $this->assign('user_money', $this->user['user_money']);    //可提现
        $this->assign('withdrawaling', $this->user['withdrawaling']);    //申请中
        $this->assign('money', $moneyed['money']);    //已提现
        return $this->fetch();
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     * 删除已取消的订单
     */
    public function order_del()
    {
        $user_id = $this->user_id;
        $order_id = I('get.order_id');
        $order = M('order')->where(array('order_id' => $order_id, 'user_id' => $user_id))->find();
        if (empty($order)) {
            return $this->error('订单不存在');
            exit;
        }
        $res = M('order')->where("order_id=$order_id and order_status=3")->delete();
        $result = M('order_goods')->where("order_id=$order_id")->delete();
        if ($res && $result) {
            return $this->success('成功', "mobile/User/order_list");
            exit;
        } else {
            return $this->error('删除失败');
            exit;
        }
    }

    /**
     * 我的关注
     * $author lxl
     * $time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     * 待收货列表
     * $author lxl
     * $time   2017/1
     */
    public function wait_receive()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索
        if (I('type') == 'WAITRECEIVE') {
            $where .= C(strtoupper(I('type')));
        }
        $count = M('order')->where($where)->count();
        $pagesize = C('PAGESIZE');
        $Page = new Page($count, $pagesize);
        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }

        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = '';
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
            //订单物流单号
            $invoice_no = M('DeliveryDoc')->where("order_id", $value['order_id'])->getField('invoice_no', true);
            $order_list[$key][invoice_no] = implode(' , ', $invoice_no);
        }
        $this->assign('page', $show);
        $this->assign('order_list', $order_list);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_wait_receive');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        return $this->fetch('user/message_notice');
    }

    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type', 0);
        $user_logic = new UsersLogic();
        $message_model = new Message();
        if ($type == 1) {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
            $user_logic->setSysMessageForRead();
        } else if ($type == 2) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $this->assign('messages', $user_sys_message);
        return $this->fetch('user/ajax_message_notice');

    }

    /**
     * 设置消息通知
     */
    public function set_notice(){
        //暂无数据
        return $this->fetch();
    }
    

}

