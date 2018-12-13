<?php
/**
 * $Author: 于明明 2017-03-23
 */ 
namespace app\home\controller; 
use app\home\logic\CartLogic;
use think\Request;
use think\Exception;
use think\DB;
use think\Model;
use think\Ftp;
use think\DOMDocument;

class Customs extends Base {

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
                $model->execute($sql);
            }
            $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultCode}',istatus=1  where orderNo='{$orderNo}'";
            $model->execute($sql);
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','paylog','orderNo={$orderNo}','易极付支付订单同步回执')";
            $model->execute($sql);
        }else{
            $info="错误的支付返回";
        }
        $this->assign('info',$info);
        return $this->fetch();
    }
    
    //易极付异步回执（MD5）
    public function notifyUrl(){
        $orderNo=I('orderNo')?I('orderNo'):'';
        $tradeNo=I('tradeNo')?I('tradeNo'):'';
        $resultCode=I('resultCode')?I('resultCode'):'';
        $resultMessage=I('resultMessage/s')?I('resultMessage/s'):'';
        $tradeStatus=I('tradeStatus/s')?I('tradeStatus/s'):'';
        $orderSn=I('merchOrderNo')?I('merchOrderNo'):'';
		//订单号有误
        if($orderSn==''){
            $model=M('action');
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','paylog','orderNo={$orderNo}','错误的回执参数')";
            $model->execute($sql);
            echo 'failed';
            exit(0);
        }
        if($orderSn!=''){
            //支付完成
            if($tradeStatus=='FINISHED'){
                $model=M('Order');
                //$sql="select se.payurl,se.intelpayid,se.intelpaytoken,se.customspaytype,se.customsNo,se.companyName,se.customsCode,se.ciqCode,se.ciqNo,se.ciqBNo,se.shopDomain,o.order_id,o.payOrderStatus,o.user_id,o.buyerName,o.buyerIdNumber,o.freight,o.taxTotal,o.stype,w.warehouse_type from {$this->db_prex}order as o left join {$this->db_prex}settings as se on se.id={$this->setId} left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_sn='{$orderSn}'";
                $sql="select ps.payurl,ps.intelpayid,ps.intelpaytoken,ps.customspaytype,ps.orderFlowType,ps.bizTypeCode,se.customsNo,se.companyName,se.ciqNo,se.ciqBNo,se.shopDomain,o.order_id,o.payOrderStatus,o.user_id,o.buyerName,o.buyerIdNumber,o.freight,o.taxTotal,o.stype,o.portId,o.paymentType,og.goods_num,og.member_goods_price,w.warehouse_type from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}settings as se on se.id=p.settingId left join {$this->db_prex}pay_shop as ps on ps.code=o.paymentType left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_sn='{$orderSn}' and o.pay_status=2";
                $data=$model->query($sql);
                if($data){
                    $time=time();
                    //更新订单支付状态
                    if($data[0]['payOrderStatus']==3){
                        $sql="update {$this->db_prex}order set tradeNo='{$tradeNo}',tradeInfo='{$resultCode}',pay_status=1,pay_time='{$time}',payOrderTime=now() where order_sn='{$orderSn}'";
                    }else{
                        $sql="update {$this->db_prex}order set tradeNo='{$tradeNo}',tradeInfo='{$resultCode}',pay_status=1,payOrderStatus=1,payCiqOrderStatus=1,pay_time='{$time}',payOrderTime=now(),payCiqOrderTime=now() where order_sn='{$orderSn}'";
                    }
                    $oret=$model->execute($sql);
                    
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultMessage}',istatus=3 where orderNo='{$orderNo}'";
                    $model->execute($sql);
                    $sql="insert {$this->db_prex}order_action(order_id,action_user,pay_status,action_note,log_time,status_desc) values({$data[0]['order_id']},{$data[0]['user_id']},1,'您成功支付了订单，支付流水号为：{$tradeNo}','{$time}','用户支付完成订单')";
                    $model->execute($sql);
                    if($data[0]['warehouse_type']==1 || $data[0]['warehouse_type'==2]){

                        $sql="select oi.psCode,oi.Code from {$this->db_prex}organization_conf as oc left join {$this->db_prex}organization_info as oi on oi.id=oc.organInfo where oc.portId={$data[0]['portId']} and oi.payCode='{$data[0]['paymentType']}'";
                        $organArr=$model->query($sql);
                        $customsCode='';
                        $ciqCode='';

                        foreach($organArr as $organ){
                            if($organ['Code']=='cus'){
                                $customsCode=$organ['psCode'];
                            }
                            if($organ['Code']=='ciq'){
                                $ciqCode=$organ['psCode'];
                            }
                        }
                        $post=array();
                        $goodsAmount=0;
                        foreach($data as $value){
                            $goodsAmount+=$value['goods_num']*$value['member_goods_price'];
                        }
                        $post['partnerId']=$data[0]['intelpayid'];
                        //$post['partnerId']='20140926020000058373';//测试
                        $post['notifyUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/OrderNotify');
                        //$post['notifyUrl']= 'http://120.25.100.81'.U('/Home/Customs/OrderNotify');   
                        $post['orderNo']='SDM'.time().str_pad(rand(0,99999),5,'0',STR_PAD_LEFT);
                        $skey = $data[0]['intelpaytoken'];                        
                        //$skey='2af0376a5dc1695aa1ab889384a8ade9';//测试                
                        $post['service']="singlePaymentUpload";
                        $merchant_private_key_yiji=C('merchant_private_key_yiji_in');
                        if($merchant_private_key_yiji==''){
                            $post['signType']='MD5';
                        }else {
                            $post['signType'] = 'RSA';
                        }
                        $post['version']="1.0";
                        $post['protocol']="httpPost";
                        //支付单上传参数
                        $post['orderFlowType']=$data[0]['orderFlowType']; //NORMAL:普通业务,SPECIAL:特殊业务
                        $post['eplatEntName']=$data[0]['companyName'];
                        $post['eplatEntCode']=$data[0]['customsNo'];
                        $post['eshopEntName']=$data[0]['companyName'];
                        $post['eshopEntCode']=$data[0]['customsNo'];
                        $post['customsCode']=$customsCode;
                        $post['ngtcCode']=$ciqCode;
                        $post['eplatCodeForNgct']=$data[0]['ciqNo'];
                        $post['eEntCodeForNgct']=$data[0]['ciqBNo'];
                        $post['eplatDNS']=$data[0]['shopDomain'];
                        $post['outOrderNo']=$orderSn;
                        $post['merchOrderNo']=$orderSn;
                        $post['paymentType']=""; //ALIPAY:支付宝
                        $post['payerDocType']="Identity_Card";/*Identity_Card:身份证 Army_Identity_Card:军官证 Passport:护照 Home_Return_Permit:回乡证 Taiwan_Compatriot_Entry_Permit:台胞证 OFFICERS_CARD:警官证 Soldiers_Card:士兵证 BUSINESS_LICENSE:营业执照 HOUSEHOLD_REGISTER:户口簿 HK_MACAO_PASS:港澳通行证 Other:其它证件*/
                        $post['payerId']=strtoupper($data[0]['buyerIdNumber']);
                        $post['payerName']=delSpecilaChar($data[0]['buyerName']);
                        //$post['bizTypeCode']=$data[0]['bizTypeCode']; //DIRECT_IMPORT:直购进口/一般模式,FREE_TAX_IMPORT:网购免税进口/保税模式
                        $post['goodsCurrency']="CNY"; 
                        //$post['orderFlowType']="NORMAL"; //NORMAL:普通业务,SPECIAL:特殊业务
                        $post['goodsAmount']=$goodsAmount;
                        $post['taxAmount']=$data[0]['taxTotal'];
                        $post['freightAmount']=$data[0]['freight'];
                        $post['tradeNo']='["'.$tradeNo.'"]';
                        $post['taxCurrency']="CNY";
                        $post['freightCurrency']="CNY";
                        //$post['bizTransType']=$data[0]['customspaytype']; 
                        //$post['appStatus']='DECLARE'; 
                        $post['ieType']='IMPORT'; 
                        //$post['bizTransType']=$data[0]['customspaytype']; 
                        //准备待签名串
                        ksort($post);
                        $strSign = "";
                        foreach($post as $k=>$v)
                        {
                            if($v===""){
                                unset($post[$k]);
                            }else{
                                $strSign.=$k."=".($v)."&";
                            }
                        }
                        if($merchant_private_key_yiji==''){
                            $strSign = substr($strSign,0,-1).$skey;
                            $post['sign'] = md5($strSign);
                        }else{
                            $signSrc=trim($strSign,'&');
                            $sign_info='';
                            $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key_yiji),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";
                            $pi_key= openssl_get_privatekey($merchant_private_key);
                            openssl_sign($signSrc,$sign_info,$pi_key);
                            $post['sign'] = base64_encode($sign_info);
                        }
                        //准备URL
                        $postURL = "";
                        foreach ($post as $key=>$value)  
                        {
                            $postURL.=$key."=".urlencode($value)."&";
                        }
                        $postURL = substr($postURL,0,-1);
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
                        curl_setopt($ch, CURLOPT_URL,$data[0]['payurl']);
                        //curl_setopt($ch, CURLOPT_URL,'http://openapi.yijifu.net/gateway.html');//测试
                        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->log.'/cookie.txt');  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postURL);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); 
                        curl_setopt($ch, CURLOPT_TIMEOUT,70);
                        $response = curl_exec($ch);
                        curl_close($ch);
                        $output_array = json_decode($response,true);
                        if($output_array['resultCode']=='EXECUTE_FAIL'){
                            if($output_array['resultMessage']=='支付单上传异常:【实名认证系统验证错误】，业务类型支付单校验不通过,手续费解冻【成功】'){
                                setBuyerIdNumber($data[0]['buyerName'],$data[0]['buyerIdNumber']);
                            }
                            $sql="update {$this->db_prex}order set payOrderStatus=2,payOrderInfo='{$output_array['resultMessage']}' where order_sn='{$orderSn}'";
                            $model->execute($sql);
                        }
                        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付订单支付异步回执成功')";
                        $model->execute($sql);
                        //支付完成，发送短信/邮件给商城管理员--start
                        $shop=tpCache('shop_info');
                        $mobile=$shop['mobile'];
                        $smsSign='商城mall';
                        $smsParam="{orderNo:'".$orderSn."'}";
                        $templateCode='SMS_109340141';
                        #手机
                        if(!empty($mobile))
                            //realSendSMS($mobile, $smsSign, $smsParam , $templateCode);
                        #邮件
                        $smtp=tpCache('smtp');
                        $smtp_user=$smtp['smtp_user'];
                        if(!empty($smtp_user))
                            //send_email($smtp_user,'客户下单支付完成','亲爱的管理员，有客户的订单支付完成了，赶紧去处理订单吧！');
                        //支付完成，发送短信/邮件给商城管理员--end
                        //支付完成，商品已售的增加-start
                        changeGoodsNum($orderSn);
                        //支付完成，商品已售的增加-end
                        echo 'success';
                        exit(0);
                    }elseif($oret && $data[0]['warehouse_type']==3){
                        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付订单支付异步回执，不需要支付单')";
                        $model->execute($sql);
                        //支付完成，发送短信/邮件给商城管理员--start
                        $shop=tpCache('shop_info');
                        $mobile=$shop['mobile'];
                        $smsSign='商城mall';
                        $smsParam="{orderNo:'".$orderSn."'}";
                        $templateCode='SMS_109340141';
                        #手机
                        if(!empty($mobile))
                            //realSendSMS($mobile, $smsSign, $smsParam , $templateCode);
                        #邮件
                        $smtp=tpCache('smtp');
                        $smtp_user=$smtp['smtp_user'];
                        if(!empty($smtp_user))
                            //send_email($smtp_user,'客户下单支付完成','亲爱的管理员，有客户的订单支付完成了，赶紧去处理订单吧！');
                        //支付完成，发送短信/邮件给商城管理员--end
                        //支付完成，商品已售的增加-start
                        changeGoodsNum($orderSn);
                        //支付完成，商品已售的增加-end
                        echo 'success';
                        exit(0);
                    }else{
                        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付订单支付异步回执失败')";
                        $model->execute($sql);
                        echo 'failed';
                        exit(0);
                    }
                }else{
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultMessage}',tradeStatus='未找到订单信息',istatus=2 where orderNo='{$orderNo}'";               
                    $model->execute($sql);
                    $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付订单支付异步回执失败')";
                    $model->execute($sql);
                    echo 'failed';
                    exit(0);
                }
            }elseif($tradeStatus=='WAIT_PAY'){
                $model=M('Order');
                $sql="select order_id,user_id from {$this->db_prex}order where order_sn='{$orderSn}'";
                $data=$model->query($sql);
                if($data){
                    $time = time();
                    $sql="update {$this->db_prex}order set tradeNo='{$tradeNo}',tradeInfo='{$resultMessage}',pay_status=3,pay_time='{$time}' where order_sn='{$orderSn}'";
                    $model->execute($sql);                    
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultMessage}',istatus=2 where orderNo='{$orderNo}'";
                    $model->execute($sql);
                    $time=time();
                    $sql="insert {$this->db_prex}order_action(order_id,action_user,pay_status,action_note,log_time,status_desc) values({$data[0]['order_id']},{$data[0]['user_id']},0,'等待付款，支付流水号为：{$tradeNo}','{$time}','{$resultMessage}')";
                    $model->execute($sql);
                    echo 'success';
                    exit(0);
                }else{
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultMessage}',tradeStatus='未找到订单信息',istatus=2 where orderNo='{$orderNo}'";               
                    $model->execute($sql);
                    $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付订单支付异步回执失败')";
                    $model->execute($sql);
                    echo 'failed';
                    exit(0);
                }
            }elseif($tradeStatus=='CLOSED'){
                $model=M('Order');
                $sql="order_id,user_id from {$this->db_prex}order where order_sn='{$orderSn}'";
                $data=$model->query($sql);
                if($data){
                    $time = time();
                    $sql="update {$this->db_prex}order set tradeNo='{$tradeNo}',tradeInfo='{$resultMessage}',pay_status=3,pay_time='{$time}' where order_sn='{$orderSn}'";
                    $model->execute($sql);                    
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultMessage}',istatus=3 where orderNo='{$orderNo}'";
                    $model->execute($sql);
                    $time=time();
                    $sql="insert {$this->db_prex}order_action(order_id,action_user,pay_status,action_note,log_time,status_desc) values({$data[0]['order_id']},{$data[0]['user_id']},1,'交易关闭，支付流水号为：{$tradeNo}','{$time}','{$resultMessage}')";
                    $model->execute($sql);
                    echo 'success';
                    exit(0);
                }else{
                    $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultMessage}',tradeStatus='未找到订单信息',istatus=2 where orderNo='{$orderNo}'";
                    $model->execute($sql);
                    $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付订单支付异步回执失败')";
                    $model->execute($sql);
                    echo 'failed';
                    exit(0);
                }
            }else{
                $model=M('Paylog');
                $sql="update {$this->db_prex}paylog set reTime=now(),message='{$resultCode}',istatus=3 where orderNo='{$orderNo}'";
                $model->execute($sql);
                $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','paylog','orderNo={$orderNo}','易极付订单支付异步回执失败')";
                $model->execute($sql);
                echo 'failed';
                exit(0);
            }
        }else{
            $model=M('action');
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','paylog','orderNo={$orderNo}','错误的回执参数')";
            $model->execute($sql);
            echo 'failed';
            exit(0);
        }
    }

    //智付支付异步回执
    public function zfPayNotify(){
        $order_no=$_REQUEST['order_no']?$_REQUEST['order_no']:'';
        $bank_seq_no=$_REQUEST['bank_seq_no']?$_REQUEST['bank_seq_no']:'';
        $trade_no=$_REQUEST['trade_no']?$_REQUEST['trade_no']:'';
        $trade_status=$_REQUEST['trade_status']?$_REQUEST['trade_status']:'';
		//未接收到订单号
        if($order_no==''){
            echo 'failed';
            exit(0);
        }
        //接收到订单号
        if($order_no){
            //支付成功
            if($trade_status=='SUCCESS'){
                $sql="select pl.id as plId,pl.istatus,o.order_id,o.user_id,o.order_sn,o.tradeNo,o.order_amount,o.freight,o.taxTotal,o.insuredFee,o.discount,o.buyerName,o.buyerIdNumber,o.pay_time,o.portId,o.paymentType,ps.payid,se.customsNo,se.ciqNo,se.companyName,og.goods_num,og.member_goods_price,w.warehouse_type from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}settings as se on se.id=p.settingId left join {$this->db_prex}pay_shop as ps on ps.code=o.paymentType left join {$this->db_prex}paylog as pl on o.payOrderNo=pl.orderNo where o.order_sn='{$order_no}'";
                $model=MM('Order','DB_CONFIG3');
                $data=$model->query($sql);
                if($data) {
                    $sqlArr = array();
                    if ($data[0]['istatus'] != 3) {
                        $sqlArr[] = "update {$this->db_prex}paylog set istatus=3,reTime=now(),message='{$trade_status}' where id={$data[0]['plId']}";
                    }
                    if($data[0]['warehouse_type']==1 || $data[0]['warehouse_type']==2){
                        $post = $ciqPost = array();

                        $sql="select oi.psCode,oi.Code from {$this->db_prex}organization_conf as oc left join {$this->db_prex}organization_info as oi on oi.id=oc.organInfo where oc.portId={$data[0]['portId']} and oi.payCode='{$data[0]['paymentType']}'";
                        $organArr=$model->query($sql);
                        $customsCode='';
                        $ciqCode='';

                        foreach($organArr as $organ){
                            if($organ['Code']=='cus'){
                                $customsCode=$organ['psCode'];
                            }
                            if($organ['Code']=='ciq'){
                                $ciqCode=$organ['psCode'];
                            }
                        }

                        $sql="";
                        foreach ($data as $value) {
                            $discount=$value['discount'];
                            $ciqPost['merchant_id'] = $post['merchant_id'] = $value['payid'];//商户号
                            $ciqPost['out_trade_no'] = $post['out_trade_no'] = $value['order_sn'];//商户订单号
                            $ciqPost['sub_trade_no'] = $post['sub_trade_no'] = $value['order_sn'];//商户子订单号
                            $ciqPost['transaction_id'] = $post['transaction_id'] = $trade_no;//支付订单号
                            $ciqPost['order_currency'] = $post['currency_id'] = 'CNY';
                            $acturalPaid=substr(sprintf('%.3f', $value['order_amount']), 0, -1);
                            $ciqPost['pay_amount'] = $ciqPost['order_amount'] = $post['order_amount'] = intval($acturalPaid*100);//应付金额
                            $ciqPost['pay_currency'] = 'CNY';
                            $ciqPost['pay_date'] = date('Y-m-d H:i:s',$value['pay_time']);
                            $ciqPost['ciq_type'] = 1;
                            $ciqPost['mch_ciq_no'] = $value['ciqNo'];
                            $ciqPost['ciqbcode'] = $ciqCode;
                            $post['protocol_no'] = 'P';
                            $freight=substr(sprintf('%.3f', $value['freight']), 0, -1);
                            $post['transport_amount'] = intval($freight*100);//物流费
                            $totalPrice=$value['goods_num']*$value['member_goods_price'];
                            $totalPrice = substr(sprintf('%.3f', $totalPrice), 0, -1);
                            $post['product_amount'] += intval($totalPrice*100);//商品价格;
                            $taxTotal=substr(sprintf('%.3f', $value['taxTotal']), 0, -1);
                            $post['duty'] = intval($taxTotal)*100;//关税
                            $insuredFee=substr(sprintf('%.3f', $value['insuredFee']), 0, -1);
                            $post['insured_amount'] = intval($insuredFee*100);//保费
                            $post['customs'] = $customsCode;//所属海关15-广州海关总署版，18-广州海关单一窗口3.0版
                            $post['mch_customs_no'] = $value['customsNo'];//商户海关备案号
                            $ciqPost['mch_ciq_name'] = $post['mch_customs_name'] = $value['companyName'];//商户企业名称
                            $ciqPost['cert_type'] = $post['cert_type'] = '1';//证件类型1-身份证
                            $ciqPost['cert_id'] = $post['cert_id'] = delSpecilaChar($value['buyerIdNumber']);//证件号码
                            $buyerName=delSpecilaChar($value['buyerName']);
                            $ciqPost['name'] = $post['name'] = $buyerName;//姓名
                            $post['return_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/zfOrderNotify');//通知地址
                            $post['business_type'] = '1';//业务类型，1-保税进口，2-直邮进口
                        }
                        $discount=substr(sprintf('%.3f', $discount), 0, -1);
                        $ciqpost['product_amount']=$post['product_amount']-($discount*100);
                        $ciqPost['service_version'] = '1.0';
                        $post['service_version'] = '3.0';
                        $ciqPost['input_charset'] = $post['input_charset'] = 'UTF-8';
                        ksort($post);
                        ksort($ciqPost);
                        $signStr = $ciqSignStr = '';
                        foreach ($post as $k => $v) {
                            $signStr .= $k . '=' . $v . '&';
                        }
                        $signStr = trim($signStr, '&');
                        $merchant_private_key_str=C('merchant_private_key');
                        $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key_str),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";
                        $merchant_private_key= openssl_get_privatekey($merchant_private_key);
                        openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);
                        $post['sign'] = base64_encode($sign_info);
                        $post['sign_type'] = "RSA-S";
                        $postURL = "";
                        foreach ($post as $key=>$value)
                        {
                            $postURL.=$key."=".urlencode($value)."&";
                        }
                        $postURL = substr($postURL,0,-1);
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_URL, 'https://customs.dinpay.com/customDeclare?input_charset=UTF-8');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postURL);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 70);
                        $output = curl_exec($ch);
                        curl_close($ch);
                        if($ciqPost['ciqbcode']=='000069') {
                            foreach ($ciqPost as $k => $v) {
                                $ciqSignStr .= $k . '=' . $v . '&';
                            }
                            $ciqSignStr = trim($ciqSignStr, '&');
                            $merchant_private_key = "-----BEGIN PRIVATE KEY-----" . "\r\n" . wordwrap(trim($merchant_private_key_str), 64, "\r\n", true) . "\r\n" . "-----END PRIVATE KEY-----";
                            $merchant_private_key = openssl_get_privatekey($merchant_private_key);
                            openssl_sign($ciqSignStr, $ciq_sign_info, $merchant_private_key, OPENSSL_ALGO_MD5);
                            $ciqPost['sign'] = base64_encode($ciq_sign_info);
                            $ciqPost['sign_type'] = "RSA-S";
                            $ciqPostURL = "";
                            foreach ($ciqPost as $key => $value) {
                                $ciqPostURL .= $key . "=" . urlencode($value) . "&";
                            }
                            $ciqPostURL = substr($ciqPostURL, 0, -1);
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_URL, 'https://customs.dinpay.com/ciqDeclare');
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $ciqPostURL);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 70);
                            $ciqOutput = curl_exec($ch);
                            curl_close($ch);
                        }else{
                            $ciqOutput='';
                        }
                        preg_match('/<retcode>(.*)<\/retcode>/i', $output, $success);
                        preg_match('/<retcode>(.*)<\/retcode>/i', $ciqOutput, $ciqSuccess);
                        $time=time();
                        $sql="update {$this->db_prex}order set tradeNo='{$trade_no}',tradeInfo='{$trade_status}',tradeSign='{$bank_seq_no}',pay_status=1,pay_time='{$time}',";
                        if ($success[1] !== '0') {
                            preg_match('/<retmsg>(.*)<\/retmsg>/i', $output, $error);
                            $sql.= "payOrderStatus=2,payOrderInfo='{$error[1]}',payOrderTime=now(),";
                        } else {
                            $sql.= "payOrderStatus=1,payOrderInfo='没有实时回执',payOrderTime=now(),";
                        }
                        if ($ciqSuccess[1] !== '0') {
                            preg_match('/<retmsg>(.*)<\/retmsg>/i', $ciqOutput, $ciqError);
                            $sql.= "payCiqOrderStatus=2,payCiqOrderInfo='{$ciqError[1]}',payCiqOrderTime=now(),";
                        } else {
                            $sql.= "payCiqOrderStatus=1,payCiqOrderInfo='没有实时回执，或者不需要推送',payCiqOrderTime=now(),";
                        }
                        $sql=trim($sql,',');
                        $sql.=" where order_sn='{$order_no}'";
                    }else{
                        $time=time();
                        $sql="update {$this->db_prex}order set tradeNo='{$trade_no}',tradeInfo='{$trade_status}',tradeSign='{$bank_seq_no}',pay_status=1,pay_time='{$time}' where order_sn='{$order_no}'";
                    }
                    $sqlArr[]=$sql;
                    $sqlArr[]="insert {$this->db_prex}order_action(order_id,action_user,pay_status,action_note,log_time,status_desc) values({$data[0]['order_id']},{$data[0]['user_id']},1,'您成功支付了订单，支付流水号为：{$trade_no}','{$time}','用户支付完成订单')";
                    if($sqlArr){
                        $ret=true;
                        foreach($sqlArr as $sql){
                            $retA=$model->execute($sql);
                            if($retA || $retA===0){

                            }else {
                                $ret = false;
                            }
                        }
                        if($ret){
                            //支付完成，商品已售的增加-start
                            changeGoodsNum($order_no);
                            //支付完成，商品已售的增加-end
                            echo 'SUCCESS';
                        }else{
                            echo 'failed';
                        }
                    }
                }else{
                    echo 'failed';
                }
            }else{
                $model=MM('Order','DB_CONFIG3');
                $sql="update {$this->db_prex}paylog as pl,{$this->db_prex}order as o set o.pay_status=2,o.tradeInfo='{$trade_status}',pl.reTime=now(),pl.message='{$trade_status}',pl.istatus=2 where o.payOrderNo=pl.orderNo and o.order_sn='{$order_no}'";
                echo $sql;die;
                $model->execute($sql);
                echo 'failed';
            }
        }else{
            echo 'failed';
        }
    }

    //自动上传支付单
    public function syncPayOrderCard(){
        $model=M('order');
        $sql="select o.order_sn,o.paymentType from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where w.warehouse_type=1 and o.deleted=0 and o.pay_status=1 and o.payOrderStatus=0 and o.orderFlowType='SPECIAL' and o.paymentType!='' and o.tradeNo!=''";
        $result=$model->query($sql);
        if($result){
            $count=0;
            foreach($result as $value){
                //$ret=$value['payFunction']($value['order_sn']);
                $payFunction=$value['paymentType'].'PayOrder';
                $ret=$payFunction($value['order_sn']);
                if($ret){
                    $count++;
                }
            }
            echo date('Y-m-d H:i:s').' 成功上传'.$count.'份支付单';
        }else{
            echo date('Y-m-d H:i:s').' 没有需要上传的支付单';
        }
    }
    
    //支付单上传异步回执
    public function OrderNotify(){
        $tradeNo=I('tradeNo')?I('tradeNo'):'';
        $status=I('status')?I('status'):'';
        $orderSn=I('outOrderNo')?trim(I('outOrderNo')):'';
        $payOrderInfo=I('resultMessage')?I('resultMessage'):'';
        $payOrderInfoSucc=I('memo')?I('memo'):'';
        $model=M('Order');
        if($status=='success'){
            if($this->db_prex=='jia_'){
                /*佳源优品特殊处理*/
                $sql="update {$this->db_prex}order set pay_status=1,payOrderStatus=3,payCiqOrderStatus=3,payOrderTime=now(),payCiqOrderTime=now(),cusOrderStatus=3,ciqOrderStatus=3,payOrderInfo='{$payOrderInfoSucc}',payCiqOrderInfo='{$payOrderInfoSucc}' where order_sn='{$orderSn}' and tradeNo='{$tradeNo}'";
            }elseif($this->db_prex=='wj_'){
                /*广州特殊处理*/
                $sql="update {$this->db_prex}order set pay_status=1,payOrderStatus=3,payCiqOrderStatus=3,payOrderTime=now(),payCiqOrderTime=now(),cusOrderStatus=3,ciqOrderStatus=3,payOrderInfo='{$payOrderInfoSucc}',payCiqOrderInfo='{$payOrderInfoSucc}',isSync=1 where order_sn='{$orderSn}' and tradeNo='{$tradeNo}'";
            }else{
                $sql="update {$this->db_prex}order set pay_status=1,payOrderStatus=3,payCiqOrderStatus=3,payOrderTime=now(),payCiqOrderTime=now(),payOrderInfo='{$payOrderInfoSucc}',payCiqOrderInfo='{$payOrderInfoSucc}' where order_sn='{$orderSn}' and tradeNo='{$tradeNo}'";
            }                        
        }else{
            if($payOrderInfo=='支付单上传异常:【实名认证系统验证错误】，业务类型支付单校验不通过,手续费解冻【成功】'){
                $sql="select buyerName,buyerIdNumber from {$this->db_prex} where order_sn='{$orderSn}' and tradeNo='{$tradeNo}'";
                $result=$model->query($sql);
                if($result){
                    setBuyerIdNumber($result[0]['buyerName'],$result[0]['buyerIdNumber']);
                }
            }
            $sql="update {$this->db_prex}order set payOrderStatus=2,payCiqOrderStatus=2,payOrderTime=now(),payCiqOrderTime=now(),payOrderInfo='{$payOrderInfo}',payCiqOrderInfo='{$payOrderInfo}' where order_sn='{$orderSn}' and tradeNo='{$tradeNo}'";
        }
        $ret=$model->execute($sql);
        if($ret){
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付支付单异步回执成功')";
            $model->execute($sql);
            echo 'success';
        }else{
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('易极付','order','order_sn={$orderSn}','易极付支付单异步回执失败')";
            $model->execute($sql);
            echo 'failed';
        }
    }

    //智付支付单异步回执
    public function zfOrderNotify(){
        echo 'SUCCESS';
    }

    //智付支付单回执查询
    public function zfPayOrderQuery(){
        $sql="select o.order_id,o.order_sn,o.tradeNo,o.payOrderStatus,o.payCiqOrderStatus,ps.payid from __PREFIX__order as o left join __PREFIX__pay_shop as ps on ps.code=o.paymentType where o.deleted=0 and o.order_status=0 and o.pay_status=1 and o.paymentType='zhifu' and ( o.payOrderStatus in (1,2) or o.payCiqOrderStatus in (1,2) )";
        $model=M('order');
        $data=$model->query($sql);
        if($data){
            $countSucc=$countErr=0;
            foreach($data as $value){
                $post=$ciqPost=$sqlArr=array();
                $post['merchant_id']=$value['payid'];
                $post['transaction_id']=$value['tradeNo'];
                $post['out_trade_no']=$value['order_sn'];
                $post['sub_trade_no']=$value['order_sn'];
                $post['service_version'] = '1.0';
                $post['input_charset'] = 'UTF-8';
                ksort($post);
                $signStr = '';
                foreach ($post as $k => $v) {
                    $signStr .= $k . '=' . $v . '&';
                }
                $signStr = trim($signStr, '&');
                $merchant_private_key_str=C('merchant_private_key');
                $merchant_private_key = "-----BEGIN PRIVATE KEY-----" . "\r\n" . wordwrap(trim($merchant_private_key_str), 64, "\r\n", true) . "\r\n" . "-----END PRIVATE KEY-----";
                $merchant_private_key = \openssl_get_privatekey($merchant_private_key);
                openssl_sign($signStr, $sign_info, $merchant_private_key, OPENSSL_ALGO_MD5);
                $post['sign'] = base64_encode($sign_info);
                $post['sign_type'] = "RSA-S";
                $postURL = "";
                foreach ($post as $key => $val) {
                    $postURL .= $key . "=" . urlencode($val) . "&";
                }
                $postURL = substr($postURL, 0, -1);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_URL, 'https://customs.dinpay.com/customQuery?input_charset=UTF-8');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postURL);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 70);
                $output = curl_exec($ch);
                curl_close($ch);
                preg_match('/<retcode>(.*)<\/retcode>/i', $output, $success);
                if ($success[1] == '0') {
                    preg_match('/<state>(.*)<\/state>/i', $output, $state);
                    if($state[1]==3 || $state[1]==5){
                        preg_match('/<explanation>(.*)<\/explanation>/i', $output, $explanation);
                        if($this->db_prex=='jia_'){
                            $sql="update {$this->db_prex}order set payOrderStatus=3,payCiqOrderStatus=3,payOrderInfo='{$explanation[1]}',payCiqOrderInfo='{$explanation[1]}',cusOrderStatus=3,ciqOrderStatus=3 where order_id={$value['order_id']}";
                        }elseif($this->db_prex=='wj_'){
                            $sql="update {$this->db_prex}order set payOrderStatus=3,payCiqOrderStatus=3,payOrderInfo='{$explanation[1]}',payCiqOrderInfo='{$explanation[1]}',cusOrderStatus=3,ciqOrderStatus=3,isSync=1 where order_id={$value['order_id']}";
                        }else{
                            $sql="update {$this->db_prex}order set payOrderStatus=3,payCiqOrderStatus=3,payOrderInfo='{$explanation[1]}',payCiqOrderInfo='{$explanation[1]}' where order_id={$value['order_id']}";
                        }
                    }elseif($state[1]==1 || $state[1]==6 || $state[1]==2){
                        $date=date('Y-m-d H:i:s');
                        $sql="update {$this->db_prex}order set payOrderTime='{$date}',payOrderStatus=1,payCiqOrderTime='{$date}',payCiqOrderStatus=1 where order_id={$value['order_id']}";
                    }elseif($state[1]==4){
                        preg_match('/<explanation>(.*)<\/explanation>/i', $output, $explanation);
                        $sql = "update {$this->db_prex}order set payOrderStatus=2,payOrderInfo='{$explanation[1]}',payCiqOrderStatus=2,payCiqOrderInfo='{$explanation[1]}' where order_id={$value['order_id']}";
                    }else{
                        $sql = "update {$this->db_prex}order set payOrderStatus=2,payOrderInfo='未知错误',payCiqOrderStatus=2,payCiqOrderInfo='未知错误' where order_id={$value['order_id']}";
                    }
                }else{
                    preg_match('/<retmsg>(.*)<\/retmsg>/i', $output, $retmsg);
                    $sql="update {$this->db_prex}order set payOrderStatus=2,payOrderInfo='{$retmsg[1]}',payCiqOrderStatus=2,payCiqOrderInfo='{$retmsg[1]}' where order_id={$value['order_id']}";
                }
                $ret=$model->execute($sql);
                if($ret){
                    $countSucc++;
                }else{
                    $countErr++;
                }
            }
            $str="成功查询{$countSucc}条支付单；失败查询{$countErr}条支付单";
            echo date('Y-m-d H:i:s').' '.$str;
        }else{
            echo date('Y-m-d H:i:s').' 没有需要查询的支付单';
        }
    }

    //生成报文
    public function orderMessage(){
        $sql="SELECT o.order_id,o.order_sn,o.cusOrderStatus,o.ciqOrderStatus,o.freight,o.coupon_price,o.taxTotal,o.order_amount,o.buyerRegNo,o.buyerName,o.buyerIdNumber,o.tradeNo,o.consignee,o.mobile,o.address,o.province,o.city,o.admin_note,o.isLoad,o.add_time,og.gNum,og.goods_num,og.member_goods_price,g.goods_name,g.goods_sn,g.shop_price,g.firstMemberPrice,g.secondMemberPrice,g.thirdMemberPrice,g.goodsBarcode,co.code as country,co.name as countryName,g.Hscode,g.brand,g.specType,g.ciqRecordNo,g.cusRecordNo,g.website,g.stockUnit,g.notes as gnotes,g.keywords,u.nickname,u.myLevel,p.ciqOrderCode,p.cusOrderCode,ps.payCusCode,ps.payCompanyName,se.gzeportcode,se.ciqNo,se.customsNo,se.companyName,se.DXPId,se.shopDomain,se.ciqHost,se.ciqPort,se.ciqUser,se.ciqPass,se.ciqUpIn,se.ciqIn,se.ciqOut,se.cusHost,se.cusPort,se.cusUser,se.cusPass,se.cusUpIn,se.cusIn,se.cusOut,sh.apiURL FROM {$this->db_prex}order AS o LEFT JOIN {$this->db_prex}order_goods AS og ON og.order_id=o.order_id LEFT JOIN {$this->db_prex}goods AS g ON g.goods_id=og.goods_id left join {$this->db_prex}country as co on co.id=g.country LEFT JOIN {$this->db_prex}users AS u ON u.user_id=o.user_id left join {$this->db_prex}port as p on p.id=o.portId LEFT JOIN {$this->db_prex}settings AS se ON se.id=p.settingId left join {$this->db_prex}pay_shop as ps on ps.code=o.paymentType left join {$this->db_prex}platform as pf on pf.id=p.platId left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as sh on sh.id=w.apiId WHERE ( o.order_status=1 or o.order_status=0 or o.order_status=2 ) AND w.warehouse_type=1 AND o.deleted=0 AND o.pay_status=1 AND o.payOrderStatus=3 AND o.payCiqOrderStatus=3 AND ( o.cusOrderStatus=0 OR o.ciqOrderStatus=0 ) and sh.isBaoshui=1 and pf.name='sdms'";
        $model=M('Order');
        $datas=$model->query($sql);
        $cusCount=0;
        $ciqCount=0;
        if($datas){
            $sqlArr=$result=$data=array();
            foreach($datas as $key=>$value){
                $data[$value['order_id']]['order_id']=$value['order_id'];
                $data[$value['order_id']]['ciqNo']=$value['ciqNo'];
                $data[$value['order_id']]['order_sn']=$value['order_sn'];
                $data[$value['order_id']]['ciqCode']=$value['ciqOrderCode'];
                $data[$value['order_id']]['customsCode']=$value['cusOrderCode'];
                $data[$value['order_id']]['buyerName']=$value['buyerName'];
                $data[$value['order_id']]['address']=$value['address'];
                $data[$value['order_id']]['buyerIdNumber']=$value['buyerIdNumber'];
                $data[$value['order_id']]['mobile']=$value['mobile'];
                $data[$value['order_id']]['gzeportcode']=$value['gzeportcode'];
                $data[$value['order_id']]['companyName']=$value['companyName'];
                $data[$value['order_id']]['customsNo']=$value['customsNo'];
                $data[$value['order_id']]['DXPId']=$value['DXPId'];
                $data[$value['order_id']]['shopDomain']=$value['shopDomain'];
                $data[$value['order_id']]['ciqHost']=$value['ciqHost'];
                $data[$value['order_id']]['ciqPort']=$value['ciqPort'];
                $data[$value['order_id']]['ciqUser']=$value['ciqUser'];
                $data[$value['order_id']]['ciqPass']=$value['ciqPass'];
                $data[$value['order_id']]['ciqUpIn']=$value['ciqUpIn'];
                $data[$value['order_id']]['ciqIn']=$value['ciqIn'];
                $data[$value['order_id']]['ciqOut']=$value['ciqOut'];
                $data[$value['order_id']]['cusHost']=$value['cusHost'];
                $data[$value['order_id']]['cusPort']=$value['cusPort'];
                $data[$value['order_id']]['cusUser']=$value['cusUser'];
                $data[$value['order_id']]['cusPass']=$value['cusPass'];
                $data[$value['order_id']]['cusUpIn']=$value['cusUpIn'];
                $data[$value['order_id']]['cusIn']=$value['cusIn'];
                $data[$value['order_id']]['cusOut']=$value['cusOut'];
                $data[$value['order_id']]['freight']=$value['freight'];
                $data[$value['order_id']]['coupon_price']=$value['coupon_price'];
                $data[$value['order_id']]['taxTotal']=$value['taxTotal'];
                $data[$value['order_id']]['order_amount']=$value['order_amount'];
                $data[$value['order_id']]['buyerRegNo']=$value['buyerRegNo'];
                $data[$value['order_id']]['payCusCode']=$value['payCusCode'];
                $data[$value['order_id']]['payCompanyName']=$value['payCompanyName'];
                $data[$value['order_id']]['tradeNo']=$value['tradeNo'];
                $data[$value['order_id']]['consignee']=$value['consignee'];
                $data[$value['order_id']]['province']=$value['province'];
                $data[$value['order_id']]['city']=$value['city'];
                $data[$value['order_id']]['notes']=$value['admin_note'];
                $data[$value['order_id']]['cusOrderStatus']=$value['cusOrderStatus'];
                $data[$value['order_id']]['ciqOrderStatus']=$value['ciqOrderStatus'];
                $data[$value['order_id']]['nickname']=$value['nickname'];
                $data[$value['order_id']]['myLevel']=$value['myLevel'];
                $data[$value['order_id']]['isLoad']=$value['isLoad'];
                $data[$value['order_id']]['add_time']=$value['add_time'];
                $data[$value['order_id']]['goods'][$key]['gNum']=$value['gNum'];
                $data[$value['order_id']]['goods'][$key]['goods_sn']=$value['goods_sn'];
                $data[$value['order_id']]['goods'][$key]['Hscode']=$value['Hscode'];
                $data[$value['order_id']]['goods'][$key]['goods_name']=$value['goods_name'];
                $data[$value['order_id']]['goods'][$key]['ciqRecordNo']=$value['ciqRecordNo'];
                $data[$value['order_id']]['goods'][$key]['cusRecordNo']=$value['cusRecordNo'];
                $data[$value['order_id']]['goods'][$key]['brand']=$value['brand'];
                $data[$value['order_id']]['goods'][$key]['specType']=$value['specType'];
                $data[$value['order_id']]['goods'][$key]['country']=$value['country'];
                $data[$value['order_id']]['goods'][$key]['countryName']=$value['countryName'];
                $data[$value['order_id']]['goods'][$key]['goods_num']=$value['goods_num'];
                $data[$value['order_id']]['goods'][$key]['stockUnit']=$value['stockUnit'];
                $data[$value['order_id']]['goods'][$key]['shop_price']=$value['shop_price'];
                $data[$value['order_id']]['goods'][$key]['member_goods_price']=$value['member_goods_price'];
                $data[$value['order_id']]['goods'][$key]['firstMemberPrice']=$value['firstMemberPrice'];
                $data[$value['order_id']]['goods'][$key]['secondMemberPrice']=$value['secondMemberPrice'];
                $data[$value['order_id']]['goods'][$key]['thirdMemberPrice']=$value['thirdMemberPrice'];
                $data[$value['order_id']]['goods'][$key]['goodsBarcode']=$value['goodsBarcode'];
                $data[$value['order_id']]['goods'][$key]['website']=$value['website'];
                $data[$value['order_id']]['goods'][$key]['gnotes']=$value['gnotes'];
                $data[$value['order_id']]['goods'][$key]['keywords']=$value['keywords'];
            }
            $result=array_values($data);
            foreach($result as $key=>$value){
                if($value['cusOrderStatus']==0 && $value['cusHost']) {
                    $str = $batchId = $goodsInfo = '';
                    $messageId = 'CEB311_' . $value['customsNo'] . '_' . date('YmdHis') . str_pad($key, 4, '0', STR_PAD_LEFT);
                    $filename = $this->xmlPath . '/'.$value['cusUpIn'].'/' . $messageId . '.xml';
                    $filename1 = $this->xmlPath . '/'.$value['cusUpIn'].'/' . $messageId . '1.xml';
                    $price = $goodsValue = $acturalPaid = $totalPrice = 0.00;
                    foreach ($value['goods'] as $k => $val) {
                        $goodsInfo .= '<ceb:OrderList>';
                        $goodsInfo .= '<ceb:gnum>' . $val['gNum'] . '</ceb:gnum>';
                        $goodsInfo .= '<ceb:itemNo>' . $val['goods_sn'] . '</ceb:itemNo>';
                        $goodsInfo .= '<ceb:itemName>' . $val['goods_name'] . '</ceb:itemName>';
                        $goodsInfo .= '<ceb:itemDescribe>' . $val['gnotes'] . '</ceb:itemDescribe>';
                        $goodsInfo .= '<ceb:barCode>' . $val['goodsBarcode'] . '</ceb:barCode>';
                        $goodsInfo .= '<ceb:unit>' . $val['stockUnit'] . '</ceb:unit>';
                        $goodsInfo .= '<ceb:qty>' . $val['goods_num'] . '</ceb:qty>';
                        $goodsInfo .= '<ceb:price>' . $val['member_goods_price'] . '</ceb:price>';
                        $goodsValue += $totalPrice = $val['goods_num'] * $val['member_goods_price'];
                        $goodsInfo .= '<ceb:totalPrice>' . $totalPrice . '</ceb:totalPrice>';
                        $goodsInfo .= '<ceb:currency>142</ceb:currency>';
                        $goodsInfo .= '<ceb:country>' . $val['country'] . '</ceb:country>';
                        $goodsInfo .= '<ceb:note></ceb:note>';
                        $goodsInfo .= '</ceb:OrderList>';
                    }
                    $str .= '<?xml version="1.0" encoding="UTF-8"?>';
                    $str .= '<ceb:CEB311Message guid="' . $messageId . '" version="1.0"  xmlns:ceb="http://www.chinaport.gov.cn/ceb" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
                    $str .= '<ceb:Order>';
                    $str .= '<ceb:OrderHead>';
                    $str .= '<ceb:guid>' . $messageId . '</ceb:guid>';
                    $str .= '<ceb:appType>1</ceb:appType>';
                    $str .= '<ceb:appTime>' . date('YmdHis') . '</ceb:appTime>';
                    $str .= '<ceb:appStatus>2</ceb:appStatus>';
                    $str .= '<ceb:orderType>I</ceb:orderType>';
                    $str .= '<ceb:orderNo>' . delSpecilaChar($value['order_sn']) . '</ceb:orderNo>';
                    $str .= '<ceb:ebpCode>' . $value['customsNo'] . '</ceb:ebpCode>';
                    $str .= '<ceb:ebpName>' . $value['companyName'] . '</ceb:ebpName>';
                    $str .= '<ceb:ebcCode>' . $value['customsNo'] . '</ceb:ebcCode>';
                    $str .= '<ceb:ebcName>' . $value['companyName'] . '</ceb:ebcName>';
                    $str .= '<ceb:goodsValue>' . $goodsValue . '</ceb:goodsValue>';
                    $str .= '<ceb:freight>' . $value['freight'] . '</ceb:freight>';
                    $str .= '<ceb:discount>' . $value['coupon_price'] . '</ceb:discount>';
                    $str .= '<ceb:taxTotal>' . $value['taxTotal'] . '</ceb:taxTotal>';
                    $acturalPaid = $goodsValue + $value['freight'] - $value['coupon_price'] + $value['taxTotal'];
                    $str .= '<ceb:acturalPaid>' . $acturalPaid . '</ceb:acturalPaid>';
                    $str .= '<ceb:currency>142</ceb:currency>';
                    $str .= '<ceb:buyerRegNo>' . $value['buyerRegNo'] . '</ceb:buyerRegNo>';

                    $str .= '<ceb:buyerName>' . delSpecilaChar($value['buyerName']) . '</ceb:buyerName>';

                    $str .= '<ceb:buyerIdType>1</ceb:buyerIdType>';
                    $str .= '<ceb:buyerIdNumber>' . delSpecilaChar($value['buyerIdNumber']) . '</ceb:buyerIdNumber>';
                    $str .= '<ceb:payCode>' . $value['payCusCode'] . '</ceb:payCode>';
                    $str .= '<ceb:payName>' . $value['payCompanyName'] . '</ceb:payName>';
                    $str .= '<ceb:payTransactionId>' . $value['tradeNo'] . '</ceb:payTransactionId>';
                    /*$stockArr=  json_decode($value['stock'],true);
                    $batchId=$stockArr[0]['id'];*/
                    $batchId = rand(1, 1000);
                    $str .= '<ceb:batchNumbers>' . $batchId . '</ceb:batchNumbers>';
                    $str .= '<ceb:consignee>' . delSpecilaChar($value['consignee']) . '</ceb:consignee>';
                    $str .= '<ceb:consigneeTelephone>' . delSpecilaChar($value['mobile']) . '</ceb:consigneeTelephone>';
                    $str .= '<ceb:consigneeAddress>' . delSpecilaChar($value['address']) . '</ceb:consigneeAddress>';
                    $sql = "SELECT city FROM {$this->db_prex}AREA WHERE provinceName=(SELECT `name` FROM {$this->db_prex}region WHERE id={$value['province']}) AND cityName=(SELECT `name` FROM {$this->db_prex}region WHERE id={$value['city']}) LIMIT 1";
                    $districtArr = $model->query($sql);
                    $str .= '<ceb:consigneeDistrict>' . $districtArr[0]['city'] . '</ceb:consigneeDistrict>';
                    $str .= '<ceb:note>' . $value['notes'] . '</ceb:note>';
                    $str .= '</ceb:OrderHead>';
                    $str .= $goodsInfo;
                    $str .= '</ceb:Order>';
                    $str .= '<ceb:BaseTransfer>';
                    $str .= '<ceb:copCode>' . $value['customsNo'] . '</ceb:copCode>';
                    $str .= '<ceb:copName>' . $value['companyName'] . '</ceb:copName>';
                    $str .= '<ceb:dxpMode>DXP</ceb:dxpMode>';
                    $str .= '<ceb:dxpId>' . $value['DXPId'] . '</ceb:dxpId>';
                    $str .= '<ceb:note></ceb:note>';
                    $str .= '</ceb:BaseTransfer>';
                    $str .= '</ceb:CEB311Message>';
                    $ret = file_put_contents($filename, $str);
                    $ret = file_put_contents($filename1, $str);

                    if($ret){
                        $sqlArr[]="update {$this->db_prex}order set cusOrderStatus=1,cusOrderMessageId='{$messageId}',cusOrderSendTime=now() where order_id=".$value['order_id'];                        
                        $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_id={$value['order_id']}','自动申报海关订单')";                
                    }
                    $cusCount++;
                }
                if($value['ciqOrderStatus']==0 && $value['ciqHost']){
                    $str=$goodsInfo='';
                    $goodsValue=0;
                    $messageId='661101_'.date('YmdHis').str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT).str_pad($key, 5, '0', STR_PAD_LEFT);
                    $filename=$this->xmlPath.'/'.$value['ciqUpIn'].'/'.$messageId.'.xml';
                    foreach($value['goods'] as $val){
                        $totalValue=0;
                        $goodsInfo.='<Record>';
                        $goodsInfo.='<EntGoodsNo>'.$val['gNum'].'</EntGoodsNo>';
                        $goodsInfo.='<Gcode>'.$val['goods_sn'].'</Gcode>';
                        $goodsInfo.='<Hscode>'.$val['Hscode'].'</Hscode>';
                        $goodsInfo.='<CiqGoodsNo>'.$val['ciqRecordNo'].'</CiqGoodsNo>';
                        $goodsInfo.='<CopGName>'.$val['goods_name'].'</CopGName>';
                        $goodsInfo.='<Brand>'.$val['brand'].'</Brand>';
                        $goodsInfo.='<Spec>'.$val['specType'].'</Spec>';
                        $goodsInfo.='<Origin>'.$val['countryName'].'</Origin>';
                        $goodsInfo.='<Qty>'.$val['goods_num'].'</Qty>';
                        $goodsInfo.='<QtyUnit>'.$val['stockUnit'].'</QtyUnit>';
                        $goodsInfo.='<DecPrice>'.$val['member_goods_price'].'</DecPrice>';
                        $totalValue=$val['goods_num']*$val['member_goods_price'];
                        $goodsValue+=$totalValue;
                        $goodsInfo.='<DecTotal>'.$totalValue.'</DecTotal>';
                        $goodsInfo.='<SellWebSite>'.$val['website'].'</SellWebSite>';
                        $goodsInfo.='<Nots></Nots>';
                        $goodsInfo.='</Record>';                        
                    }
                    $str.='<?xml version="1.0" encoding="utf-8"?><ROOT><Head>';
                    $str.='<MessageID>'.$messageId.'</MessageID>';
                    $str.='<FunctionCode/>';
                    $str.='<MessageType>661101</MessageType>';
                    $str.='<Sender>'.$value['ciqNo'].'</Sender>';
                    $str.='<Receiver>ICIP</Receiver>';
                    $str.='<SendTime>'.date('YmdHis').'</SendTime>';
                    $str.='<Version>1.0</Version>';
                    $str.='</Head><Body><swbebtrade><Record>';
                    $str.='<EntInsideNo>'.delSpecilaChar($value['order_sn']).'</EntInsideNo>';
                    $str.='<Ciqbcode>'.$value['ciqCode'].'</Ciqbcode>';
                    $str.='<CbeComcode>'.$value['ciqNo'].'</CbeComcode>';
                    $str.='<CbepComcode>'.$value['ciqNo'].'</CbepComcode>';
                    $str.='<OrderStatus>1</OrderStatus>';                   
                  
                    $str.='<ReceiveName>'.delSpecilaChar($value['buyerName']).'</ReceiveName>';
                    
                    $str.='<ReceiveAddr>'.delSpecilaChar($value['address']).'</ReceiveAddr>';
                    $str.='<ReceiveNo>'.delSpecilaChar($value['buyerIdNumber']).'</ReceiveNo>';
                    $str.='<ReceivePhone>'.delSpecilaChar($value['mobile']).'</ReceivePhone>';
                    $str.='<FCY>'.$goodsValue.'</FCY>';
                    $str.='<Fcode>CNY</Fcode>';
                    $str.='<Editccode>'.$value['ciqNo'].'</Editccode>';
                    $str.='<DrDate>'.date('Ymd').'</DrDate>';
                    $str.='<swbebtradeg>';
                    $str.=$goodsInfo;
                    $str.='</swbebtradeg></Record></swbebtrade></Body></ROOT>';
                    $ret=file_put_contents($filename,$str);
                    if($ret){
                        $sqlArr[]="update {$this->db_prex}order set ciqOrderStatus=1,ciqOrderMessageId='{$messageId}',ciqOrderSendTime=now() where order_id={$value['order_id']}";                        
                        $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_id={$value['order_id']}','自动申报国检订单')";
                    }
                    $ciqCount++;
                }
            }
            if($sqlArr){
                $ret=true;
                $model->startTrans();
                try{
                    foreach($sqlArr as $sql){
                        if(!$model->execute($sql)){
                            $ret=false;
                        }
                    }
                }catch (Exception $e){
                    $model->rollback();
                    echo date("Y-m-d H:i:s").' '.$e->getMessage().': '.$e->getLine();
                    exit(0);
                }
                if($ret){
                    $model->commit();
                    echo date('Y-m-d H:i:s').' 生成'.$cusCount.'份海关订单报文；生成'.$ciqCount.'份国检订单报文';
                }else{
                    $model->rollback();
                    echo date('Y-m-d H:i:s').' 执行sql失败：'.$sql;
                }
            }
        }else{
            echo date('Y-m-d H:i:s').' 没有需要生成报文的订单';
        }
    }

    //上传海关报文
    public function upCusXml(){
        $sql="select se.ciqHost,se.ciqPort,se.ciqUser,se.ciqPass,se.ciqUpIn,se.ciqIn,se.ciqOut,se.cusHost,se.cusPort,se.cusUser,se.cusPass,se.cusUpIn,se.cusIn,se.cusOut from {$this->db_prex}port as p left join {$this->db_prex}settings as se on se.id=p.settingId left join {$this->db_prex}platform as pf on pf.id=p.platId where pf.name='sdms'";
        $result=M('port')->query($sql);
        if(!$result){
            echo date('Y-m-d H:i:s').' 上传报文，未查询到海关ftp信息';exit(0);
        }
        if($result[0]['cusHost']) {
            $ftpCusArr = array(
                'hostname' => $result[0]['cusHost'],
                'username' => $result[0]['cusUser'],
                'password' => $result[0]['cusPass'],
                'port' => $result[0]['cusPort']
            );
            //$conn=new Ftp(C('ftpCusArr'));
            $conn = new Ftp($ftpCusArr);
            if ($conn->connect()) {
                $Localpath = C('xml_path') . '/'.$result[0]['cusUpIn'].'/';
                $Movepath = C('xml_path') . '/back/cus/' . date('Ymd') . '/';
                if (!is_dir($Movepath)) {
                    $ret = mkdir($Movepath);
                }
                if (is_dir($Localpath)) {
                    if ($conn->chgdir($result[0]['cusIn'])) {
                        $fileArr = array();
                        $fileArr = scandir($Localpath);
                        $count = 0;
                        foreach ($fileArr as $key => $value) {
                            if ($value != '.' && $value != '..') {
                                $ret = $conn->upload($Localpath . $value, $value);
                                if ($ret) {
                                    $count++;
                                    rename($Localpath . $value, $Movepath . $value);
                                }
                            }
                        }
                        echo date('Y-m-d H:i:s') . ' 成功上传' . $count . '个海关订单报文';
                        exit(0);
                    } else {
                        echo date('Y-m-d H:i:s') . ' 设置ftp目录失败';
                        exit(0);
                    }
                } else {
                    echo date('Y-m-d H:i:s') . ' 文件目录异常';
                    exit(0);
                }
            } else {
                echo date('Y-m-d H:i:s') . ' 连接ftp失败';
                exit(0);
            }
        }else{
            echo date('Y-m-d H:i:s') . ' 海关ftp信息为空';
            exit(0);
        }
    }
    
    //上传国检报文
    public function upCiqXml(){
        $sql="select se.ciqHost,se.ciqPort,se.ciqUser,se.ciqPass,se.ciqUpIn,se.ciqIn,se.ciqOut,se.cusHost,se.cusPort,se.cusUser,se.cusPass,se.cusUpIn,se.cusIn,se.cusOut from {$this->db_prex}port as p left join {$this->db_prex}settings as se on se.id=p.settingId left join {$this->db_prex}platform as pf on pf.id=p.platId where pf.name='sdms'";
        $result=M('port')->query($sql);
        if(!$result){
            echo date('Y-m-d H:i:s').' 上传报文，未查询到国检ftp信息';exit(0);
        }
        if($result[0]['ciqHost']) {
            $ftpCiqArr = array(
                'hostname' => $result[0]['ciqHost'],
                'username' => $result[0]['ciqUser'],
                'password' => $result[0]['ciqPass'],
                'port' => $result[0]['ciqPort']
            );
            //$conn=new Ftp(C('ftpCiqArr'));
            $conn = new Ftp($ftpCiqArr);
            if ($conn->connect()) {
                $Localpath = C('xml_path') . '/'.$result[0]['ciqUpIn'].'/';
                $Movepath = C('xml_path') . '/back/ciq/' . date('Ymd') . '/';
                if (!is_dir($Movepath)) {
                    $ret = mkdir($Movepath);
                }
                if (is_dir($Localpath)) {
                    if ($conn->chgdir($result[0]['ciqIn'])) {
                        $fileArr = array();
                        $fileArr = scandir($Localpath);
                        $count = 0;
                        foreach ($fileArr as $key => $value) {
                            if ($value != '.' && $value != '..') {
                                $ret = $conn->upload($Localpath . $value, $value);
                                if ($ret) {
                                    $count++;
                                    rename($Localpath . $value, $Movepath . $value);
                                }
                            }
                        }
                        echo date('Y-m-d H:i:s') . ' 成功上传' . $count . '个国检订单报文';
                        exit(0);
                    } else {
                        echo date('Y-m-d H:i:s') . ' 设置ftp目录失败';
                        exit(0);
                    }
                } else {
                    echo date('Y-m-d H:i:s') . ' 文件目录异常';
                    exit(0);
                }
            } else {
                echo date('Y-m-d H:i:s') . ' 连接ftp失败';
                exit(0);
            }
        }else{
            echo date('Y-m-d H:i:s') . ' 国检ftp信息为空';
            exit(0);
        }
    }
    
    //下载海关报文
    public function downCusXml(){
        $sql="select se.ciqHost,se.ciqPort,se.ciqUser,se.ciqPass,se.ciqUpOut,se.ciqIn,se.ciqOut,se.cusHost,se.cusPort,se.cusUser,se.cusPass,se.cusUpOut,se.cusIn,se.cusOut from {$this->db_prex}port as p left join {$this->db_prex}settings as se on se.id=p.settingId left join {$this->db_prex}platform as pf on pf.id=p.platId where pf.name='sdms'";
        $result=M('port')->query($sql);
        if(!$result){
            echo date('Y-m-d H:i:s').' 下载报文，未查询到海关ftp信息';exit(0);
        }
        $ftpCusArr=array(
            'hostname'=>$result[0]['cusHost'],
            'username'=>$result[0]['cusUser'],
            'password'=>$result[0]['cusPass'],
            'port'=>$result[0]['cusPort']
        );
        //$conn=new Ftp(C('ftpCusArr'));
        $conn=new Ftp($ftpCusArr);
        if($conn->connect()){
            $Localpath=C('xml_path').'/'.$result[0]['cusUpOut'].'/cus/';
            if($conn->chgdir($result[0]['cusOut'])){
                $fileList=$conn->filelist();
                $count=0;
                foreach($fileList as $val){
                    $ret=$conn->download($val,$Localpath.$val);
                    if($ret){
                        $count++;
                        $conn->delete_file($val);
                    }
                }
                echo date('Y-m-d H:i:s').' 成功下载'.$count.'个海关订单报文回执';exit(0);
            }else{
                echo date('Y-m-d H:i:s').' 设置ftp目录失败';exit(0);
            }
        }else{
            echo date('Y-m-d H:i:s').' 连接ftp失败';exit(0);
        }
    }
    
    //下载国检报文
    public function downCiqXml(){
        $sql="select se.ciqHost,se.ciqPort,se.ciqUser,se.ciqPass,se.ciqUpOut,se.ciqIn,se.ciqOut,se.cusHost,se.cusPort,se.cusUser,se.cusPass,se.cusUpOut,se.cusIn,se.cusOut from {$this->db_prex}port as p left join {$this->db_prex}settings as se on se.id=p.settingId left join {$this->db_prex}platform as pf on pf.id=p.platId where pf.name='sdms'";
        $result=M('port')->query($sql);
        if(!$result){
            echo date('Y-m-d H:i:s').' 下载报文，未查询到国检ftp信息';exit(0);
        }
        $ftpCiqArr=array(
            'hostname'=>$result[0]['ciqHost'],
            'username'=>$result[0]['ciqUser'],
            'password'=>$result[0]['ciqPass'],
            'port'=>$result[0]['ciqPort']
        );
        //$conn=new Ftp(C('ftpCiqArr'));
        $conn=new Ftp($ftpCiqArr);
        if($conn->connect()){
            $Localpath=C('xml_path').'/'.$result[0]['ciqUpOut'].'/ciq/';
            if($conn->chgdir($result[0]['ciqOut'])){
                $fileList=$conn->filelist();
                $count=0;
                foreach($fileList as $val){
                    $ret=$conn->download($val,$Localpath.$val);
                    if($ret){
                        $count++;
                        $conn->delete_file($val);
                    }
                }
                echo date('Y-m-d H:i:s').' 成功下载'.$count.'个国检订单报文回执';exit(0);
            }else{
                echo date('Y-m-d H:i:s').' 设置ftp目录失败';exit(0);
            }
        }else{
            echo date('Y-m-d H:i:s').' 连接ftp失败';exit(0);
        }
    }
    
    //ajax 请求购物车列表
    public function header_cart_list()
    {
        $this->cartLogic = new CartLogic();
    	$cart_result = $this->cartLogic->cartList($this->user, $this->session_id,0,1);
    	if(empty($cart_result['total_price']))
    		$cart_result['total_price'] = Array( 'total_fee' =>0, 'cut_fee' =>0, 'num' => 0);
    	$this->assign('cartList', $cart_result['cartList']); // 购物车的商品
    	$this->assign('cart_total_price', $cart_result['total_price']); // 总计
        $template = I('template','header_cart_list');    	 
        return $this->fetch($template);		 
    }

    public function map(){
        $model = M('map');
        $sql = "select map_id,parent_id,name,url from {$this->db_prex}map where parent_id=0";
        $sql2 = "select map_id,parent_id,name,url from {$this->db_prex}map";
        $parentList = $model->query($sql);
        $chlidList = $model->query($sql2);
        $this->assign('parentList',$parentList);
        $this->assign('chlidList',$chlidList);
        return $this->fetch('Public:map');
    }
    
    //一键同步订单sdms
    public function syncOrder(){
        $model=M('order');
        $sql="select s.warehouseCode,s.shopCode,s.shopSercet,s.apiURL,o.order_id,o.order_sn,o.consignee,o.address,o.mobile,o.buyerRegNo,o.buyerName,o.buyerIdNumber,o.goods_price,o.coupon_price,o.isLoad,o.integral_money,o.taxTotal,o.order_amount,o.add_time,o.pay_time,o.user_note,o.city,o.district,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price as ogoods_price,og.member_goods_price,r.name as province from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}region as r on r.id=o.province left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as s on s.id=w.apiId left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}platform as pf on pf.id=p.platId where o.order_status=0 and w.warehouse_type=1 and o.deleted=0 and o.pay_status=1 and o.payOrderStatus=3 and o.cusOrderStatus=3 and o.ciqOrderStatus=3 and o.isSync=0 and s.isBaoshui=1 and pf.name='sdms' order by o.order_id desc,og.goods_sn asc";
        $datas=$model->query($sql);
        $sqlArr=$back=$data=$goodsInfo=array();
        $result='';
        if($datas){
            foreach($datas as $key=>$value){
                $data[$value['order_id']]['wareCode']=$value['warehouseCode'];
                $data[$value['order_id']]['shopCode']=$value['shopCode'];
                $data[$value['order_id']]['shopSercet']=$value['shopSercet'];
                $data[$value['order_id']]['orderSn']=$value['order_sn'];
                $data[$value['order_id']]['appType']=1;
                $data[$value['order_id']]['appStatus']=1;
                $data[$value['order_id']]['consignee']=delSpecilaChar($value['consignee']);
                $data[$value['order_id']]['province']=$value['province'];
                $sql="select name as city from {$this->db_prex}region where id={$value['city']}";   
                $cityArr=$model->query($sql);
                $data[$value['order_id']]['city']=$cityArr[0]['city'];
                $sql="select name as district from {$this->db_prex}region where id={$value['district']}";   
                $districtArr=$model->query($sql);
                $data[$value['order_id']]['district']=$districtArr[0]['district'];
                $data[$value['order_id']]['address']=delSpecilaChar($value['address']);
                $data[$value['order_id']]['mobile']=delSpecilaChar($value['mobile']);
                $data[$value['order_id']]['buyerRegNo']=$value['buyerRegNo'];
                
                $data[$value['order_id']]['buyerName']=delSpecilaChar($value['buyerName']);
                
                $data[$value['order_id']]['buyerIdType']=1;
                $data[$value['order_id']]['buyerIdNumber']=delSpecilaChar($value['buyerIdNumber']);
                $data[$value['order_id']]['freight']=0;
                $data[$value['order_id']]['insuredFee']=0;
                $data[$value['order_id']]['taxTotal']=$value['taxTotal'];
                $data[$value['order_id']]['discount']=$value['coupon_price']+$value['integral_money'];
                $data[$value['order_id']]['goodsValue']+=$value['goods_num']*$value['member_goods_price'];
                $data[$value['order_id']]['acturalPaid']=$value['order_amount'];
                $orderDate=date('Y-m-d H:i:s',$value['add_time']);
                $data[$value['order_id']]['orderdate']=$orderDate;
                $time=time();
                if($value['pay_time'] && $value['pay_time']<$time){
                    $payDate=date('Y-m-d H:i:s',$value['pay_time']);
                    $data[$value['order_id']]['paydate']=$payDate;
                }else{
                    $data[$value['order_id']]['paydate']=$orderDate;
                }
                $data[$value['order_id']]['info']=$value['user_note'];
                $goodsInfo[$value['order_id']][$key]['gNum']=$value['gNum'];
                $goodsInfo[$value['order_id']][$key]['goodsName']=delSpecilaChar($value['goods_name']);
                $goodsInfo[$value['order_id']][$key]['goodsCode']=$value['goods_sn'];
                $goodsInfo[$value['order_id']][$key]['qty']=$value['goods_num'];
                $goodsInfo[$value['order_id']][$key]['price']=$value['member_goods_price'];                
            }
            foreach($data as $key=>$value){
                $data[$key]['goods']=array_values($goodsInfo[$key]);
            }
            $result['orders']=json_encode(array_values($data));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_URL,'http://a.gdyyb.com/Home/Customs/orderImport');
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->log.'/cookie.txt');  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $result);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); 
            curl_setopt($ch, CURLOPT_TIMEOUT,70);
            $response = curl_exec($ch);
            curl_close($ch);
            $ret=  json_decode($response,true);
            if($ret['retCode']==0){
                $success=0;
                $error='';
                if($ret['success']){
                    foreach($ret['success'] as $key=>$value){
                        $sqlArr[]="update {$this->db_prex}order set isSync=1 where order_sn='{$value['orderSn']}'";
                        $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_sn={$value['orderSn']}','自动同步订单成功')";
                        $success++;
                    }
                }
                if($ret['error']){
                    foreach($ret['error'] as $key=>$value){
                        if($value['retCode']==400){
                            $sqlArr[]="update {$this->db_prex}order set isSync=2,syncInfo='{$value['retMessage']}' where order_sn='{$value['orderSn']}'";
                            $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_sn={$value['orderSn']}','自动同步订单失败')";
                            $error.=$value['orderSn']."同步失败\r\n";
                        }elseif($value['retCode']==300){
                            $sqlArr[]="update {$this->db_prex}order set isSync=3,syncInfo='{$value['retMessage']}' where order_sn='{$value['orderSn']}'";
                            $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_sn={$value['orderSn']}','自动同步订单异常')";
                            $error.=$value['orderSn']."同步异常\r\n";
                        }
                    }
                }
                if($sqlArr){
                    foreach($sqlArr as $sql){
                        $model->execute($sql);
                    }
                }
                if($error && $success){
                    echo date('Y-m-d H:i:s')." 成功同步".$success."条订单\r\n".$error;
                }elseif($error){
                    echo date('Y-m-d H:i:s')." 成功同步".$success."条订单";
                }elseif($success){
                    echo date('Y-m-d H:i:s')." 成功同步".$success."条订单";
                }else{
                    echo date('Y-m-d H:i:s')." 未知返回";
                }
            }else{
                echo date('Y-m-d H:i:s').' 同步数据出错，请联系接口开发';
            }
        }else{
            echo  date('Y-m-d H:i:s')." 没有需要同步的订单";
        }
    }
    
    //同步订单重庆西永
    public function syncOrderToChongQing(){
        $model=M('order');
        $sql="select p.interfaceUrl,p.appKey,p.customerId,o.order_id,o.order_sn,o.add_time,o.user_note,o.address,o.consignee,o.buyerRegNo,o.buyerName,o.buyerIdNumber,o.mobile,o.freight,o.taxTotal,o.order_amount,o.tradeNo,og.gNum,og.goods_sn,og.goods_name,og.goods_num,og.member_goods_price,g.tax,ps.payCompanyName,ps.code from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}goods as g on g.goods_id=og.goods_id left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}pay_shop as ps on ps.code=o.paymentType left join {$this->db_prex}platform as pf on pf.id=p.platId LEFT JOIN {$this->db_prex}warehouse AS w ON w.warehouse_id=o.wareId LEFT JOIN {$this->db_prex}shop AS s ON s.id=w.apiId where o.order_status=0 and w.warehouse_type=1 and o.pay_status=1 and o.payOrderStatus=3 and o.payCiqOrderStatus=3 and o.isSync=0 and s.isBaoshui=1 and pf.name='重庆西永' order by o.order_id desc,og.gNum asc";
        $datas=$model->query($sql);
        $data=$goodsInfo=array();
        if($datas){
            foreach($datas as $value){
                $data[$value['order_id']]['order_id']=$value['order_id'];
                $data[$value['order_id']]['CustomerId']=$value['customerId'];
                $data[$value['order_id']]['appKey']=$value['appKey'];
                $data[$value['order_id']]['interfaceUrl']=$value['interfaceUrl'];
                $data[$value['order_id']]['UserOrderId']=$value['order_sn'];
                $data[$value['order_id']]['OrderTime']=date('Y-m-d',$value['add_time']);
                if($value['user_note']){
                    $data[$value['order_id']]['OrderRemark']=$value['user_note'];
                }
                $data[$value['order_id']]['address']=$value['address'];
                $data[$value['order_id']]['Name']=$value['consignee'];
                $data[$value['order_id']]['CheckUserName']=$value['buyerName'];
                $data[$value['order_id']]['IdentityCardNO']=$value['buyerIdNumber'];
                $data[$value['order_id']]['CellPhone']=$value['mobile'];
                $data[$value['order_id']]['PayMentRegisterId']=$value['buyerRegNo'];
                $data[$value['order_id']]['Freight']=$value['freight'];
                $data[$value['order_id']]['OrderCessTotal']=$value['taxTotal'];
                $data[$value['order_id']]['OrderTotal']=$value['order_amount'];
                if($value['code']=='yiji'){
                    $data[$value['order_id']]['PaymentComCode']='YJF';
                }else{
                    $data[$value['order_id']]['PaymentComCode']=$value['code'];
                }
                $data[$value['order_id']]['PaymentComName']=$value['payCompanyName'];
                $data[$value['order_id']]['PaymentNO']=$value['tradeNo'];
                $data[$value['order_id']]['goods'][$value['gNum']]['gNum']=$value['gNum'];
                $data[$value['order_id']]['goods'][$value['gNum']]['SKU']=$value['goods_sn'];
                $data[$value['order_id']]['goods'][$value['gNum']]['SKUName']=$value['goods_name'];
                $data[$value['order_id']]['goods'][$value['gNum']]['Quantity']=$value['goods_num'];
                $data[$value['order_id']]['goods'][$value['gNum']]['Price']=$value['member_goods_price'];
                $data[$value['order_id']]['goods'][$value['gNum']]['SKUTotal']=$value['goods_num']*$value['member_goods_price'];
                $data[$value['order_id']]['goods'][$value['gNum']]['SKUCess']=$value['goods_num']*$value['member_goods_price']*$value['tax'];
            }
            if($data){
                $count=0;
                foreach($data as $value){
                    $str='<Order><Header>';
                    $str.='<CustomerId>'.$value['CustomerId'].'</CustomerId>';
                    $str.='</Header><OrderBody>';
                    $str.='<UserOrderId>'.$value['UserOrderId'].'</UserOrderId>';
                    $str.='<OrderTime>'.$value['OrderTime'].'</OrderTime>';
                    if($value['OrderRemark']){
                        $str.='<OrderRemark>'.$value['OrderRemark'].'</OrderRemark>';
                    }else{
                        $str.='<OrderRemark></OrderRemark>';
                    }
                    $str.='<address>'.$value['address'].'</address>';
                    $str.='<Province></Province>';
                    $str.='<City></City>';
                    $str.='<County></County>';
                    $str.='<AddressLine></AddressLine>';
                    $str.='<IESType></IESType>';
                    $str.='<ZipCode></ZipCode>';
                    $str.='<Name>'.$value['Name'].'</Name>';
                    $str.='<CheckUserName>'.$value['CheckUserName'].'</CheckUserName>';
                    $str.='<IdentityCardNO>'.$value['IdentityCardNO'].'</IdentityCardNO>';
                    $str.='<CellPhone>'.$value['CellPhone'].'</CellPhone>';
                    $str.='<TelPhone></TelPhone>';
                    $str.='<PayMentRegisterId>'.$value['PayMentRegisterId'].'</PayMentRegisterId>';
                    $str.='<SKUTotal></SKUTotal>';
                    $str.='<Freight>'.$value['Freight'].'</Freight>';
                    $str.='<OrderCessTotal>'.$value['OrderCessTotal'].'</OrderCessTotal>';
                    $str.='<OrderTotal>'.$value['OrderTotal'].'</OrderTotal>';
                    $str.='<PayForStatus>1</PayForStatus>';
                    $str.='<PaymentComCode>'.$value['PaymentComCode'].'</PaymentComCode>';
                    $str.='<PaymentComName>'.$value['PaymentComName'].'</PaymentComName>';
                    $str.='<PaymentNO>'.$value['PaymentNO'].'</PaymentNO>';
                    $str.='</OrderBody><ProductItems>';
                    foreach($value['goods'] as $val){
                        $str.='<Product>';
                        $str.='<SKU>'.$val['SKU'].'</SKU>';
                        $str.='<SKUName>'.$val['SKUName'].'</SKUName>';
                        $str.='<Quantity>'.$val['Quantity'].'</Quantity>';
                        $str.='<Price>'.$val['Price'].'</Price>';
                        $str.='<SKUTotal>'.$val['SKUTotal'].'</SKUTotal>';
                        if($val['SKUCess']){
                            $skucess=round($val['SKUCess'],2);
                            $str.='<SKUCess>'.$skucess.'</SKUCess>';
                        }else{
                            $str.='<SKUCess></SKUCess>';
                        }
                        $str.="</Product>";
                    }
                    $str.='</ProductItems></Order>';
                    $flag=urlencode(base64_encode(strtoupper(md5($value['appKey'].$str))));
                    $dataStr=urlencode(base64_encode($str));
                    $url=$value['interfaceUrl'].'?hander=orderadd&Flag='.$flag.'&data='.$dataStr;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch, CURLOPT_URL,$url);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); 
                    curl_setopt($ch, CURLOPT_TIMEOUT,70);
                    $response = curl_exec($ch);
                    //$res=curl_getinfo($ch);
                    curl_close($ch);
                    $ret=  base64_decode($response);
                    preg_match('/<success>(.*)<\/success>/i', $ret, $success);
                    if($success[1]=='1'){
                        $sql="update {$this->db_prex}order set cusOrderStatus=5,cusOrderSendTime=now(),cusOrderEndTime=now(),ciqOrderStatus=5,ciqOrderSendTime=now(),ciqOrderEndTime=now(),isSync=1 where order_id={$value['order_id']}";
                    }else{
                        preg_match('/<Error>(.*)<\/Error>/i', $ret, $error);
                        $sql="update {$this->db_prex}order set cusOrderStatus=5,cusOrderSendTime=now(),cusOrderEndTime=now(),cusOrderInfo='{$error[1]}',ciqOrderStatus=5,ciqOrderSendTime=now(),ciqOrderEndTime=now(),ciqOrderInfo='{$error[1]}',isSync=2,syncInfo='{$error[1]}' where order_id={$value['order_id']}";
                    }
                    if($sql){
                        $mRet=$model->execute($sql);
                        if($mRet){
                            $count++;
                        }
                    }
                }
                echo date('Y-m-d H:i:s').' 成功同步'.$count.'条订单到重庆西永';
            }else{
                echo date('Y-m-d H:i:s').' 重组数据错误';
            }
        }else{
            echo  date('Y-m-d H:i:s')." 没有需要同步到重庆西永的订单";
        }
    }
    
    //同步快递单号
    public function syncLogisticCode(){
        $model=M('order');
        $sql="select o.order_sn,o.order_id,o.shipping_status,o.order_status,o.pay_status,s.shopCode,s.shopSercet from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as s on s.id=w.apiId left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}platform as pf on pf.id=p.platId WHERE w.warehouse_type=1 AND ( o.order_status=0 OR o.order_status=1 ) AND o.shipping_status=0 AND o.pay_status=1 AND o.payOrderStatus=3 AND o.cusOrderStatus=3 AND o.ciqOrderStatus=3 AND ( o.isSync=1 OR o.isSync=3 ) and s.isBaoshui=1 and pf.name='sdms'";
        $datas=$model->query($sql);
        if($datas){
            $orderStr = '';
            $orderIdArr=array();
            foreach($datas as $key => $value) {
                $orderStr .= "'".$value['order_sn']."',";
                $orderIdArr[$value['order_sn']]=$value['order_id'];
            }
            $orders = trim($orderStr,',');
            $shopCode = $datas[0]['shopCode'];
            $shopSercet = $datas[0]['shopSercet'];
            $post['shopCode']=$datas[0]['shopCode'];
            $post['shopSercet']=$datas[0]['shopSercet'];
            $post['orders']=$orders;
            $ch = curl_init();
            // curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_URL,"http://a.gdyyb.com/Home/Customs/getMoreLogisticCode");
            //curl_setopt($ch, CURLOPT_COOKIEJAR, $this->log.'/cookie.txt'); 
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); 
            curl_setopt($ch, CURLOPT_TIMEOUT,70);
            $response = curl_exec($ch);
            curl_close($ch);
            $ret=  json_decode($response,true);
            $sqlArr =array();
            if($ret['statusCode']==200){
                $success=0;
                foreach($ret['data'] as $key=>$value){
					if($value['deleted']==1){
							$sqlArr[]="update {$this->db_prex}order set order_status=5 where order_sn='{$value['orderSn']}'";
					}elseif($value['logisticsCode']!='') {
						$time=time();
						$sqlArr[]="update {$this->db_prex}order set isSync=1,shipping_code='{$value['logisticsCode']}',shipping_name='{$value['logisticsName']}',shipping_time='{$time}',shipping_status=1,order_status=1 where order_sn='{$value['orderSn']}'";
						if($datas[0]['order_status']==0){//如果order_status=0,生成一条确认订单日志，生成一条订单发货日志
							$sqlArr[]="insert into {$this->db_prex}order_action(order_id,action_user,order_status,shipping_status,pay_status,action_note,log_time,status_desc) values ({$orderIdArr[$value['orderSn']]},{$this->setId},1,0,1,'商家确认了订单', unix_timestamp(now()),'confirm')";
						}
						$sqlArr[]="insert into {$this->db_prex}order_action (order_id,action_user,order_status,shipping_status,pay_status,action_note,log_time,status_desc) values ({$orderIdArr[$value['orderSn']]},{$this->setId},1,1,1,'商家发货', unix_timestamp(now())+5,'delivery')";
						$sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_sn={$value['orderSn']}','自动同步获取快递单号')";
						$success++;
					}
                }
                if($sqlArr){
                    foreach($sqlArr as $sql){
                        $model->execute($sql);
                    }
                }
                echo date('Y-m-d H:i:s').' 成功为'.$success.'条订单获取快递单号';
            }else{
                echo date('Y-m-d H:i:s').' '.$ret['retMessage'];
            }  
        }else{
            echo date('Y-m-d H:i:s').' 没有需要获取运单号的订单；';
        }
    }
    
    //同步重庆快递单号
    public function syncLogisByChongQing(){
        $fileName=C('pay_log').'/chongqingLogis.txt';
        $datas=I('data/s')?I('data/s'):'';
        if($datas){
            $data=base64_decode($datas);
            $str=date('Y-m-d H:i:s').' '.$data."\n\n";
            @file_put_contents($fileName,$str,FILE_APPEND);
            preg_match('/<ORIGINAL_ORDER_NO>(.*)<\/ORIGINAL_ORDER_NO>/i', $data, $orderArr);
            if($orderArr[1]){
                $sql="select order_id,order_status from {$this->db_prex}order where order_sn='{$orderArr[1]}' and deleted=0 and pay_status=1 and ( order_status=0 OR order_status=1 ) AND shipping_status=0";
                $model=M('order');
                $result=$model->query($sql);
                if($result){
                    preg_match('/<TRANSPORT_BILL_NO>(.*)<\/TRANSPORT_BILL_NO>/i', $data, $logisArr);
                    preg_match('/<LOGISTCS_NAME>(.*)<\/LOGISTCS_NAME>/i', $data, $logisNameArr);
					$time=time();
                    $sqlArr[]="update {$this->db_prex}order set isSync=1,shipping_code='{$logisArr[1]}',shipping_name='{$logisNameArr[1]}',shipping_time='{$time}',shipping_status=1,order_status=1 where order_id={$result[0]['order_id']}";
                    if($result[0]['order_status']==0){//如果order_status=0,生成一条确认订单日志，生成一条订单发货日志
                        $sqlArr[]="insert into {$this->db_prex}order_action(order_id,action_user,order_status,shipping_status,pay_status,action_note,log_time,status_desc) values ({$result[0]['order_id']},{$this->setId},1,0,1,'商家确认了订单', unix_timestamp(now()),'confirm')";
                    }
                    $sqlArr[]="insert into {$this->db_prex}order_action (order_id,action_user,order_status,shipping_status,pay_status,action_note,log_time,status_desc) values ({$result[0]['order_id']},{$this->setId},1,1,1,'商家发货', unix_timestamp(now())+5,'delivery')";
                    $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_sn={$orderArr[1]}','自动同步获取快递单号')";
                    if($sqlArr){
                        foreach($sqlArr as $sql){
                            $model->execute($sql);
                        }
                        echo true;
                    }else{
                        echo 'error';
                    }
                }else{
                    echo 'none';
                }
            }else{
                echo 'error';
            }
        }else{
            echo 'none';
        }
        
    }
    
    //同步出仓信息
    public function syncOutStorage(){
        $model=M('order');
        $sql="select o.order_sn,o.order_id,o.shipping_status,o.order_status,o.pay_status,s.shopCode,s.shopSercet from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as s on s.id=w.apiId left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}platform as pf on pf.id=p.platId WHERE w.warehouse_type=1 AND ( o.order_status=1 or o.order_status=2 ) AND o.shipping_status=1 AND o.pay_status=1 AND o.isSync=1 AND o.outStoreStatus=0 and s.isBaoshui=1 and pf.name='sdms' order by o.order_id asc";
        $datas=$model->query($sql);
        if($datas){
            $orderStr = '';
            foreach($datas as $key => $value) {
                $orderStr .= "'".$value['order_sn']."',";
            }
            $orders = trim($orderStr,',');
            $shopCode = $datas[0]['shopCode'];
            $shopSercet = $datas[0]['shopSercet'];
            $post['shopCode']=$datas[0]['shopCode'];
            $post['shopSercet']=$datas[0]['shopSercet'];
            $post['orders']=$orders;
            
            $ch = curl_init();
            // curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_URL,"http://a.gdyyb.com/Home/Customs/getOutStoreage");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); 
            curl_setopt($ch, CURLOPT_TIMEOUT,70);
            $response = curl_exec($ch);
            curl_close($ch);
            $ret=  json_decode($response,true);
            $sqlArr =array();
            if($ret['statusCode']==200){
                $success=0;
                foreach($ret['data'] as $key=>$value){

                    $sqlArr[]="update {$this->db_prex}order set outStoreStatus=1,outStoreTime='{$value['outStoreTime']}' where order_sn='{$value['orderSn']}'";
                    $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_sn={$value['orderSn']}','自动同步同步出仓日期')";
                    $success++;
                }
                if($sqlArr){
                    foreach($sqlArr as $sql){
                        $model->execute($sql);
                    }
                }
                echo date('Y-m-d H:i:s').' 成功设置'.$success.'条订单出仓';
            }else{
                echo date('Y-m-d H:i:s').' '.$ret['retMessage'];
            }  
        }else{
            echo date('Y-m-d H:i:s').' 没有需要出仓的订单；';
        }
    }

    //超过三天自定出仓
    public function syncOutStorageExcThird(){
        $model=M('order');
        $sql="SELECT order_sn FROM {$this->db_prex}order WHERE deleted=0 AND pay_status=1 AND order_status=1 AND shipping_status=1 AND outStoreStatus=0 and shipping_code!='' AND shipping_time>0 AND DATEDIFF(NOW(),FROM_UNIXTIME(shipping_time))>=3";
        $data=$model->query($sql);
        $success=0;
        if($data){
            $sqlArr=array();
            $date=date('Y-m-d H:i:s');
            foreach($data as $value){
                $sqlArr[]="update {$this->db_prex}order set outStoreStatus=1,outStoreTime='{$date}' where order_sn='{$value['order_sn']}'";
                $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_sn={$value['order_sn']}','自动同步同步出仓日期')";
                $success++;
            }
            if($sqlArr){
                foreach($sqlArr as $sql){
                    $model->execute($sql);
                }
            }
        }
        echo date('Y-m-d H:i:s').' 成功设置'.$success.'条订单出仓';
    }
    
    //设置完成订单
    public function syncComplete(){
        $model=M('Order');
        $sql="select o.order_id from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where ( o.order_status=1 or o.order_status=2 ) AND o.shipping_status=1 AND o.pay_status=1 AND o.outStoreStatus=1 AND DATEDIFF(NOW(),o.outStoreTime)>=15";
        $data=$model->query($sql);
        if($data){
            $sqlArr=array();
            $success=0;
            foreach($data as $value){
                $sqlArr[]="update {$this->db_prex}order set order_status=4 where order_id='{$value['order_id']}'";
                $sqlArr[]="insert into {$this->db_prex}order_action(order_id,action_user,order_status,shipping_status,pay_status,action_note,log_time,status_desc) values ({$value['order_id']},{$this->setId},4,1,1,'订单完成', unix_timestamp(now()),'订单完成')";
                $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('自动服务','order','order_id={$value['order_id']}','自动设置订单成功')";
                $success++;
                calculateTicheng(4,$value['order_id']);
            }
            //查找订单状态不是4
            if($sqlArr){
                foreach($sqlArr as $sql){
                    $model->execute($sql);
                }
            }
            echo date('Y-m-d H:i:s').' 成功设置'.$success.'条订单完成';
        }else{
            echo date('Y-m-d H:i:s').' 没有需要设置完成的订单；';
        }
        
    }

    //计算提成金额
    public function setOrderTicheng(){
        header("Content-Type: text/html;charset=utf-8");
        set_time_limit(0);
        $model=M('Order');
        $sql="select * from {$this->db_prex}order where pay_status=1 and order_status=4 and deleted=0 and isDistribut=0 LIMIT 0,100";
        $data=$model->query($sql);
        $count=0;
        if($data){
            foreach ($data as $v){
                $model->where('order_id',$v['order_id'])->save(array('isDistribut'=>1));
                $res=calculateTicheng(4,$v['order_id']);
                $count++;
            }
        }       
        echo date('Y-m-d H:i:s').' 成功为'.$count.'条订单计算提成';
    }

    //清除缓存
    public function clearCache(){
        delFile(RUNTIME_PATH);
        echo '清除缓存成功<br />';
    }

    //定时删除无效订单
    public function delInvalidOrder(){
        $sql="UPDATE jia_order SET deleted=1 WHERE deleted=0 AND order_status IN (0,7) AND pay_status=2 AND UNIX_TIMESTAMP()-pay_time>169200";
        M('Order')->execute($sql);
    }

    //获取订单的接口
    public function getOrder(){
        //返回信息
        $ret['retCode']=200;
        $ret['retMessage']='';
        //api编码
        $key=I('key/s')?I('key/s'):'';
        if($key=='') errorMsg(400,'API编码不能为空！');
        //api秘钥
        $sercet=I('sercet/s')?I("sercet/s"):'';
        if($sercet=='') errorMsg(400,'API秘钥不能为空！');
        //api查询
        $shop=M('shop')->where("shopCode='{$key}' and shopSercet='{$sercet}'")->find();
        if(!$shop) errorMsg(400,'未查询到当前授权接口！');

        //用户信息
        $userId=$shop['userId'];
        $users=M('users')->where('user_id='.$userId)->cache('users'.$userId,TPSHOP_CACHE_TIME)->find();

        //订单数据
        $order_sn=I('order_sn/s')?I('order_sn/s'):'';
        if($order_sn=='') errorMsg(400,'订单号不能为空！');
        $oret=M('order')->where("order_sn='{$order_sn}' and deleted=0")->find();
        if($oret) errorMsg(400,'订单号已经存在，不能推送！');
        $consignee=I('consignee/s')?I('consignee/s'):'';

        $province=I('province/s')?I('province/s'):'';
        $pregion=M('region')->where("name='{$province}' and level=1")->find();
        if(!$pregion) errorMsg(400,'未查询到省份！');
        $provinceId=$pregion['id'];

        $city=I('city/s')?I('city/s'):'';
        $cregion=M('region')->where("name='{$city}' and level=2")->find();
        if(!$cregion) errorMsg(400,'未查询到城市！');
        $cityId=$cregion['id'];

        $district=I('district/s')?I('district/s'):'';
        $dregion=M('region')->where("name='{$district}' and level=3")->find();
        if(!$dregion) errorMsg(400,'未查询到地区！');
        $districtId=$dregion['id'];

        $address=I('address/s')?I('address/s'):'';
        if($address=='') errorMsg(400,'收货地址不能为空！');
        $mobile=I('mobile/s')?I('mobile/s'):'';
        if($mobile=='') errorMsg(400,'联系电话不能为空！');
        $buyerRegNo=I('buyerRegNo/s')?I('buyerRegNo/s'):'';
        $buyerName=I('buyerName/s')?I('buyerName/s'):'';
        if($buyerName=='') errorMsg(400,'收件人不能为空！');
        $buyerIdNumber=I('buyerIdNumber/s')?I('buyerIdNumber/s'):'';
        if($buyerIdNumber=='') errorMsg(400,'收件人身份证不能为空！');
        $freight=I('freight/d')?I('freight/d'):0;
        $insuredFee=I('insuredFee/d')?I('insuredFee/d'):0;
        $discount=I('discount/d')?I('discount/d'):0;

        //订单商品
        $goods=$_REQUEST['goods']?$_REQUEST['goods']:'';
        $orderArr=$orderGoodsArr=array();
        $goodsValue=0;
        foreach($goods as $k=>$g){
            $goodsInfo = M('Goods')->where("goods_sn", $g['goods_sn'])->cache(true,TPSHOP_CACHE_TIME)->find();
            $field=$goodsInfo['wareId'].$goodsInfo['portId'];
            if(!$goodsInfo) errorMsg(400,'未查询到商品：'.$g['goods_name'].'详情！');
            if($goodsInfo['store_count'] < $g['goods_num']) errorMsg(400,'商品：'.$g['goods_name'].'库存不足！');
            //根据用户级别，获取商品单价
            if($users['myLevel']==3){
                $memberGoodsPrice= $goodsInfo['firstMemberPrice'];
            }elseif($users['myLevel']==2){
                $memberGoodsPrice= $goodsInfo['secondMemberPrice'];
            }elseif($users['myLevel']==1){
                $memberGoodsPrice= $goodsInfo['thirdMemberPrice'];
            }else{
                $memberGoodsPrice= $goodsInfo['shop_price'];
            }
            if(!isset($orderArr[$field]['goodsValue'])){
                $orderArr[$field]['goodsValue']=0;
            }
            //累加订单商品总价
            $goodsValue+=$memberGoodsPrice*$g['goods_num'];
            //累加商品总价
            $orderArr[$field]['stype']=$goodsInfo['stype'];
            $orderArr[$field]['wareId']=$goodsInfo['wareId'];
            $orderArr[$field]['portId']=$goodsInfo['portId'];
            $orderArr[$field]['goods_price']+=substr(sprintf('%.3f',$goodsInfo['shop_price']*$g['goods_num']), 0, -1);
            $orderArr[$field]['taxTotal'] += substr(sprintf('%.3f', $memberGoodsPrice*$g['goods_num']*$goodsInfo['tax']), 0, -1);
            $orderArr[$field]['member_goods_price'] += substr(sprintf('%.3f', $memberGoodsPrice*$g['goods_num']), 0, -1);
            //设置订单商品信息
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['goods_id']=$goodsInfo['goods_id'];
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['goods_name']=$goodsInfo['goods_name'];
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['goods_sn']=$goodsInfo['goods_sn'];
            if(!isset($orderGoodsArr[$field][$goodsInfo['goods_id']]['goods_num'])){
                $orderGoodsArr[$field][$goodsInfo['goods_id']]['goods_num']=0;
            }
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['goods_num']+=$g['goods_num'];
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['market_price']=$goodsInfo['market_price'];
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['goods_price']=$goodsInfo['shop_price'];
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['prom_type']=$goodsInfo['prom_type'];
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['member_goods_price']=$memberGoodsPrice;
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['cost_price']=$goodsInfo['cost_price'];
            $orderGoodsArr[$field][$goodsInfo['goods_id']]['goodsRate']=$goodsInfo['goodsRate'];
        }
        //订单详情
        $count=count($orderArr);
        foreach($orderArr as $key=>$value){
            //1.新增订单
            $order=array();
            if($count>1){
                $order['order_sn']=$order_sn.'_'.$key;
            }else{
                $order['order_sn']=$order_sn;
            }
            $order['user_id']=$userId;
            $order['stype']=$value['stype'];
            $order['wareId']=$value['wareId'];
            $order['portId']=$value['portId'];
            $order['consignee']=delSpecilaChar($consignee);
            $order['province']=$provinceId;
            $order['city']=$cityId;
            $order['district']=$districtId;
            $order['address']=delSpecilaChar($address);
            $order['mobile']=$mobile;
            $order['buyerRegNo']=$buyerRegNo;
            $order['buyerName']=delSpecilaChar($buyerName);
            $order['buyerIdNumber']=strtoupper($buyerIdNumber);
            $order['add_time']=time();
            $order['isLoad']=2;
            //$order['goods_price']=$value['goods_price'];
            $order['member_goods_price']=$value['member_goods_price'];
            $order['taxTotal']=$value['taxTotal'];
            $order['freight']=0;
            $order['insuredFee']=0;
            $order['order_amount']=$value['member_goods_price']+$order['taxTotal'];
            $order['coupon_price']=0;
            $order['goods_price']=$order['total_amount']=$order['order_amount'];
            if($order['stype']==1){
                if($order['order_amount']>2000) errorMsg(400,'保税商品海关限额每单不超过2000,请确认！');
                //验证身份证额度
                $checkCardNum = checkBuyerIdNumber($buyerName,$buyerIdNumber);
                if($checkCardNum){
                    errorMsg(400,'当前身份证已被加入黑名单，请确认！');
                }else{
                    $checkBNP = checkBuyerIdNumerPrice($buyerName,$buyerIdNumber);//查询额度
                    if(($checkBNP+$order['order_amount'])>20000){//当前订单支付价格+已经使用额度超过20000,提示
                        errorMsg(400,'该订单购买人超过海关购买限额2万，请确认！');
                    }else{//如果没有超过，那么额度往上加
                        setBuyerIdNumberPrice($buyerName,$buyerIdNumber,$order['order_amount'],1);
                    }
                }
            }
            $order_id = M("Order")->insertGetId($order);
            if(!$order_id) errorMsg(400, '添加订单失败，请刷新重试');

            // 2.记录订单操作日志
            $action_info = array(
                'order_id'        =>$order_id,
                'action_user'     =>$userId,
                'action_note'     => '您提交了订单，请等待系统确认',
                'status_desc'     =>'提交订单', //''
                'log_time'        =>time(),
            );
            M('order_action')->insertGetId($action_info);
            //3.新增订单商品
            $num=1;
            foreach($orderGoodsArr[$key] as $gValue){
                $goodsArr=array();
                $goodsArr = $gValue;
                $goodsArr['order_id']           = $order_id; // 订单id
                $goodsArr['gNum']               =$num;
                M("order_goods")->insertGetId($goodsArr);
                $num++;
            }
        }
        errorMsg(200,'done');
    }

    //查询运单号的接口
    public function getLogis(){
        //api编码
        $key=I('key/s')?I('key/s'):'';
        if($key=='') errorMsg(400,'API编码不能为空！');
        //api秘钥
        $sercet=I('sercet/s')?I("sercet/s"):'';
        if($sercet=='') errorMsg(400,'API秘钥不能为空！');
        //api查询
        $shop=M('shop')->where("shopCode='{$key}' and shopSercet='{$sercet}'")->find();
        if(!$shop) errorMsg(400,'未查询到当前授权接口！');
        //运单号
        $order_sn=I('order_sn/s')?I('order_sn/s'):'';
        if($order_sn=='') errorMsg(400,' 订单号不能为空！');
        //查询订单
        $order=M('order')->field('order_status,shipping_status,shipping_code,shipping_name')->where("order_sn='{$order_sn}'")->find();
        if(!$order) errorMsg(400,'未查询到订单信息！');
        errorMsg(200,$order);
    }

    //查询订单发货接口
    public function checkOutStorage(){
        //api编码
        $key=I('key/s')?I('key/s'):'';
        if($key=='') errorMsg(400,'API编码不能为空！');
        //api秘钥
        $sercet=I('sercet/s')?I("sercet/s"):'';
        if($sercet=='') errorMsg(400,'API秘钥不能为空！');
        //api查询
        $shop=M('shop')->where("shopCode='{$key}' and shopSercet='{$sercet}'")->find();
        if(!$shop) errorMsg(400,'未查询到当前授权接口！');
        //运单号
        $order_sn=I('order_sn/s')?I('order_sn/s'):'';
        if($order_sn=='') errorMsg(400,' 订单号不能为空！');
        //查询订单
        $order=M('order')->field('order_status,outStoreStatus,outStoreTime')->where("order_sn='{$order_sn}'")->find();
        if(!$order) errorMsg(400,'未查询到订单信息！');
        errorMsg(200,$order);
    }
}
