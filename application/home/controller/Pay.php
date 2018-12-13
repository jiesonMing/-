<?php
/**
 * $Author: 于明明 2017-03-23
 */ 
namespace app\home\controller; 
use think\Controller;
use think\Request;
use think\Exception;
use think\Db;

class Pay extends Base {
    
    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code
    public $user_id = 0;
    public $mylevel=0;
    public $user = array();
    public $db_prex='';
    //析构流函数
    public function  _initialize() {   
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
        //用户中心面包屑导航
        $navigate_user = navigate_user();
        $this->assign('navigate_user',$navigate_user);  
    }
   
    //易极付，微信支付
    public function thirdsCanPay(){
        $orderId = I('orderId/s')?I('orderId/s'):''; // 订单id
        //拉起支付的时候，把payType更新到Order表
        $code = I('code')?I('code'):'';
        if($orderId){
            $orderId=trim($orderId,',');
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    $sql="select o.order_id,o.order_sn,o.pay_status,o.order_amount,o.add_time,og.goods_num,og.goods_name,g.goods_id,g.prom_type,ps.payurl,ps.payid,ps.intelpayid,ps.paytoken from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}goods as g on g.goods_id=og.goods_id and g.goods_sn=og.goods_sn and g.goods_name=og.goods_name and g.market_price=og.market_price and g.shop_price=og.goods_price and ( g.shop_price=og.member_goods_price or g.firstMemberPrice=og.member_goods_price or g.secondMemberPrice=og.member_goods_price or g.thirdMemberPrice=og.member_goods_price ) left join {$this->db_prex}users as u on u.user_id=o.user_id left join {$this->db_prex}pay_shop as ps on ps.code='{$code}' where o.order_id={$orderId} and ( o.pay_status=0 or o.pay_status=2 or o.pay_status=4 ) and o.order_status=0";
                    if($datas=$model->query($sql)){
                        $goodsNames='';
                        $count=0;
                        foreach($datas as $value){
                            if($value['prom_type']==0) {
                                if(!$value['goods_id']) 
                                {
                                    $this->assign('info','商品信息已经更新，返回订单中心检查！');
                                    return $this->fetch('pay');
                                }
                            } 
                            if((time()-$value['add_time'])>(60*60*24)){
                                $this->assign('info','订单超过24小时未支付，请重新下单！');
                                return $this->fetch('pay');
                            }
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $count++;
                        }
                        if($count>1){
                            $data['goodsName']=$goodsName.'等'.$count.'种商品';
                        }else{
                            $data['goodsName']=$goodsName;
                        }
                        $ckey=$datas[0]['paytoken'];
                        $data['orderNo']=$orderNo;
                        $data['service']='aggregatePay';
                        $data['version']='1.0';
                        $data['partnerId']=$datas[0]['payid'];
                        $merchant_private_key_yiji=C('merchant_private_key_yiji');
                        if($merchant_private_key_yiji==''){
                            $data['signType'] = 'MD5';
                        }else {
                            $data['signType'] = 'RSA';
                        }
                        $data['merchOrderNo']=$datas[0]['order_sn'];
                        $data['returnUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/payReturn');
                        $data['notifyUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/notifyUrl');
                        //$data['buyerUserId']=$datas[0]['intelpayid'];
                        $data['userTerminalType']='PC';            
                        $data['sellerUserId']=$datas[0]['payid'];
                        $data['goodsName']=$datas[0]['intelpayid'];
                        $data['tradeAmount']=$datas[0]['order_amount'];  
                        $data['paymentType']='THIRDSCANPAY';
                        ksort($data);
                        $signSrc='';
                        foreach($data as $key=>$value){
                            if(empty($value)||$value===""){
                                unset($data[$key]);
                            }else{
                                $signSrc.= $key.'='.$value.'&';
                            }
                        }
                        if($merchant_private_key_yiji==''){
                            $signSrc = trim($signSrc, '&') . $ckey;
                            $data['sign'] = md5($signSrc);
                        }else {
                            $signSrc=trim($signSrc,'&');
                            $sign_info='';
                            $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key_yiji),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";
                            $pi_key= openssl_get_privatekey($merchant_private_key);
                            openssl_sign($signSrc,$sign_info,$pi_key);
                            $data['sign'] = base64_encode($sign_info);
                        }
                        $ip=getClientIP();
                        $time=time();
                        $sql="insert {$this->db_prex}paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$datas[0]['order_amount']}',1,'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update {$this->db_prex}order set paymentType='{$code}',payOrderNo='{$orderNo}',pay_status=2,pay_code='thirdsCanPay',pay_name='微信支付',pay_time='{$time}' where order_id in ({$orderId})";
                            $oret=$model->execute($sql);
                            if($oret){
                                $this->assign('data',$data);
                            }else{
                                $this->assign('info','网络繁忙，请刷新页面重试');
                                //errorMsg(400, '网络繁忙，请刷新页面重试');
                            }
                        }else{
                            $this->assign('info','网络繁忙，请刷新页面重试');
                            //errorMsg(400, '网络繁忙，请刷新页面重试');
                        }
                    }else{
                        throw new Exception('订单数据不存在，或者订单均已支付，请刷新页面重试');
                        //errorMsg(400, '订单数据不存在，或者订单均已支付，请刷新页面重试');
                    }
                }else{
                    throw new Exception('网络繁忙，请刷新页面重试');
                    //errorMsg(400, '网络繁忙，请刷新页面重试');
                }
            }catch(Exception $e){
                $this->assign('info',$e->getMessage());
                //errorMsg(400, $e->getMessage());
            }

        }else{
            $this->assign('info','订单号有误，请刷新页面重试');
            //errorMsg(400, '订单号有误，请刷新页面重试');
        }
        return $this->fetch('pay');  // 分跳转 和不 跳转    
    }
    
    //易极付银联支付
    public function onlineBank(){
        $orderId = I('orderId/s')?I('orderId/s'):''; // 订单id
        //拉起支付的时候，把payType更新到Order表
        $code = I('code')?I('code'):'';
        if($orderId){
            $orderId=trim($orderId,',');
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    $sql="select o.order_id,o.order_sn,o.pay_status,o.order_amount,o.add_time,og.goods_num,og.goods_name,g.goods_id,g.prom_type,ps.payurl,ps.payid,ps.intelpayid,ps.paytoken from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}goods as g on g.goods_id=og.goods_id and g.goods_sn=og.goods_sn and g.goods_name=og.goods_name and g.market_price=og.market_price and g.shop_price=og.goods_price and ( g.shop_price=og.member_goods_price or g.firstMemberPrice=og.member_goods_price or g.secondMemberPrice=og.member_goods_price or g.thirdMemberPrice=og.member_goods_price ) left join {$this->db_prex}users as u on u.user_id=o.user_id left join {$this->db_prex}pay_shop as ps on ps.code='{$code}' where o.order_id in ({$orderId}) and ( o.pay_status=0 or o.pay_status=2 or o.pay_status=4 ) and o.order_status=0";
                    if($datas=$model->query($sql)){
                        $goodsNames='';
                        $count=0;
                        foreach($datas as $value){
                            if($value['prom_type']==0) {
                                if(!$value['goods_id']) 
                                {
                                    $this->assign('info','商品信息已经更新，返回订单中心检查！');
                                    return $this->fetch('pay');
                                }
                            }
                            if((time()-$value['add_time'])>(60*60*24)){
                                $this->assign('info','订单超过24小时未支付，请重新下单！');
                                return $this->fetch('pay');
                            }
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $count++;
                        }
                        if($count>1){
                            $data['goodsName']=$goodsName.'等'.$count.'种商品';
                        }else{
                            $data['goodsName']=$goodsName;
                        }
                        $ckey=$datas[0]['paytoken'];
                        $data['orderNo']=$orderNo;
                        $data['service']='aggregatePay';
                        $data['version']='1.0';
                        $data['partnerId']=$datas[0]['payid'];
                        $merchant_private_key_yiji=C('merchant_private_key_yiji');
                        if($merchant_private_key_yiji==''){
                            $data['signType'] = 'MD5';
                        }else {
                            $data['signType'] = 'RSA';
                        }
                        $data['merchOrderNo']=$datas[0]['order_sn'];
                        $data['returnUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/payReturn');
                        $data['notifyUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/notifyUrl');
                        //$data['buyerUserId']=$datas[0]['intelpayid'];
                        $data['userTerminalType']='PC';
                        $data['sellerUserId']=$datas[0]['payid'];
                        $data['goodsName']=$datas[0]['intelpayid'];
                        $data['tradeAmount']=$datas[0]['order_amount'];  
                        $data['paymentType']='ONLINEBANK';
                        ksort($data);
                        $signSrc='';
                        foreach($data as $key=>$value){
                            if(empty($value)||$value===""){
                                unset($data[$key]);
                            }else{
                                $signSrc.= $key.'='.$value.'&';
                            }
                        }
                        if($merchant_private_key_yiji==''){
                            $signSrc = trim($signSrc, '&') . $ckey;
                            $data['sign'] = md5($signSrc);
                        }else {
                            $signSrc=trim($signSrc,'&');
                            $sign_info='';
                            $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key_yiji),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";
                            $pi_key= openssl_get_privatekey($merchant_private_key);
                            openssl_sign($signSrc,$sign_info,$pi_key);
                            $data['sign'] = base64_encode($sign_info);
                        }
                        $ip=getClientIP();
                        $time=time();
                        $sql="insert {$this->db_prex}paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$datas[0]['order_amount']}',1,'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update {$this->db_prex}order set paymentType='{$code}',payOrderNo='{$orderNo}',pay_status=2,pay_code='thirdsCanPay',pay_name='微信支付',pay_time='{$time}' where order_id in ({$orderId})";
                            $oret=$model->execute($sql);
                            if($oret){
                                $this->assign('data',$data);
                            }else{
                                $this->assign('info','网络繁忙，请刷新页面重试');
                                //errorMsg(400, '网络繁忙，请刷新页面重试');
                            }
                        }else{
                            $this->assign('info','网络繁忙，请刷新页面重试');
                            //errorMsg(400, '网络繁忙，请刷新页面重试');
                        }
                    }else{
                        throw new Exception('订单数据不存在,或者订单均已支付，请刷新页面重试');
                    }
                }else{
                    throw new Exception('网络繁忙，请刷新页面重试');
                }
            }catch(Exception $e){
                $this->assign('info',$e->getMessage());
                //errorMsg(400, $e->getMessage());
            }

        }else{
            $this->assign('info','订单号有误，请刷新页面重试');
            //errorMsg(400, '订单号有误，请刷新页面重试');
        }
        return $this->fetch('pay');  // 分跳转 和不 跳转
    }
    
    //易极付快捷支付
    public function quickPay(){
        $orderId = I('orderId/s')?I('orderId/s'):''; // 订单id
        //拉起支付的时候，把payType更新到Order表
        $code = I('code')?I('code'):'';
        if($orderId){
            $orderId=trim($orderId,',');
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    //$sql="SELECT o.order_id,o.order_sn,o.pay_status,order_amount,og.goods_id,og.goods_num,og.goods_name,ps.payurl,ps.payid,ps.intelpayid,ps.paytoken FROM {$this->db_prex}order AS o LEFT JOIN {$this->db_prex}order_goods AS og ON og.order_id=o.order_id LEFT JOIN {$this->db_prex}users AS u ON u.user_id=o.user_id LEFT JOIN {$this->db_prex}pay_shop AS ps ON ps.code='yiji' WHERE o.order_id in ({$orderId}) and ( o.pay_status=0 or o.pay_status=2 or o.pay_status=4 )";
                    $sql="select o.order_id,o.order_sn,o.pay_status,o.order_amount,o.add_time,og.goods_num,og.goods_name,g.goods_id,g.prom_type,ps.payurl,ps.payid,ps.intelpayid,ps.paytoken from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}goods as g on g.goods_id=og.goods_id and g.goods_sn=og.goods_sn and g.goods_name=og.goods_name and g.market_price=og.market_price and g.shop_price=og.goods_price and ( g.shop_price=og.member_goods_price or g.firstMemberPrice=og.member_goods_price or g.secondMemberPrice=og.member_goods_price or g.thirdMemberPrice=og.member_goods_price ) left join {$this->db_prex}users as u on u.user_id=o.user_id left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}pay_shop as ps on ps.code='{$code}' where o.order_id in ({$orderId}) and ( o.pay_status=0 or o.pay_status=2 or o.pay_status=4 ) and o.order_status=0";
                    if($datas=$model->query($sql)){
                        $goodsNames='';
                        $count=0;
                        foreach($datas as $value){
                            if($value['prom_type']==0) {
                                if(!$value['goods_id']) 
                                {
                                    $this->assign('info','商品信息已经更新，返回订单中心检查！');
                                    return $this->fetch('pay');
                                }
                            }
                            if((time()-$value['add_time'])>(60*60*24)){
                                $this->assign('info','订单超过24小时未支付，请重新下单！');
                                return $this->fetch('pay');
                            }
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $count++;
                        }
                        if($count>1){
                            $data['goodsName']=$goodsName.'等'.$count.'种商品';
                        }else{
                            $data['goodsName']=$goodsName;
                        }
                        $ckey=$datas[0]['paytoken'];
                        $data['orderNo']=$orderNo;
                        $data['service']='aggregatePay';
                        $data['version']='1.0';
                        $data['partnerId']=$datas[0]['payid'];
                        $merchant_private_key_yiji=C('merchant_private_key_yiji');
                        if($merchant_private_key_yiji==''){
                            $data['signType'] = 'MD5';
                        }else {
                            $data['signType'] = 'RSA';
                        }
                        $data['merchOrderNo']=$datas[0]['order_sn'];
                        $data['returnUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/payReturn');
                        $data['notifyUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/notifyUrl');
                        //$data['buyerUserId']=$datas[0]['intelpayid'];
                        $data['userTerminalType']='PC';            
                        $data['sellerUserId']=$datas[0]['payid'];
                        $data['goodsName']=$datas[0]['intelpayid'];
                        $data['tradeAmount']=$datas[0]['order_amount'];  
                        $data['paymentType']='QUICKPAY';
                        ksort($data);
                        $signSrc='';
                        foreach($data as $key=>$value){
                            if(empty($value)||$value===""){
                                unset($data[$key]);
                            }else{
                                $signSrc.= $key.'='.$value.'&';
                            }
                        }
                        if($merchant_private_key_yiji==''){
                            $signSrc = trim($signSrc, '&') . $ckey;
                            $data['sign'] = md5($signSrc);
                        }else {
                            $signSrc=trim($signSrc,'&');
                            $sign_info='';
                            $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key_yiji),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";
                            $pi_key= openssl_get_privatekey($merchant_private_key);
                            openssl_sign($signSrc,$sign_info,$pi_key);
                            $data['sign'] = base64_encode($sign_info);
                        }
                        $ip=getClientIP();
                        $time=time();
                        $sql="insert {$this->db_prex}paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$datas[0]['order_amount']}',1,'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update {$this->db_prex}order set paymentType='{$code}',payOrderNo='{$orderNo}',pay_status=2,pay_code='thirdsCanPay',pay_name='微信支付',pay_time='{$time}' where order_id in ({$orderId})";
                            $oret=$model->execute($sql);
                            if($oret){
                                $this->assign('data',$data);
                            }else{
                                $this->assign('info','网络繁忙，请刷新页面重试');
                                //errorMsg(400, '网络繁忙，请刷新页面重试');
                            }
                        }else{
                            $this->assign('info','网络繁忙，请刷新页面重试');
                            //errorMsg(400, '网络繁忙，请刷新页面重试');
                        }
                    }else{
                        throw new Exception('订单数据不存在,或者订单均已支付，请刷新页面重试');
                        //errorMsg(400, '订单数据不存在,或者订单均已支付，请刷新页面重试');
                    }
                }else{
                    throw new Exception('网络繁忙，请刷新页面重试');
                }
            }catch(Exception $e){
                $this->assign('info',$e->getMessage());
                //errorMsg(400, $e->getMessage());
            }

        }else{
            $this->assign('info','订单号有误，请刷新页面重试');
        }
        return $this->fetch('pay');  // 分跳转 和不 跳转
    }

    //智付银联支付
    public function zfPayBank(){
        $orderId = I('orderId/s')?I('orderId/s'):''; // 订单id
        //拉起支付的时候，把payType更新到Order表
        $code = I('code')?I('code'):'';
        if($orderId){
            $orderId=trim($orderId,',');
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    $sql="select o.order_id,o.order_sn,o.buyerName,o.buyerIdNumber,o.add_time,o.order_amount,og.goods_name,ps.payurl,ps.payid from __PREFIX__order as o left join __PREFIX__order_goods as og on og.order_id=o.order_id left join __PREFIX__pay_shop as ps on ps.code='zhifu' where o.order_id in ({$orderId}) and o.deleted=0 AND o.order_status=0 and o.pay_status in (0,2,3)";
                    if($datas=$model->query($sql)){
                        $post = $result = $data = array();
                        $ip = getClientIP();
                        foreach($datas as $key=>$value){
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $post['product_name']=$goodsName;
                        }
                        $post['merchant_code'] = $datas[0]['payid'];
                        $post['service_type'] = "direct_pay";
                        $post['notify_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/zfPayNotify');
                        $post['interface_version'] = "V3.0";
                        $post['input_charset'] = "UTF-8";
                        $post['return_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/payReturn');
                        $post['pay_type'] = "b2c";
                        $post['client_ip']= $ip;
                        $post['order_no']=$datas[0]['order_sn'];
                        $post['order_time']=date('Y-m-d H:i:s',$datas[0]['add_time']);
                        $post['order_amount']=$datas[0]['order_amount'];
                        $buyerName=delSpecilaChar($datas[0]['buyerName']);
                        $buyerIdNumber=delSpecilaChar($datas[0]['buyerIdNumber']);
                        $post['extend_param']='customer_name^'.$buyerName.'|customer_idNumber^'.$buyerIdNumber;
                        //$post['extend_param']='customer_name^'.$datas[0]['buyerName'].'|customer_idNumber^'.$datas[0]['buyerIdNumber'];

                        $signStr = "";
                        ksort($post);
                        foreach ($post as $k => $val) {
                            $signStr .= $k . '=' . $val . '&';
                        }
                        $signStr = trim($signStr, '&');
                        $merchant_private_key_str=C('merchant_private_key');
                        $merchant_private_key = "-----BEGIN PRIVATE KEY-----" . "\r\n" . wordwrap(trim($merchant_private_key_str), 64, "\r\n", true) . "\r\n" . "-----END PRIVATE KEY-----";
                        $merchant_private_key = \openssl_get_privatekey($merchant_private_key);
                        openssl_sign($signStr, $sign_info, $merchant_private_key, OPENSSL_ALGO_MD5);
                        $post['sign'] = base64_encode($sign_info);
                        $post['sign_type'] = "RSA-S";

                        $url=$datas[0]['payurl'];
                        $time=time();
                        $sql="insert __PREFIX__paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$datas[0]['order_amount']}',1,'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update __PREFIX__order set paymentType='{$code}',payOrderNo='{$orderNo}',pay_status=2,pay_code='zfWechat',pay_name='Mobile微信支付',pay_time='{$time}' where order_id in ({$orderId})";
                            $oret=$model->execute($sql);
                            if($oret){
                                $this->assign('url',$url);
                                $this->assign('data',$post);
                            }else{
                                $this->assign('info','网络繁忙，请刷新页面重试');
                            }
                        }else{
                            $this->assign('info','网络繁忙，请刷新页面重试');
                        }
                    }else{
                        throw new Exception('订单数据不存在,或者订单均已支付，请刷新页面重试');
                    }
                }else{
                    throw new Exception('网络繁忙，请刷新页面重试');
                }
            }catch(Exception $e){
                $this->assign('info',$e->getMessage());
            }
        }else{
            $this->assign('info','订单号有误，请刷新页面重试');
        }
        return $this->fetch('zfPay');  // 分跳转 和不 跳转
    }

    //智付微信扫码支付
    public function zfPayWechatScanCode(){
        $orderId = I('orderId/s')?I('orderId/s'):''; // 订单id
        //拉起支付的时候，把payType更新到Order表
        $code = I('code')?I('code'):'';
        if($orderId){
            $orderId=trim($orderId,',');
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    $sql="select o.order_id,o.order_sn,o.buyerName,o.buyerIdNumber,o.add_time,o.order_amount,og.goods_name,ps.payurl,ps.payid from __PREFIX__order as o left join __PREFIX__order_goods as og on og.order_id=o.order_id left join __PREFIX__pay_shop as ps on ps.code='zhifu' where o.order_id in ({$orderId}) and o.deleted=0 AND o.order_status=0 and o.pay_status in (0,2,3)";
                    if($datas=$model->query($sql)){
                        $post = $result = $data = array();
                        $ip = getClientIP();
                        foreach($datas as $key=>$value){
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $post['product_name']=$goodsName;
                        }
                        $post['merchant_code'] = $datas[0]['payid'];
                        $post['service_type'] = "direct_pay";
                        $post['notify_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/zfPayNotify');
                        $post['interface_version'] = "V3.0";
                        $post['input_charset'] = "UTF-8";
                        $post['return_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/payReturn');
                        $post['pay_type'] = "weixin";
                        $post['client_ip']= $ip;
                        $post['order_no']=$datas[0]['order_sn'];
                        $post['order_time']=date('Y-m-d H:i:s',$datas[0]['add_time']);
                        $post['order_amount']=$datas[0]['order_amount'];
                        $buyerName=delSpecilaChar($datas[0]['buyerName']);
                        $buyerIdNumber=delSpecilaChar($datas[0]['buyerIdNumber']);
                        $post['extend_param']='customer_name^'.$buyerName.'|customer_idNumber^'.$buyerIdNumber;
                        //$post['extend_param']='customer_name^'.$datas[0]['buyerName'].'|customer_idNumber^'.$datas[0]['buyerIdNumber'];

                        $signStr = "";
                        ksort($post);
                        foreach ($post as $k => $val) {
                            $signStr .= $k . '=' . $val . '&';
                        }
                        $signStr = trim($signStr, '&');
                        $merchant_private_key_str=C('merchant_private_key');
                        $merchant_private_key = "-----BEGIN PRIVATE KEY-----" . "\r\n" . wordwrap(trim($merchant_private_key_str), 64, "\r\n", true) . "\r\n" . "-----END PRIVATE KEY-----";
                        $merchant_private_key = \openssl_get_privatekey($merchant_private_key);
                        openssl_sign($signStr, $sign_info, $merchant_private_key, OPENSSL_ALGO_MD5);
                        $post['sign'] = base64_encode($sign_info);
                        $post['sign_type'] = "RSA-S";

                        $url=$datas[0]['payurl'];
                        $time=time();
                        $sql="insert __PREFIX__paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$datas[0]['order_amount']}',1,'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update __PREFIX__order set paymentType='{$code}',payOrderNo='{$orderNo}',pay_status=2,pay_code='zfWechat',pay_name='Mobile微信支付',pay_time='{$time}' where order_id in ({$orderId})";
                            $oret=$model->execute($sql);
                            if($oret){
                                $this->assign('url',$url);
                                $this->assign('data',$post);
                            }else{
                                $this->assign('info','网络繁忙，请刷新页面重试');
                            }
                        }else{
                            $this->assign('info','网络繁忙，请刷新页面重试');
                        }
                    }else{
                        //throw new Exception('订单数据不存在,或者订单均已支付，请刷新页面重试');
                        $this->assign('info','订单已支付或者订单已取消，请刷新页面');
                    }
                }else{
                    //throw new Exception('网络繁忙，请刷新页面重试');
                    $this->assign('info','网络繁忙，请刷新页面重试');
                }
            }catch(Exception $e){
                $this->assign('info',$e->getMessage());
            }
        }else{
            $this->assign('info','订单号有误，请刷新页面重试');
        }
        return $this->fetch('zfPay');  // 分跳转 和不 跳转
    }

    //智付支付宝扫码支付
    public function zfPayAliScanCode(){
        $orderId = I('orderId/s')?I('orderId/s'):''; // 订单id
        //拉起支付的时候，把payType更新到Order表
        $code = I('code')?I('code'):'';
        if($orderId){
            $orderId=trim($orderId,',');
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    $sql="select o.order_id,o.order_sn,o.buyerName,o.buyerIdNumber,o.add_time,o.order_amount,og.goods_name,ps.payurl,ps.payid from __PREFIX__order as o left join __PREFIX__order_goods as og on og.order_id=o.order_id left join __PREFIX__pay_shop as ps on ps.code='zhifu' where o.order_id in ({$orderId}) and o.deleted=0 AND o.order_status=0 and o.pay_status in (0,2,3)";
                    if($datas=$model->query($sql)){
                        $post = $result = $data = array();
                        $ip = getClientIP();
                        foreach($datas as $key=>$value){
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $post['product_name']=$goodsName;
                        }
                        $post['merchant_code'] = $datas[0]['payid'];
                        $post['service_type'] = "direct_pay";
                        $post['notify_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/zfPayNotify');
                        $post['interface_version'] = "V3.0";
                        $post['input_charset'] = "UTF-8";
                        $post['return_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/payReturn');
                        $post['pay_type'] = "alipay_scan";
                        $post['client_ip']= $ip;
                        $post['order_no']=$datas[0]['order_sn'];
                        $post['order_time']=date('Y-m-d H:i:s',$datas[0]['add_time']);
                        $post['order_amount']=$datas[0]['order_amount'];
                        $buyerName=delSpecilaChar($datas[0]['buyerName']);
                        $buyerIdNumber=delSpecilaChar($datas[0]['buyerIdNumber']);
                        $post['extend_param']='customer_name^'.$buyerName.'|customer_idNumber^'.$buyerIdNumber;
                        //$post['extend_param']='customer_name^'.$datas[0]['buyerName'].'|customer_idNumber^'.$datas[0]['buyerIdNumber'];

                        $signStr = "";
                        ksort($post);
                        foreach ($post as $k => $val) {
                            $signStr .= $k . '=' . $val . '&';
                        }
                        $signStr = trim($signStr, '&');
                        $merchant_private_key_str=C('merchant_private_key');
                        $merchant_private_key = "-----BEGIN PRIVATE KEY-----" . "\r\n" . wordwrap(trim($merchant_private_key_str), 64, "\r\n", true) . "\r\n" . "-----END PRIVATE KEY-----";
                        $merchant_private_key = \openssl_get_privatekey($merchant_private_key);
                        openssl_sign($signStr, $sign_info, $merchant_private_key, OPENSSL_ALGO_MD5);
                        $post['sign'] = base64_encode($sign_info);
                        $post['sign_type'] = "RSA-S";

                        $url=$datas[0]['payurl'];
                        $time=time();
                        $sql="insert __PREFIX__paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$datas[0]['order_amount']}',1,'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update __PREFIX__order set paymentType='{$code}',payOrderNo='{$orderNo}',pay_status=2,pay_code='zfWechat',pay_name='Mobile微信支付',pay_time='{$time}' where order_id in ({$orderId})";
                            $oret=$model->execute($sql);
                            if($oret){
                                $this->assign('url',$url);
                                $this->assign('data',$post);
                            }else{
                                $this->assign('info','网络繁忙，请刷新页面重试');
                            }
                        }else{
                            $this->assign('info','网络繁忙，请刷新页面重试');
                        }
                    }else{
                        //throw new Exception('订单数据不存在,或者订单均已支付，请刷新页面重试');
                        $this->assign('info','订单已支付或者订单已取消，请刷新页面');
                    }
                }else{
                    //throw new Exception('网络繁忙，请刷新页面重试');
                    $this->assign('info','网络繁忙，请刷新页面重试');
                }
            }catch(Exception $e){
                $this->assign('info',$e->getMessage());
            }
        }else{
            $this->assign('info','订单号有误，请刷新页面重试');
        }
        return $this->fetch('zfPay');  // 分跳转 和不 跳转
    }
}
