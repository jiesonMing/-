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
    //手机端w微信支付
    public function wechat(){
        $orderId = I('order_id'); // 订单id
        if($orderId){
            $orderNo='P'.date('YmdHis').  str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
            try{
                if($model=M('Order')){
                    $sql="SELECT u.openid,o.order_id,o.order_sn,o.pay_status,o.order_amount,og.goods_id,og.goods_num,og.goods_name,ps.payurl,ps.payid,ps.intelpayid,ps.paytoken FROM {$this->db_prex}order AS o LEFT JOIN {$this->db_prex}order_goods AS og ON og.order_id=o.order_id LEFT JOIN {$this->db_prex}users AS u ON u.user_id=o.user_id LEFT JOIN {$this->db_prex}pay_shop AS ps ON ps.code='yiji' WHERE o.order_id in ({$orderId}) and ( o.pay_status=0 or o.pay_status=2 or o.pay_status=4 )";
                    if($datas=$model->query($sql)){
                        $orderArr=$data=$tradeArr=array();
                        $tradeInfo='';
                        $amount=0;
                        $orderNum=0;
                        foreach($datas as $key=>$value){
                            if(!in_array($value['order_id'],$orderArr)){
                                $orderArr[]=$value['order_id'];
                                $orderNum++;
                            }
                            $tradeArr[$value['order_id']]['merchOrderNo']=$value['order_sn'];
                            $tradeArr[$value['order_id']]['tradeName']='及时到账';
                            $tradeArr[$value['order_id']]['tradeAmount']=$value['order_amount'];
                            $amount+=$value['order_amount'];
                            $tradeArr[$value['order_id']]['currency']='CNY';
                            $tradeArr[$value['order_id']]['goodsName']=$value['goods_name'];                    
                            $tradeArr[$value['order_id']]['sellerUserId']=$value['payid'];
                            //$tradeArr[$value['order_id']]['sellerUserId']='20160617020000748575';//测试
                        }
                        $tradeInfo=array_values($tradeArr);
                        $ckey=$datas[0]['paytoken'];
                        //$ckey='6592639bb6379b5538c2bd2500778c45';//测试
                        $data['orderNo']=$orderNo;
                        $data['service']='fastPayTradeMergePay';
                        $data['version']='1.0';
                        $data['partnerId']=$datas[0]['payid'];
                        $data['buyerUserId']=$datas[0]['intelpayid'];
                        //$data['partnerId']='20160617020000748575';//测试
                        //$data['buyerUserId']='20150818010000465797';//测试
                        $data['signType']='MD5';
                        $data['merchOrderNo']=$orderNo;
                        $data['returnUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Mobile/Pay/payReturn');
                        $data['notifyUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/notifyUrl');
                        //$data['notifyUrl']= 'http://120.25.100.81'.U('/Home/Customs/notifyUrl');                 
                        $data['paymentType']='PAYMENT_TYPE_WECHAT';
                        $data['openid']=$datas[0]['openid'];
                        $data['userTerminalType']='MOBILE';
                        $data['tradeInfo']='[';
                        foreach($tradeInfo as $key=>$value){
                            if($key>0){
                                $data['tradeInfo'].=",{";
                            }else{
                                $data['tradeInfo'].="{";
                            }
                            foreach($value as $k=>$v){
                                if($k=='merchOrderNo'){
                                    $data['tradeInfo'].="'".$k."':'".$v."'";
                                }else{
                                    $data['tradeInfo'].=",'".$k."':'".$v."'";
                                }
                            }
                            $data['tradeInfo'].='}';
                        }
                        $data['tradeInfo'].=']';
                        ksort($data);
                        $signStr='';
                        foreach($data as $key=>$value){
                            if(empty($value)||$v===""){
                                unset($data[$key]);
                            }else{
                                $signSrc.= $key.'='.$value.'&';
                            }
                        }
                        $signSrc = trim($signSrc, '&').$ckey;
                        $data['sign']=md5($signSrc);
                        //dump($data);die;
                        $ip=getClientIP();
                        $time=time();
                        $sql="insert {$this->db_prex}paylog(orderNo,amount,orderNum,ip) values('{$orderNo}','{$amount}',{$orderNum},'{$ip}')";
                        $pret=$model->execute($sql);
                        if($pret){
                            $sql="update {$this->db_prex}order set payOrderNo='{$orderNo}',pay_status=2,pay_code='wechat',pay_name='Mobile微信支付',pay_time='{$time}' where order_id in ({$orderId})";
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
       return $this->fetch('pay');  // 分跳转 和不 跳转
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
                $sql="update {$this->db_prex}order set pay_status=0 where payOrderNo='{$orderNo}'";
                $info="支付失败，请重新支付";
                $oret=$model->execute($sql);
            }
            $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultCode}',istatus=1  where orderNo='{$orderNo}'";
            $pret=$model->execute($sql);
        }else{
            $info="错误的支付返回";
        }
        $this->assign('info',$info);
        return $this->fetch('payReturn');
    }
}
