<?php
namespace app\mobile\controller;
use app\mobile\controller\MobileBase;
/**
 * Description of Pay
 *
 * @author 邓小明 2017.05.03
 */
class Pay extends MobileBase{
    //put your code here
    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code
    public $user_id = 0;
    public $mylevel=0;
    public $user = array();
    public $db_prex='';
    /**
     * 析构流函数
     */
    public function  _initialize() {   
        parent::_initialize();
        $this->db_prex=C('db_prex');
    }

    //易极付手机端w微信支付
    public function wechat(){
        $orderId = I('order_id'); // 订单id
        $payComCode=I('payComCode');//支付公司代码
        if($orderId){
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    $sql="SELECT u.openid,o.order_id,o.order_sn,o.pay_status,o.order_amount,og.goods_id,og.goods_num,og.goods_name,ps.payurl,ps.payid,ps.intelpayid,ps.paytoken FROM __PREFIX__order AS o LEFT JOIN __PREFIX__order_goods AS og ON og.order_id=o.order_id LEFT JOIN __PREFIX__users AS u ON u.user_id=o.user_id LEFT JOIN __PREFIX__pay_shop AS ps ON ps.code='yiji' WHERE o.order_id in ({$orderId}) and ( o.pay_status=0 or o.pay_status=2 or o.pay_status=4 )";
                    if($datas=$model->query($sql)){
                        $data=array();
                        $goodsNames='';
                        $count=0;
                        foreach($datas as $key=>$value){
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $data['goodsName']=str_replace('+', '', $goodsName);
                        }
                        $ckey=$datas[0]['paytoken'];
                        $data['orderNo']=$orderNo;
                        $data['service']='aggregatePay';
                        $data['version']='1.0';
                        $data['partnerId']=$datas[0]['payid'];
                        //$data['buyerUserId']=$datas[0]['intelpayid'];
                        $merchant_private_key_yiji=C('merchant_private_key_yiji');
                        if($merchant_private_key_yiji==''){
                            $data['signType'] = 'MD5';
                        }else {
                            $data['signType'] = 'RSA';
                        }
                        $data['sellerUserId']=$datas[0]['payid'];
                        $data['tradeAmount']=$datas[0]['order_amount'];
                        
                        //$data['signType']='MD5';
                        $data['merchOrderNo']=$datas[0]['order_sn'];
                        $data['returnUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Mobile/Pay/payReturn');
                        $data['notifyUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/notifyUrl');
                        //$data['notifyUrl']= 'http://120.25.100.81'.U('/Home/Customs/notifyUrl');                 
                        $data['paymentType']='PAYMENT_TYPE_WECHAT';
                        $data['openid']=$datas[0]['openid'];
                        $data['userTerminalType']='MOBILE';
                        $data['memberType']='MEMBER_TYPE_PATERN';
                        ksort($data);
                        $signSrc='';
                        foreach($data as $key=>$value){
                            if(empty($value)||$key===""){
                                unset($data[$key]);
                            }else{
                                $signSrc.= $key.'='.$value.'&';
                            }
                        }
                        //dump($this->db_prex);die;
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
                        $sql="insert __PREFIX__paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$datas[0]['order_amount']}',1,'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update __PREFIX__order set paymentType='{$payComCode}',payOrderNo='{$orderNo}',pay_status=2,pay_code='wechat',pay_name='Mobile微信支付',pay_time='{$time}' where order_id in ({$orderId})";
                            $oret=$model->execute($sql);
                            if($oret){
                                $this->assign('data',$data);
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
                    $this->assign('info','网络繁忙，请刷新页面重试试');
                }
            }catch(Exception $e){
                $this->assign('info',$e->getMessage());
            }
            
        }else{
            $this->assign('info','订单号有误，请刷新页面重试');
        }
       return $this->fetch('pay');  // 分跳转 和不 跳转
    }

    //智付手机端微信支付
    public function zfWechat(){
        $orderId = I('order_id'); // 订单id
        $payComCode=I('payComCode');//支付公司代码
        if($orderId){
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    //$sql="SELECT u.openid,o.order_id,o.order_sn,o.pay_status,o.order_amount,og.goods_id,og.goods_num,og.goods_name,ps.payurl,ps.payid,ps.intelpayid,ps.paytoken FROM __PREFIX__order AS o LEFT JOIN __PREFIX__order_goods AS og ON og.order_id=o.order_id LEFT JOIN __PREFIX__users AS u ON u.user_id=o.user_id LEFT JOIN __PREFIX__pay_shop AS ps ON ps.code='yiji' WHERE o.order_id in ({$orderId}) and ( o.pay_status=0 or o.pay_status=2 or o.pay_status=4 )";
                    $sql="select o.order_id,o.order_sn,o.buyerName,o.buyerIdNumber,o.add_time,o.order_amount,og.goods_name,ps.payurl,ps.payid from __PREFIX__order as o left join __PREFIX__order_goods as og on og.order_id=o.order_id left join __PREFIX__pay_shop as ps on ps.code='zhifu' where o.order_id in ({$orderId}) and o.deleted=0 AND o.order_status=0 and o.pay_status in (0,2,3)";

                    if($datas=$model->query($sql)){
                        $post = $result = $data = array();
                        $ip = getClientIP();
                        foreach($datas as $key=>$value){
                            $goodsName=delSpecilaChar($value['goods_name']);
                            $post['product_name']=$goodsName;
                        }
                        $post['merchant_code'] = $datas[0]['payid'];
                        $post['service_type'] = "wxpub_pay";
                        $post['notify_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/zfPayNotify');
                        $post['interface_version'] = "V3.0";
                        $post['input_charset'] = "UTF-8";
                        $post['return_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Mobile/Customs/payReturn');
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
                            $sql="update __PREFIX__order set paymentType='{$payComCode}',payOrderNo='{$orderNo}',pay_status=2,pay_code='zfWechat',pay_name='Mobile微信支付',pay_time='{$time}' where order_id in ({$orderId})";
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

     //同步回执
    public function payReturn(){
        $orderNo=I('orderNo')?I('orderNo'):'';
        if($orderNo){
            $resultCode=I('resultCode')?I('resultCode'):'';
            $model=M('Order');
            if($resultCode=='EXECUTE_SUCCESS'){
                $info="支付完成，请等待异步回执信息";
            }else if($resultCode=='EXECUTE_PROCESSING'){
                $info="支付处理中，请等待异步回执信息";
            }else{
                $sql="update __PREFIX__order set pay_status=0 where payOrderNo='{$orderNo}'";
                $info="支付失败，请重新支付";
                $oret=$model->execute($sql);
            }
            $sql="update __PREFIX__paylog set reTime=now(),message='{$resultCode}',istatus=1  where orderNo='{$orderNo}'";
            $pret=$model->execute($sql);
        }else{
            $info="错误的支付返回";
        }
        $this->assign('info',$info);
        return $this->fetch('payReturn');
    }
}
