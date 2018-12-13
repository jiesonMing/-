<?php
/**
 * $Author: 当燃 2016-01-09
 */
namespace app\mobile\controller;
use app\home\logic\UsersLogic;
use Think\Db;
class Index extends MobileBase {
    public $db_prex='';
    public $mylevel=0;
    public $user_id = 0;
     public function _initialize()
    {
        parent::_initialize();
         $this->db_prex=C('db_prex');
         $user = session('user');
        if($user){
            $this->user_id = $user['user_id'];
            $this->mylevel = $user['myLevel'];
            // if($user['mobile'] ==''){
            //     session('wxUserInfo',$user);//用户信息
            //     $this->redirect('Mobile/ThirdLogin/setMobile');//验证手机号
            //     exit;
            // }
        }    
        $this->assign('pay_status',C('GOODS_PRICES'));
        $this->assign('myLevel',$this->mylevel);
        setcookie('user_id',$this->user_id);
        $weixin_config = M('wx_user')->find(); //获取微信配置
        $tpshop_config=M('config')->where("inc_type='shop_info' and name='store_title'")->find();
        $appid=$weixin_config['appid'];//appid
        $appSecret=$weixin_config['appsecret'];       
        if($appSecret!=0||$appSecret!=''){
            $jssdk=new JSSDK($appid, $appSecret);//获取jssdk
            $signPackage = $jssdk->GetSignPackage();//配置信息，签名
        }
        $url='http://'.$_SERVER['HTTP_HOST'].U('Mobile/ThirdLogin/myShare').'?shareUid='.$this->user_id;
        $imgurl=M('users')->where('user_id', $this->user_id)->field('head_pic')->find();
        $this->assign('title',$tpshop_config['value']);
        $this->assign('imgurl',$imgurl['head_pic']);
        $this->assign('link',$url);
        $this->assign('signPackage',$signPackage);
     }
        
    public function index(){
        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;        
            // 微信Jssdk 操作类 用分享朋友圈 JS            
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();              
            print_r($signPackage);
        */
        $hot_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_category')->where('level=1')->order('sort_order')->limit(9)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('thems',$thems);
        $this->assign('hot_goods',$hot_goods);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

        //秒杀商品
        $now_time = time();  //当前时间
        if(is_int($now_time/7200)){      //双整点时间，如：10:00, 12:00
            $start_time = $now_time;
        }else{
            $start_time = floor($now_time/7200)*7200; //取得前一个双整点时间
        }
        $end_time = $start_time+7200;   //结束时间
        $seckill_list=DB::query("select * from __PREFIX__goods as g inner join __PREFIX__flash_sale as f on g.goods_id = f.goods_id where start_time = $start_time and end_time = $end_time limit 3");     //获取秒杀商品
        //没有促销商品就不显示促销栏
        $sql="select count(*) as count from __PREFIX__goods as g inner join __PREFIX__flash_sale as f on g.goods_id = f.goods_id where UNIX_TIMESTAMP(NOW())<=f.end_time and f.start_time<=UNIX_TIMESTAMP(NOW()) order by f.end_time asc limit 9";
        $isexist_prom=M('goods')->query($sql);
        $this->assign('isexist_prom',$isexist_prom[0]['count']);     
        $this->assign('seckill_list',$seckill_list);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('favourite_goods',$favourite_goods);             
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 模板列表
     */
    public function mobanlist(){
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach($arr as $key => $val)
        {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";            
        }        
    }
    
    /**
     * 商品列表页
     */
    public function goodsList(){
        $id = I('get.id',0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    
    public function ajaxGetMore(){
    	$p = I('p/d',1);
    	$favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->page($p,10)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
    	$this->assign('favourite_goods',$favourite_goods);
    	return $this->fetch();
    }
}