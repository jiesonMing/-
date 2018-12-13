<?php
/**
* jieson edit
 */

namespace app\home\logic;
use think\Model;
use think\Db;
use app\common\model\Cart;
use app\common\model\SpecGoodsPrice;
use app\common\model\Goods;
/**
 * 购物车 逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class CartLogic extends Model
{
    public $myLevel=0;
    public $db_prex='';
    protected $session_id;//session_id
    public function __construct()
    {
        parent::__construct();
        $this->session_id = session_id();
    }
    /**
     * 加入购物车方法
     * @param type $goods_id  商品id
     * @param type $goods_num   商品数量
     * @param type $goods_spec  选择规格 
     * @param type $user_id 用户id
     */
    function addCart($goods_id,$goods_num,$goods_spec,$session_id,$user_id = 0)
    {
        $user = session('user');
        if($user){
            $this->myLevel = $user['myLevel'];
        }
        //若是促销商品
        // if(session('prom_goods')==1){
        //     $goods=M('flash_sale')->query("select fs.goods_id,fs.price,fs.buy_limit,fs.start_time,fs.end_time,fs.goods_name,gs.goods_name,gs.market_price,gs.shop_price,fs.goods_num as store_count,gs.brand_id,gs.goods_content,gs.goods_sn,gs.stype,gs.wareId,gs.portId from __PREFIX__flash_sale fs left join __PREFIX__goods gs on gs.goods_id=fs.goods_id where fs.goods_id={$goods_id} limit 0,1");
        //     $goods=$goods[0];
        // }else{
        //     $goods = M('Goods')->where("goods_id", $goods_id)->cache(true,TPSHOP_CACHE_TIME)->find(); // 找出这个商品
        // }
        $goods = M('Goods')->where("goods_id", $goods_id)->cache(true,TPSHOP_CACHE_TIME)->find(); // 找出这个商品
        $specGoodsPriceList = M('SpecGoodsPrice')->where("goods_id", $goods_id)->cache(true,TPSHOP_CACHE_TIME)->getField("key,key_name,price,store_count,sku"); // 获取商品对应的规格价钱 库存 条码
	    $where = " session_id = :session_id ";
        $bind['session_id'] = $session_id;
        $user_id = $user_id ? $user_id : 0;
	    if($user_id){
            $where .= "  or user_id= :user_id ";
            $bind['user_id'] = $user_id;
        }
        $catr_count = M('Cart')->where($where)->bind($bind)->count(); // 查找购物车商品总数量

        if($catr_count >= 20) 
            return array('status'=>-9,'msg'=>'购物车最多只能放20种商品','result'=>'');            
        
//        if(!empty($specGoodsPriceList) && empty($goods_spec)) // 有商品规格 但是前台没有传递过来
//            return array('status'=>-1,'msg'=>'必须传递商品规格','result'=>'');                        
        if($goods_num <= 0) 
            return array('status'=>-2,'msg'=>'购买商品数量不能为0','result'=>'');            
        if(empty($goods))
            return array('status'=>-3,'msg'=>'购买商品不存在','result'=>'');            
        if(($goods['store_count'] < $goods_num))
            return array('status'=>-4,'msg'=>'商品库存不足','result'=>'');        
        if($goods['prom_type'] > 0 && $user_id == 0)
            return array('status'=>-101,'msg'=>'购买活动商品必须先登录','result'=>'');
        
        //限时抢购 不能超过购买数量        
        if($goods['prom_type'] == 1) 
        {
            $flash_sale = M('flash_sale')->where(['id'=>$goods['prom_id'],'start_time'=>['<',time()],'end_time'=>['>',time()],'goods_num'=>['>','buy_num']])->find(); // 限时抢购活动
            if($flash_sale){
                $cart_goods_num = M('Cart')->where($where)->where("goods_id", $goods['goods_id'])->bind($bind)->getField('goods_num');
                // 如果购买数量 大于每人限购数量
                if(($goods_num + $cart_goods_num) > $flash_sale['buy_limit'])
                {  
                    $cart_goods_num && $error_msg = "你当前购物车已有 $cart_goods_num 件!";
                    return array('status'=>-4,'msg'=>"每人限购 {$flash_sale['buy_limit']}件 $error_msg",'result'=>'');
                }                        
                // 如果剩余数量 不足 限购数量, 就只能买剩余数量
                if(($flash_sale['goods_num'] - $flash_sale['buy_num']) < $flash_sale['buy_limit'])
                    return array('status'=>-4,'msg'=>"库存不够,你只能买".($flash_sale['goods_num'] - $flash_sale['buy_num'])."件了.",'result'=>'');                    
            }
        }                
        
        foreach($goods_spec as $key => $val) // 处理商品规格
            $spec_item[] = $val; // 所选择的规格项                            
        if(!empty($spec_item)) // 有选择商品规格
        {
            sort($spec_item);
            $spec_key = implode('_', $spec_item);
            if($specGoodsPriceList[$spec_key]['store_count'] < $goods_num) 
                return array('status'=>-4,'msg'=>'商品库存不足','result'=>'');
            $spec_price = $specGoodsPriceList[$spec_key]['price']; // 获取规格指定的价格
        }
                
        $where = " goods_id = :goods_id and spec_key = :spec_key"; // 查询购物车是否已经存在这商品
        if($spec_key){
            $cart_bind['spec_key'] = $spec_key;
        }else{
            $cart_bind['spec_key'] = '';
        }
        $cart_bind['goods_id'] = $goods_id;
        if($user_id > 0){
            $where .= " and (session_id = :session_id or user_id = :user_id) ";
            $cart_bind['session_id'] = $session_id;
            $cart_bind['user_id'] = $user_id;
        } else{
            $where .= " and  session_id = :session_id ";
            $cart_bind['session_id'] = $session_id;
        }

        $catr_goods = M('Cart')->where($where)->bind($cart_bind)->find(); // 查找购物车是否已经存在该商品
        $price = $spec_price ? $spec_price : $goods['shop_price']; // 如果商品规格没有指定价格则用商品原始价格
        //促销商品
        $prom_goods=M('flash_sale')->where("goods_id",$goods_id)->find();
        if($prom_goods){
            $memberGoodsPrice= $prom_goods['price'];
            $goods['prom_type']=1;
        }else{
            if($this->myLevel==3){
                $memberGoodsPrice= $goods['firstMemberPrice'];
            }elseif($this->myLevel==2){
                $memberGoodsPrice= $goods['secondMemberPrice'];            
            }elseif($this->myLevel==1){
                $memberGoodsPrice= $goods['thirdMemberPrice'];            
            }else{
                $memberGoodsPrice= $goods['shop_price'];            
            }
        }
        
        // 商品参与促销
        if($goods['prom_type'] > 0)
        {            
            $prom = get_goods_promotion($goods_id,$user_id);
            $price = $prom['price'];
            $goods['prom_type'] = $prom['prom_type'];
            $goods['prom_id']   = $prom['prom_id'];
        }
        
        $data = array(                    
                    'user_id'         => $user_id,   // 用户id
                    'session_id'      => $session_id,   // sessionid
                    'goods_id'        => $goods_id,   // 商品id
                    'goods_sn'        => $goods['goods_sn'],   // 商品货号
                    'goods_name'      => $goods['goods_name'],   // 商品名称
                    'market_price'    => $goods['market_price'],   // 市场价
                    'goods_price'     => $price,  // 购买价
                    'member_goods_price' => $memberGoodsPrice,  // 会员折扣价 默认为 购买价
                    'goods_num'       => $goods_num, // 购买数量
                    'spec_key'        => "{$spec_key}", // 规格key
                    'spec_key_name'   => "{$specGoodsPriceList[$spec_key]['key_name']}", // 规格 key_name
                    'sku'        => "{$specGoodsPriceList[$spec_key]['sku']}", // 商品条形码                    
                    'add_time'        => time(), // 加入购物车时间
                    'prom_type'       => $goods['prom_type'],   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                    'prom_id'         => $goods['prom_id'],   // 活动id   
                    'stype'           => $goods['stype'], //1-南沙保税仓，2-普通仓
                    'wareId'          => $goods['wareId'],
                    'portId'          => $goods['portId']
        );
        if($prom_goods){
            $data['prom_type']=1;
        }
        //return array('status'=>1,'msg'=>'成功加入购物车','result'=>$data);    exit;         
       // 如果商品购物车已经存在 
       if($catr_goods) 
       {          
           // 如果购物车的已有数量加上 这次要购买的数量  大于  库存输  则不再增加数量
            if(($catr_goods['goods_num'] + $goods_num) > $goods['store_count'])
                $goods_num = 0;
            $result = M('Cart')->where("id", $catr_goods['id'])->save(  array("goods_num"=> ($catr_goods['goods_num'] + $goods_num)) ); // 数量相加
            $cart_count = cart_goods_num($user_id,$session_id); // 查找购物车数量 
            setcookie('cn',$cart_count,null,'/');
            return array('status'=>1,'msg'=>'成功加入购物车','result'=>$cart_count);
       }
       else
       {         
             $insert_id = M('Cart')->add($data);
             $cart_count = cart_goods_num($user_id,$session_id); // 查找购物车数量
             setcookie('cn',$cart_count,null,'/');
             return array('status'=>1,'msg'=>'成功加入购物车','result'=>$cart_count);
       }     
            $cart_count = cart_goods_num($user_id,$session_id); // 查找购物车数量 
            return array('status'=>-5,'msg'=>'加入购物车失败','result'=>$cart_count);        
    }
    
    /**
     * 购物车列表 
     * @param type $user   用户
     * @param type $session_id  session_id
     * @param type $selected  是否被用户勾选中的 0 为全部 1为选中  一般没有查询不选中的商品情况
     * $mode 0  返回数组形式  1 直接返回result
     */
    function cartList($user = array() , $session_id = '', $selected = 0,$mode =0)
    {                   
        
        $where = " 1 = 1 ";
        //if($selected != NULL)
        //    $where = " selected = $selected "; // 购物车选中状态
        $bind = array();
        if($user[user_id])// 如果用户已经登录则按照用户id查询
        {
             $where .= " and user_id = $user[user_id] ";
             // 给用户计算会员价 登录前后不一样             
        }           
        else
        {
            $where .= " and session_id = :session_id";
            $bind['session_id'] = $session_id;
            $user[user_id] = 0;
        }
                                
        $cartList = M('Cart')->where($where)->bind($bind)->select();  // 获取购物车商品
        $anum = $total_price =  $cut_fee = 0;

        foreach ($cartList as $k=>$val){
            $cartList[$k]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];
            $cartList[$k]['store_count']  = getGoodNum($val['goods_id'],$val['spec_key']); // 最多可购买的库存数量        	
            $anum += $val['goods_num'];

            $checkChangeSql = 'select goods_id from __PREFIX__goods where goods_id="'.$val['goods_id'].'" and goods_name="'.$val['goods_name'].'" and market_price="'.$val['market_price'].'" and shop_price="'.$val['goods_price'].'" and (firstMemberPrice="'.$val['member_goods_price'].'" or secondMemberPrice="'.$val['member_goods_price'].'" or thirdMemberPrice="'.$val['member_goods_price'].'" or shop_price="'.$val['member_goods_price'].'")';
            $checkChange = DB::query($checkChangeSql);
            //促销商品
            $isPromGoods=DB::query("select id,end_time from __PREFIX__flash_sale where goods_id=".$val['goods_id']);
            if($val['prom_type']==0){
                if(!$checkChange && !$isPromGoods){
                    $data['selected'] = 3;
                    M('Cart')->where('id',$val['id'])->save($data);
                }
            }
            if($isPromGoods){
                $cartList[$k]['end_time']=$isPromGoods[0]['end_time'];
            }
            // 如果要求只计算购物车选中商品的价格 和数量  并且  当前商品没选择 则跳过,3表示当前商品修改过跳过
            if(($selected == 1 && $val['selected'] == 0) || $val['selected']==3)
            continue;
            $cut_fee += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price'];                
            $total_price += $val['goods_num'] * $val['member_goods_price'];
        }
        
        // return $checkChangeSql;
        $total_price = array('total_fee' =>$total_price , 'cut_fee' => $cut_fee,'num'=> $anum,); // 总计        
        setcookie('cn',$anum,time()+(3600*2),'/');
        if($mode == 1) return array('cartList' => $cartList, 'total_price' => $total_price);
        return array('status'=>1,'msg'=>'','result'=>array('cartList' =>$cartList, 'total_price' => $total_price));
    }

    /**
     * 计算商品的的运费
     * @param type $shipping_code 物流 编号
     * @param type $province 省份
     * @param type $city 市
     * @param type $district 区
     * @return int
     */
    function cart_freight2($shipping_code, $province, $city, $district, $weight)
    {

        if ($weight == 0) return 0; // 商品没有重量
        if ($shipping_code == '') return 0;
        // 先根据 镇 县 区找 shipping_area_id
        $shipping_area_id = M('AreaRegion')->where("shipping_area_id in (select shipping_area_id from  " . C('database.prefix') . "shipping_area where shipping_code = :shipping_code) and region_id = :region_id")->bind(['shipping_code'=>$shipping_code,'region_id'=>$district])->getField('shipping_area_id');
        // 先根据市区找 shipping_area_id
        if ($shipping_area_id == false)
            $shipping_area_id = M('AreaRegion')->where("shipping_area_id in (select shipping_area_id from  " . C('database.prefix') . "shipping_area where shipping_code = :shipping_code) and region_id = :region_id")->bind(['shipping_code'=>$shipping_code,'region_id'=>$city])->getField('shipping_area_id');

        // 市区找不到 根据省份找shipping_area_id
        if ($shipping_area_id == false)
            $shipping_area_id = M('AreaRegion')->where("shipping_area_id in (select shipping_area_id from  " . C('database.prefix') . "shipping_area where shipping_code = :shipping_code) and region_id = :region_id")->bind(['shipping_code'=>$shipping_code,'region_id'=>$province])->getField('shipping_area_id');

        // 省份找不到 找默认配置全国的物流费
        if ($shipping_area_id == false) {
            // 如果市和省份都没查到, 就查询 tp_shipping_area 表 is_default = 1 的  表示全国的  select * from `tp_plugin`  select * from  `tp_shipping_area` select * from  `tp_area_region`
            $shipping_area_id = M("ShippingArea")->where(['shipping_code'=>$shipping_code,'is_default'=>1])->getField('shipping_area_id');
        }
        if ($shipping_area_id == false)
            return 0;
        /// 找到了 shipping_area_id  找config
        $shipping_config = M('ShippingArea')->where("shipping_area_id", $shipping_area_id)->getField('config');
        $shipping_config = unserialize($shipping_config);
        $shipping_config['money'] = $shipping_config['money'] ? $shipping_config['money'] : 0;

        // 1000 克以内的 只算个首重费
        if ($weight < $shipping_config['first_weight']) {
            return $shipping_config['money'];
        }
        // 超过 1000 克的计算方法
        $weight = $weight - $shipping_config['first_weight']; // 续重
        $weight = ceil($weight / $shipping_config['second_weight']); // 续重不够取整
        $freight = $shipping_config['money'] + $weight * $shipping_config['add_money']; // 首重 + 续重 * 续重费

        return $freight;
    }
  
    /**
     * 获取用户可以使用的优惠券
     * @param type $user_id  用户id 
     * @param type $coupon_id 优惠券id
     * $mode 0  返回数组形式  1 直接返回result
     */
    public function getCouponMoney($user_id, $coupon_id,$mode)
    {
        if($coupon_id == 0)
        {
            if($mode == 1) return 0;    
            return array('status'=>1,'msg'=>'','result'=>0);            
        }        
        $couponlist = M('CouponList')->where("uid", $user_id)->where('id', $coupon_id)->find(); // 获取用户的优惠券
        if(empty($couponlist)) {
            if($mode == 1) return 0;    
            return array('status'=>1,'msg'=>'','result'=>0);
        }            
        
        $coupon = M('Coupon')->where("id", $couponlist['cid'])->find(); // 获取 优惠券类型表
        $coupon['money'] = $coupon['money'] ? $coupon['money'] : 0;
       
        if($mode == 1) return $coupon['money'];
        return array('status'=>1,'msg'=>'','result'=>$coupon['money']);        
    }
    
    /**
     * 根据优惠券代码获取优惠券金额
     * @param type $couponCode 优惠券代码
     * @param type $order_momey Description 订单金额
     * return -1 优惠券不存在 -2 优惠券已过期 -3 订单金额没达到使用券条件
     */
    public function getCouponMoneyByCode($couponCode,$order_momey)
    {
        $couponlist = M('CouponList')->where("code", $couponCode)->find(); // 获取用户的优惠券
        if(empty($couponlist)) 
            return array('status'=>-9,'msg'=>'优惠券码不存在','result'=>'');
        if($couponlist['order_id'] > 0){
            return array('status'=>-20,'msg'=>'该优惠券已被使用','result'=>'');
        }
        $coupon = M('Coupon')->where("id", $couponlist['cid'])->find(); // 获取优惠券类型表
        if(time() > $coupon['use_end_time'])  
            return array('status'=>-10,'msg'=>'优惠券已经过期','result'=>'');
        if($order_momey < $coupon['condition'])
            return array('status'=>-11,'msg'=>'金额没达到优惠券使用条件','result'=>'');
        if($couponlist['order_id'] > 0)
            return array('status'=>-12,'msg'=>'优惠券已被使用','result'=>'');
        
        return array('status'=>1,'msg'=>'','result'=>$coupon['money']);
    }
    
    public function addOrderNew($address_id,$orderArr,$user_id,$myLevel){
        $this->db_prex=C('db_prex');
        
        $sql="select ad.consignee,ad.buyerIdNumber,ad.province,ad.city,ad.district,ad.twon,ad.address,ad.mobile,ad.zipcode,ad.email,ad.buyerName,c.id as cartId,c.goods_id,c.goods_name,c.goods_num,c.market_price,c.goods_price,c.spec_key,c.spec_key_name,c.member_goods_price,c.prom_type,c.prom_id,g.goods_sn,g.cost_price,g.give_integral,g.stype,g.wareId,g.tax,g.portId,g.give_integral,u.nickname from __PREFIX__cart as c left join __PREFIX__user_address as ad on ad.address_id={$address_id} left join __PREFIX__goods as g on g.goods_id=c.goods_id left join __PREFIX__users as u on u.user_id={$user_id} where c.user_id={$user_id} and c.selected=1";
        $model=M('Cart');
        $datas=$model->query($sql);
        foreach($datas as $key=>$value){
            $filed = $value['wareId'].$value['portId'];
            $data[$filed]['user_id']=$user_id;
            $data[$filed]['stype']=$value['stype'];
            $data[$filed]['wareId']=$value['wareId'];
            $data[$filed]['portId']=$value['portId'];
            $data[$filed]['consignee']=$value['consignee'];
            $data[$filed]['province']=$value['province'];
            $data[$filed]['city']=$value['city'];
            $data[$filed]['district']=$value['district'];
            $data[$filed]['twon']=$value['twon'];
            $data[$filed]['address']=$value['address'];
            $data[$filed]['mobile']=$value['mobile'];
            $data[$filed]['zipcode']=$value['zipcode'];
            $data[$filed]['email']=$value['email'];
            $data[$filed]['buyerRegNo']=$value['nickname'];
            $data[$filed]['buyerName']=$value['buyerName'];
            $data[$filed]['buyerIdNumber']=$value['buyerIdNumber'];
            if($orderArr[$value['wareId']]){
                $data[$filed]['integral']=$orderArr[$value['wareId']];
                $data[$filed]['integral_money']=$orderArr[$value['wareId']]*0.02;
            }else{
                $data[$filed]['integral']=0;
                $data[$filed]['integral_money']=0;
            }
            $data[$filed]['goods_price']+=$value['member_goods_price']*$value['goods_num'];

            //$data[$filed]['taxTotal'] += round(($value['member_goods_price']*$value['goods_num']*$value['tax']),2);
            $data[$filed]['taxTotal'] += substr(sprintf('%.3f', $value['member_goods_price']*$value['goods_num']*$value['tax']), 0, -1);
            //$data[$filed]['member_goods_price'] += round($value['member_goods_price']*$value['goods_num'],2);
            $data[$filed]['member_goods_price'] += substr(sprintf('%.3f', $value['member_goods_price']*$value['goods_num']), 0, -1);
            if($value['give_integral']){
                $data[$filed]['pNum']+=$value['give_integral'];
            }
            $data[$filed]['goods'][$value['goods_id']]['cartId']=$value['cartId'];
            $data[$filed]['goods'][$value['goods_id']]['goods_id']=$value['goods_id'];
            $data[$filed]['goods'][$value['goods_id']]['goods_name']=$value['goods_name'];
            $data[$filed]['goods'][$value['goods_id']]['goods_sn']=$value['goods_sn'];
            $data[$filed]['goods'][$value['goods_id']]['goods_num']=$value['goods_num'];
            $data[$filed]['goods'][$value['goods_id']]['market_price']=$value['market_price'];
            $data[$filed]['goods'][$value['goods_id']]['goods_price']=$value['goods_price'];
            $data[$filed]['goods'][$value['goods_id']]['member_goods_price']=$value['member_goods_price'];
            $data[$filed]['goods'][$value['goods_id']]['cost_price']=$value['cost_price'];
            $data[$filed]['goods'][$value['goods_id']]['prom_type']=$value['prom_type'];
        }
        $result=array_values($data);
        
        $pNum=0;
        $orders='';
        foreach($result as $key=>$value){
            
            //1.新增订单
            $order = array(
                'order_sn'         => C('prec_order').date('mdHis').rand(100,999), // 订单编号
                'user_id'          =>$user_id, // 用户id
                'stype'            =>$value['stype'],
                'wareId'           =>$value['wareId'],
                'portId'           =>$value['portId'],
                'consignee'        =>$value['consignee'], // 收货人
                'province'         =>$value['province'],//'省份id',
                'city'             =>$value['city'],//'城市id',
                'district'         =>$value['district'],//'县',
                'twon'             =>$value['twon'],// '街道',
                'address'          =>$value['address'],//'详细地址',
                'mobile'           =>$value['mobile'],//'手机',
                'zipcode'          =>$value['zipcode'],//'邮编',            
                'email'            =>$value['email'],//'邮箱',             
                'buyerRegNo'       =>$value['buyerRegNo'],//'邮箱',            
                'buyerName'        =>$value['buyerName'],//'邮箱',            
                'buyerIdNumber'    =>$value['buyerIdNumber'],//'邮箱',             
                'goods_price'      =>$value['goods_price'],//'商品价格',                        
                'integral'         =>$value['integral'], //'使用积分',
                'integral_money'   =>$value['integral_money'],//'使用积分抵多少钱',
                'order_amount'     =>$value['member_goods_price']-$value['integral_money']+$value['taxTotal'],// '应付款金额', 
                'taxTotal'         =>$value['taxTotal'],//订单的税费
                'coupon_price'     =>$value['goods_price']-$value['member_goods_price'],//会员优惠价
                'total_amount'     =>$value['goods_price'],//订单总额
                'add_time'         =>time(), // 下单时间 
                'isLoad'         =>1, // 下单时间 
                //'isLoad'           =>1//商城下单
            );

            //订单人保税额度查询检查----开始
            if($order['stype']==1){//保税订单判断海关额度限制

                if($order['order_amount']>2000){
                    return array("statusCode"=>400, "retMessage"=>'保税商品海关限额每单不超过2000,请确认！');
                    continue;
                }

                $buyerName = myTrim($value['buyerName']);
                $buyerIdNumber = myTrim(strtoupper($value['buyerIdNumber']));
                $checkCardNum = checkBuyerIdNumber($buyerName,$buyerIdNumber);
                if($checkCardNum){//如果存在黑名单中
                     
                }else{
                    $checkBNP = checkBuyerIdNumerPrice($buyerName,$buyerIdNumber);//查询额度
                    if($checkBNP){
                        if(($checkBNP+$value['order_amount'])>20000){//当前订单支付价格+已经使用额度超过20000,提示
                            return array("statusCode"=>400, "retMessage"=>'该订单购买人超过海关购买限额2万，请确认！');
                            continue;
                        }else{//如果没有超过，那么额度往上加
                            $apiRet = setBuyerIdNumberPrice($buyerName,$buyerIdNumber,$order['order_amount'],1);
                            if(!($apiRet) && ($apiRet==2)){
                                return array("statusCode"=>400, "retMessage"=>'海关额度限制修改失败，联系管理员！');
                                continue;
                            }
                        }
                    }else{//没有额度记录，添加 
                        $apiRet = setBuyerIdNumberPrice($buyerName,$buyerIdNumber,$order['order_amount'],1);
                        if(!($apiRet) && ($apiRet==2)){
                            return array("statusCode"=>400, "retMessage"=>'海关额度限制添加失败，联系管理员！');
                            continue;
                        }
                    }
                }
            }

            //防止商品信息修改机制---开始
            $check = false;
            $checkChangeSql ='';
            foreach ($value['goods'] as $k => $val) {
                if($val['prom_type']==0){
                    $checkChangeSql = 'select goods_id from __PREFIX__goods where goods_id="'.$val['goods_id'].'" and goods_name="'.$val['goods_name'].'" and market_price="'.$val['market_price'].'" and shop_price="'.$val['goods_price'].'" and (firstMemberPrice="'.$val['member_goods_price'].'" or secondMemberPrice="'.$val['member_goods_price'].'" or thirdMemberPrice="'.$val['member_goods_price'].'" or shop_price="'.$val['member_goods_price'].'")';
                    $checkChange = DB::query($checkChangeSql);
                    if(!$checkChange){
                        $checkData['selected'] = 3;
                        $check = true;
                        M('Cart')->where('id',$val['cartId'])->save($checkData);
                    }
                }    
            }
            if($check){
                return array("statusCode"=>400, "retMessage"=>'商品信息已经修改！请修改！');
            }
            //防止商品信息修改机制---结束

            //订单人保税额度查询检查----结束
            
            $order_id = M("Order")->insertGetId($order);
                    
            if(!$order_id) return array("statusCode"=>400, "retMessage"=>'添加订单失败，请刷新重试');
            // 2.记录订单操作日志
            $action_info = array(
                'order_id'        =>$order_id,
                'action_user'     =>$user_id,            
                'action_note'     => '您提交了订单，请等待系统确认',
                'status_desc'     =>'提交订单', //''
                'log_time'        =>time(),
            );
            M('order_action')->insertGetId($action_info);
            //3.新增订单商品
            $num=1;
            foreach($value['goods'] as $ke=>$val){
                $goods = M('goods')->where("goods_id", $val['goods_id'])->cache(true,TPSHOP_CACHE_TIME)->find();
                $goodsArr=array();
                $goodsArr['order_id']           = $order_id; // 订单id
                $goodsArr['gNum']               =$num;
                $goodsArr['goods_id']           = $val['goods_id']; // 商品id
                $goodsArr['goods_name']         = $val['goods_name']; // 商品名称
                $goodsArr['goods_sn']           = $val['goods_sn']; // 商品货号
                $goodsArr['goods_num']          = $val['goods_num']; // 购买数量
                $goodsArr['market_price']       = $val['market_price']; // 市场价
                $goodsArr['goods_price']        = $val['goods_price']; // 商品价               为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
                $goodsArr['prom_type']          = $val['prom_type']; // 普通商品,团购,限购
                $goodsArr['member_goods_price'] = $val['member_goods_price']; // 会员折扣价
                $goodsArr['cost_price']         = $goods['cost_price']; // 成本价
                $goodsArr['goodsRate']          = $goods['goodsRate']; // 商品税率        
                $order_goods_id  = M("order_goods")->insertGetId($goodsArr);
                $num++;
            }
            // 4 扣除积分
            $sql='';            
            if($value['integral']>$value['pNum']){
                $pNum=$value['integral']-$value['pNum'];
                $sql="update __PREFIX__users set pay_points=pay_points-{$pNum} where user_id={$user_id}";
            }elseif($value['pNum']>$value['integral']){
                $pNum=$value['pNum']-$value['integral'];
                $sql="update __PREFIX__users set pay_points=pay_points+{$pNum} where user_id={$user_id}";
            }
            if($sql){
        	$model->execute($sql);
            }
            // 5 删除已提交订单商品
            M('Cart')->where(['user_id' => $user_id,'selected' => 1])->delete();
            
            //6.记录log 日志
            $log=array();
            $log['user_id'] = $user_id;
            $data4['user_money'] = -$order['order_amount'];
            $data4['pay_points'] = -$value['integral'];
            $data4['change_time'] = time();
            $data4['desc'] = '下单消费';
            $data4['order_sn'] = $order['order_sn'];
            $data4['order_id'] = $order_id;    
            // 如果使用了积分或者余额才记录
            if($value['integral']) M("AccountLog")->add($data4);
            $orders.=','.$order_id;
        }
        return array("statusCode"=>200,"retMessage"=>'done',"data"=>trim($orders,','));
    }
    /**
    *验证购买额度
    *$address 购买者的姓名，身份证
    *$pay_price 应付的金额
    */
    public function checkBuyLimit($address,$pay_price){
        $resBuyerIdNum=checkBuyerIdNumber($address['consignee'],$address['buyerIdNumber']);
        //不在身份证黑名单中
        if(!$resBuyerIdNum){
            //查询身份证额度
            $resBuyerIdNumPrice=checkBuyerIdNumerPrice($address['consignee'],$address['buyerIdNumber']);

            if($resBuyerIdNumPrice){
                //额度大于2万不给下单
                if(($resBuyerIdNumPrice+$pay_price)>20000){
                    return array('status'=>-1,'msg'=>'抱歉,此身份证的购买额度大于2万','result'=>''); 
                }else{
                    //设置额度
                    $resSetBuyerIdNumberPrice=setBuyerIdNumberPrice($address['consignee'],$address['buyerIdNumber'],$pay_price,1);
                    if($resSetBuyerIdNumberPrice==1){
                        return true;
                    }else{
                        return array('status'=>-1,'msg'=>'抱歉,设置身份证额度出现错误,请查证身份证信息是否正确','result'=>'');;
                    }
                }
            }else{
            //没有查到身份证额度就重新设置额度
                $resSetBuyerIdNumberPrice=setBuyerIdNumberPrice($address['consignee'],$address['buyerIdNumber'],$pay_price,1);
                //return $resSetBuyerIdNumberPrice;exit;
                if($resSetBuyerIdNumberPrice==1){
                    return true;
                }else{
                    return array('status'=>-1,'msg'=>'抱歉,设置身份证额度出现错误,请查证身份证信息是否正确','result'=>'');;
                }
            }
        //在身份证黑名单
        }else{
            return array('status'=>-1,'msg'=>'抱歉,此身份证在购买额度的黑名单中','result'=>'');
        }
    }
    /**
     *  添加一个订单
     * @param type $user_id  用户id     
     * @param type $address_id 地址id
     * @param type $shipping_code 物流编号
     * @param type $invoice_title 发票
     * @param type $coupon_id 优惠券id
     * @param type $car_price 各种价格
     * @param type $user_note 用户备注
     * @return type $order_id 返回新增的订单id
     */
    public function addOrder($user_id,$address_id,$shipping_code,$invoice_title,$coupon_id = 0,$car_price,$user_note='')
    {
        set_time_limit(0);
        // 0插入订单 order
        $address = M('UserAddress')->where("address_id", $address_id)->find();
        //验证身份证国检的额度-start
        $this->checkBuyLimit($address,$car_price['payables']);
        //return array('status'=>-1,'msg'=>'测试通过11','result'=>$res);
        //验证身份证国检的额度-end
        $shipping = M('Plugin')->where("code", $shipping_code)->cache(true,TPSHOP_CACHE_TIME)->find();
        $data = array(
                'order_sn'         =>C('prec_order').date('mdHis').rand(100,999), // 订单编号
                'user_id'          =>$user_id, // 用户id
                'buyerRegNo'       =>$address['mobile'],
                'buyerName'        =>$address['buyerName'],
                'buyerIdNumber'    =>$address['buyerIdNumber'],
                'consignee'        =>$address['consignee'], // 收货人
                'province'         =>$address['province'],//'省份id',
                'city'             =>$address['city'],//'城市id',
                'district'         =>$address['district'],//'县',
                'twon'             =>$address['twon'],// '街道',
                'address'          =>$address['address'],//'详细地址',
                'mobile'           =>$address['mobile'],//'手机',
                'zipcode'          =>$address['zipcode'],//'邮编',            
                'email'            =>$address['email'],//'邮箱',
                'shipping_name'    =>$shipping['name'], //'物流名称',                
                'invoice_title'    =>$invoice_title, //'发票抬头',                
                'goods_price'      =>$car_price['goodsFee'],//'商品价格',
                'shipping_price'   =>$car_price['postFee'],//'物流价格',                
                'user_money'       =>$car_price['balance'],//'使用余额',
                'coupon_price'     =>$car_price['couponFee'],//'使用优惠券',                        
                'integral'         =>($car_price['pointsFee'] * tpCache('shopping.point_rate')), //'使用积分',
                'integral_money'   =>$car_price['pointsFee'],//'使用积分抵多少钱',
                'taxTotal'         =>$car_price['taxTotal'],//税
                'total_amount'     =>($car_price['goodsFee'] + $car_price['postFee']),// 订单总额
                'order_amount'     =>$car_price['payables'],//'应付款金额',                
                'add_time'         =>time(), // 下单时间                
                'order_prom_id'    =>$car_price['order_prom_id'],//'订单优惠活动id',
                'order_prom_amount'=>$car_price['order_prom_amount'],//'订单优惠活动优惠了多少钱',
                'user_note'        =>$user_note, // 用户下单备注
                'stype'            =>$car_price['stype'],//1南沙保税仓，2普通仓
                'wareId'           =>$car_price['wareId'],//仓库id
                'portId'           =>$car_price['portId'],//关区id
                'ismobile'         =>1,
        );
        //return array('status'=>-1,'msg'=>'提交订单成功11','result'=>$data); //测试     
        $data['order_id'] = $order_id = M("Order")->insertGetId($data);
        $order = $data;//M('Order')->where("order_id", $order_id)->find();
        if(!$order_id)
            return array('status'=>-8,'msg'=>'添加订单失败','result'=>NULL);
              
        // 记录订单操作日志
        $action_info = array(
            'order_id'        =>$order_id,
            'action_user'     =>$user_id,            
            'action_note'     => '您提交了订单，请等待系统确认',
            'status_desc'     =>'提交订单', //''
            'log_time'        =>time(),
        );
        M('order_action')->insertGetId($action_info);                  
        // 1插入order_goods 表
        $sql="select c.*,gs.portId from __PREFIX__cart c left join __PREFIX__goods gs on gs.goods_id=c.goods_id where c.user_id={$user_id} and c.selected=1 and c.wareId=".$car_price['wareId']." and gs.wareId=".$car_price['wareId']." and gs.portId=".$car_price['portId'];
        $cartList = M('Cart')->query($sql);
        //$cartList = M('Cart')->where(['wareId'=>$car_price['wareId'],'portId'=>$car_price['portId'],'user_id'=>$user_id,'selected'=>1])->select();
        foreach($cartList as $key => $val)
        {
           $goods = M('goods')->where("goods_id", $val['goods_id'])->cache(true,TPSHOP_CACHE_TIME)->find();
           $data2['order_id']           = $order_id; // 订单id
           $data2['goods_id']           = $val['goods_id']; // 商品id
           $data2['goods_name']         = $val['goods_name']; // 商品名称
           $data2['goods_sn']           = $val['goods_sn']; // 商品货号
           $data2['gNum']               = $key+1; //商品项号
           $data2['goods_num']          = $val['goods_num']; // 购买数量
           $data2['market_price']       = $val['market_price']; // 市场价
           $data2['goods_price']        = $val['goods_price']; // 商品价
           $data2['spec_key']           = $val['spec_key']; // 商品规格
           $data2['spec_key_name']      = $val['spec_key_name']; // 商品规格名称
           $data2['member_goods_price'] = $val['member_goods_price']; // 会员折扣价
           $data2['cost_price']         = $goods['cost_price']; // 成本价
           $data2['give_integral']      = $goods['give_integral']; // 购买商品赠送积分         
           $data2['prom_type']          = $val['prom_type']; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
           $data2['prom_id']            = $val['prom_id']; // 活动id
           $data2['goodsRate']          = $goods['goodsRate']; // 商品税率
           $order_goods_id              = M("order_goods")->insertGetId($data2);
           // 扣除商品库存  扣除库存移到 付完款后扣除
           //M('Goods')->where("goods_id = ".$val['goods_id'])->setDec('store_count',$val['goods_num']); // 商品减少库存
        } 
        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付 
        if($data['order_amount'] == 0)
        {                        
            update_pay_status($order['order_sn']);
        }           
        
        // 2修改优惠券状态  
        if($coupon_id > 0){
        	$data3['uid'] = $user_id;
        	$data3['order_id'] = $order_id;
        	$data3['use_time'] = time();
        	M('CouponList')->where("id", $coupon_id)->save($data3);
                $cid = M('CouponList')->where("id", $coupon_id)->getField('cid');
                M('Coupon')->where("id", $cid)->setInc('use_num'); // 优惠券的使用数量加一
        }
        // 3 扣除积分 扣除余额
        if($car_price['pointsFee']>0)
        	M('Users')->where("user_id", $user_id)->setDec('pay_points',($car_price['pointsFee'] * tpCache('shopping.point_rate'))); // 消费积分
        if($car_price['balance']>0)
        	M('Users')->where("user_id", $user_id)->setDec('user_money',$car_price['balance']); // 抵扣余额
        // 4 删除已提交订单商品
        M('Cart')->where(['wareId'=>$car_price['wareId'],'user_id' => $user_id,'selected' => 1])->delete();
      
        // 5 记录log 日志
        $data4['user_id'] = $user_id;
        $data4['user_money'] = -$car_price['balance'];
        $data4['pay_points'] = -($car_price['pointsFee'] * tpCache('shopping.point_rate'));
        $data4['change_time'] = time();
        $data4['desc'] = '下单消费';
        $data4['order_sn'] = $order['order_sn'];
        $data4['order_id'] = $order_id;    
        // 如果使用了积分或者余额才记录
        ($data4['user_money'] || $data4['pay_points']) && M("AccountLog")->add($data4);
        
        //分销开关全局
        $distribut_switch = tpCache('distribut.switch');
        if($distribut_switch  == 1 && file_exists(APP_PATH.'common/logic/DistributLogic.php'))
        {
            $distributLogic = new \app\common\logic\DistributLogic();
            $distributLogic->rebate_log($order); // 生成分成记录
        }
        // 如果有微信公众号 则推送一条消息到微信
        //$user = M('users')->where("user_id", $user_id)->find();
        //$bind = M('users')->where("bindId", $user_id)->find();//有绑定微信号的pc账号
        // if($user['oauth']== 'weixin' || !empty($bind))
        // {
        //     $wx_user = M('wx_user')->find();
        //     $jssdk = new \app\mobile\logic\Jssdk($wx_user['appid'],$wx_user['appsecret']);
        //     $wx_content = "你刚刚下了一笔订单:{$order['order_sn']} 尽快支付,过期失效!";
        //     $jssdk->push_msg($user['openid'],$wx_content);
        // }
    	//用户下单, 发送短信给商家
    	$res = checkEnableSendSms("3");
    	$sender = tpCache("shop_info.mobile");
    	
    	if($res && $res['status'] ==1 && !empty($sender)){
    		 
    	    $params = array('consignee'=>$order['consignee'] , 'mobile' => $order['mobile']);
    	    $resp = sendSms("3", $sender, $params);
    	} 	
        return array('status'=>1,'msg'=>'提交订单成功','result'=>$order_id); // 返回新增的订单id        
    }
    /**
     * 查看购物车的商品数量
     * @param type $user_id
     * $mode 0  返回数组形式  1 直接返回result
     */
    public function cart_count($user_id,$mode = 0){
        $count = M('Cart')->where(['user_id' => $user_id , 'selected' => 1])->count();
        if($mode == 1) return  $count;
        
        return array('status'=>1,'msg'=>'','result'=>$count);         
    }
        
   /**
    * 获取商品团购价
    * 如果商品没有团购活动 则返回 0
    * @param type $attr_id
    * $mode 0  返回数组形式  1 直接返回result
    */
   public function get_group_buy_price($goods_id,$mode=0)
   {
       $group_buy = M('GroupBuy')->where(['goods_id' => $goods_id,'start_time'=>['<=',time()],'end_time'=>['>=',time()]])->find(); // 找出这个商品
       if(empty($group_buy))       
            return 0;
       
        if($mode == 1) return $group_buy['groupbuy_price'];
        return array('status'=>1,'msg'=>'','result'=>$group_buy['groupbuy_price']);       
   }  
   
   /**
    * 用户登录后 需要对购物车 一些操作
    * @param type $session_id
    * @param type $user_id
    */
   public function login_cart_handle($session_id,$user_id)
   {
	   if(empty($session_id) || empty($user_id))
	     return false;
        // 登录后将购物车的商品的 user_id 改为当前登录的id            
        M('cart')->where("session_id", $session_id)->save(array('user_id'=>$user_id));
                
        // 查找购物车两件完全相同的商品
        $cart_id_arr = DB::query("select id from `__PREFIX__cart` where user_id = $user_id group by  goods_id,spec_key having count(goods_id) > 1");
        if(!empty($cart_id_arr))
        {
            $cart_id_arr = get_arr_column($cart_id_arr, 'id');
            $cart_id_str = implode(',', $cart_id_arr);
            M('cart')->delete($cart_id_str); // 删除购物车完全相同的商品
        }
   }
    /**
     * 添加预售商品订单
     * @param $user_id
     * @param $address_id
     * @param $shipping_code
     * @param $invoice_title
     * @param $act_id
     * @param $pre_sell_price
     * @return array
     */
    public function addPreSellOrder($user_id,$address_id,$shipping_code,$invoice_title,$act_id,$pre_sell_price)
    {
        // 仿制灌水 1天只能下 50 单
        $order_count = M('Order')->where("user_id= $user_id and order_sn like '".date('Ymd')."%'")->count(); // 查找购物车商品总数量
        if($order_count >= 50){
            return array('status'=>-9,'msg'=>'一天只能下50个订单','result'=>'');
        }
        $address = M('UserAddress')->where(array('address_id' => $address_id))->find();
        $shipping = M('Plugin')->where(array('code' => $shipping_code))->find();
        $data = array(
            'order_sn'         => date('YmdHis').rand(1000,9999), // 订单编号
            'user_id'          =>$user_id, // 用户id
            'consignee'        =>$address['consignee'], // 收货人
            'province'         =>$address['province'],//'省份id',
            'city'             =>$address['city'],//'城市id',
            'district'         =>$address['district'],//'县',
            'twon'             =>$address['twon'],// '街道',
            'address'          =>$address['address'],//'详细地址',
            'mobile'           =>$address['mobile'],//'手机',
            'zipcode'          =>$address['zipcode'],//'邮编',
            'email'            =>$address['email'],//'邮箱',
            'shipping_code'    =>$shipping_code,//'物流编号',
            'shipping_name'    =>$shipping['name'], //'物流名称',
            'invoice_title'    =>$invoice_title, //'发票抬头',
            'goods_price'      =>$pre_sell_price['cut_price'] * $pre_sell_price['goods_num'],//'商品价格',
            'total_amount'     =>$pre_sell_price['cut_price'] * $pre_sell_price['goods_num'],// 订单总额
            'add_time'         =>time(), // 下单时间
            'order_prom_type'  => 4,
            'order_prom_id'    => $act_id
        );
        if($pre_sell_price['deposit_price'] == 0){
            //无定金
            $data['order_amount'] = $pre_sell_price['cut_price'] * $pre_sell_price['goods_num'];//'应付款金额',
        }else{
            //有定金
            $data['order_amount'] = $pre_sell_price['deposit_price'] * $pre_sell_price['goods_num'];//'应付款金额',
        }
        $order_id = Db::name('order')->insertGetId($data);
//        M('goods_activity')->where(array('act_id'=>$act_id))->setInc('act_count',$pre_sell_price['goods_num']);
        if($order_id === false){
            return array('status'=>-8,'msg'=>'添加订单失败','result'=>NULL);
        }
        logOrder($order_id,'您提交了订单，请等待系统确认','提交订单',$user_id);
        $order = M('Order')->where("order_id = $order_id")->find();
        $goods_activity = M('goods_activity')->where(array('act_id'=>$act_id))->find();
        $goods = M('goods')->where(array('goods_id'=>$goods_activity['goods_id']))->find();
        $data2['order_id']           = $order_id; // 订单id
        $data2['goods_id']           = $goods['goods_id']; // 商品id
        $data2['goods_name']         = $goods['goods_name']; // 商品名称
        $data2['goods_sn']           = $goods['goods_sn']; // 商品货号
        $data2['goods_num']          = $pre_sell_price['goods_num']; // 购买数量
        $data2['market_price']       = $goods['market_price']; // 市场价
        $data2['goods_price']        = $goods['shop_price']; // 商品团价
        $data2['cost_price']         = $goods['cost_price']; // 成本价
        $data2['member_goods_price'] = $pre_sell_price['cut_price']; //预售价钱
        $data2['give_integral']      = $goods_activity['integral']; // 购买商品赠送积分
        $data2['prom_type']          = 4; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠 ,4 预售商品
        $data2['prom_id']    = $goods_activity['act_id'];
        Db::name('order_goods')->insert($data2);
        // 如果有微信公众号 则推送一条消息到微信
        $user = M('users')->where("user_id = $user_id")->find();
        if($user['oauth']== 'weixin')
        {
            $wx_user = M('wx_user')->find();
            $jssdk = new \app\mobile\logic\Jssdk($wx_user['appid'],$wx_user['appsecret']);
            $wx_content = "你刚刚下了一笔预售订单:{$order['order_sn']} 尽快支付,过期失效!";
            $jssdk->push_msg($user['openid'],$wx_content);
        }
        return array('status'=>1,'msg'=>'提交订单成功','result'=>$order_id); // 返回新增的订单id
    }
    /**
     * 设置用户ID
     * @param $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }
    /**
     * @param int $selected|是否被用户勾选中的 0 为全部 1为选中  一般没有查询不选中的商品情况
     * 获取用户的购物车列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCartList($selected = 0){
        $cart = new Cart();
        // 如果用户已经登录则按照用户id查询
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
        }
        if($selected != 0){
            $cartWhere['selected'] = 1;
        }
        $cartList = $cart->with('promGoods,goods')->where($cartWhere)->select();  // 获取购物车商品
        $cartCheckAfterList = $this->checkCartList($cartList);
//        $cartCheckAfterList = $cartList;
        $cartGoodsTotalNum = array_sum(array_map(function($val){return $val['goods_num'];}, $cartCheckAfterList));//购物车购买的商品总数
        setcookie('cn', $cartGoodsTotalNum, null, '/');
        return $cartCheckAfterList;
    }
    /**
     * 过滤掉无效的购物车商品
     * @param $cartList
     */
    public function checkCartList($cartList){
        $goodsPromFactory = new \app\common\logic\GoodsPromFactory();
        foreach($cartList as $cartKey=>$cart){
            //商品不存在或者已经下架
            if(empty($cart['goods']) || $cart['goods']['is_on_sale'] != 1){
                $cart->delete();
                unset($cartList[$cartKey]);
                continue;
            }
            //活动商品的活动是否失效
            if ($goodsPromFactory->checkPromType($cart['prom_type'])) {
                if (!empty($cart['spec_key'])) {
                    $specGoodsPrice = SpecGoodsPrice::get(['goods_id' => $cart['goods_id'], 'key' => $cart['spec_key']], '', true);
                    if($specGoodsPrice['prom_id'] != $cart['prom_id']){
                        $cart->delete();
                        unset($cartList[$cartKey]);
                        continue;
                    }
                } else {
                    if($cart['goods']['prom_id'] != $cart['prom_id']){
                        $cart->delete();
                        unset($cartList[$cartKey]);
                        continue;
                    }
                    $specGoodsPrice = null;
                }
                $goodsPromLogic = $goodsPromFactory->makeModule($cart['goods'], $specGoodsPrice);
                if ($goodsPromLogic && !$goodsPromLogic->isAble()) {
                    $cart->delete();
                    unset($cartList[$cartKey]);
                    continue;
                }

            }
        }
        return $cartList;
    }
    /**
     * 获取用户购物车商品总数
     * @return float|int
     */

    public function getUserCartGoodsTypeNum()
    {
        if ($this->user_id) {
            $goods_num = Db::name('cart')->where(['user_id' => $this->user_id])->count();
        } else {
            $goods_num = Db::name('cart')->where(['session_id' => $this->session_id])->count();
        }
        return empty($goods_num) ? 0 : $goods_num;
    }
}