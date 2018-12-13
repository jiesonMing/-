<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * 2015-11-21
 */
namespace app\home\controller; 
use app\home\logic\UsersLogic;
use app\home\logic\CartLogic;
use app\home\model\Message;
use think\Controller;
use think\Url;
use think\Page;
use think\Config;
use think\Verify;
use think\Db;
use think\Exception;
use PHPExcel_IOFactory;
use PHPExcel;
class User extends Base{

	public $user_id = 0;
        public $mylevel=0;
	public $user = array();
        public $db_prex='';
        public $prec_order='';
	
    public function _initialize() {
        parent::_initialize();

        if(session('?user'))
        {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user',$user);  //覆盖session 中的 user               
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->mylevel = $user['myLevel'];
            $this->assign('user',$user); //存储用户信息+
            $this->assign('mylevel',$this->mylevel);
            $this->assign('user_id',$this->user_id);
        }else{
            $nologin = array(
                'login','pop_login','do_login','logout','verify','set_pwd','finished',
                'verifyHandle','reg','send_sms_reg_code','identity','check_validate_code',
                'forget_pwd','check_captcha','check_username','send_validate_code',
            );
            if(!in_array(ACTION_NAME,$nologin)){
            $this->redirect('Home/User/login');
                exit;
            }
        }
        $this->db_prex=C('db_prex');
        $this->prec_order=C('prec_order');
        $sql="select count(order_id) as count from __PREFIX__order where user_id={$this->user_id} and order_status=0 and ( pay_status=0 or pay_status=2 or pay_status=4 ) and deleted=0";
        $count=M('order')->query($sql);
        if($count){
            $this->assign('count',$count[0]['count']);
        }else{
            $this->assign('count',0);
        }
        //用户中心面包屑导航
        $navigate_user = navigate_user();
        $this->assign('navigate_user',$navigate_user);        
    }

    /*
     * 用户中心首页
     */
    public function index(){
        $logic = new UsersLogic();
        $user = $logic->get_info($this->user_id);
        $levelName = M('user_role')->field('role_name')->where('role_level='.$this->mylevel)->find();
        $user = $user['result'];
        $this->assign('levelName',C('MYLEVEL'));
        $this->assign('newName',$levelName['role_name']);
        $this->assign('myLevel',$this->mylevel);
        $this->assign('user',$user);
        return $this->fetch();
    }


    public function logout(){
        delFile(RUNTIME_PATH);
    	setcookie('uname','',time()-3600,'/');
    	setcookie('cn','',time()-3600,'/');
    	setcookie('user_id','',time()-3600,'/');
        session_unset();
        session_destroy();
        //$this->success("退出成功",U('Home/Index/index'));
        $this->redirect('Home/Index/index');
        exit;
    }

    /*
     * 账户资金
     */
    public function account(){
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id,I('get.type'));
        $account_log = $data['result'];

