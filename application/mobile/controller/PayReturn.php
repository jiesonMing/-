<?php
namespace app\mobile\controller;
use think\Controller;
/**
 * Description of PayReturn
 *
 * @author 邓小明 2017.05.03
 */
class PayReturn extends Controller{
    public $cartLogic; // 购物车逻辑操作类
    public $db_prex='';
    public $setId=1;
    public $log='';
    public $xmlPath='';
    /**
     * 析构流函数
     */
    public function  _initialize() {  
        parent::_initialize();
        $this->db_prex=C('db_prex');
        $this->log=C('pay_log');
        $this->xmlPath=C('xml_path');
    }
    //异步回执
    public function notifyUrl(){
        $orderNo=I('orderNo')?I('orderNo'):'';
        $tradeNo=I('tradeNo')?I('tradeNo'):'';
        $resultCode=I('resultCode')?I('resultCode'):'';
        $sign=I('sign')?I('sign'):'';;
        $tradeAmount=I('tradeAmount')?I('tradeAmount'):'';
        $orderSn=I('merchOrderNo')?I('merchOrderNo'):'';
        $fastPayStatus=I('fastPayStatus')?I('fastPayStatus'):'';
        $str='';
        if($orderNo && $orderSn){
            if($resultCode=='EXECUTE_SUCCESS'){
                $model=M('Order');
                $sql="select se.payurl,se.intelpayid,se.intelpaytoken,se.customspaytype,se.customsNo,se.customsName,se.customsCode,se.ciqCode,se.ciqNo,se.ciqBNo,se.shopDomain,o.order_id,o.user_id,o.buyerName,o.buyerIdNumber,o.freight,o.taxTotal,o.stype from {$this->db_prex}order as o left join {$this->db_prex}settings as se on se.id={$this->setId} where o.order_sn='{$orderSn}'";
                $data=$model->query($sql);
                if($data){
                    $sql="update {$this->db_prex}order set tradeNo='{$tradeNo}',tradeInfo='{$resultCode}',tradeSign='{$sign}',pay_status=1,payOrderStatus=1 where order_sn='{$orderSn}'";
                    $str=date("Y-m-d H:i:s").':'.$sql."\r\n";
                    $oret=$model->execute($sql);
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultCode}',istatus=3 where orderNo='{$orderNo}'";
                    $str.=date("Y-m-d H:i:s").':'.$sql."\r\n\r\n";
                    $pret=$model->execute($sql);
                    $time=time();
                    $sql="insert {$this->db_prex}order_action(order_id,action_user,pay_status,action_note,log_time,status_desc) values({$data[0]['order_id']},{$data[0]['user_id']},1,'您成功支付了订单，支付流水号为：{$tradeNo}','{$time}','用户支付完成订单')";
                    $str.=date("Y-m-d H:i:s").':'.$sql."\r\n\r\n";
                    $aret=$model->execute($sql);
                    if($oret && $data[0]['stype']==1){
                        echo 'success';
                    }elseif($oret && $data[0]['stype']==2){
                        @file_put_contents($this->log.'/pay.log',$str, FILE_APPEND);
                        echo 'success';
                    }else{
                        @file_put_contents($this->log.'/pay.log',$str, FILE_APPEND);
                        echo 'failed';
                    }
                }else{
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultCode}',tradeStatus='未找到订单信息',istatus=2 where orderNo='{$orderNo}'";
                    $str=date("Y-m-d H:i:s").':'.$sql."\r\n\r\n";                  
                    $pret=$model->execute($sql);
                    @file_put_contents($this->log.'/pay.log',$str, FILE_APPEND);
                    echo 'failed';
                }
            }else{
                $model=M('Paylog');
                $model->startTrans();
                $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultCode}',istatus=2 where orderNo='{$orderNo}'";
                $str=date("Y-m-d H:i:s").':'.$sql."\r\n\r\n";
                $pret=$model->execute($sql);
                @file_put_contents($this->log.'/pay.log',$str, FILE_APPEND);
                echo 'failed';
            }
        }else{
            @file_put_contents($this->log.'/pay.log',"支付异步回执的订单号或者支付单号为空\r\n\r\n", FILE_APPEND);
            echo 'failed';
        }
    }
}
