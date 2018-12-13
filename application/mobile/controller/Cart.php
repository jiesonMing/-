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
 * $Author: IT宇宙人 2015-08-10 $
 */ 
namespace app\mobile\controller;
use think\Db;
class Cart extends MobileBase {
    
    public $cartLogic; // 购物车逻辑操作类    
    public $user_id = 0;
    public $user = array();
    public $db_prex='';
    public $myLevel=0;
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();                
        $this->cartLogic = new \app\home\logic\CartLogic();
        $this->db_prex=C('db_prex');
        if(session('?user'))
        {
        	$user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user',$user);  //覆盖session 中的 user               			                
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
            $this->myLevel=$user['myLevel'];
        	$this->assign('user',$user); //存储用户信息
            // 给用户计算会员价 登录前后不一样
            $goodsIdArr=Db::query("select fs.goods_id from __PREFIX__cart c left join __PREFIX__flash_sale fs on fs.goods_id=c.goods_id where fs.end_time>unix_timestamp(now())");       
            $goodsIdArr=implode(',',array_column($goodsIdArr,'goods_id'));
            if(!$goodsIdArr)    $goodsIdArr=0;
            if($user){
               if($this->myLevel==1){
                    Db::execute("update __PREFIX__cart as c,__PREFIX__goods as g set c.member_goods_price=g.thirdMemberPrice where c.goods_id=g.goods_id and c.user_id={$this->user_id} and c.goods_id not in ({$goodsIdArr})");
                }elseif($this->myLevel==2){
                    Db::execute("update __PREFIX__cart as c,__PREFIX__goods as g set c.member_goods_price=g.secondMemberPrice where c.goods_id=g.goods_id and c.user_id={$this->user_id} and c.goods_id not in ({$goodsIdArr})");
                }elseif($this->myLevel==3){
                   Db::execute("update __PREFIX__cart as c,__PREFIX__goods as g set c.member_goods_price=g.firstMemberPrice where c.goods_id=g.goods_id and c.user_id={$this->user_id} and c.goods_id not in ({$goodsIdArr})");
                }else{
                   Db::execute("update __PREFIX__cart as c,__PREFIX__goods as g set c.member_goods_price=g.shop_price where c.goods_id=g.goods_id and c.user_id={$this->user_id} and c.goods_id not in ({$goodsIdArr})");
                }
            }               
        }            
    }
    
    public function cart(){
        //获取热卖商品
        $hot_goods = M('Goods')->where('is_hot=1 and is_on_sale=1')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('hot_goods',$hot_goods);
        $time=time();
        $this->assign('nowTime',$time);
        return $this->fetch('cart');
    }
    /**
     * 将商品加入购物车
     */
    function addCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $goods_spec = I("goods_spec"); // 商品规格                
        $goods_spec = json_decode($goods_spec,true); //app 端 json 形式传输过来
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $user_id = I("user_id",0); // 用户id       
        $result = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec,$unique_id,$user_id); // 将商品加入购物车
        exit(json_encode($result)); 
    }
    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $goods_spec = I("goods_spec/a",array()); // 商品规格
        $result = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec,$this->session_id,$this->user_id); // 将商品加入购物车
        exit(json_encode($result));
    }

    /*
     * 请求获取购物车列表
     */
    public function cartList()
    {
        $cart_form_data = input('cart_form_data'); // goods_num 购物车商品数量
        $cart_form_data = json_decode($cart_form_data,true); //app 端 json 形式传输过来

        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $user_id = I("user_id/d"); // 用户id
        $where['session_id'] = $unique_id; // 默认按照 $unique_id 查询
        if($user_id){
            $where['user_id'] = $user_id;
        }
        $cartList = M('Cart')->where($where)->getField("id,goods_num,selected");

        if($cart_form_data)
        {
            // 修改购物车数量 和勾选状态
            foreach($cart_form_data as $key => $val)
            {
                $data['goods_num'] = $val['goodsNum'];
                $data['selected'] = $val['selected'];
                $cartID = $val['cartID'];
                if(($cartList[$cartID]['goods_num'] != $data['goods_num']) || ($cartList[$cartID]['selected'] != $data['selected']))
                    M('Cart')->where("id", $cartID)->save($data);
            }
            //$this->assign('select_all', $_POST['select_all']); // 全选框
        }

        $result = $this->cartLogic->cartList($this->user, $unique_id,0);
        exit(json_encode($result));
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2()
    {
        if($this->user_id == 0)
            $this->error('请先登陆',U('Mobile/User/login'));
        $address_id = I('address_id/d');
        if($address_id)
            $address = M('user_address')->where("address_id", $address_id)->find();
        else
            $address = M('user_address')->where(['user_id'=>$this->user_id,'is_default'=>1])->find();
        
        if(empty($address)){
        	header("Location: ".U('Mobile/User/add_address',array('source'=>'cart2')));
        }else{
        	$this->assign('address',$address);
        }
        if($this->cartLogic->cart_count($this->user_id,1) == 0 )
            $this->error ('你的购物车没有选中商品','Cart/cart');
        $result = $this->cartLogic->cartList($this->user, $this->session_id,1,1); // 获取购物车商品
        //检查保税仓身份证
        if(I('post.buyerIdNumber')==1){
            foreach($result['cartList'] as $v){
                if($v['selected']==1){
                    if($address['buyerIdNumber'] == '')
                        echo -1;//身份证为空
                }
            }
            exit;
        }
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();// 物流公司

        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        $sql = "select c1.name,c1.money,c1.condition, c2.* from __PREFIX__coupon as c1 inner join __PREFIX__coupon_list as c2  on c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0  where c2.uid = {$this->user_id} and ".time()." < c1.use_end_time and c1.condition <= {$result['total_price']['total_fee']}";
        $couponList = DB::query($sql);
        if(I('cid') != ''){
            $cid = I('cid');
            $checkconpon = M('coupon')->field('id,name,money')->where("id = $cid")->find();    //要使用的优惠券
            $checkconpon['lid'] = I('lid');
        }
        $bindId=M('users')->where("mobile='' and oauth='weixin' and user_id=".$this->user_id)->field('bindId')->find();//是否有绑定pc商城
        if(empty($bindId))
            $bindId['bindId']=1;
        //dump($result['cartList']);exit;
        foreach($result['cartList'] as $v){
            if($v['selected']==1){
                if($address['buyerIdNumber'] == ''){
                    $this->assign('isBaoshui',1);
                }
            }
        }
        $this->assign('bindId',$bindId);
        $this->assign('couponList', $couponList); // 优惠券列表
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('cartList', $result['cartList']); // 购物车的商品
        $this->assign('total_price', $result['total_price']); // 总计
        $this->assign('checkconpon', $checkconpon); // 使用的优惠券
        return $this->fetch();
    }

    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){
        set_time_limit(0);
        if($this->user_id == 0)
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态       
        $address_id = I("address_id/d"); //  收货地址id
        $shipping_code =  I("shipping_code"); //  物流编号        
        $invoice_title = I('invoice_title'); // 发票
        $couponTypeSelect =  I("couponTypeSelect"); //  优惠券类型  1 下拉框选择优惠券 2 输入框输入优惠券代码
        $coupon_id =  I("coupon_id/d"); //  优惠券id
        $couponCode =  I("couponCode"); //  优惠券代码
        $pay_points =  I("pay_points",0); //  使用积分
        $user_money =  I("user_money",0); //  使用余额
        $user_note = trim(I('user_note'));   //买家留言
        $user_money = $user_money ? $user_money : 0;
        if($this->cartLogic->cart_count($this->user_id,1) == 0 ) exit(json_encode(array('status'=>-2,'msg'=>'你的购物车没有选中商品','result'=>null))); // 返回结果状态
        if(!$address_id) exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>null))); // 返回结果状态
        if(!$shipping_code) exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>null))); // 返回结果状态
		
	    $address = M('UserAddress')->where("address_id", $address_id)->find();

        $sql="select c.*,gs.portId,gs.tax from __PREFIX__cart c left join __PREFIX__goods gs on gs.goods_id=c.goods_id where c.user_id={$this->user_id} and c.selected=1";
        $order_goods = M('cart')->query($sql);
        //判断商品在那个仓库
        $order_goods1=$order_goods2=$totalArr=$order_goodsTest=array();
        foreach ($order_goods as $k=>$v){
            $fenWare=M('goods')->where(['goods_id'=>$v['goods_id']])->field('stype,wareId,portId')->find();
            $field=$fenWare['wareId'].$fenWare['portId'];
            $order_goodsTest[$field][]=$order_goods[$k];
        }
        foreach ($order_goodsTest as $k => $v) {
            $totalArr[]=$v;
        }
        $car_price1=array();
        foreach ($totalArr as $order_goods){
            $result = calculate_price($this->user_id,$order_goods,$shipping_code,0,$address[province],$address[city],$address[district],$pay_points,$user_money,$coupon_id,$couponCode);
            if($result['status'] < 0){
                exit(json_encode($result));
            }		      	
            // 订单满额优惠活动		                
            $order_prom = get_order_promotion($result['result']['order_amount']);
            $result['result']['order_amount'] = $order_prom['order_amount'] ;
            $result['result']['order_prom_id'] = $order_prom['order_prom_id'] ;
            $result['result']['order_prom_amount'] = $order_prom['order_prom_amount'] ;
            $car_price = array(
                'postFee'      => $result['result']['shipping_price'], // 物流费
                'couponFee'    => $result['result']['coupon_price'], // 优惠券            
                'balance'      => $result['result']['user_money'], // 使用用户余额
                'pointsFee'    => $result['result']['integral_money'], // 积分支付
                'payables'     => $result['result']['order_amount'], // 应付金额
                'goodsFee'     => $result['result']['goods_price'],// 商品价格
                'order_prom_id' => $result['result']['order_prom_id'], // 订单优惠活动id
                'taxTotal'      => $result['result']['taxTotal'], // 商品税
                'order_prom_amount' => $result['result']['order_prom_amount'], // 订单优惠活动优惠了多少钱
                'stype'         =>$order_goods[0]['stype'],//1-南沙保税仓，2-普通仓
                'wareId'        =>$order_goods[0]['wareId'],
                'portId'        =>$order_goods[0]['portId']
            );
            $car_price1[]=$car_price;//中间变量
        }
        //如有2个订单就把2个订单的数据相加，再还原为$car_price
        if(count($car_price1)>=2){
            $car_price=array();
            foreach ($car_price1 as $k=> $cp1){
                $car_price['postFee']+=$cp1['postFee'];
                $car_price['couponFee']+=$cp1['couponFee'];
                $car_price['balance']+=$cp1['balance'];
                $car_price['pointsFee']+=$cp1['pointsFee'];
                $car_price['payables']+=$cp1['payables'];
                $car_price['taxTotal']+=$cp1['taxTotal'];
                $car_price['goodsFee']+=$cp1['goodsFee'];
                $car_price['order_prom_id']+=$cp1['order_prom_id'];
                $car_price['order_prom_amount']+=$cp1['order_prom_amount'];
                $car_price['stype']+=$cp1['stype'];
                $car_price['wareId']+=$cp1['wareId'];
                $car_price['portId']+=$cp1['portId'];
            }
        }
        //$return_arr = array('status'=>1,'msg'=>'测试成功','result'=>$car_price1); // 返回结果状态
        //exit(json_encode($return_arr));

        // 提交订单        
        if($_REQUEST['act'] == 'submit_order')
        {           
            if(empty($coupon_id) && !empty($couponCode)){
                $coupon_id = M('CouponList')->where("code", $couponCode)->getField('id');
            }
            //若有2个订单把两个订单分别进库
            if(count($car_price1)>=2){
                //两个不同仓的单
                $resultArr=array();
                foreach ($car_price1 as $car_price){                   
                    $result = $this->cartLogic->addOrder($this->user_id,$address_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$user_note); // 添加订单
                    //exit(json_encode($result));
                    $resultArr[]=$result['result'];
                }
                exit(json_encode(array('status'=>1,'msg'=>'提交订单成功','result'=>$resultArr)));//'status'=>1,'msg'=>'提交订单成功','result'=>$order_id
            }else{
                //同一个仓的单
                $result = $this->cartLogic->addOrder($this->user_id,$address_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$user_note); // 添加订单
                exit(json_encode($result));
            }
        }
        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price); // 返回结果状态
        exit(json_encode($return_arr));
    }	
    /*
     * 订单支付页面
     */
    public function cart4(){
        $order_id = I('order_id/d');
        if(empty($order_id)){
            $this->redirect(U("Mobile/Index/index"));
            exit;
        }
        $order = M('Order')->where("order_id", $order_id)->find();
        // 如果已经支付过的订单或支付中直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){//$order['pay_status'] == 2
            $order_detail_url = U("Mobile/User/order_detail",array('id'=>$order_id));
            header("Location: $order_detail_url");
            exit;
        }

        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if($order['order_prom_type'] == 4){
                //预售订单
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if($order['order_prom_type'] == 4){
                //预售订单
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1'));
        }
        //$paymentList = M('Plugin')->where($payment_where)->select();
        //$paymentList = convert_arr_key($paymentList, 'code');

        // foreach($paymentList as $key => $val)
        // {
        //     $val['config_value'] = unserialize($val['config_value']);
        //     if($val['config_value']['is_bank'] == 2)
        //     {
        //         $bankCodeList[$val['code']] = unserialize($val['bank_code']);
        //     }
        //     //判断当前浏览器显示支付方式
        //     if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
        //         unset($paymentList[$key]);
        //     }
        // }
        //$userType=M('users')->where('user_id',$this->user_id)->field('userType')->find();
        $payment_where = array(
            //'userType'=>$userType['userType'],
            'status'=>1,
            'isMobile'=>1,
            'isAdmin'=>0,
            //'scene'=>array('in',array(0,2))
        );       
        $paymentList = M('Pay_type')->where($payment_where)->limit(0,1)->find();
        $this->assign('paymentList',$paymentList); 
        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        //$this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        //$this->assign('bankCodeList',$bankCodeList);
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }


    /*
    * ajax 请求获取购物车列表
    */
    public function ajaxCartList()
    {
        $post_goods_num = I("goods_num/a"); // goods_num 购物车商品数量
        $post_cart_select = I("cart_select/a"); // 购物车选中状态
        $where['session_id'] = $this->session_id; // 默认按照 session_id 查询
        // 如果这个用户已经登录了则按照用户id查询
        if($this->user_id){
            unset($where);
            $where['user_id'] = $this->user_id;
            //如果有联合的uid,就用联合的id
            if(!empty(session('unionUser_id')))
                $where['user_id']="in (".session('unionUser_id').")";
        }
        $cartList = M('Cart')->where($where)->getField("id,goods_num,selected,prom_type,prom_id");
        if($post_goods_num)
        {
            // 修改购物车数量 和勾选状态
            foreach($post_goods_num as $key => $val)
            {                
                $data['goods_num'] = $val < 1 ? 1 : $val;
                if($cartList[$key]['prom_type'] == 1) //限时抢购 不能超过购买数量
                {
                    $flash_sale = M('flash_sale')->where("id", $cartList[$key]['prom_id'])->find();
                    $data['goods_num'] = $data['goods_num'] > $flash_sale['buy_limit'] ? $flash_sale['buy_limit'] : $data['goods_num'];
                }
                
                $data['selected'] = $post_cart_select[$key] ? 1 : 0 ;
                if(($cartList[$key]['goods_num'] != $data['goods_num']) || ($cartList[$key]['selected'] != $data['selected']))
                    M('Cart')->where("id", $key)->save($data);
            }
            $this->assign('select_all', input('post.select_all')); // 全选框
        }       
        $result = $this->cartLogic->cartList($this->user, $this->session_id,1,1);        
        if(empty($result['total_price']))
            $result['total_price'] = Array( 'total_fee' =>0, 'cut_fee' =>0, 'num' => 0, 'atotal_fee' =>0, 'acut_fee' =>0, 'anum' => 0);
        $this->assign('cartList', $result['cartList']); // 购物车的商品                
        $this->assign('total_price', $result['total_price']); // 总计
        return $this->fetch('ajax_cart_list');
    }

    /*
 * ajax 获取用户收货地址 用于购物车确认订单页面
 */
    public function ajaxAddress(){

        $regionList = M('Region')->getField('id,name');

        $address_list = M('UserAddress')->where("user_id", $this->user_id)->select();
        $c = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址
        if((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;

        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }

    /**
     * ajax 删除购物车的商品
     */
    public function ajaxDelCart()
    {
        $ids = I("ids"); // 商品 ids
        $result = M("Cart")->where("id","in",$ids)->delete(); // 删除id为5的用户数据
        $return_arr = array('status'=>1,'msg'=>'删除成功','result'=>''); // 返回结果状态
        exit(json_encode($return_arr));
    }

}