        $this->assign('user',$user);
        $this->assign('account_log',$account_log);
        $this->assign('page',$data['show']);
        $this->assign('active','account');
        return $this->fetch();
    }
    
    /*
     * 我开的账户
     */
    public function myUser(){
        $mylevel=$this->mylevel;
        $data=array();
        $logic = new UsersLogic();
        $data = $logic->getSubUser($this->user_id);
        $this->assign('users',$data['result']);
        $this->assign('page',$data['show']);
        
        $this->assign('active','myUser');
        return $this->fetch();
    }
    
    /*
     *开账户 
     */
    public function addMyUser(){
        if(IS_POST){
            if($this->mylevel!=3) errorMsg (400, '您不是一级会员，不能添加下级账户');
            $nickname=I('nickname')?I('nickname'):'';
            if(!$nickname) errorMsg (400, '用户名不能为空');
            $password=I('password')?I('password'):'';
            if(!$password) errorMsg (400, '密码不能为空');
            $repass=I('repass')?I('repass'):'';
            if($password!=$repass) errorMsg (400, '两次密码输入不一致');
            $mobile=I('mobile')?I('mobile'):'';
            if(strlen($mobile)!=11) errorMsg (400, '联系电话长度不正确');
            $email=I('email')?I('email'):'';
            $myLevel=I('myLevel')?I('myLevel'):0;
            try{
                if($model=MM('Users','DB_CONFIG3')){
                
                    $data=$model->where("mobile='{$mobile}'")->find();
                    if($data){
                        errorMsg(400, '该电话号码已经存在，不能重复添加');
                    }
                    $time=time();
                    $salt = rand(0,999999);
                    $password=md5($password.$salt);
                    $sql="insert __PREFIX__users(parentUser,email,password,mobile,reg_time,nickname,myLevel,salt) values({$this->user_id},'{$email}','{$password}','{$mobile}','{$time}','{$nickname}','{$myLevel}','{$salt}')";
                    $model->startTrans();
                    if($model->execute($sql)){
                        $model->commit();
                        errorMsg(200, '添加成功');
                    }else{
                        $model->rollback();
                        throw new Exception('插入数据失败');
                    }
                }else{
                    throw new Exception('初始化数据库连接失败');
                }
            }catch (Exception $e){
                errorMsg(400, $e->getMessage(),$e->getLine());
            }
        }else{
            $sql="select role_name,role_level from {$this->db_prex}user_role where role_level<={$this->mylevel} order by role_id desc";
            $userRoles=M('User_role')->query($sql);
            $this->assign('active','myUser');
            $this->assign('userRoles',$userRoles);
            return $this->fetch();
        }
    }
    /*
     * 我的分享
     */
    public function myShare(){
        $uid= urlencode(base64_encode($this->user_id.'mallShare'));
        $url='http://'.$_SERVER['HTTP_HOST'].'?uid='.$uid;
        $this->assign('url',$url);
        return $this->fetch();
    }
    /*
     * 优惠券列表
     */
    public function coupon(){
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id,I('type'));
        $coupon_list = $data['result'];
        $this->assign('coupon_list',$coupon_list);
        $this->assign('page',$data['show']);
        $this->assign('active','coupon');
        return $this->fetch();
    }
    /**
     *  登录
     */
    public function login(){
        if($this->user_id > 0){
            $this->redirect('Home/User/index');
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Home/User/index");
        $this->assign('referurl',$referurl);
        $db_prex=$this->db_prex;
        //微信扫码登录需要的参数
        if($db_prex == 'jia_'){
            $appid='wx824b0d5f71c3f19b';//微信开放平台上的应用appid,佳源
        }elseif($db_prex == 'vis_'){
            $appid='wxe50c6c813d9ac065';//安捷易
        }
        $url='http://'.$_SERVER['HTTP_HOST'].U('Home/Weixin/codeBack');
        $url= urlencode($url);
        $rand=rand(000000,999999);
        session('state',$rand);
        $state=md5($rand);
        $this->assign('state',$state);
        $this->assign('url',$url);
        $this->assign('appid',$appid);
        return $this->fetch();
    }

    public function pop_login(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/index');
    	}
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Home/User/index");
        $this->assign('referurl',$referurl);
    	return $this->fetch();
    }
    
    public function do_login(){
    	$username = trim(I('post.username'));
    	$password = trim(I('post.password'));   
        #验证码
    	//$verify_code = I('post.verify_code');     
//        $verify = new Verify();
//        if (!$verify->check($verify_code,'user_login'))
//        {
//             $res = array('status'=>0,'msg'=>'验证码错误');
//             exit(json_encode($res));
//       } 
        delFile(RUNTIME_PATH);
    	$logic = new UsersLogic();
    	$res = $logic->login($username,$password);    
    	if($res['status'] == 1){
            $res['url'] =  urldecode(I('post.referurl'));
            session('user',$res['result']);
            if($res['result']['nickname']=='admin' && $res['result']['user_id']==2603){
                session('admin_id',1);
                session('act_list','all');
            }
            setcookie('user_id',$res['result']['user_id'],time()+(3600*2),'/');
            setcookie('is_distribut',$res['result']['is_distribut'],time()+(3600*2),'/');
            setcookie('mylevel',$res['result']['mylevel'],time()+(3600*2),'/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname',urlencode($nickname),null,'/');
            setcookie('cn',0,time()-3600,'/');
            $cartLogic = new CartLogic();
            $cartLogic->login_cart_handle($this->session_id,$res['result']['user_id']);  //用户登录后 需要对购物车 一些操作
    	}
    	exit(json_encode($res));
    }
    /**
     *  注册
     */
    public function reg(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/index');
        }
    	
        if(IS_POST){
            $logic = new UsersLogic();
            //验证码检验
            $this->verifyHandle('user_reg');
            $username = I('post.username','');
            $password = I('post.password','');
            $password2 = I('post.password2','');
            $code = I('post.code','');
            $session_id = session_id();
            
            //是否开启注册验证码机制
             if(check_mobile($username)){
                 $reg_sms_enable = tpCache('sms.regis_sms_enable');
                 if(!$reg_sms_enable){
                     //邮件功能关闭
                     $this->verifyHandle('user_reg');
                 }
                 $check_code = $logic->check_validate_code($code, $username, $session_id);
                 if($check_code['status'] != 1){
                     $this->error($check_code['msg']);
                 }
             }
            // //是否开启注册邮箱验证码机制
            // if(check_email($username)){
            //     $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
            //     if(!$reg_smtp_enable){
            //         //邮件功能关闭
            //         $this->verifyHandle('user_reg');
            //     }
            //     $check_code = $logic->check_validate_code($code, $username, $session_id, 'email');
            //     if($check_code['status'] != 1){
            //         $this->error($check_code['msg']);
            //     }
            // }
            $data = $logic->reg($username,$password,$password2);
            if($data['status'] != 1){
                $this->error($data['msg']);
            }
            session('user',$data['result']);
    		setcookie('user_id',$data['result']['user_id'],time()+(3600*2),'/');
    		setcookie('is_distribut',$data['result']['is_distribut'],time()+(3600*2),'/');
            $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
            setcookie('uname',$nickname,time()+(3600*2),'/');
            $cartLogic = new CartLogic();
            $cartLogic->login_cart_handle($this->session_id,$data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
            
            $this->success($data['msg'],U('Home/User/index'));
            exit;
        }
        $this->assign('regis_sms_enable',tpCache('sms.regis_sms_enable')); // 注册启用短信：
        //$this->assign('regis_smtp_enable',tpCache('smtp.regis_smtp_enable')); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    /*
     * 订单列表
     */
    public function order_list(){
        $where = ' user_id=:user_id';
        $bind['user_id'] = $this->user_id;
        //条件搜索
       if(I('get.type')){
           $where .= C(strtoupper(I('get.type')));
       }
       // 搜索订单 根据商品名称 或者 订单编号
       $search_key = trim(I('search_key'));   
       if($search_key)
       {
          $where .= " and (mobile like :search_key4 or consignee like :search_key3 or order_sn like :search_key1 or order_id in (select order_id from `".C('database.prefix')."order_goods` where goods_name like :search_key2) ) ";
           $bind['search_key1'] = "%$search_key%";//订单编号
           $bind['search_key2'] = "%$search_key%";//商品名
           $bind['search_key3'] = "%$search_key%";//收货人
           $bind['search_key4'] = "%$search_key%";//手机
           $this->assign('search_key',$search_key);
       }
       
        $count = M('order')->where($where)->bind($bind)->count();
        $Page = new Page($count,60);

        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->bind($bind)->limit($Page->firstRow.','.$Page->listRows)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach($order_list as $k=>$v)
        {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            if($order_list[$k]['order_prom_type'] == 4){
                $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_list[$k]['order_prom_id']))->find();
                $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            }else{
                $order_list[$k]['pre_sell_is_finished'] = -1;//没有参与预售的订单
            }
        }
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('page',$show);
        $this->assign('lists',$order_list);
        $this->assign('active','order_list');
        $this->assign('active_status',I('get.type'));
        return $this->fetch();
    }
    
    /*批量支付*/
    public function allPay(){
        $where = ' user_id=:user_id';
        $bind['user_id'] = $this->user_id;
        $where .= ' AND pay_status = 0 AND order_status = 0 ';
       
        $count = M('order')->where($where)->bind($bind)->count();
        $Page = new Page($count,60);

        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->bind($bind)->limit($Page->firstRow.','.$Page->listRows)->select();
        
        $this->assign('page',$show);
        $this->assign('lists',$order_list);
        $this->assign('active','allPay');
        return $this->fetch();
    }
    
    public function import(){
        $this->assign('active','import');
        $this->assign('active_status',I('get.type'));
        return $this->fetch();       
    }
    
    public function inser_order(){
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        if(count($_FILES) > 0){
                $f = $_FILES['file'];
                $result =array();
                if($_FILES['file']['size']>3145728){
                    errorMsg(400, '上传文件超过限制！');
                }else{
                    // $dateFile = date('Ymd');
                    // $path = C('public').'/import_order/'.$dateFile;
                    // if(!file_exists($path)){
                    //     mkdir($path);
                    // }
                    // $fileName = $path.'/'.$f['name'];
                    // move_uploaded_file($f['tmp_name'],mb_convert_encoding($fileName,"gbk", "utf-8"));
                    $goodsData = self::getexcelUnRepeat($_FILES["file"]["tmp_name"]);
                    $goodsArr = $priceArr = $wsArr = array();
                    foreach($goodsData as $key=>$value){
                        if($value[1]==''){
                            unset($goodsData[$key]);
                            continue;
                        }
                        $orderSn = str_replace(array("'","\"","“","’"," "," "),'',htmlspecialchars($value[1])); 
                        if($value[1]!=''){                          
                            $goodsData[$key]['order_sn'] = $orderSn;
                            $result[$key]['status']=200;
                            $result[$key]['orderSn']=$orderSn;
                            $result[$key]['info']="订单导入成功";
                            $orderContinue = $noStore = false;
                            $checkSql = "select order_id from {$this->db_prex}order where order_sn='{$orderSn}'";
                            $check = DB::query($checkSql);//检查订单是否已经存在
                            if($check){
                                $result[$key]['status']=400;
                                $result[$key]['orderSn']=$orderSn;
                                $result[$key]['info']="订单号已经存在";
                                $orderContinue=true;
                                continue;
                            }
                            foreach(array_values($value['goods']) as $k=>$val){
                                $goodsName = str_replace(' ','',$val[1]);
                                $goodsSn = str_replace(' ','',$val[2]);
                                $goodsIdSql = "select goods_id,goods_name,store_count,stype,wareId,tax,portId,market_price,specType,cost_price,shop_price,firstMemberPrice,secondMemberPrice,thirdMemberPrice from ".$this->db_prex."goods where goods_sn='{$goodsSn}'";
                                $goodsIdArr = DB::query($goodsIdSql);
                                if(!$goodsIdArr){
                                    $result[$key]['status']=400;
                                    $result[$key]['orderSn']=$orderSn;
                                    $result[$key]['info']="商品编码不正确！";
                                    $orderContinue=true;
                                    continue;
                                }elseif($goodsIdArr[0]['store_count']<=0){
                                    $result[$key]['status']=400;
                                    $result[$key]['orderSn']=$orderSn;
                                    $result[$key]['info']="商品库存不足";
                                    $orderContinue=true;
                                    $noStore = true;
                                }elseif(trim($goodsIdArr[0]['goods_name']) != strip_tags(trim(htmlspecialchars($val[1])))){
                                    $result[$key]['status']=400;
                                    $result[$key]['orderSn']=$orderSn;
                                    $result[$key]['info']="导入的商品名称跟商城上的商品名称不一致！";
                                    $orderContinue=true;
                                    continue;
                                }

                                $field=$goodsIdArr[0]['wareId'].$goodsIdArr[0]['portId'];//重组仓库ID跟关区ID，作为商品下标
                                $goodsArr[$key][$field][$k]['gNum'] = $val[0];
                                $goodsArr[$key][$field][$k]['goods_id'] = $goodsIdArr[0]['goods_id'];
                                $goodsArr[$key][$field][$k]['goods_name'] = $val[1];
                                $goodsArr[$key][$field][$k]['goods_sn'] = $val[2];
                                $goodsArr[$key][$field][$k]['goods_num'] = $val[3];
                                $goodsArr[$key][$field][$k]['market_price'] = $goodsIdArr[0]['market_price'];
                                $goodsArr[$key][$field][$k]['goods_price'] = $goodsIdArr[0]['shop_price'];
                                $goodsArr[$key][$field][$k]['cost_price'] = $goodsIdArr[0]['cost_price'];
                                $goodsArr[$key][$field][$k]['store_count'] = $goodsIdArr[0]['store_count'];
                                $goodsArr[$key][$field][$k]['wareId'] = $goodsIdArr[0]['wareId'];
                                $goodsArr[$key][$field][$k]['stype'] = $goodsIdArr[0]['stype'];
                                $goodsArr[$key][$field][$k]['portId'] = $goodsIdArr[0]['portId'];
                                $goodsArr[$key][$field][$k]['tax'] = $goodsIdArr[0]['tax'];
                                switch($this->mylevel){
                                case 1:
                                    $goodsArr[$key][$field][$k]['member_goods_price'] = $goodsIdArr[0]['thirdMemberPrice'];
                                    break;    
                                case 2:
                                    $goodsArr[$key][$field][$k]['member_goods_price'] = $goodsIdArr[0]['secondMemberPrice'];
                                    break;
                                case 3:
                                    $goodsArr[$key][$field][$k]['member_goods_price'] = $goodsIdArr[0]['firstMemberPrice'];
                                    break;    
                                default:
                                    $goodsArr[$key][$field][$k]['member_goods_price'] = $goodsIdArr[0]['shop_price'];
                                    break;
                                }

                                $priceArr[$key][$field]['goods_price'] = ($goodsArr[$key][$field][$k]['member_goods_price'])*($goodsArr[$key][$field][$k]['goods_num']);
                                $priceArr[$key][$field]['order_amount'] = round(($goodsArr[$key][$field][$k]['member_goods_price'])*($goodsArr[$key][$field][$k]['goods_num']),2);
                                $priceArr[$key][$field]['taxTotal'] = round(($goodsArr[$key][$field][$k]['member_goods_price'])*($goodsArr[$key][$field][$k]['goods_num'])*$goodsArr[$key][$field][$k]['tax'],2);
                                $priceArr[$key][$field]['coupon_price'] = 0;
                                if($value[1]){
                                    $wsArr[$key][$field]['wareId'] = $goodsIdArr[0]['wareId'];
                                    $wsArr[$key][$field]['stype'] = $goodsIdArr[0]['stype'];
                                    $wsArr[$key][$field]['portId'] = $goodsIdArr[0]['portId'];

                                    $goodsArr[$key][$field][$k]['spec_key'] = $goodsIdArr[0]['specType'];
                                    
                                    $goodsData[$key]['goods_price'] += ($goodsArr[$key][$field][$k]['member_goods_price'])*($goodsArr[$key][$field][$k]['goods_num']);
                                    $goodsData[$key]['order_amount'] += round(($goodsArr[$key][$field][$k]['member_goods_price'])*($goodsArr[$key][$field][$k]['goods_num']),2);
                                    $goodsData[$key]['taxTotal'] += round((($goodsArr[$key][$field][$k]['member_goods_price'])*($goodsArr[$key][$field][$k]['goods_num'])*$goodsArr[$key][$field][$k]['tax']),2);
                                    $goodsData[$key]['coupon_price'] = 0;
                                    if($noStore){
                                        $goodsData[$key]['noStore'] = 1;
                                    }
                                }
                            }
                            if($orderContinue){
                                continue;
                            }
                        }                        
                    }
                    // errorMsg(400,$goodsData);
                    if($goodsData){
                        foreach($goodsData as $key=>$value){
                            if($goodsArr[$key]){
                                $count=count($goodsArr[$key]);
                                $ogdatas=$odata=array();
                                $odata['user_id']=$this->user_id;   //用户ID
                                $odata['orderFlowType'] = 'NORMAL';
                                $odata['paymentType'] = 'yiji';//默认支付公司编码
                                // $userTypeSql = "select userType from __PREFIX__users where user_id=".$this->user_id;
                                // $typeResult = DB::query($userTypeSql);
                                // if($typeResult[0]['userType'] ==1){ //特殊用户导订单，判断支付公司，支付流水号
                                //     $paymentType = myTrim($value[16]);
                                //     $tradeNo = myTrim($value[17]);
                                //     if(($paymentType!='' && $tradeNo=='') || ($paymentType=='' && $tradeNo!='')){
                                //         $result[$key]['status']=400;
                                //         $result[$key]['orderSn']=$value['order_sn'];
                                //         $result[$key]['info']="支付公司编码和支付流水号必须同时填写！";
                                //         continue;
                                //     }
                                //     if($paymentType!=''){
                                //         $checkPaymentTypeSql = "select order_id from __PREFIX__order where tradeNo='{$tradeNo}'";
                                //         $checkPaymentType = DB::query($checkPaymentTypeSql);
                                //         if($checkPaymentType){
                                //             $result[$key]['status']=400;
                                //             $result[$key]['orderSn']=$value['order_sn'];
                                //             $result[$key]['info']="支付流水号已经存在,请检查！";
                                //             continue;
                                //         }
                                //         $odata['paymentType'] = $paymentType;//支付公司编码
                                //         $odata['tradeNo'] = $tradeNo;        //支付流水号
                                //         $odata['pay_code'] = $paymentType;
                                //         $odata['pay_name'] = '导单已支付';
                                //         $odata['pay_time'] = time()+rand(4000,5000);
                                //         $odata['orderFlowType'] = 'SPECIAL';
                                //         $odata['pay_status'] = 1;
                                //     }
                                // } 
                                
                                $odata['consignee']=$value[5];      //收件人
                                $odata['buyerRegNo']=$value[2];     //购买人昵称
                                $odata['buyerName']=$value[3];       //购买人姓名
                                $odata['buyerIdNumber']=strtoupper($value[4]);   //购买人身份证

                                if($value['noStore']){//这里用于限制上面库存不足也可以导入订单
                                    continue;
                                }
                                $provinceName = str_replace(' ','',$value[7]);
                                $cityName = str_replace(' ','',$value[8]);
                                $districtName = str_replace(' ','',$value[9]);
                                // $twonName = str_replace(' ','',$value[10]);
                                $pro = mb_substr($provinceName,0,2,'utf-8');
                                $cit = mb_substr($cityName,0,2,'utf-8');
                                $proSql = "select id from __PREFIX__region where name like'{$pro}%' and level=1";
                                $citySql = "select id from __PREFIX__region where name like'{$cit}%' and level=2";
                                $province = DB::query($proSql);
                                $city = DB::query($citySql);
                                
                                if(!$province){
                                    $result[$key]['status']=400;
                                    $result[$key]['orderSn']=$value['order_sn'];
                                    $result[$key]['info']="所填省份不存在，请查证";
                                    continue;
                                }elseif(!$city){
                                    $result[$key]['status']=400;
                                    $result[$key]['orderSn']=$value['order_sn'];
                                    $result[$key]['info']="所填城市不存在，请查证";
                                    continue;
                                }
                                $address = str_replace(array("'","\"","“","’"," "),'',$value[10]);
                                $pret = mb_strpos($address,mb_substr($provinceName,0,2,'UTF-8'));
                                $cret = mb_strpos($address,mb_substr($cityName,0,2,'UTF-8'));
                                
                                if($this->db_prex=='xct_'){
                                    $cret=true;
                                } 
                                if($pret===false){
                                    $result[$key]['status']=400;
                                    $result[$key]['orderSn']=$value['order_sn'];
                                    $result[$key]['info']="所填省份与详细地址中省份不一致";
                                    continue;
                                }
                                if($cret===false){
                                    $result[$key]['status']=400;
                                    $result[$key]['orderSn']=$value['order_sn'];
                                    $result[$key]['info']="所填城市与详细地址中城市不一致";
                                    continue;
                                }

                                
                                $keywordList = M('address_keyword')->select();
                                $ckArr = array();
                                if($keywordList){
                                    foreach ($keywordList as $kid => $kval) {
                                        $ck = mb_strpos($address,$kval['keyword']);
                                        if($ck){
                                            $ckArr[$kid] = 1;
                                        }else{
                                            $ckArr[$kid] = 2;
                                        }
                                    }
                                    if(!(in_array(1,$ckArr))){
                                        $result[$key]['status']=400;
                                        $result[$key]['orderSn']=$value['order_sn'];
                                        $result[$key]['info']="详细地址中必须包含特定的字符！";
                                        continue;
                                    }
                                }

                                $odata['province'] = $province[0]['id'];
                                $odata['city'] = $city[0]['id'];

                                $odata['address'] = $address;
                                $odata['mobile']=$value[6];
                                $odata['user_note']=$value[15];
                                

                                $odata['add_time']=time();
                                if($count>1){
                                    foreach($goodsArr[$key] as $k=>$val){
                                        $num=count($goodsArr[$key][$k]);
                                        $odata['stype']=$wsArr[$key][$k]['stype'];          //报税仓和普通仓
                                        $odata['wareId']=$wsArr[$key][$k]['wareId'];        //仓库ID
                                        $odata['portId']=$wsArr[$key][$k]['portId'];        //仓库ID
                                        $odata['goods_price']=$priceArr[$key][$k]['goods_price'];
                                        $odata['order_amount']= $priceArr[$key][$k]['order_amount']+$priceArr[$key][$k]['taxTotal'];
                                        $odata['taxTotal']=$priceArr[$key][$k]['taxTotal'];
                                        $odata['coupon_price']=$priceArr[$key][$k]['coupon_price'];
                                        $odata['order_sn']=$value['order_sn']."_".$k;

                                        // $odata['order_sn']=str_replace(array("'","\"","“","’"," "),'',htmlspecialchars($odata['order_sn']));
                                        //分单时，再检查有没有重复的订单，防止重复导入
                                        $checkSql2 = "select order_id from {$this->db_prex}order where order_sn='".$odata['order_sn']."'";
                                        $check2 = DB::query($checkSql2);//检查订单是否已经存在
                                        if($check2){
                                            $result[$key]['status']=400;
                                            $result[$key]['orderSn']=$odata['order_sn'];
                                            $result[$key]['info']="订单号已经存在";
                                            $orderContinue=true;
                                            continue;
                                        }

                                        //订单人保税额度查询检查----开始
                                        if($odata['stype']==1){//保税订单判断海关额度限制
                                            if($odata['order_amount']>2000) {
                                                $result[$key]['status']=400;
                                                $result[$key]['orderSn']=$odata['order_sn'];
                                                $result[$key]['info']="保税仓单笔订单超过海关限额2000元！";
                                                continue;
                                            }
                                            $buyerName = myTrim($value[3]);
                                            $buyerIdNumber = myTrim(strtoupper($value[4]));
                                            $checkCardNum = checkBuyerIdNumber($buyerName,$buyerIdNumber);
                                            if($checkCardNum){//如果存在黑名单中
                                                $result[$key]['status']=400;
                                                $result[$key]['orderSn']=$odata['order_sn'];
                                                $result[$key]['info']=$checkCardNum;
                                                continue;   
                                            }else{
                                                $checkBNP = checkBuyerIdNumerPrice($buyerName,$buyerIdNumber);//查询额度
                                                if($checkBNP){
                                                    if(($checkBNP+$value['order_amount'])>20000){//当前订单支付价格+已经使用额度超过20000,提示
                                                        $result[$key]['status']=400;
                                                        $result[$key]['orderSn']=$odata['order_sn'];
                                                        $result[$key]['info']="该订单购买人超过海关购买限额2万，请确认！";
                                                        continue;
                                                    }else{//如果没有超过，那么额度往上加
                                                        $apiRet = setBuyerIdNumberPrice($buyerName,$buyerIdNumber,$odata['order_amount'],1);
                                                        if(!($apiRet) && ($apiRet==2)){
                                                            $result[$key]['status']=400;
                                                            $result[$key]['orderSn']=$odata['order_sn'];
                                                            $result[$key]['info']='海关额度限制修改失败，联系管理员！';
                                                            continue;
                                                        }
                                                    }
                                                }else{//没有额度记录，添加 
                                                    $apiRet = setBuyerIdNumberPrice($buyerName,$buyerIdNumber,$odata['order_amount'],1);
                                                    if(!($apiRet) && ($apiRet==2)){
                                                        $result[$key]['status']=400;
                                                        $result[$key]['orderSn']=$odata['order_sn'];
                                                        $result[$key]['info']='海关额度限制添加失败，联系管理员！';
                                                        continue;
                                                    }
                                                }
                                            }
                                        }
                                        //订单人保税额度查询检查----结束
                                        
                                        
                                        $order_id = M('order')->add($odata);
                                        //添加日志
                                        $adata['order_id']=$order_id;
                                        $adata['action_user']=$this->user_id;
                                        $adata['action_note']='您提交了订单，请等待系统确认';
                                        $time=time();
                                        $adata['log_time']=$time;
                                        $adata['status_desc']='提交订单';
                                        $action_id = M('order_action')->add($adata);
                                        //if($num>1){
                                            $gNum=$num;
                                            foreach($goodsArr[$key][$k] as $goods){
                                                //分单，插入order
                                                //遍历插入order_goods
                                                $ogdata=array();
                                                $ogdata['order_id']=$order_id;
                                                $ogdata['goods_id']=$goods['goods_id'];
                                                $ogdata['gNum']=$gNum;
                                                $ogdata['goods_num']=$goods['goods_num'];
                                                $ogdata['goods_sn']=$goods['goods_sn'];
                                                $ogdata['goods_name']=strip_tags(trim(htmlspecialchars($goods['goods_name'])));
                                                $ogdata['market_price']=$goods['market_price'];
                                                $ogdata['goods_price']=$goods['goods_price'];
                                                $ogdata['cost_price']=$goods['cost_price'];
                                                $ogdata['member_goods_price']=$goods['member_goods_price'];
                                                $ogdata['spec_key']=$goods['spec_key'];
                                                $og_id = M('order_goods')->add($ogdata);
                                                $gNum--;
                                            }
                                        /*}else{
                                            foreach($goodsArr[$key][$k] as $goods){
                                                //遍历插入order_goods
                                                $ogdata=array();
                                                $ogdata['order_id']=$order_id;
                                                $ogdata['goods_id']=$goods['goods_id'];
                                                $ogdata['gNum']=$goods['gNum'];
                                                $ogdata['goods_num']=$goods['goods_num'];
                                                $ogdata['goods_sn']=$goods['goods_sn'];
                                                $ogdata['goods_name']=$goods['goods_name'];
                                                $ogdata['market_price']=$goods['market_price'];
                                                $ogdata['goods_price']=$goods['goods_price'];
                                                $ogdata['cost_price']=$goods['cost_price'];
                                                $ogdata['member_goods_price']=$goods['member_goods_price'];
                                                $ogdata['spec_key']=$goods['spec_key'];
                                                $og_id = M('order_goods')->add($ogdata);
                                            }   
                                        }*/
                                    }
                                }else{
                                    foreach ($wsArr[$key] as $k=>$v){
                                        $odata['stype'] = $v['stype'];       //报税仓和普通仓
                                        $odata['wareId']= $v['wareId'];      //仓库ID
                                        $odata['portId']= $v['portId'];      //仓库ID
                                    }
                                    $odata['goods_price']=$value['goods_price'];
                                    $odata['order_amount']=$value['order_amount']+(round($value['taxTotal'],2));
                                    $odata['coupon_price']=$value['coupon_price'];
                                    $odata['taxTotal']= round($value['taxTotal'],2);
                                    $odata['order_sn']=$value['order_sn'];

                                    // $odata['order_sn']=str_replace(array("'","\"","“","’"," "),'',htmlspecialchars($odata['order_sn']));
                                    // errorMsg(400,htmlspecialchars($odata['order_sn']));
                                    //订单人保税额度查询检查----开始
                                    if($odata['stype']==1){//保税订单判断海关额度限制

                                        if($odata['order_amount']>2000) {
                                            $result[$key]['status']=400;
                                            $result[$key]['orderSn']=$odata['order_sn'];
                                            $result[$key]['info']="保税仓单笔订单超过海关限额2000元！";
                                            continue;
                                        }

                                        $buyerName = myTrim($value[3]);
                                        $buyerIdNumber = myTrim(strtoupper($value[4]));

                                        $checkCardNum = checkBuyerIdNumber($buyerName,$buyerIdNumber);

                                        if($checkCardNum){//如果存在黑名单中
                                            $result[$key]['status']=400;
                                            $result[$key]['orderSn']=$odata['order_sn'];
                                            $result[$key]['info']=$checkCardNum;
                                            continue;   
                                        }else{
                                            $checkBNP = checkBuyerIdNumerPrice($buyerName,$buyerIdNumber);//查询额度
                                            if($checkBNP){
                                                if(($checkBNP+$value['order_amount'])>20000){//当前订单支付价格+已经使用额度超过20000,提示
                                                    $result[$key]['status']=400;
                                                    $result[$key]['orderSn']=$odata['order_sn'];
                                                    $result[$key]['info']="该订单购买人超过海关购买限额2万，请确认！";
                                                    continue;
                                                }else{//如果没有超过，那么额度往上加
                                                    $apiRet = setBuyerIdNumberPrice($buyerName,$buyerIdNumber,$odata['order_amount'],1);
                                                    if(!($apiRet) && ($apiRet==2)){
                                                        $result[$key]['status']=400;
                                                        $result[$key]['orderSn']=$odata['order_sn'];
                                                        $result[$key]['info']='海关额度限制修改失败，联系管理员！';
                                                        continue;
                                                    }
                                                }
                                            }else{//没有额度记录，添加 
                                                $apiRet = setBuyerIdNumberPrice($buyerName,$buyerIdNumber,$odata['order_amount'],1);
                                                if(!($apiRet) && ($apiRet==2)){
                                                    $result[$key]['status']=400;
                                                    $result[$key]['orderSn']=$odata['order_sn'];
                                                    $result[$key]['info']='海关额度限制添加失败，联系管理员！';
                                                    continue;
                                                }
                                            }
                                        }
                                    }
                                    //订单人保税额度查询检查----结束
                                    $order_id = M('order')->add($odata);
                                    //添加日志
                                    $adata['order_id']=$order_id;
                                    $adata['action_user']=$this->user_id;
                                    $adata['action_note']='您提交了订单，请等待系统确认';
                                    $time=time();
                                    $adata['log_time']=$time;
                                    $adata['status_desc']='提交订单';

                                    $action_id = M('order_action')->add($adata);
                                    foreach($goodsArr[$key] as $k=>$val){
                                        $num=count($goodsArr[$key][$k]);
                                        foreach($goodsArr[$key][$k] as $goods){
                                            //分单，插入order
                                            //遍历插入order_goods
                                            $ogdata=array();
                                            $ogdata['order_id']=$order_id;
                                            $ogdata['goods_id']=$goods['goods_id'];
                                            $ogdata['gNum']=$num;
                                            $ogdata['goods_num']=$goods['goods_num'];
                                            $ogdata['goods_sn']=$goods['goods_sn'];
                                            $ogdata['goods_name']= strip_tags(trim(htmlspecialchars($goods['goods_name'])));
                                            $ogdata['market_price']=$goods['market_price'];
                                            $ogdata['goods_price']=$goods['goods_price'];
                                            $ogdata['cost_price']=$goods['cost_price'];
                                            $ogdata['member_goods_price']=$goods['member_goods_price'];
                                            $ogdata['spec_key']=$goods['spec_key'];
                                            $og_id = M('order_goods')->add($ogdata);
                                            $num--;
                                        }
                                    }
                                }
                            }
                        }
                    }else{
                        errorMsg(400,'没有上传订单');
                    }
                }
                // usleep(500000);
                errorMsg(200, 'success',$result);
        }else{
            errorMsg(400,'','没有检测到文件存在');
        }
    }
    
    /*读取excel文档内容，合并相同订单，导入订单专用
     *Z和AA列必须是日期格式数据
     *S、T、U、V、W、X列是商品信息 
    */
    protected  function getexcelUnRepeat($filename){
        set_time_limit(90); 
        @ini_set("memory_limit", "512M");
        vendor("phpexcel.PHPExcel");
        //$objPHPExcel = new \PHPExcel();
        $objPHPExcel = \PHPExcel_IOFactory::createReaderForFile(mb_convert_encoding($filename,"gbk", "utf-8"));//use excel2007 for 2007 format
        $objReader = $objPHPExcel->load(mb_convert_encoding($filename,"gbk", "utf-8")); 
        $objWorksheet = $objReader->setActiveSheetIndex();
        $highestRow = $objWorksheet->getHighestRow();   //获取行数
        // $highestColumn = $objWorksheet->getHighestColumn();  //获取列数
        // $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn);
        for ($row = 2; $row <= $highestRow; $row++) {
            $orderSn=(string)$objWorksheet->getCellByColumnAndRow(1, $row)->getValue();
            for ($col = 1; $col < 16; $col++) {                
                $afcol = \PHPExcel_Cell::stringFromColumnIndex($col);
                if($afcol=='L' || $afcol=='M' || $afcol=='N' || $afcol=='O' ){//商品详情放入goods字段下
                    $excelData[$orderSn]['goods'][$row][]=trim((string)$objWorksheet->getCellByColumnAndRow($col, $row)->getValue());
                }else{
                    $excelData[$orderSn][$col]=trim((string)$objWorksheet->getCellByColumnAndRow($col, $row)->getValue());
                }
            }
        }
        $data = array_values($excelData);
        return $data;
    }
    
    /*我的下级用户订单*/
    public function myUserOrderList(){
        $model=MM('Order','DB_CONFIG3');
        $sql="select user_id from __PREFIX__users where parentUser={$this->user_id}";
        $userArr=$model->query($sql);
        if($userArr){
            $users='';
            foreach($userArr as $value){
                $users.=",{$value['user_id']}";
            }
            $users=trim($users,',');
            $where = " user_id in ({$users})";
            //条件搜索
           if(I('get.type')){
               $where .= C(strtoupper(I('get.type')));
           }
           //dump($where);die;
            $count = M('order')->where($where)->count();
            $Page = new Page($count,10);

            $show = $Page->show();
            $order_str = "order_id DESC";
            if(I('get.type')=='WAITPAY'){
                $where ="o.user_id in ({$users}) AND o.pay_status = 0 AND o.order_status = 0 AND o.pay_code !='cod'";
            }elseif(I('get.type')=='AlRADYPAY'){
                $where ="o.user_id in ({$users}) AND o.pay_status > 1 AND o.order_status = 0 AND o.pay_code !='cod'";
            }elseif (I('get.type')=='WAITSEND') {
                $where = "o.user_id in ({$users}) AND (o.pay_status=1 OR o.pay_code='cod') AND o.shipping_status !=1 AND o.order_status in(0,1)";
            }elseif (I('get.type')=='WAITRECEIVE') {
                $where = "o.user_id in ({$users}) AND o.shipping_status=1 AND o.order_status = 1";
            }elseif (I('get.type')=='WAITCCOMMENT') {
                $where = "o.user_id in ({$users}) AND o.order_status=2";
            }else{
                $where = "o.user_id in ({$users})";
            }
            $sql="select o.order_id,o.order_prom_id,o.order_sn,o.add_time,o.order_status,o.goods_price,o.shipping_price,o.coupon_price,o.integral_money,o.order_prom_amount,o.user_money,o.order_amount,u.nickname from __PREFIX__order as o left join __PREFIX__users as u on u.user_id=o.user_id where {$where} order by o.order_id DESC limit {$Page->firstRow},{$Page->listRows}";
            $order_list = $model->query($sql);
            
            //获取订单商品
            $model = new UsersLogic();
            foreach($order_list as $k=>$v)
            {
                $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
                //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
                $data = $model->get_order_goods($v['order_id']);
                $order_list[$k]['goods_list'] = $data['result'];
                if($order_list[$k]['order_prom_type'] == 4){
                    $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_list[$k]['order_prom_id']))->find();
                    $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
                    $order_list[$k]['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
                    $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                    $order_list[$k]['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
                }else{
                    $order_list[$k]['pre_sell_is_finished'] = -1;//没有参与预售的订单
                }
            }
        }else{
            $order_list=array();
        }
        
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('page',$show);
        $this->assign('lists',$order_list);
        $this->assign('active','myUserOrderLisg');
        $this->assign('active_status',I('get.type'));
        return $this->fetch();
    }

    /*
     * 订单详情
     */
    public function order_detail(){
        $id = I('get.id/d');
        $type=I('get.type');
        
        $map['order_id'] = $id;
        $sql="select o.*,u.nickname from __PREFIX__order as o left join __PREFIX__users as u on u.user_id=o.user_id where o.order_id={$id}";
        $result=MM('order','DB_CONFIG3')->query($sql);
        $order_info=$result[0];
        //$order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        
        if(!$order_info){
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        if($order_info['order_prom_type'] == 4){
            $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_info['order_prom_id']))->find();
            $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
            $order_info['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
            $order_info['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
            $order_info['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            $order_info['pre_sell_deliver_goods'] = $pre_sell_item['deliver_goods'];
        }else{
            $order_info['pre_sell_is_finished'] = -1;//没有参与预售的订单
        }
        
        //获取订单进度条
        $sql = "SELECT action_id,log_time,status_desc,order_status FROM ((SELECT * FROM __PREFIX__order_action WHERE order_id = :id AND status_desc <>'' ORDER BY action_id) AS a) GROUP BY status_desc ORDER BY action_id";
        $bind['id'] = $id;
        $items = DB::query($sql,$bind);
        $items_count = count($items);
        $region_list = get_region_list();
        
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no',true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id'=>$id))->order('action_id asc')->select();
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('region_list',$region_list);
        $this->assign('order_info',$order_info);
        $this->assign('order_action',$order_action);
        if($type==1){
            $this->assign('active','myUserOrderLisg');
        }else{
            $this->assign('active','order_list');
        }
        $this->assign('type',$type);
        return $this->fetch();
    }

    /*
     * 取消订单
     */
    public function cancel_order(){
        $id = I('get.id/d');
        //检查是否有积分，余额支付
        $logic = new UsersLogic();
        $data = $logic->cancel_order($this->user_id,$id);
        if($data['status'] < 0)
            $this->error($data['msg']);
        $this->success($data['msg']);
    }

    /*
     * 用户地址列表
     */
    public function address_list(){
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list',$region_list);
        $this->assign('lists',$address_lists);
        $this->assign('active','address_list');

        return $this->fetch();
    }
    /*
     * 添加地址
     */
    public function add_address(){
        header("Content-type:text/html;charset=utf-8");
        if(IS_POST){
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id,0,I('post.'));
            if($data['status'] != 1)
                exit('<script>alert("'.$data['msg'].'");history.go(-1);</script>');
            $call_back = $_REQUEST['call_back'];
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功 回调closeWindow方法 并返回新增的id
        }
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $this->assign('province',$p);
        return $this->fetch('edit_address');

    }

    /*
     * 地址编辑
     */
    public function edit_address(){
        header("Content-type:text/html;charset=utf-8");
        $id = I('get.id/d');
        $address = M('user_address')->where(array('address_id'=>$id,'user_id'=> $this->user_id))->find();
        if(IS_POST){
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id,$id,I('post.'));
            if($data['status'] != 1)
                exit('<script>alert("'.$data['msg'].'");history.go(-1);</script>');

            $call_back = $_REQUEST['call_back'];
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功 回调closeWindow方法 并返回新增的id
        }
        //获取省份
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $c = M('region')->where(array('parent_id'=>$address['province'],'level'=> 2))->select();
        $d = M('region')->where(array('parent_id'=>$address['city'],'level'=> 3))->select();
        if($address['twon']){
        	$e = M('region')->where(array('parent_id'=>$address['district'],'level'=>4))->select();
        	$this->assign('twon',$e);
        }

        $this->assign('province',$p);
        $this->assign('city',$c);
        $this->assign('district',$d);
        $this->assign('address',$address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default(){
        $id = I('get.id/d');
        M('user_address')->where(array('user_id'=>$this->user_id))->save(array('is_default'=>0));
        $row = M('user_address')->where(array('user_id'=>$this->user_id,'address_id'=>$id))->save(array('is_default'=>1));
        if(!$row)
            $this->error('操作失败');
        $this->success("操作成功");
    }
    
    /*
     * 地址删除
     */
    public function del_address(){
        $id = I('get.id/d');
        
        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id'=>$this->user_id,'address_id'=>$id))->delete();                
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if($address['is_default'] == 1)
        {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default'=>1));
        }        
        if(!$row)
            $this->error('操作失败',U('User/address_list'));
        else
            $this->success("操作成功",U('User/address_list'));
    }


    public function save_pickup()
    {
        $post = I('post.');
        if (empty($post['consignee'])) {
            return array('status' => -1, 'msg' => '收货人不能为空', 'result' => '');
        }
        if (!$post['province'] || !$post['city'] || !$post['district']) {
            return array('status' => -1, 'msg' => '所在地区不能为空', 'result' => '');
        }
        if(!check_mobile($post['mobile'])){
            return array('status'=>-1,'msg'=>'手机号码格式有误','result'=>'');
        }
        if(!$post['pickup_id']){
            return array('status'=>-1,'msg'=>'请选择自提点','result'=>'');
        }

        $user_logic = new UsersLogic();
        $res = $user_logic->add_pick_up($this->user_id, $post);
        if($res['status'] != 1){
            exit('<script>alert("'.$res['msg'].'");history.go(-1);</script>');
        }
        $call_back = $_REQUEST['call_back'];
        echo "<script>parent.{$call_back}({$post['province']},{$post['city']},{$post['district']});</script>";
        exit(); // 成功 回调closeWindow方法 并返回新增的id
    }
        
    /*
     * 评论晒单
     */
    public function comment(){
        $user_id = $this->user_id;
        $status = I('get.status',-1);
        $logic = new UsersLogic();
        $data = $logic->get_comment($user_id,$status); //获取评论列表
        $this->assign('page',$data['show']);// 赋值分页输出
        $this->assign('comment_list',$data['result']);
        $this->assign('active','comment');
        return $this->fetch();
    }

    /**
     * @time 2017/2/9
     * @author lxl
     * 订单商品评价列表
     */
    public function comment_list()
    {
        $order_id = I('get.order_id');
        $good_id = I('get.good_id');
        if (empty($order_id) || empty($good_id)) {
            $this->error("参数错误");
        } else {
            //查找订单
            $order_comment_where['order_id'] = $order_id;
            $order_info = M('order')->field('order_sn,order_id,add_time') ->where($order_comment_where)->find();
            //查找评价商品
            $order_comment_where['goods_id'] = $good_id;
            $order_goods = M('order_goods')
                ->field('goods_id,is_comment,goods_name,goods_num,goods_price,spec_key_name')
                ->where($order_comment_where)
                ->find();
            $order_info = array_merge($order_info,$order_goods);
            $this->assign('order_info', $order_info);
            return $this->fetch();
        }
    }

    /*
     *添加评论
     */
    public function add_comment()
    {          
            $user_info = session('user');
            $comment_img = serialize(I('comment_img/a')); // 上传的图片文件
            $add['goods_id'] = I('goods_id/d');
            $add['email'] = $user_info['email'];
            //$add['nick'] = $user_info['nickname'];
            $add['username'] = $user_info['nickname'];
            $add['order_id'] = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            $add['is_show'] = 1;
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['img'] = $comment_img;
            $add['add_time'] = time();
            $add['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $add['user_id'] = $this->user_id;
            $logic = new UsersLogic();
            //添加评论
            $row = $logic->add_comment($add);            
            exit(json_encode($row));        
    }

    /*
     * 个人信息
     */
    public function info(){
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $parentId = $user_info['parentUser'];
        $nickname = M('users')->field('nickname')->where('user_id='.$parentId)->find();
        if(IS_POST){
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            if(!$userLogic->update_info($this->user_id,$post))
                $this->error("保存失败");
            $this->success("操作成功");
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取订单城市
        $city =  M('region')->where(array('parent_id'=>$user_info['province'],'level'=>2))->select();
        //获取订单地区
        $area =  M('region')->where(array('parent_id'=>$user_info['city'],'level'=>3))->select();

        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);
        $this->assign('nickname',$nickname['nickname']);
        $this->assign('user',$user_info);
        $this->assign('sex',C('SEX'));
        $this->assign('active','info');
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate(){
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step',1);
        if(IS_POST){
            $email = I('post.email');
            $old_email = I('post.old_email',''); //旧邮箱
            $code = I('post.code');
            $info = session('validate_code');
            if(!$info)
                $this->error('非法操作');
            if($info['time']<time()){
            	session('validate_code',null);
            	$this->error('验证超时，请重新验证');
            }
            //检查原邮箱是否正确
            if($user_info['email_validated'] == 1 && $old_email != $user_info['email'])
                $this->error('原邮箱匹配错误');
            //验证邮箱和验证码
            if($info['sender'] == $email && $info['code'] == $code){
                session('validate_code',null);
                if(!$userLogic->update_email_mobile($email,$this->user_id))
                    $this->error('邮箱已存在');
                $this->success('绑定成功',U('Home/User/index'));
                exit;
            }
            $this->error('邮箱验证码不匹配');
        }
        $this->assign('user_info',$user_info);
        $this->assign('step',$step);
        return $this->fetch();
    }


    /*
    * 手机验证
    */
    public function mobile_validate(){
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); //获取用户信息
        $user_info = $user_info['result'];
        $config = F('sms','',TEMP_PATH);
        $sms_time_out = $config['sms_time_out'];
        $step = I('get.step',1);
        if(IS_POST){
            $mobile = I('post.mobile');
            $old_mobile = I('post.old_mobile','');
            $code = I('post.code');
            $info = session('validate_code');
            if(!$info)
                $this->error('非法操作');
            if($info['time']<time()){
            	session('validate_code',null);
            	$this->error('验证超时，请重新验证');
            }
            //检查原手机是否正确
            if($user_info['mobile_validated'] == 1 && $old_mobile != $user_info['mobile'])
                $this->error('原手机号码错误');
            //验证手机和验证码
            if($info['sender'] == $mobile && $info['code'] == $code){
                session('validate_code',null);
                //验证有效期
                if($info['time'] < time())
                    $this->error('验证码已失效');
                if(!$userLogic->update_email_mobile($mobile,$this->user_id,2))
                    $this->error('手机已存在');
                $this->success('绑定成功',U('Home/User/index'));
                exit;
            }
            $this->error('手机验证码不匹配');
        }
        $this->assign('user_info',$user_info);
        $this->assign('time',$sms_time_out);
        $this->assign('step',$step);
        return $this->fetch();
    }
    
    /**
     * 发送手机注册验证码
     */
    public function send_sms_reg_code(){
         
        $mobile = I('mobile');
        $userLogic = new UsersLogic();
        if(!check_mobile($mobile))
            exit(json_encode(array('status'=>-1,'msg'=>'手机号码格式有误')));
        $code =  rand(1000,9999);
        $send = $userLogic->sms_log($mobile,$code,$this->session_id);
        if($send['status'] != 1)
            exit(json_encode(array('status'=>-1,'msg'=>$send['msg'])));
        exit(json_encode(array('status'=>1,'msg'=>'验证码已发送，请注意查收')));
    }
    /*
     *商品收藏
     */
    public function goods_collect(){
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page',$data['show']);// 赋值分页输出
        $this->assign('lists',$data['result']);
        $this->assign('active','goods_collect');
        return $this->fetch();
    }

    /*
     * 删除一个收藏商品
     */
    public function del_goods_collect(){
        $id = I('get.id');
        if(!$id)
            $this->error("缺少ID参数");
        $row = M('goods_collect')->where(array('collect_id'=>$id,'user_id'=>$this->user_id))->delete();
        if(!$row)
            $this->error("删除失败");
        $this->success('删除成功');
    }

    /*
     * 密码修改
     */
    public function password(){
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if($user['mobile'] == ''&& $user['email'] == '')
            $this->error('请先绑定手机或邮箱',U('Home/User/info'));
        if(IS_POST){
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id,I('post.old_password'),I('post.new_password'),I('post.confirm_password')); // 获取用户信息
            if($data['status'] == -1)
                $this->error($data['msg']);
            $this->success($data['msg']);
            exit;
        }
        return $this->fetch();
    }

    public function forget_pwd(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/index');
    	}
    	if(IS_POST){
    		$logic = new UsersLogic();
    		$username = I('post.username');
    		$code = I('post.code');
    		$new_password = I('post.new_password');
    		$confirm_password = I('post.confirm_password');
    		$pass = false;
    	
    		//检查是否手机找回
    		if(check_mobile($username)){
    			if(!$user = get_user_info($username,2))
    				$this->error('账号不存在');
    			$check_code = $logic->sms_code_verify($username,$code,$this->session_id);
    			if($check_code['status'] != 1)
    				$this->error($check_code['msg']);
    			$pass = true;
    		}
    		//检查是否邮箱
    		if(check_email($username)){
    			if(!$user = get_user_info($username,1))
    				$this->error('账号不存在');
    			$check = session('forget_code');
    			if(empty($check))
    				$this->error('非法操作');
    			if(!$username || !$code || $check['email'] != $username || $check['code'] != $code)
    				$this->error('邮箱验证码不匹配');
    			$pass = true;
    		}
    		if($user['user_id'] > 0 && $pass)
    			$data = $logic->password($user['user_id'],'',$new_password,$confirm_password,false); // 获取用户信息
    		if($data['status'] != 1)
    			$this->error($data['msg'] ? $data['msg'] :  '操作失败');
    		$this->success($data['msg'],U('Home/User/login'));
    		exit;
    	}
        return $this->fetch();
    }
    
    public function set_pwd(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/Index');
    	}
    	$check = session('validate_code');
    	$logic = new UsersLogic();
    	if(empty($check)){
            $this->redirect('Home/User/forget_pwd');
    	}elseif($check['is_check']==0){
    		$this->error('验证码还未验证通过',U('Home/User/forget_pwd'));
    	}    	
    	if(IS_POST){
    		$password = I('post.password');
    		$password2 = I('post.password2');
    		if($password2 != $password){
                    $this->error('两次密码不一致',U('Home/User/forget_pwd'));
    		}
    		if($check['is_check']==1){
                    //$user = get_user_info($check['sender'],1);
                    $user = M('users')->where("mobile|email", '=', $check['sender'])->find();
                    M('users')->where("user_id", $user['user_id'])->save(array('password'=> md5($password.$user['salt'])));
                    session('validate_code',null);
                    $this->redirect('Home/User/finished');
    		}else{
    			$this->error('验证码还未验证通过',U('Home/User/forget_pwd'));
    		}
    	}
    	return $this->fetch();
    }
    
    public function finished(){
    	if($this->user_id > 0){
            $this->redirect('Home/User/Index');
    	}
    	return $this->fetch();
    }   
    
    public function check_captcha(){
    	$verify = new Verify();
    	$type = I('post.type','user_login');
    	if (!$verify->check(I('post.verify_code'), $type)) {
    		exit(json_encode(0));
    	}else{
    		exit(json_encode(1));
    	}
    }
    
    public function check_username(){
    	$username = I('post.username');
    	if(!empty($username)){
    		//$count = M('users')->where("(mobile=".$username." or email=".$username.") and oauth=''")->count();
            $count = M('users')->where("(oauth='' or (oauth !='' and (email !='' or mobile !=''))) and (username='{$username}' or email='{$username}' or mobile='{$username}')")->count();
    		exit(json_encode(intval($count)));
    	}else{
    		exit(json_encode(0));
    	}  	
    }
    
    public function identity(){
       
    	if($this->user_id > 0){
            $this->redirect('Home/User/Index');
    	}
    	$username = I('post.username');
    	$userinfo = array();
    	if($username){
    		$userinfo = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
    		$userinfo['username'] = $username;
    		session('userinfo',$userinfo);
    	}else{
    		$this->error('参数有误！！！');
    	} 	
    	if(empty($userinfo)){
    		$this->error('非法请求！！！');
    	}
    	unset($user_info['password']);
    	$this->assign('userinfo',$userinfo);
    	return $this->fetch();
    }
      
    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        $result = $verify->check(I('post.verify_code'), $id ? $id : 'user_login');
        if (!$result) {
            $this->error("图像验证码错误");
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

    public function order_confirm(){
        $id = I('get.id/d',0);
                                  
        $data = confirm_order($id,$this->user_id);
        $time=time();
        $sql="insert __PREFIX__order_action(order_id,action_user,order_status,action_note,log_time,status_desc) values({$id},{$this->user_id},2,'您确认收货','{$time}','用户确认收货')";
        M('Order')->execute($sql);
        if(!$data['status'])
            $this->error($data['msg']);
	else	
	   $this->success($data['msg']);
    }
    /**
     * 申请退货
     */
    public function return_goods()
    {
        $order_id = I('order_id/d',0);
        $order_sn = I('order_sn',0);
        $goods_id = I('goods_id/d',0);
	    $spec_key = I('spec_key');
        
        $c = M('order')->where("order_id", $order_id)->where('user_id', $this->user_id)->count();
        if($c == 0)
        {
            $this->error('非法操作');
            exit;
        }         
        
        $return_goods = M('return_goods')->where(['order_id'=>$order_id,'goods_id'=>$goods_id,'spec_key'=>$spec_key])->find();
        if(!empty($return_goods))
        {
            $this->success('已经提交过退货申请!',U('Home/User/return_goods_info',array('id'=>$return_goods['id'])));
            exit;
        }       
        if(IS_POST)
        {
            $data['order_id'] = $order_id; 
            $data['order_sn'] = $order_sn; 
            $data['goods_id'] = $goods_id; 
            $data['addtime'] = time(); 
            $data['user_id'] = $this->user_id;            
            $data['type'] = I('type'); // 服务类型  退货 或者 换货
            $data['reason'] = I('reason'); // 问题描述
            $data['imgs'] = I('imgs'); // 用户拍照的相片
            $data['spec_key'] = I('spec_key'); // 商品规格			
            M('return_goods')->add($data);            
            $this->success('申请成功,客服第一时间会帮你处理',U('Home/User/order_list'));
            exit;
        }
               
        $goods = M('goods')->where("goods_id", $goods_id)->find();
        $this->assign('goods',$goods);
        $this->assign('order_id',$order_id);
        $this->assign('order_sn',$order_sn);
        $this->assign('goods_id',$goods_id);
        return $this->fetch();
    }
    
    /**
     * 退换货列表
     */
    public function return_goods_list()
    {        
        $count = M('return_goods')->where("user_id", $this->user_id)->count();
        $page = new Page($count,10);
        $list = M('return_goods')->where("user_id", $this->user_id)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr))
            $goodsList = M('goods')->where("goods_id","in", implode(',',$goods_id_arr))->getField('goods_id,goods_name');
        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('page', $page->show());// 赋值分页输出
        return $this->fetch();
    }
    
    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id = I('id/d',0);
        $return_goods = M('return_goods')->where("id", $id)->find();
        if($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);        
        $goods = M('goods')->where("goods_id", $return_goods['goods_id'])->find();
        $this->assign('goods',$goods);
        $this->assign('return_goods',$return_goods);
        return $this->fetch();
    }
    
    /**
     * 安全设置
     */
    public function safety_settings()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];        
        $this->assign('user',$user_info);
        return $this->fetch();
    }
    
    /**
     * 申请提现记录
     */
    public function withdrawals(){
        
    	//C('TOKEN_ON',true);
    	if(IS_POST)
    	{
            $this->verifyHandle('withdrawals');                
    		$data = I('post.');
    		$data['user_id'] = $this->user_id;    		    		
    		$data['create_time'] = time();                
                $distribut_min = tpCache('basic.min'); // 最少提现额度
                if($data['money'] < $distribut_min)
                {
                        $this->error('每次最少提现额度'.$distribut_min);
                        exit;
                }
                if($data['money'] > $this->user['user_money'])
                {
                        $this->error("你最多可提现{$this->user['user_money']}账户余额.");
                        exit;
                }     
                 
    		if(M('withdrawals')->add($data)){
    			$this->success("已提交申请");
                        exit;
    		}else{
    			$this->error('提交失败,联系客服!');
                        exit;
    		}
    	}
        
        $where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($where)->count();
        $page = new Page($count,10);
        $show = $page->show();
        $list = M('withdrawals')->where($where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select(); 
        $this->assign('active','withdrawals');
        $this->assign('show',$show);// 赋值分页输出
        $this->assign('list',$list); // 下线
        return $this->fetch();
    }
    
   public  function recharge(){
   		if(IS_POST){
   			$user = session('user');
   			$data['user_id'] = $this->user_id;
   			$data['nickname'] = $user['nickname'];
   			$data['account'] = I('account');
   			$data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
   			$data['ctime'] = time();
   			$order_id = M('recharge')->add($data);
   			if($order_id){
   				$url = U('Payment/getPay',array('pay_radio'=>$_REQUEST['pay_radio'],'order_id'=>$order_id));
   				redirect($url);
   			}else{
   				$this->error('提交失败,参数有误!');
   			}
   		}
   		
	   	$paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,2)")->select();
	   	$paymentList = convert_arr_key($paymentList, 'code');	   	
	   	foreach($paymentList as $key => $val)
	   	{
	   		$val['config_value'] = unserialize($val['config_value']);
	   		if($val['config_value']['is_bank'] == 2)
	   		{
	   			$bankCodeList[$val['code']] = unserialize($val['bank_code']);
	   		}
	   	}
	   	$bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
	   	$this->assign('paymentList',$paymentList);
	   	$this->assign('bank_img',$bank_img);
	   	$this->assign('bankCodeList',$bankCodeList);
	   	
	   	$count = M('recharge')->where(array('user_id'=>$this->user_id))->count();
	   	$Page = new Page($count,10);
	   	$show = $Page->show();
	   	$recharge_list = M('recharge')->where(array('user_id'=>$this->user_id))->limit($Page->firstRow.','.$Page->listRows)->select();
	   	$this->assign('page',$show);
	   	$this->assign('recharge_list',$recharge_list);//充值记录
	   	
	   	$count2 = M('account_log')->where(array('user_id'=>$this->user_id,'user_money'=>array('gt',0)))->count();
	   	$Page2 = new Page($count2,10);
	   	$consume_list = M('account_log')->where(array('user_id'=>$this->user_id,'user_money'=>array('gt',0)))->limit($Page2->firstRow.','.$Page2->listRows)->select();
        $this->assign('point_rate', tpCache('shopping.point_rate'));
	   	$this->assign('consume_list',$consume_list);//消费记录
	   	$this->assign('page2',$Page2->show());
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
        $type = I('type',0);
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
     * 用户中心商品列表
     * @author ww
     * @time 2017/07/20
     */
    public function goods_list(){
        $search = I('search')?I('search'):'';
        $where = '';
        if($search){
            $where = " and ((goods_name like BINARY'%{$search}%') or (goods_sn like BINARY'%{$search}%'))";
        }
        $count = M('goods')->where('is_on_sale=1'.$where)->count();
        $Page = new Page($count,15);
        $show = $Page->show();
        $goods = M('goods')->where('is_on_sale=1'.$where)->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('goods',$goods);
        $this->assign('page',$show);
        $this->assign('active','goods_list');
        return $this->fetch();
    }

    /**
     * 系统地址库
     * @author ww
     * @time 2017/08/01
     */
    public function shop_region(){
        $province = M('region')->where('level=1')->select();
        $this->assign('province',$province);
        $this->assign('active','shop_region');
        return $this->fetch();
    }

    public function changeProvince(){
        $province = I('province');
        $city = M('region')->where('level=2 and parent_id='.$province)->select();
        errorMsg(200,'',$city);
    }

    public function changeCity(){
        $city = I('city');
        $area = M('region')->where('level=3 and parent_id='.$city)->select();
        errorMsg(200,'',$area);
    }
    //用户导出商品价格表
    public function outputPrice(){
        $goods=M('goods')->where('is_on_sale=1')->field('goods_name,goods_sn,shop_price,firstMemberPrice,secondMemberPrice,thirdMemberPrice')->select();
        $user_role=M('user_role')->select();
        vendor("phpexcel.PHPExcel");
        $objPHPExcel = new PHPExcel();  
        // Set properties    
        $objPHPExcel->getProperties()->setCreator("ctos")  
                ->setLastModifiedBy("ctos")  
                ->setTitle("Office 2007 XLSX Test Document")  
                ->setSubject("Office 2007 XLSX Test Document")  
                ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")  
                ->setKeywords("office 2007 openxml php")  
                ->setCategory("Test result file");  
        // set width    
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(40);  
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);  
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);  
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20); 
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20); 

        // 设置行高度    
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(22);  
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(20);  
        $arr = array();
        foreach ($user_role as $key => $value) {
            switch ($value['role_level']) {
                case 3:
                    $arr[0] = $value['role_name'];
                    break;
                case 2:
                    $arr[1] = $value['role_name'];
                    break;
                case 1:
                    $arr[2] = $value['role_name'];
                    break;
                case 0:
                    $arr[3] = $value['role_name'];
                    break;        
                default:
                    break;
            }
        }

        // 表头  
        $objPHPExcel->setActiveSheetIndex(0)   
                ->setCellValue('A1', '商品名称')  
                ->setCellValue('B1', '商品编码')  
                ->setCellValue('C1', $arr[0].'价格')
                ->setCellValue('D1', $arr[1].'价格')
                ->setCellValue('E1', $arr[2].'价格')
                ->setCellValue('F1', $arr[3].'价格');
        // 内容  
        for ($i = 0, $len = count($goods); $i < $len; $i++) {  
            $objPHPExcel->getActiveSheet(1)->setCellValue('A' . ($i + 2), $goods[$i]['goods_name']);  
            $objPHPExcel->getActiveSheet(1)->setCellValue('B' . ($i + 2), $goods[$i]['goods_sn']);  
            $objPHPExcel->getActiveSheet(1)->setCellValue('C' . ($i + 2), $goods[$i]['firstMemberPrice']);
            $objPHPExcel->getActiveSheet(1)->setCellValue('D' . ($i + 2), $goods[$i]['secondMemberPrice']);
            $objPHPExcel->getActiveSheet(1)->setCellValue('E' . ($i + 2), $goods[$i]['thirdMemberPrice']);
            $objPHPExcel->getActiveSheet(1)->setCellValue('F' . ($i + 2), $goods[$i]['shop_price']);
        }  

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet    
        $objPHPExcel->setActiveSheetIndex(0);  

        // 输出  
        header('Content-Type: application/vnd.ms-excel');  
        header('Content-Disposition: attachment;filename="' . '商品会员价格表' . '.xls"');  
        header('Cache-Control: max-age=0');  

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');  
        $objWriter->save('php://output'); 
    }
}