<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 当燃
 * Date: 2015-09-09
 */
namespace app\admin\controller;
use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Db;

class Order extends Base {
    public  $order_status;
    public  $pay_status;
    public  $shipping_status;
    public  $db_prex='';
    public $log='';
    public  $setId=1;
	public $payShopId=1;
    
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        C('TOKEN_ON',false); // 关闭表单令牌验证
        $this->order_status = C('ORDER_STATUS');
        $this->pay_status = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        $this->payOrderStatus = C('PAY_ORDER_STATUS');
        $this->cusOrderStatus = C('CUS_ORDER_STATUS');
        $this->ciqOrderStatus = C('CIQ_ORDER_STATUS');
        $this->sync=C('SYNC');
        $this->db_prex=C('db_prex');
        $this->log=C('pay_log');
        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('payOrderStatus',$this->payOrderStatus);
        $this->assign('cusOrderStatus',$this->cusOrderStatus);
        $this->assign('sync',$this->sync);
        $this->assign('ciqOrderStatus',$this->ciqOrderStatus);
        $this->assign('shipping_status',$this->shipping_status);
    }

    public function all_order(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));  
        $this->assign('timegap',$begin.'-'.$end);
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);

        $warehouseSql = "select warehouse_id,warehouse_name from {$this->db_prex}warehouse where warehouse_type=1";
        $warehouse = DB::query($warehouseSql);
        $this->assign('warehouse',$warehouse);
        // dump($num);exit;
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);
        $this->assign('status',10);
        
        return $this->fetch();
    }

    /*
     *订单首页
     */
    public function index(){
    	$begin = date('Y-m-d',strtotime("-1 year"));//30天前
    	$end = date('Y/m/d',strtotime('+1 days'));
        $updateSql = "update {$this->db_prex}order set order_status=7 where pay_status=0 and order_status in(0,1) and deleted!=1 and (unix_timestamp(now())-add_time>86400) and stype=1";
        DB::execute($updateSql); 	//未处理中超过一天未支付的订单做超时处理
        
    	$this->assign('timegap',$begin.'-'.$end);
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        // dump($num);exit;
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);
        $this->assign('status',1);
        return $this->fetch();
    }

    public function order1(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days')); 
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']); 
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('status',2);
        return $this->fetch();
    }

    public function order2(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('status',3);
        return $this->fetch();
    }
    public function order3(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days')); 
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']); 
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('status',4);
        return $this->fetch();
    }

    public function order4(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('status',5);
        return $this->fetch();
    }
    public function order5(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('status',6);
        return $this->fetch();
    }

    public function order6(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('status',7);
        return $this->fetch();
    }
    public function order7(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('status',8);
        return $this->fetch();
    }

    /*
     *Ajax首页
     */
    public function ajaxindex(){
        session('ajaxpage',I('get.p'));
        $where = '';
        $status = I('status')?I('status'):session('orderStatus');
        session('orderStatus',$status);
        switch ($status) {
            case 1://未支付
                $where .= ' and o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 ';
                break;
            case 2://处理中
                $where .= ' and o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 ';
                break;
            case 3://异常
                $where .= ' and o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 ';
                break;
            case 4://发货
                $where .= ' and o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 ';
                break;
            case 5://收货
                $where .= ' and o.order_status=2 and o.deleted!=1 ';
                break;
            case 6://完成
                $where .= ' and o.order_status=4 and o.deleted!=1 ';
                break;
            case 7://售后
                $where .= ' and o.order_status=6 and o.deleted!=1 ';
                break;
            case 8://回收站
                $where .= ' and (o.deleted=1 or o.order_status=5 or o.order_status=3) ';
                break;
            case 9://订单导出
                $where .= '';
                break;
            case 10://全部订单
                $where .= 'and o.deleted!=1';                                
            default:
                $where = '';
                break;
        }

        $orderLogic = new OrderLogic();       
        $timegap = I('timegap');
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        }else{
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end = strtotime(I('add_time_end'));
        }

        // 搜索条件
        $condition = array();
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');
        
        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;
        $consignee ? $where.=" and o.consignee='".trim($consignee)."'":false;
        
        if($begin && empty($end)){
            $condition['add_time'] = array('>=',"$begin");
            $where.=" and o.add_time>={$begin}";
        }elseif($end && empty($begin)){
            $condition['add_time'] = array('<=',"$end");
            $where.=" and o.add_time<={$end}";
        }elseif($begin && $end){
            $condition['add_time'] = array('between',"$begin,$end");
            $where.=" and o.add_time between {$begin} and {$end}";
        }

        $nickname = ($keyType && $keyType == 'nickname') ? $keywords : I('nickname') ;
        $nickname ? $condition['nickname'] = trim($nickname) : false;
        $nickname ? $where.=" and u.nickname='".trim($nickname)."' ":false;
        
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        $order_sn ? $where.=" and o.order_sn='".trim($order_sn)."' ":false;

        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;
        I('order_status') != ''? $where.=" and o.order_status=".I('order_status')." ":false;

        I('pay_status') != '' ? $condition['pay_status'] = I('pay_status') : false;
        I('pay_status') != ''? $where.=" and o.pay_status=".I('pay_status')." ":false;

        I('pay_code') != '' ? $condition['pay_code'] = I('pay_code') : false;
        I('pay_code') != ''? $where.=" and o.pay_code='".I('pay_code')."' ":false;

        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;
        I('shipping_status') != ''? $where.=" and o.shipping_status='".I('shipping_status')."' ":false;

        I('warehouse_id') !=0 ?  $condition['wareId'] = I('warehouse_id') : false;
        I('warehouse_id') !=0 ?  $where .= " and w.warehouse_id=".I('warehouse_id') : false;

        // I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        // I('user_id') != ''? $where.=" and o.user_id='".trim(I('user_id'))."' ":false;

        // I('level_one') ? $condition['user_id'] = trim(I('level_one')) : false;
        $level = I('level_one');
        $level_two = I('level_two');
        $userIdArr= $pid = $gid = array();
        if($level){
            $pidSql = "select user_id from {$this->db_prex}users where parentUser={$level}";
            $pid = DB::query($pidSql);//查询二级用户ID
            foreach($pid as $value) {
                $gidSql = "select user_id from {$this->db_prex}users where parentUser={$value['user_id']}";
                $gid = DB::query($gidSql);//查询所有三级用户ID
            }
            $userIdArr = array_merge($pid,$gid);//合并ID数组
            foreach ($userIdArr as $value) {
                $userIdStr .=$value['user_id'].',';
            }
            if($level && !$level_two){
                $where .= " and o.user_id in($userIdStr$level) ";
            }elseif($level && $level_two){
                switch($level_two){
                    case 'two':
                        $pidStr = '';
                        foreach ($pid as $value) {
                            $pidStr .= $value['user_id'].',';
                        }
                        $pidStr = trim($pidStr,',');
                        $where .=" and o.user_id in($pidStr) ";
                        break;
                    case 'three':
                        $gidStr = '';
                        foreach ($gid as $value) {
                            $gidStr .= $value['user_id'].',';
                        }
                        $gidStr = trim($gidStr,',');
                        $where .=" and o.user_id in($gidStr) ";
                        break;
                    case 'two_three':
                        $userIdStr = trim($userIdStr,',');
                        $where .=" and o.user_id in($userIdStr) ";
                        break;    
                    default:
                        break;
                }
            }
        }
        // dump($where);exit;
        $type=I('type/d')?I('type/d'):0;
        session('type',$type);
        if($status == 10) {
           $sort_order = ' o.add_time desc ';
        }else{
            if($type){
                switch ($type) {
                    case 1:
                        $sort_order = ' field(o.pay_status,3,4,0,1,2) desc ';
                        break;
                    case 2:
                        $sort_order = ' field(o.payOrderStatus,3,4,0,1,2) desc,o.payOrderInfo asc ';
                        break;
                    case 3:
                        $sort_order = ' field(o.cusOrderStatus,3,4,0,1,2) desc ';
                        break;
                    case 4:
                        $sort_order = ' field(o.ciqOrderStatus,3,4,0,1,2) desc ';
                        break;
                    case 5:
                        $sort_order = ' field(o.isSync,3,4,0,1,2) desc ';
                        break;
                    case 6:
                        $sort_order = ' o.add_time desc ';
                        break;
                    case 7:
                        $sort_order = 'field(o.payCiqOrderStatus,3,4,0,1,2) desc,o.payCiqOrderInfo asc';
                        break;
                    default:
                        $sort_order = '';
                        break;
                }
            }else{
                $sort_order = '';
            }
        }    
        // dump($sort_order);exit;
        $where .=' and w.warehouse_type=1 ';
        $sql="select count(*) as count from {$this->db_prex}order as o left join {$this->db_prex}users as u on u.user_id=o.user_id left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where 0=0 {$where}";
        // dump($where);exit;
        $countArr = DB::query($sql);
        $count=$countArr[0]['count'];
        $Page  = new AjaxPage($count,60);
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if($key == 'add_time'){
                $between_time = explode(',',$val[1]);
                $parameter_add_time = date('Y/m/d',$between_time[0]) . '-' . date('Y/m/d',$between_time[1]);
                $Page->parameter['timegap'] = $parameter_add_time;
            }else{
                $Page->parameter[$key] =  urlencode($val);
            }
        }
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($where,$sort_order,$Page->firstRow,$Page->listRows);
        $this->assign('count',$count);
        $this->assign('orderList',$orderList);
        $this->assign('status',$status);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('type',$type);
        return $this->fetch();
    }

    
    /*
     * ajax 发货订单列表
    */
    public function ajaxdelivery(){
    	$orderLogic = new OrderLogic();
    	$condition = array();
    	I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
    	I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
    	$shipping_status = I('shipping_status');
    	$condition['shipping_status'] = empty($shipping_status) ? array('neq',1) : $shipping_status;
        $condition['order_status'] = array('in','1,2,4');
    	$count = M('order')->where($condition)->count();
    	$Page  = new AjaxPage($count,10);
    	//搜索条件下 分页赋值
    	foreach($condition as $key=>$val) {
            if(!is_array($val)){
                $Page->parameter[$key]   =   urlencode($val);
            }
    	}
    	$show = $Page->show();
    	$orderList = M('order')->where($condition)->limit($Page->firstRow.','.$Page->listRows)->order('add_time DESC')->select();
    	$this->assign('orderList',$orderList);
    	$this->assign('page',$show);// 赋值分页输出
    	$this->assign('pager',$Page);
    	return $this->fetch();
    }
    
    /**
     * 订单详情
     * @param int $id 订单id
     */
    public function detail($order_id){
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $button = $orderLogic->getOrderButton($order);
        // 获取操作记录
        $action_log = M('order_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();
        $userIds = array();
        //查找用户昵称
        foreach ($action_log as $k => $v){
            $userIds[$k] = $v['action_user'];
        }
        if($userIds && count($userIds) > 0){
            $users = M("users")->where("user_id in (".implode(",",$userIds).")")->getField("user_id , nickname" , true);
        }
        $this->assign('users',$users);
        $this->assign('order',$order);
        $this->assign('action_log',$action_log);
        $this->assign('orderGoods',$orderGoods);
        $split = count($orderGoods) >1 ? 1 : 0;
        foreach ($orderGoods as $val){
        	if($val['goods_num']>1){
        		$split = 1;
        	}
        }
        $shipping = DB::query("select * from __PREFIX__shipping where enabled=1");
        $this->assign('shipping',$shipping);
        $this->assign('split',$split);
        $this->assign('button',$button);
        return $this->fetch();
    }
    //快递单号编辑
    public function editShippingCode(){
        //写日志
        $model = M('action');
        $userName = session('userName');
        $shipping_code = I('shipping_code');
        $orderId = I('order_id');
        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId},shipping_code={$shipping_code}','快递单号修改')";
        $model->execute($sql);
        M('order')->where('order_id',I('order_id'))->save(array('shipping_code'=>I('shipping_code')));
        echo 1;
    }
    //快递快速编辑
    public function editShippingName(){
        $arr= explode(',', I('post.newShippingName'));
        $shipping_name=$arr[0];
        $express_code=$arr[1];
        $model = M('action');
        $userName = session('userName');
        $orderId = I('order_id');
        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId},shipping_name={$shipping_name},express_code={$express_code}','快递快速编辑修改')";
        $model->execute($sql);
        M('order')->where('order_id',I('order_id'))->save(array('shipping_name'=>$shipping_name,'express_code'=>$express_code));
        echo $shipping_name;
    }
    //收货人，身份证的修改
    public function editBuyerIdNumber(){
        $res=M('order')->where('order_id',I('order_id'))->save(array('buyerName'=>I('buyerName'),'buyerIdNumber'=>I('buyerIdNumber')));
        $model = M('action');
        $userName = session('userName');
        $buyerIdNumber = I('buyerIdNumber');
        $consignee = I('buyerName');
        $orderId = I('order_id');
        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId}','订购人，身份证的修改')";
        $ret = $model->execute($sql);
        if($res && $ret){
            echo 1;
        }else{
            echo 0;
        }
    }

    //支付流水号的修改
    public function changeTradeNo(){
        $res=M('order')->where('order_id',I('order_id'))->save(array('tradeNo'=>I('tradeNo')));
        $model = M('action');
        $userName = session('userName');
        $orderId = I('order_id');
        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId}','修改支付流水号')";
        $ret = $model->execute($sql);
        if($res && $ret){
            echo 1;
        }else{
            echo 0;
        }
    }
    //申请退款单
    public function refund_order(){
        if(I('refundType')==='confirm'){
            //确认退款
            $res=M('order')->where('order_id',I('order_id'))->save(array('isRefund'=>1));
            $model = M('action');
            $userName = session('userName');
            $orderId = I('order_id');
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId},isRefund=1','申请退款单修改')";
            $ret = $model->execute($sql);
            //更改商品库存
            $order_goods=M('order_goods')->where('order_id',I('order_id'))->select();
            foreach($order_goods as $v){
                $sql="update __PREFIX__goods set store_count=store_count+{$v['goods_num']} where goods_id={$v['goods_id']}";
                M('goods')->execute($sql);
            }
            if($res && $ret){
                echo 1;
            }else{
                echo 0;
            }
        }else{
            //申请退款
            $res=M('order')->where('order_id',I('order_id'))->save(array('order_status'=>6));
            if($res){
                $this->success('申请成功');
            }else{
                $this->error('申请失败');
            }
        }
        
    }
    /**
     * 订单编辑
     * @param int $id 订单id
     */
    public function edit_order(){
    	$order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        // if($order['shipping_status'] != 0){
        //     $this->error('已发货订单不允许编辑');
        //     exit;
        // } 
    
        $orderGoods = $orderLogic->getOrderGoods($order_id);
                
       	if(IS_POST)
        {
            $order['buyerName'] = I('buyerName');// 购买人
            $order['consignee'] = I('consignee');// 收货人
            $order['buyerIdNumber'] = I('buyerIdNumber');// 身份证
            $order['province'] = I('province'); // 省份
            $order['city'] = I('city'); // 城市
            $order['district'] = I('district'); // 县
            $order['address'] = I('address'); // 收货地址
            $order['mobile'] = I('mobile'); // 手机           
            $order['invoice_title'] = I('invoice_title');// 发票
            $order['admin_note'] = I('admin_note'); // 管理员备注
            $order['admin_note'] = I('admin_note'); //                  
            $order['shipping_code'] = I('shipping');// 物流方式
            $order['shipping_name'] = M('plugin')->where(array('status'=>1,'type'=>'shipping','code'=>I('shipping')))->getField('name');            
            $order['pay_code'] = I('payment');// 支付方式            
            $order['pay_name'] = M('pay_type')->where(array('type'=>I('payment')))->getField('desc');                            
            $goods_id_arr = I("goods_id/a");
            $new_goods = $old_goods_arr = array();
            //################################订单添加商品
            /*if($goods_id_arr){
            	$new_goods = $orderLogic->get_spec_goods($goods_id_arr);
            	foreach($new_goods as $key => $val)
            	{
            		$val['order_id'] = $order_id;
            		$rec_id = M('order_goods')->add($val);//订单添加商品
            		if(!$rec_id)
            			$this->error('添加失败');
            	}
            }*/
            
            //################################订单修改删除商品
            /*$old_goods = I('old_goods/a');
            foreach ($orderGoods as $val){
            	if(empty($old_goods[$val['rec_id']])){
            		M('order_goods')->where("rec_id=".$val['rec_id'])->delete();//删除商品
            	}else{
            		//修改商品数量
            		if($old_goods[$val['rec_id']] != $val['goods_num']){
            			$val['goods_num'] = $old_goods[$val['rec_id']];
            			M('order_goods')->where("rec_id=".$val['rec_id'])->save(array('goods_num'=>$val['goods_num']));
            		}
            		$old_goods_arr[] = $val;
            	}
            }
            
            $goodsArr = array_merge($old_goods_arr,$new_goods);
            $result = calculate_price($order['user_id'],$goodsArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0,0);
            if($result['status'] < 0)
            {
            	$this->error($result['msg']);
            }*/
       
            //################################修改订单费用
           /* $order['goods_price']    = $result['result']['goods_price']; // 商品总价
            $order['shipping_price'] = $result['result']['shipping_price'];//物流费
            $order['order_amount']   = $result['result']['order_amount']; // 应付金额
            $order['total_amount']   = $result['result']['total_amount']; // 订单总价
*/
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id}','订单修改')";
            $ret = $model->execute($sql);

            $o = M('order')->where('order_id='.$order_id)->save($order);
            
            $l = $orderLogic->orderActionLog($order_id,'edit','修改订单');//操作日志
            if($o && $l && $ret){
            	$this->success('修改成功',U('Admin/Order/editprice',array('order_id'=>$order_id)));
            }else{
            	$this->success('修改失败',U('Admin/Order/detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        // 获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //获取订单城市
        $city =  M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
        //获取订单地区
        $area =  M('region')->where(array('parent_id'=>$order['city'],'level'=>3))->select();
        //获取支付方式
        $payment_list = M('pay_type')->select();
        //获取配送方式
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping'))->select();
        
        $this->assign('order',$order);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);
        $this->assign('orderGoods',$orderGoods);
        $this->assign('shipping_list',$shipping_list);
        $this->assign('payment_list',$payment_list);
        return $this->fetch();
    }
    
    /*
     * 拆分订单
     */
    public function split_order(){
    	$order_id = I('order_id');
    	$orderLogic = new OrderLogic();
    	$order = $orderLogic->getOrderInfo($order_id);
    	if($order['shipping_status'] != 0){
    		$this->error('已发货订单不允许编辑');
    		exit;
    	}
    	$orderGoods = $orderLogic->getOrderGoods($order_id);
    	if(IS_POST){
    		$data = I('post.');
    		//################################先处理原单剩余商品和原订单信息
    		$old_goods = I('old_goods/a');
    		
    		foreach ($orderGoods as $val){
    			if(empty($old_goods[$val['rec_id']])){
    				M('order_goods')->where("rec_id=".$val['rec_id'])->delete();//删除商品
    			}else{
    				//修改商品数量
    				if($old_goods[$val['rec_id']] != $val['goods_num']){
    					$val['goods_num'] = $old_goods[$val['rec_id']];
    					M('order_goods')->where("rec_id=".$val['rec_id'])->save(array('goods_num'=>$val['goods_num']));
    				}
    				$oldArr[] = $val;//剩余商品
    			}
    			$all_goods[$val['rec_id']] = $val;//所有商品信息
    		}
    		$result = calculate_price($order['user_id'],$oldArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0,0);
    		if($result['status'] < 0)
    		{
    			$this->error($result['msg']);
    		}
    		//修改订单费用
    		$res['goods_price']    = $result['result']['goods_price']; // 商品总价
    		$res['order_amount']   = $result['result']['order_amount']; // 应付金额
    		$res['total_amount']   = $result['result']['total_amount']; // 订单总价
    		M('order')->where("order_id=".$order_id)->save($res);
			//################################原单处理结束
			
    		//################################新单处理
    		for($i=1;$i<20;$i++){
                $temp = $this->request->param($i.'_old_goods/a');
    			if(!empty($temp)){
    				$split_goods[] = $temp;
    			}
    		}

    		foreach ($split_goods as $key=>$vrr){
    			foreach ($vrr as $k=>$v){
    				$all_goods[$k]['goods_num'] = $v;
    				$brr[$key][] = $all_goods[$k];
    			}
    		}

    		foreach($brr as $goods){
    			$result = calculate_price($order['user_id'],$goods,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0,0);
    			if($result['status'] < 0)
    			{
    				$this->error($result['msg']);
    			}
    			$new_order = $order;
    			$new_order['order_sn'] = C('prec_order').date('mdHis').rand(100,999);
    			$new_order['parent_sn'] = $order['order_sn'];
    			//修改订单费用
    			$new_order['goods_price']    = $result['result']['goods_price']; // 商品总价
    			$new_order['order_amount']   = $result['result']['order_amount']; // 应付金额
    			$new_order['total_amount']   = $result['result']['total_amount']; // 订单总价
    			$new_order['add_time'] = time();
    			unset($new_order['order_id']);
    			$new_order_id = DB::name('order')->insertGetId($new_order);//插入订单表
    			foreach ($goods as $vv){
    				$vv['order_id'] = $new_order_id;
    				unset($vv['rec_id']);
    				$nid = M('order_goods')->add($vv);//插入订单商品表
    			}
    		}
    		//################################新单处理结束
    		$this->success('操作成功',U('Admin/Order/detail',array('order_id'=>$order_id)));
            exit;
    	}

        $orderSn = $order['order_sn'];
    	$model = M('action');
        $userName = session('userName');
        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderSn={$orderSn}','拆分订单')";
        $model->execute($sql);
    	foreach ($orderGoods as $val){
    		$brr[$val['rec_id']] = array('goods_num'=>$val['goods_num'],'goods_name'=>getSubstr($val['goods_name'], 0, 35).$val['spec_key_name']);
    	}
    	$this->assign('order',$order);
    	$this->assign('goods_num_arr',json_encode($brr));
    	$this->assign('orderGoods',$orderGoods);
    	return $this->fetch();
    }
    
    /*
     * 价钱修改
     */
    public function editprice($order_id){
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $this->editable($order);
        if(IS_POST){
        	$admin_id = session('admin_id');
            if(empty($admin_id)){
                $this->error('非法操作');
                exit;
            }
            $update['discount'] = $order['coupon_price'] + (-I('post.discount'));//传过来的负数为下调，正数为上调
            $update['coupon_price'] = $order['coupon_price'] + (-I('post.discount'));//价格下调，优惠券抵扣为 传过来的价格调整+原先的优惠
            $update['shipping_price'] = I('post.shipping_price');
			// $update['order_amount'] = $order['goods_price'] + $update['shipping_price'] - (-($update['discount']))- $order['user_money'] - $order['integral_money'] - $order['coupon_price'];
            $update['order_amount'] = $order['goods_price'] + $update['shipping_price'] - $order['user_money'] - $order['integral_money'] - $update['coupon_price'];
            $row = M('order')->where(array('order_id'=>$order_id))->save($update);
            if(!$row){
                //写日志
                $model = M('action');
                $userName = session('userName');
                $actionData = json_encode($update);
                $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},set={$actionData}','价钱修改')";
                $model->execute($sql);
                $this->success('没有更新数据',U('Admin/Order/editprice',array('order_id'=>$order_id)));
            }else{
                $this->success('操作成功',U('Admin/Order/detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        $this->assign('order',$order);
        return $this->fetch();
    }

    /**
     * 订单删除
     * @param int $id 订单id
     */
    public function delete_order($order_id){
    	// $orderLogic = new OrderLogic();
    	// $del = $orderLogic->delOrder($order_id);
        // $data = array(
        //     'delete'=>1
        //     );

        //写日志
        $model = M('action');
        $model->startTrans();
        $userName = session('userName');
        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},deleted=1','订单状态删除')";
        $ret = $model->execute($sql); 
        $upSql = "update {$this->db_prex}order set deleted=1 where order_id={$order_id}";
        $del = $model->execute($upSql);
        if($del && $ret){
            $model->commit();
            $this->success('删除订单成功');
        }else{
            $model->rollback();
        	$this->error('订单删除失败');
        }
    }
    
    /**
     * 订单取消付款
     */
    public function pay_cancel($order_id){
    	if(I('remark')){
    		$data = I('post.');
    		$note = array('退款到用户余额','已通过其他方式退款','不处理，误操作项');
    		if($data['refundType'] == 0 && $data['amount']>0){
    			accountLog($data['user_id'], $data['amount'], 0,  '退款到用户余额');
    		}
    		$orderLogic = new OrderLogic();
            $orderLogic->orderProcessHandle($data['order_id'],'pay_cancel');
    		$d = $orderLogic->orderActionLog($data['order_id'],'pay_cancel',$data['remark'].':'.$note[$data['refundType']]);

    		if($d){
                //写日志
                $model = M('action');
                $userName = session('userName');
                $actionData = json_encode($data);
                $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},set={$actionData}','订单取消付款')";
                $model->execute($sql);
    			exit("<script>window.parent.pay_callback(1);</script>");
    		}else{
    			exit("<script>window.parent.pay_callback(0);</script>");
    		}
    	}else{
    		$order = M('order')->where("order_id=$order_id")->find();
    		$this->assign('order',$order);
    		return $this->fetch();
    	}
    }

    /**
     * 订单打印
     * @param int $id 订单id
     */
    public function order_print(){
    	$order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        $order['full_address'] = $order['province'].' '.$order['city'].' '.$order['district'].' '. $order['address'];
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $shop = tpCache('shop_info');
        $this->assign('order',$order);
        $this->assign('shop',$shop);
        $this->assign('orderGoods',$orderGoods);
        $template = I('template','print');
        return $this->fetch($template);
    }

    /**
     * 快递单打印
     */
    public function shipping_print(){
        $order_id = I('get.order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        //查询是否存在订单及物流
        $shipping = M('plugin')->where(array('code'=>$order['shipping_code'],'type'=>'shipping'))->find();        
        if(!$shipping){
        	$this->error('物流插件不存在');
        }
		if(empty($shipping['config_value'])){
			$this->error('请设置'.$shipping['name'].'打印模板');
		}
        $shop = tpCache('shop_info');//获取网站信息
        $shop['province'] = empty($shop['province']) ? '' : getRegionName($shop['province']);
        $shop['city'] = empty($shop['city']) ? '' : getRegionName($shop['city']);
        $shop['district'] = empty($shop['district']) ? '' : getRegionName($shop['district']);

        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        if(empty($shipping['config'])){
        	$config = array('width'=>840,'height'=>480,'offset_x'=>0,'offset_y'=>0);
        	$this->assign('config',$config);
        }else{
        	$this->assign('config',unserialize($shipping['config']));
        }
        $template_var = array("发货点-名称", "发货点-联系人", "发货点-电话", "发货点-省份", "发货点-城市",
        		 "发货点-区县", "发货点-手机", "发货点-详细地址", "收件人-姓名", "收件人-手机", "收件人-电话", 
        		"收件人-省份", "收件人-城市", "收件人-区县", "收件人-邮编", "收件人-详细地址", "时间-年", "时间-月", 
        		"时间-日","时间-当前日期","订单-订单号", "订单-备注","订单-配送费用");
        $content_var = array($shop['store_name'],$shop['contact'],$shop['phone'],$shop['province'],$shop['city'],
        	$shop['district'],$shop['phone'],$shop['address'],$order['consignee'],$order['mobile'],$order['phone'],
        	$order['province'],$order['city'],$order['district'],$order['zipcode'],$order['address'],date('Y'),date('M'),
        	date('d'),date('Y-m-d'),$order['order_sn'],$order['admin_note'],$order['shipping_price'],
        );
        $shipping['config_value'] = str_replace($template_var,$content_var, $shipping['config_value']);
        $this->assign('shipping',$shipping);
        return $this->fetch("Plugin/print_express");
    }

    /**
     * 生成发货单
     */
    public function deliveryHandle(){
        $orderLogic = new OrderLogic();
            $data = I('post.');
            //写日志
            $model = M('action');
            $userName = session('userName');
            $orderId = $data['order_id'];
            $actionData = json_encode($data);
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId},set={$actionData}','生成发货单')";
            $model->execute($sql);
            $res = $orderLogic->deliveryHandle($data);
            if($res){
                $this->success('操作成功',U('Admin/Order/delivery_info',array('order_id'=>$data['order_id'])));
            }else{
                $this->success('操作失败',U('Admin/Order/delivery_info',array('order_id'=>$data['order_id'])));
            }
    }

    
    public function delivery_info(){
    	$order_id = I('order_id');
    	$orderLogic = new OrderLogic();
    	$order = $orderLogic->getOrderInfo($order_id);
    	$orderGoods = $orderLogic->getOrderGoods($order_id);
		$delivery_record = M('delivery_doc')->alias('d')->join('__ADMIN__ a','a.admin_id = d.admin_id')->where('d.order_id='.$order_id)->select();
		if($delivery_record){
			$order['invoice_no'] = $delivery_record[count($delivery_record)-1]['invoice_no'];
		}
        $shipping = DB::query("select shipping_name from {$this->db_prex}shipping where enabled=1");
        $this->assign('shipping',$shipping);
		$this->assign('order',$order);
		$this->assign('orderGoods',$orderGoods);
		$this->assign('delivery_record',$delivery_record);//发货记录
    	return $this->fetch();
    }
    
    /**
     * 发货单列表
     */
    public function delivery_list(){
        return $this->fetch();
    }
	
    /*
     * ajax 退货订单列表
     */
    public function ajax_return_list(){
        // 搜索条件        
        $order_sn =  trim(I('order_sn'));
        $order_by = I('order_by') ? I('order_by') : 'id';
        $sort_order = I('sort_order') ? I('sort_order') : 'desc';
        $status =  I('status');
        
        $where = " 1 = 1 ";
        $order_sn && $where.= " and order_sn like '%$order_sn%' ";
        empty($order_sn) && $where.= " and status = '$status' ";
        $count = M('return_goods')->where($where)->count();
        $Page  = new AjaxPage($count,13);
        $show = $Page->show();
        $list = M('return_goods')->where($where)->order("$order_by $sort_order")->limit("{$Page->firstRow},{$Page->listRows}")->select();        
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr)){
            $goods_list = M('goods')->where("goods_id in (".implode(',', $goods_id_arr).")")->getField('goods_id,goods_name');
        }
        $this->assign('goods_list',$goods_list);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }
    
    /**
     * 删除某个退换货申请
     */
    public function return_del(){
        $id = I('get.id');
        M('return_goods')->where("id = $id")->delete();
        //写日志
        $model = M('action');
        $userName = session('userName');
        $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','return_goods','id={$id}','删除某个退换货申请')";
        $model->execute($sql); 
        $this->success('成功删除!');
    }
    /**
     * 退换货操作
     */
    public function return_info()
    {
        $id = I('id');
        $return_goods = M('return_goods')->where("id= $id")->find();
        if($return_goods['imgs'])            
             $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $user = M('users')->where("user_id = {$return_goods[user_id]}")->find();
        $goods = M('goods')->where("goods_id = {$return_goods[goods_id]}")->find();
        $type_msg = array('退换','换货');
        $status_msg = array('未处理','处理中','已完成');
        if(IS_POST)
        {
            $data['type'] = I('type');
            $data['status'] = I('status');
            $data['remark'] = I('remark');                                    
            $note ="退换货:{$type_msg[$data['type']]}, 状态:{$status_msg[$data['status']]},处理备注：{$data['remark']}";
            $result = M('return_goods')->where("id= $id")->save($data);    
            if($result)
            {        
            	$type = empty($data['type']) ? 2 : 3;
            	$where = " order_id = ".$return_goods['order_id']." and goods_id=".$return_goods['goods_id'];
            	M('order_goods')->where($where)->save(array('is_send'=>$type));//更改商品状态        
                $orderLogic = new OrderLogic();
                $log = $orderLogic->orderActionLog($return_goods[order_id],'refund',$note);

                //写日志
                $model = M('action');
                $userName = session('userName');
                $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','return_goods','id={$id}','退换货操作')";
                $model->execute($sql);

                $this->success('修改成功!');            
                exit;
            }  
        }        
        
        $this->assign('id',$id); // 用户
        $this->assign('user',$user); // 用户
        $this->assign('goods',$goods);// 商品
        $this->assign('return_goods',$return_goods);// 退换货               
        return $this->fetch();
    }
    
    /**
     * 管理员生成申请退货单
     */
    public function add_return_goods()
   {                
            $order_id = I('order_id'); 
            $goods_id = I('goods_id');
                
            $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id")->find();            
            if(!empty($return_goods))
            {
                $this->error('已经提交过退货申请!',U('Admin/Order/return_list'));
                exit;
            }
            $order = M('order')->where("order_id = $order_id")->find();
            
            $data['order_id'] = $order_id; 
            $data['order_sn'] = $order['order_sn']; 
            $data['goods_id'] = $goods_id; 
            $data['addtime'] = time(); 
            $data['user_id'] = $order[user_id];            
            $data['remark'] = '管理员申请退换货'; // 问题描述            
            M('return_goods')->add($data);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order|return_goods','orderId={$order_id},goodsId={$goods_id}','管理员生成申请退货单')";
            $model->execute($sql);

            $this->success('申请成功,现在去处理退货',U('Admin/Order/return_list'));
            exit;
    }

    /**
     * 订单操作
     * @param $id
     */
    public function order_action(){    	
        $orderLogic = new OrderLogic();
        $action = I('get.type');
        $order_id = I('get.order_id');
        if($action && $order_id){
            $notes='';
            if($action =='pay'){
                $notes='商家付款';
            }
            if($action=='pay_cancel'){
                $notes='商家取消付款';
            }
            if($action=='confirm'){
                $notes='商家确认了订单';
            }
            if($action=='cancel'){
                $notes='商家取消确认订单';
            }
            if($action=='invalid'){
                $notes='商家作废了订单';
            }
            if($action=='remove'){
                $notes='商家移除了订单';
            }
            if($action=='delivery_confirm'){
                $notes='商家确认收货';
            }
            if($notes && $action!=='pay'){
                $res = $orderLogic->orderActionLog($order_id,$action,$notes);
            }
            $a = $orderLogic->orderProcessHandle($order_id,$action,array('note'=>$notes,'admin_id'=>0));

            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},set={$action}','{$notes}')";
            $model->execute($sql);
            if($res !== false && $a !== false){
                if ($action == 'remove') {
                    exit(json_encode(array('status' => 1, 'msg' => '操作成功', 'data' => array('url' => U('admin/order/index')))));
                }
                   exit(json_encode(array('status' => 1,'msg' => '操作成功')));
            }else{
                if ($action == 'remove') {
                    exit(json_encode(array('status' => 0, 'msg' => '操作失败', 'data' => array('url' => U('admin/order/index')))));
                }
                    exit(json_encode(array('status' => 0,'msg' => '操作失败')));
            }
        }else{
            $this->error('参数错误',U('Admin/Order/detail',array('order_id'=>$order_id)));
        }
    }
    
    public function order_log(){
    	$timegap = I('timegap');
    	if($timegap){
    		$gap = explode('-', $timegap);
    		$begin = strtotime($gap[0]);
    		$end = strtotime($gap[1]);
    	}else{
    	    //@new 兼容新模板
    	    $begin = strtotime(I('timegap_begin'));
    	    $end = strtotime(I('timegap_end'));
    	}
    	$condition = array();
    	$log =  M('order_action');
    	if($begin && $end){
    		$condition['log_time'] = array('between',"$begin,$end");
    	}
    	$admin_id = I('admin_id');
		if($admin_id >0 ){
			$condition['action_user'] = $admin_id;
		}
    	$count = $log->where($condition)->count();
    	$Page = new Page($count,20);
    	foreach($condition as $key=>$val) {
    		$Page->parameter[$key] = urlencode($val);
    	}
    	$show = $Page->show();
    	$list = $log->where($condition)->order('action_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
    	$this->assign('list',$list);
    	$this->assign('pager',$Page);
    	$this->assign('page',$show);   	
    	$admin = M('admin')->getField('admin_id,user_name');
    	$this->assign('admin',$admin);    	
    	return $this->fetch();
    }

    /**
     * 检测订单是否可以编辑
     * @param $order
     */
    private function editable($order){
        if($order['shipping_status'] != 0){
            $this->error('已发货订单不允许编辑');
            exit;
        }
        return;
    }

    // public function export_order()
    // {
    //     //搜索条件
    //     $where = 'where 1=1 ';
    //     $consignee = I('consignee');
        
    //     if($consignee){
    //         $where .= " AND o.consignee like '%$consignee%' ";
    //     }
    //     $order_sn =  I('order_sn');
    //     if($order_sn){
    //         $where .= " AND o.order_sn = '$order_sn' ";
    //     }
    //     if(I('order_status')){
    //         $where .= " AND o.order_status = ".I('order_status');
    //     }
    //     if(I('shipping_status')){
    //         $where .= " AND o.shipping_status = ".I('shipping_status');
    //     }
    //     if(I('pay_code')){
    //         $where .= " AND o.pay_code = ".I('pay_code');
    //     }
    //     if(I('pay_status')){
    //         $where .= " AND o.pay_status = ".I('pay_status');
    //     }

    //     $level = I('level_one');
    //     $level_two = I('level_two');


    //     // $timegap = I('timegap');
    //     // // if($timegap){
    //     //     $gap = explode('-', $timegap);
    //     //     $begin = strtotime($gap[0]);
    //     //     $end = strtotime($gap[1]);
    //     //     $where .= " AND o.add_time>$begin and o.add_time<$end ";
    //     // }
    //     $begin = strtotime(I('add_time_begin'));
    //     $end = strtotime(I('add_time_end'));
    //     if($begin && !$end){
    //         $where .= " AND o.add_time>$begin ";
    //     }elseif(!$begin && $end){
    //         $where .= " AND o.add_time<$end ";
    //     }elseif($begin && $end){
    //         $where .= " AND o.add_time>$begin and o.add_time<$end ";
    //     }
    //     $ids = I('ids');
    //     $orderList = $userIdArr= $pid = $gid = array();
    //     $userIdStr = '';
    //     if($ids){
    //         $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_id in({$ids})"; 
    //     }else{
    //         if($level){
    //             $pidSql = "select user_id from __PREFIX__users where parentUser={$level}";
    //             $pid = DB::query($pidSql);//查询二级用户ID
    //             foreach($pid as $value) {
    //                 $gidSql = "select user_id from __PREFIX__users where parentUser={$value['user_id']}";
    //                 $gid = DB::query($gidSql);//查询所有三级用户ID
    //             }
    //             $userIdArr = array_merge($pid,$gid);//合并ID数组
    //             foreach ($userIdArr as $key => $value) {
    //                 $userIdStr .=$value['user_id'].',';
    //             }
    //             if($level && !$level_two){
    //                 $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($userIdStr$level) order by o.order_id";
    //             }elseif($level && $level_two){
    //                 switch ($level_two) {
    //                     case 'two':
    //                         $pidStr = '';
    //                         foreach ($pid as $value) {
    //                             $pidStr .= $value['user_id'].',';
    //                         }
    //                         $pidStr = trim($pidStr,',');
    //                         $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($pidStr) order by o.order_id";
    //                         break;
    //                     case 'three':
    //                         $gidStr = '';
    //                         foreach ($gid as $value) {
    //                             $gidStr .=$value['user_id'];
    //                         }
    //                         $gidStr = trim($gidStr,',');
    //                         $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($gidStr) order by o.order_id";
    //                         break;
    //                     case 'two_three':
    //                         $userIdStr = trim($userIdStr,',');
    //                         $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($userIdStr) order by o.order_id";
    //                         break;    
    //                     default:
    //                         break;
    //                 }
    //             }
    //         }else{
    //             $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where order by o.order_id";  
    //         }       
    //     }

    //     $orderList = DB::query($sql);
    //     $strTable ='<table width="500" border="1">';
    //     $strTable .= '<tr>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">仓库名称</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户名称</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">电话号码</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单状态</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">支付状态</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">订单时间</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">付款时间</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品名称</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品编码</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品单价</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">付款单价</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品数量</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品总价</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">积分金额</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">应付金额</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="150px">购买人-身份证</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">收件人-姓名</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">收件人-地址</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="20px">运费</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="50px">支付单号</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="50px">支付方式</td>';
    //     $strTable .= '</tr>';
    //     if(is_array($orderList)){
    //         $region = M('region')->getField('id,name');
    //         foreach($orderList as $k=>$val){
    //             $strTable .= '<tr>';
    //             $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
    //             $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['warehouse_name'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['buyerName'].' </td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].' </td>';
    //             $order_status = '';
    //             switch($val['order_status']){
    //                 case 0:
    //                     $order_status="待确认";
    //                     break;
    //                 case 1:
    //                     $order_status="已确认";
    //                     break;
    //                 case 2:
    //                     $order_status="已收货";
    //                     break;
    //                 case 3:
    //                     $order_status="已取消";
    //                     break;
    //                 case 4:
    //                     $order_status="已完成";
    //                     break;
    //                 case 5:
    //                     $order_status="已作废";
    //                     break;
    //                 case 6:
    //                     $order_status="退款";
    //                     break;               
    //                 default:
    //                     $order_status="其他";
    //                     break;
    //             }
    //             $buyerIdNumber = "&nbsp;".$val['buyerIdNumber'];
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$order_status.'</td>';
    //             $pay_status = '';
    //             switch($val['pay_status']){
    //                 case 0:
    //                     $pay_status = '未支付';
    //                     break;
    //                 case 1:
    //                     $pay_status = '已支付';
    //                     break;
    //                 case 2:
    //                     $pay_status = '支付中';
    //                     break;
    //                 case 3:
    //                     $pay_status = '支付失败';
    //                     break;
    //                 default:
    //                     $pay_status = '未知异常';
    //                     break;
    //             }
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$pay_status.'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['create_time'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['pay_time']).'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['member_goods_price'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_num'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['member_goods_price']*$val['goods_num'].'</td>';              
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['integral_money'].'</td>';                      
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$buyerIdNumber.'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'."{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}".'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['freight'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['payOrderNo'].'</td>';
    //             $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_name'].'</td>';
    //             $strTable .= '</tr>';
    //         }
    //     }
    //     $strTable .='</table>';
    //     unset($orderList);
    //     downloadExcel($strTable,'order');
    //     exit();
    // }
    
    /**
     * 退货单列表
     */
    public function return_list(){
        return $this->fetch();
    }
    
    /**
     * 添加一笔订单
     */
    public function add_order()
    {
        $order = array();
        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取订单城市
        $city =  M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
        //  获取订单地区
        $area =  M('region')->where(array('parent_id'=>$order['city'],'level'=>3))->select();
        //  获取配送方式
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping'))->select();
        //  获取支付方式
        $payment_list = M('plugin')->where(array('status'=>1,'type'=>'payment'))->select();
        if(IS_POST)
        {
            $order['user_id'] = I('user_id');// 用户id 可以为空
            $order['consignee'] = I('consignee');// 收货人
            $order['province'] = I('province'); // 省份
            $order['city'] = I('city'); // 城市
            $order['district'] = I('district'); // 县
            $order['address'] = I('address'); // 收货地址
            $order['mobile'] = I('mobile'); // 手机           
            $order['invoice_title'] = I('invoice_title');// 发票
            $order['admin_note'] = I('admin_note'); // 管理员备注            
            $order['order_sn'] = date('YmdHis').mt_rand(1000,9999); // 订单编号;
            $order['admin_note'] = I('admin_note'); // 
            $order['add_time'] = time(); //                    
            $order['shipping_code'] = I('shipping');// 物流方式
            $order['shipping_name'] = M('plugin')->where(array('status'=>1,'type'=>'shipping','code'=>I('shipping')))->getField('name');            
            $order['pay_code'] = I('payment');// 支付方式            
            $order['pay_name'] = M('plugin')->where(array('status'=>1,'type'=>'payment','code'=>I('payment')))->getField('name');            
                            
            $goods_id_arr = I("goods_id/a");
            $orderLogic = new OrderLogic();
            $order_goods = $orderLogic->get_spec_goods($goods_id_arr);          
            $result = calculate_price($order['user_id'],$order_goods,$order['shipping_code'],0,$order[province],$order[city],$order[district],0,0,0,0);      
            if($result['status'] < 0)	
            {
                 $this->error($result['msg']);      
            } 
           
           $order['goods_price']    = $result['result']['goods_price']; // 商品总价
           $order['shipping_price'] = $result['result']['shipping_price']; //物流费
           $order['order_amount']   = $result['result']['order_amount']; // 应付金额
           $order['total_amount']   = $result['result']['total_amount']; // 订单总价
           $order['wareId'] = 1;//保税仓
            // 添加订单
            $order_id = M('order')->add($order);
            $order_insert_id = DB::getLastInsID();
            //写日志
            $model = M('action');
            $userName = session('userName');
            $actionData = json_encode($order);
            $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_insert_id},set={$actionData}','添加一笔订单')";
            $ret = $model->execute($sql);
            if($order_id && $ret)
            {
                foreach($order_goods as $key => $val)
                {
                    $val['order_id'] = $order_id;
                    $rec_id = M('order_goods')->add($val);
                    if(!$rec_id)
                        $this->error('添加失败');                                  
                }
                $this->success('添加商品成功',U("Admin/Order/detail",array('order_id'=>$order_insert_id)));
                exit();
            }
            else{
                $this->error('添加失败');
            }                
        }

        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=1 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=1";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);     
        $this->assign('shipping_list',$shipping_list);
        $this->assign('payment_list',$payment_list);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);        
        return $this->fetch();
    }
    
    /**
     * 选择搜索商品
     */
    public function search_goods()
    {
    	$brandList =  M("brand")->select();
    	$categoryList =  M("goods_category")->select();
    	$this->assign('categoryList',$categoryList);
    	$this->assign('brandList',$brandList);   	
    	$where = ' is_on_sale = 1 ';//搜索条件
    	I('intro')  && $where = "$where and ".I('intro')." = 1";
    	if(I('cat_id')){
    		$this->assign('cat_id',I('cat_id'));    		
            $grandson_ids = getCatGrandson(I('cat_id')); 
            $where = " $where  and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
                
    	}
        if(I('brand_id')){
            $this->assign('brand_id',I('brand_id'));
            $where = "$where and brand_id = ".I('brand_id');
        }
    	if(!empty($_REQUEST['keywords']))
    	{
    		$this->assign('keywords',I('keywords'));
    		$where = "$where and (goods_name like '%".I('keywords')."%' or keywords like '%".I('keywords')."%')" ;
    	}  	
    	$goodsList = M('goods')->where($where)->order('goods_id DESC')->limit(10)->select();
                
        foreach($goodsList as $key => $val)
        {
            $spec_goods = M('spec_goods_price')->where("goods_id = {$val['goods_id']}")->select();
            $goodsList[$key]['spec_goods'] = $spec_goods;            
        }
        if($goodsList){
            //计算商品数量
            foreach ($goodsList as $value){
                if($value['spec_goods']){
                    $count += count($value['spec_goods']);
                }else{
                    $count++;
                }
            }
            $this->assign('totalSize',$count);
        }
        
    	$this->assign('goodsList',$goodsList);
    	return $this->fetch();
    }
    
    public function ajaxOrderNotice(){
        $order_amount = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();
        echo $order_amount;
    }
	
    //易极付批量支付
    public function payBatch(){
        $orders=I('orders/s')?I('orders'):0;
        $orders=trim($orders,',');
        $userName = session('userName');
        if($orders){
            $orderNo="P".date('YmdHis').str_pad(rand(0,9999),5,'0',STR_PAD_LEFT);
            $model=M('Order');
            $sql="select o.order_id,o.order_sn,o.pay_status,o.order_amount,og.goods_num,og.goods_name,ps.payurl,ps.payid,ps.paytoken,ps.intelpayid,ps.intelpaytoken,ps.payType from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}users as u on u.user_id=o.user_id left join {$this->db_prex}pay_shop as ps on ps.code='yiji' where o.order_id={$orders} and o.pay_status in (0,2,3) and u.userType=1";
            $datas=$model->query($sql);
            //$tradeInfo='';
            if($datas){
                $goodsNames='';
                $count=0;
                foreach($datas as $value){
                    $goodsName=delSpecilaChar($value['goods_name']);
                    $count++;
                }
                if($count>1){
                    $data['goodsName']=$goodsName.'等'.$count.'种商品';
                }else{
                    $data['goodsName']=$goodsName;
                }
                $merchant_private_key_yiji=C('merchant_private_key_yiji');//获取易极付支付私钥，
                $ckey=$datas[0]['paytoken'];
                $data['orderNo']=$orderNo;
                //$data['orderNo']='P2017122014093705589';
                $data['service']='aggregatePay';
                $data['version']='1.0';
                $data['partnerId']=$datas[0]['payid'];
                if($merchant_private_key_yiji==''){
                    $data['signType'] = 'MD5';
                }else {
                    $data['signType']='RSA';
                }
                $data['merchOrderNo']=$datas[0]['order_sn'];
                $data['returnUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/payReturn');
                $data['notifyUrl']= 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/notifyUrl');
                //$data['buyerUserId']=$datas[0]['intelpayid'];
                $data['userTerminalType']='PC';            
                $data['sellerUserId']=$datas[0]['payid'];
                //$data['goodsName']=$datas[0]['intelpayid'];
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
                    //$merchant_private_key_yiji=C('merchant_private_key_yiji');
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
                    $sql="update {$this->db_prex}order set paymentType='yiji',payOrderNo='{$orderNo}',pay_status=2,pay_code='payBatch',pay_name='后台易极付支付',pay_time='{$time}' where order_id={$orders}";
                    $oret=$model->execute($sql);  
                    $sql="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}支付','order','order_id={$datas[0]['order_id']}','拉起了易极付支付')";
                    $model->execute($sql);
                    if($pret && $oret){
                        $this->assign('data',$data);
                    }else{
                        $this->assign('info','拉起支付页面失败，请退出重试');
                    }
                }else{
                    $this->assign('info','网络繁忙，请刷新页面重试');
                }
            }else{
                $this->assign('info','订单数据不存在，请退出重试');
            }
        }else{
            $this->assign('info','订单号不能为空，请退出重试');
        }
        return $this->fetch();
    }

    //智付批量支付
    public function zfPayBatch(){
        $orders=I('orders/s')?I('orders'):0;
        $orders=trim($orders,',');
        $userName = session('userName');
        if($orders){
            $sql="select o.order_id,o.order_sn,o.consignee,o.mobile,o.address,o.buyerName,o.add_time,o.order_amount,og.goods_name,ps.payurl,ps.payid,pr.name as province,cr.name as city from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}users as u on u.user_id=o.user_id left join {$this->db_prex}region as pr on pr.id=o.province left join {$this->db_prex}region as cr on cr.id=o.city left join {$this->db_prex}pay_shop as ps on ps.code='zhifu' where o.order_id in ({$orders}) and o.deleted=0 and o.pay_status in (0,2,3) and u.userType=1";
            $model=M('order');
            $datas=$model->query($sql);
            if($datas) {
                $sqlArr = $post = $result = $data = array();
                $ip = getClientIP();
                $time = time();
                foreach ($datas as $value) {
                    $data[$value['order_id']]['id'] = $value['order_id'];
                    $data[$value['order_id']]['order_no'] = $value['order_sn'];
                    $consignee=delSpecilaChar($value['consignee']);
                    $data[$value['order_id']]['ship_to_name'] = $consignee;
                    $data[$value['order_id']]['ship_to_phone'] = $value['mobile'];
                    $data[$value['order_id']]['ship_to_state'] = $value['province'];
                    $data[$value['order_id']]['ship_to_city'] = $value['city'];
                    $address = $value['address'];
                    $address = delSpecilaChar($address);
                    $data[$value['order_id']]['ship_to_street'] = $address;
                    $buyerName=delSpecilaChar($value['buyerName']);
                    $data[$value['order_id']]['customer_name'] = $buyerName;
                    $data[$value['order_id']]['order_time'] = date('Y-m-d H:i:s', $value['add_time']);
                    $orderAmount = sprintf('%.2f', $value['order_amount']);
                    $data[$value['order_id']]['order_amount'] = $orderAmount;
                    $goodsName = $value['goods_name'];
                    $goodsName = delSpecilaChar($goodsName);
                    $data[$value['order_id']]['product_name'] = $goodsName;
                    $post['merchant_code'] = $value['payid'];
                    $payurl = $value['payurl'];
                }
                $result = array_values($data);
                $order_info = "[";
                foreach ($result as $value) {
                    $post['total_amount'] += $value['order_amount'];
                    $post['total_num']++;
                    $info = '{';
                    $info .= "'order_no':'{$value['order_no']}',";
                    $info .= "'ship_to_name':'{$value['ship_to_name']}',";
                    $info .= "'ship_to_phone':'{$value['ship_to_phone']}',";
                    $info .= "'ship_to_state':'{$value['ship_to_state']}',";
                    $info .= "'ship_to_city':'{$value['ship_to_city']}',";
                    $info .= "'ship_to_street':'{$value['ship_to_street']}',";
                    $info .= "'customer_name':'{$value['customer_name']}',";
                    $info .= "'order_time':'{$value['order_time']}',";
                    $info .= "'order_amount':'{$value['order_amount']}',";
                    $info .= "'product_name':'{$value['product_name']}',";
                    $info = trim($info, ',');
                    $info .= '},';
                    $order_info .= $info;
                    $sqlArr[] = "insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}支付','order','order_id={$value['id']}','拉起了智付支付')";
                }
                $order_info = trim($order_info, ',');
                $order_info .= ']';
                $post['orders_info'] = $order_info;
                $post['batch_num'] = date('mdHi') . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
                $post['input_charset'] = "UTF-8";
                $post['interface_version'] = "V3.0";
                $post['notify_url'] = 'http://'.$_SERVER['SERVER_NAME'].U('/Home/Customs/zfPayNotify');
                $post['service_type'] = "direct_pay";
                $post['payment_type'] = "batch_pay";
                //$post['redo_flag'] =1;
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
                $sql = "insert {$this->db_prex}paylog(orderNo,amount,orderNum,ip) values('{$post['batch_num']}','{$post['total_amount']}',1,'{$ip}')";
                $pret = $model->execute($sql);
                if ($pret) {
                    $sql = "update {$this->db_prex}order set paymentType='zhifu',payOrderNo='{$post['batch_num']}',pay_status=2,pay_code='zhifuPayBatch',pay_name='后台智付支付',pay_time='{$time}' where order_id in ({$orders})";
                    $oret = $model->execute($sql);
                    if($oret){
                        foreach ($sqlArr as $sql) {
                            $model->execute($sql);
                        }
                        $this->assign('data', $post);
                        $this->assign('url',$payurl);
                    }else{
                        $this->assign('info', '拉起支付页面失败，请退出重试');
                    }
                }else{
                    $this->assign('info', '写入支付信息失败');
                }

            }else{
                $this->assign('info','订单数据不存在，请退出重试');
            }
        }else{
            $this->assign('info','订单号不能为空，请退出重试');
        }
        return $this->fetch();
    }

    //重新支付订单
    public function rePay(){
        $orders=I('orders/s')?I('orders/'):'';
        if($orders){
            $model=M('order');
            $orders=trim($orders,',');
            $orderArr=explode(',',$orders);
            $count=0;
            foreach($orderArr as $order){
                $sql="update {$this->db_prex}order set pay_status=0 where order_id={$order} and deleted=0 and order_status=0 and pay_status=2 and payOrderStatus=0 and payCiqOrderStatus=0";
                $ret=$model->execute($sql);
                if($ret){
                    $count++;
                }
            }
            errorMsg(200,'成功重新支付'.$count.'条订单');
        }else{
            errorMsg(400,'请选择订单');
        }
    }

    //重新上传支付单
    public function rePayOrder(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $model=M('Order');
            $orders=trim($orders,',');
            $sql="select order_sn,paymentType from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_id in ({$orders}) and o.pay_status=1 and o.order_status in (0,1)";
            $data=$model->query($sql);
            if($data){
                $result=array();
                $result['retCode']=0;
                $result['success']=0;
                $result['error']=array();
                foreach($data as $ind=>$value){
                    $payFunction=$value['paymentType'].'PayOrder';
                    $payFunction($value['order_sn']);
                    $result['success']++;
                }
                errorMsg(200,'done', $result);
            }else{
                errorMsg(400, '没有需要重新上传支付单的订单');
            }
        }else{
            errorMsg(400, '订单不能为空');
        }
    }
    
    //设置支付单状态为成功过
    public function payOrderSuc(){
        $orders=I('orders')?I('orders'):"";
        if($orders){
            $orders=trim($orders,',');
            if($this->db_prex=='jia_'){
                /*佳源优品特殊处理*/
                $sql="update {$this->db_prex}order set payOrderStatus=3,payCiqOrderStatus=3,cusOrderStatus=3,ciqOrderStatus=3 where order_id in ({$orders})";
            }elseif($this->db_prex=='wj_'){
                /*广州味吉特殊处理*/
                $sql="update {$this->db_prex}order set payOrderStatus=3,payCiqOrderStatus=3,cusOrderStatus=3,ciqOrderStatus=3,isSync=1 where order_id in ({$orders})";
            }else{
                $sql="update {$this->db_prex}order set payOrderStatus=3,payCiqOrderStatus=3 where order_id in ({$orders})";
            }            
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','order_id={$orders}','设置支付单状态为成功过')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }else{
                errorMsg(400, '更新失败，请刷新重试');
            }
        }else{
            errorMsg(400, '订单不能为空');
        }
    }

    //设置支付状态为成功过
    public function payOrder(){
        $orders=I('orders')?I('orders'):"";
        if($orders){
            $orders=trim($orders,',');
            $paySql = "select order_sn,payOrderStatus from {$this->db_prex} where order_id in ({$orders})";
            $payRet = M('Order')->query();
            $check = false;
            foreach ($payRet as $key => $value) {
                if($value['payOrderStatus']!=3){
                    $check = true;
                }
            }
            if($check){
                errorMsg(400, '订单中有支付单还未成功！请检查');
            }
            $sql="update {$this->db_prex}order set pay_status=1 where order_id in ({$orders})";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','order_id={$orders}','设置支付状态为成功')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }else{
                errorMsg(400, '更新失败，请刷新重试');
            }
        }else{
            errorMsg(400, '订单不能为空');
        }
    }
    
    //重新申报海关订单
    public function reCusOrder(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $orders=trim($orders,',');
            $sql="update {$this->db_prex}order set pay_status=1,cusOrderStatus=0 where order_id in ({$orders})";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderIds={$orders},pay_status=1,cusOrderStatus=0','重新申报海关订单')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }elseif($ret==0){
                errorMsg(400, '没有需要更新的数据');
            }else{
                errorMsg(400, '更新失败，请刷新重试');
            }
        }else{
            errorMsg(400, '订单不能为空');
        }
    }
    
    //设置海关订单状态为成功
    public function cusOrderSuc(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $orders=trim($orders,',');
            $sql="update {$this->db_prex}order set cusOrderStatus=3 where order_id in ({$orders}) and cusOrderStatus!=0";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderIds={$orders},cusOrderStatus=3','设置海关订单状态为成功')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }elseif($ret==0){
                errorMsg(400, '没有需要更新的数据');
            }else{
                errorMsg(400, '更新失败，请刷新重试');
            }
        }else{
            errorMsg(400, '订单不能为空');
        }
    }
    
    //重新申报国检订单
    public function reCiqOrder(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $orders=trim($orders,',');
            $sql="update {$this->db_prex}order set pay_status=1,ciqOrderStatus=0 where order_id in ({$orders})";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderIds={$orders},pay_status=1,ciqOrderStatus=0','设置海关订单状态为成功')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }elseif($ret==0){
                errorMsg(400, '没有需要更新的数据');
            }else{
                errorMsg(400, '更新失败，请刷新重试');
            }
        }else{
            errorMsg(400, '订单不能为空');
        }
    }
    
    //设置国检订单状态为成功
    public function ciqOrderSuc(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $orders=trim($orders,',');
            $sql="update {$this->db_prex}order set ciqOrderStatus=3 where order_id in ({$orders}) and ciqOrderStatus!=0";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderIds={$orders},cusOrderStatus=3','设置国检订单状态为成功')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }elseif($ret==0){
                errorMsg(400, '没有需要更新的数据');
            }else{
                errorMsg(400, '更新失败，请刷新重试');
            }
        }else{
            errorMsg(400, '订单不能为空');
        }
    }
    
    //一键同步订单
    public function syncOrder(){
        $model=M('order');
        $orders=I('orders/s')?I('orders/s'):'';
        if(!$orders){
            errorMsg(400,'请选择需要推送的订单');
        }
        $orders=trim($orders,',');
        $sql="select s.warehouseCode,s.shopCode,s.shopSercet,s.apiUrl,o.order_id,o.order_sn,o.consignee,o.address,o.mobile,o.buyerRegNo,o.buyerName,o.buyerIdNumber,o.goods_price,o.coupon_price,o.isLoad,o.integral_money,o.taxTotal,o.order_amount,o.add_time,o.pay_time,o.user_note,o.city,o.district,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price as ogoods_price,og.member_goods_price,r.name as province,pf.name as pfName from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}region as r on r.id=o.province left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as s on s.id=w.apiId left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}platform as pf on pf.id=p.platId where o.order_id in ({$orders}) and w.warehouse_type=1 and o.deleted=0 and o.order_status=0 and o.pay_status=1 and o.payOrderStatus=3 and o.cusOrderStatus in (3,5) and o.ciqOrderStatus in (3,5) and (o.isSync=0 or o.isSync=2) and s.isBaoshui=1 order by o.order_id desc,og.gNum asc";
        $datas=$model->query($sql);
        $sqlArr=$back=$data=$goodsInfo=$dataChongQing=array();
        $result='';
        if($datas){
            foreach($datas as $key=>$value){
                if($value['pfName']=='sdms'){
                    $data[$value['order_id']]['wareCode']=$value['warehouseCode'];
                    $data[$value['order_id']]['shopCode']=$value['shopCode'];
                    $data[$value['order_id']]['shopSercet']=$value['shopSercet'];
                    $data[$value['order_id']]['orderSn']=$value['order_sn'];
                    $data[$value['order_id']]['appType']=1;
                    $data[$value['order_id']]['appStatus']=1;
                    $data[$value['order_id']]['consignee']=$value['consignee'];
                    $data[$value['order_id']]['province']=$value['province'];
                    $sql="select name as city from {$this->db_prex}region where id={$value['city']}";   
                    $cityArr=$model->query($sql);
                    $data[$value['order_id']]['city']=$cityArr[0]['city'];
                    $sql="select name as district from {$this->db_prex}region where id={$value['district']}";   
                    $districtArr=$model->query($sql);
                    if($districtArr){
                        $data[$value['order_id']]['district']=$districtArr[0]['district'];
                    }else{
                        $data[$value['order_id']]['district']='';  
                    }
                    $data[$value['order_id']]['address']=$value['address'];
                    $data[$value['order_id']]['mobile']=$value['mobile'];
                    $data[$value['order_id']]['buyerRegNo']=$value['buyerRegNo'];
                    $data[$value['order_id']]['buyerName']=$value['buyerName'];
                    $data[$value['order_id']]['buyerIdType']=1;
                    $data[$value['order_id']]['buyerIdNumber']=$value['buyerIdNumber'];
                    $data[$value['order_id']]['goodsValue']=$value['goods_price'];
                    $data[$value['order_id']]['freight']=0;
                    $data[$value['order_id']]['insuredFee']=0;
                    $data[$value['order_id']]['taxTotal']=$value['taxTotal'];
                    $data[$value['order_id']]['discount']=$value['coupon_price']+$value['integral_money'];
                    $data[$value['order_id']]['goodsValue']=$value['goods_price'];
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
                    $data[$value['order_id']]['info']='';
                    $goodsInfo[$value['order_id']][$value['gNum']]['gNum']=$value['gNum'];
                    $goodsInfo[$value['order_id']][$value['gNum']]['goodsName']=$value['goods_name'];
                    $goodsInfo[$value['order_id']][$value['gNum']]['goodsCode']=$value['goods_sn'];
                    $goodsInfo[$value['order_id']][$value['gNum']]['qty']=$value['goods_num'];
                    $goodsInfo[$value['order_id']][$value['gNum']]['price']=$value['member_goods_price'];  
                }elseif($value['pfName']=='重庆西永'){
                    $dataChongQing[$value['order_id']]['order_id']=$value['order_id'];
                    $dataChongQing[$value['order_id']]['order_sn']=$value['order_sn'];
                }
            }
            $back['success']=0;
            $back['error']=array();
            if($data){
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
                    if($ret['success']){
                        foreach($ret['success'] as $key=>$value){
                            $sqlArr[]="update {$this->db_prex}order set isSync=1 where order_sn='{$value['orderSn']}'";
                            $back['success']++;
                            //写日志
                            $userName = session('userName');
                            $orderSn = $value['orderSn'];
                            $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderSn={$orderSn},isSync=1','一键同步订单')";
                        }
                    }
                    if($ret['error']){
                        foreach($ret['error'] as $key=>$value){
                            if($value['retCode']==400){
                                $sqlArr[]="update {$this->db_prex}order set isSync=2,syncInfo='{$value['retMessage']}' where order_sn='{$value['orderSn']}'";
                                //写日志
                                $userName = session('userName');
                                $orderSn = $value['orderSn'];
                                $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderSn={$orderSn},isSync=2','一键同步订单')";
                            }elseif($value['retCode']==300){

                                $sqlArr[]="update {$this->db_prex}order set isSync=3,syncInfo='{$value['retMessage']}' where order_sn='{$value['orderSn']}'";
                                //写日志
                                $userName = session('userName');
                                $orderSn = $value['orderSn'];
                                $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderSn={$orderSn},isSync=3','一键同步订单')";
                            }
                        }
                        $back['error']=$ret['error'];
                    }
                }else{
                    errorMsg(400, '同步数据出错，请联系接口开发');
                }
            }
            if($dataChongQing){
                $userName = session('userName');
                foreach($dataChongQing as $chongqing){
                    $back['success']++;
                    $sqlArr[]="update {$this->db_prex}order set isSync=0 where order_id='{$chongqing['order_id']}'";
                    $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderSn={$chongqing['order_sn']},isSync=0','一键同步订单，设置重新同步')";
                }
            }
            if($sqlArr){
                $ret=true;
                foreach($sqlArr as $sql){
                    $model->execute($sql);
                }
            }
            errorMsg(200, 'done',$back);
        }else{
            errorMsg(400, '没有需要同步的订单');
        }
    }

    public function syncLogisticCode(){
        $model=M('order');
        //$sql="select o.order_sn,s.shopCode,s.shopSercet from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as s on s.id=w.apiId WHERE w.warehouse_type=1 AND ( o.order_status=0 OR o.order_status=1 ) AND o.shipping_status=0 AND o.pay_status=1 AND o.payOrderStatus=3 AND o.cusOrderStatus=3 AND o.ciqOrderStatus=3 AND ( o.isSync=1 OR o.isSync=3 ) AND o.shipping_code='0'";
        $sql="select o.order_sn,s.shopCode,s.shopSercet from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as s on s.id=w.apiId left join {$this->db_prex}port as p on p.id=o.portId left join {$this->db_prex}platform as pf on pf.id=p.platId WHERE w.warehouse_type=1 AND ( o.order_status=0 OR o.order_status=1 ) AND o.shipping_status=0 AND o.pay_status=1 AND o.payOrderStatus=3 AND o.cusOrderStatus=3 AND o.ciqOrderStatus=3 AND ( o.isSync=1 OR o.isSync=3 ) AND o.shipping_code='0' and s.isBaoshui=1 and pf.name='sdms'";
        $datas=$model->query($sql);
        $orderStr = '';
        foreach($datas as $key => $value) {
            $orderStr .= "'".$value['order_sn']."',";
        }
        $orders = trim($orderStr,',');
        $shopCode = $datas[0]['shopCode'];
        $shopSercet = $datas[0]['shopSercet'];
        $ch = curl_init();
        // curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_URL,"http://a.gdyyb.com/Home/Customs/getMoreLogisticCode?shopCode=".$shopCode."&shopSercet=".$shopSercet."&orders=".$orders);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->log.'/cookie.txt'); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); 
        curl_setopt($ch, CURLOPT_TIMEOUT,70);
        $response = curl_exec($ch);
        curl_close($ch);
        $ret=  json_decode($response,true);
        $sqlArr =array();
        if($ret['statusCode']==200){
            $back['success']=0;
            foreach($ret['data'] as $key=>$value){

                $sqlArr[]="update {$this->db_prex}order set shipping_code='{$value['logisticsCode']}',shipping_name='{$value['logisticsName']}',shipping_status=1,order_status=1 where order_sn='{$value['orderSn']}'";
                $orderSql = "select order_id,shipping_status,order_status,pay_status from {$this->db_prex}order where order_sn='{$value['orderSn']}'";
                $orderList = $model->query($orderSql);
                if($orderList[0]['order_status']==0){//如果order_status=0,生成一条确认订单日志，生成一条订单发货日志
                    $sqlArr[]="insert into {$this->db_prex}order_action(order_id,action_user,order_status,shipping_status,pay_status,action_note,log_time,status_desc) values ({$orderList[0]['order_id']},{$this->setId},1,0,1,'商家确认了订单', unix_timestamp(now()),'confirm')";
                }
                $sqlArr[]="insert into {$this->db_prex}order_action (order_id,action_user,order_status,shipping_status,pay_status,action_note,log_time,status_desc) values ({$orderList[0]['order_id']},{$this->setId},1,1,1,'商家发货', unix_timestamp(now())+5,'delivery')";

                //写日志
                $userName = session('userName');
                $orderSn = $value['orderSn'];
                $sqlArr[]="insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderSn={$orderSn},shipping_status=1,order_status=1','同步快递单')";
                $back['success']++;
            }
            if($sqlArr){
                foreach($sqlArr as $sql){
                    $model->execute($sql);
                }
            }
            errorMsg(200, 'done',$back);
        }else{
            errorMsg(400, $ret['retMessage']);
        }    
    }

    //一键删除
    public function delSome(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $orders=trim($orders,',');
            $sql="update {$this->db_prex}order set deleted=1 where order_id in ({$orders})";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2 = "insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','order_id={$orders}','一键删除')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }elseif($ret==0){
                errorMsg(400, '没有需要更新的数据');
            }else{
                errorMsg(400, '更新失败，请刷新重试');
            }
        }
    }

    //一级用户订单查询导出
    public function order_search(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days')); 
        $level = M('users')->field('user_id,username,nickname,mobile')->where('myLevel=3')->select();
        // dump($level);exit;
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('level',$level);
        return $this->fetch();
    }

    public function export_order(){
        //搜索条件
        $where = 'where 1=1 ';
        $consignee = I('consignee');
        
        if($consignee){
            $where .= " AND o.consignee like '%$consignee%' ";
        }
        $order_sn =  I('order_sn');
        if($order_sn){
            $where .= " AND o.order_sn = '$order_sn' ";
        }
        if(I('order_status')){
            $where .= " AND o.order_status = ".I('order_status');
        }
        if(I('shipping_status')){
            $where .= " AND o.shipping_status = ".I('shipping_status');
        }
        if(I('pay_code')){
            $where .= " AND o.pay_code = ".I('pay_code');
        }
        if(I('pay_status')){
            $where .= " AND o.pay_status = ".I('pay_status');
        }

        $level = I('level_one');
        $level_two = I('level_two');


        // $timegap = I('timegap');
        // // if($timegap){
        //     $gap = explode('-', $timegap);
        //     $begin = strtotime($gap[0]);
        //     $end = strtotime($gap[1]);
        //     $where .= " AND o.add_time>$begin and o.add_time<$end ";
        // }
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        if($begin && !$end){
            $where .= " AND o.add_time>$begin ";
        }elseif(!$begin && $end){
            $where .= " AND o.add_time<$end ";
        }elseif($begin && $end){
            $where .= " AND o.add_time>$begin and o.add_time<$end ";
        }

        $where .= " AND w.warehouse_type=1 ";
        $ids = I('ids');
        $orderList = $userIdArr= $pid = $gid = array();
        $userIdStr = '';
        if($ids){
            $sql = "select o.*,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_code,r.name as provinceName,r2.name as cityName,r3.name as districtName from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId and w.warehouse_type=2 left join __PREFIX__region as r on r.id=o.province left join __PREFIX__region as r2 on r2.id=o.city left join __PREFIX__region as r3 on r3.id=o.district where o.order_id in({$ids})"; 
        }else{
            if($level){
                $pidSql = "select user_id from __PREFIX__users where parentUser={$level}";
                $pid = DB::query($pidSql);//查询二级用户ID
                foreach($pid as $value) {
                    $gidSql = "select user_id from __PREFIX__users where parentUser={$value['user_id']}";
                    $gid = DB::query($gidSql);//查询所有三级用户ID
                }
                $userIdArr = array_merge($pid,$gid);//合并ID数组
                foreach ($userIdArr as $key => $value) {
                    $userIdStr .=$value['user_id'].',';
                }
                if($level && !$level_two){
                    $sql = "select o.*,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_code,r.name as provinceName,r2.name as cityName,r3.name as districtName from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId and w.warehouse_type=2 left join __PREFIX__region as r on r.id=o.province left join __PREFIX__region as r2 on r2.id=o.city left join __PREFIX__region as r3 on r3.id=o.district $where and o.user_id in($userIdStr$level) order by o.order_id";
                }elseif($level && $level_two){
                    switch ($level_two) {
                        case 'two':
                            $pidStr = '';
                            foreach ($pid as $value) {
                                $pidStr .= $value['user_id'].',';
                            }
                            $pidStr = trim($pidStr,',');
                            $sql = "select o.*,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_code,r.name as provinceName,r2.name as cityName,r3.name as districtName from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId and w.warehouse_type=2 left join __PREFIX__region as r on r.id=o.province left join __PREFIX__region as r2 on r2.id=o.city left join __PREFIX__region as r3 on r3.id=o.district $where and o.user_id in($pidStr) order by o.order_id";
                            break;
                        case 'three':
                            $gidStr = '';
                            foreach ($gid as $value) {
                                $gidStr .=$value['user_id'];
                            }
                            $gidStr = trim($gidStr,',');
                            $sql = "select o.*,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_code,r.name as provinceName,r2.name as cityName,r3.name as districtName from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId and w.warehouse_type=2 left join __PREFIX__region as r on r.id=o.province left join __PREFIX__region as r2 on r2.id=o.city left join __PREFIX__region as r3 on r3.id=o.district $where and o.user_id in($gidStr) order by o.order_id";
                            break;
                        case 'two_three':
                            $userIdStr = trim($userIdStr,',');
                            $sql = "select o.*,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_code,r.name as provinceName,r2.name as cityName,r3.name as districtName from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId and w.warehouse_type=2 left join __PREFIX__region as r on r.id=o.province left join __PREFIX__region as r2 on r2.id=o.city left join __PREFIX__region as r3 on r3.id=o.district $where and o.user_id in($userIdStr) order by o.order_id";
                            break;    
                        default:
                            break;
                    }
                }
            }else{
                $sql = "select o.*,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_code,r.name as provinceName,r2.name as cityName,r3.name as districtName from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId left join __PREFIX__region as r on r.id=o.province left join __PREFIX__region as r2 on r2.id=o.city left join __PREFIX__region as r3 on r3.id=o.district $where order by o.order_id";  
            }       
        }
        // echo $sql;exit;
        $orderList = DB::query($sql);
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">序号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">仓库编码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">收件人姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">收件人省</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">收件人市</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">收件人区</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">收件人地址</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">收件人电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单人ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单人姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">订单人证件类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="300px">订单人证件号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">订单商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">运杂费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">税款</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">非现金抵扣金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">支付金额(实付)</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品项号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品单价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="150px">备注</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">支付流水号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="20px">支付时间</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            $region = M('region')->getField('id,name');
            foreach($orderList as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">'.($k+1).'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['warehouse_code'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['provinceName'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['cityName'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['districtName'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['address'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['mobile'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['buyerRegNo'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['buyerName'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">1</td>';
                $strTable .= '<td style="vnd.ms-excel.numberformat:@">'.strval($val['buyerIdNumber']).' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['freight'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['taxTotal'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['integral_money'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['gNum'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_num'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['member_goods_price'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.($val['member_goods_price']*$val['goods_num']).' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_note'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['tradeNo'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['pay_time']).' </td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'order');
        exit();
    }

    //一键设置订单为收货
    public function set_status(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $orders=trim($orders,',');
            $sql="update {$this->db_prex}order set order_status=2 where order_id in ({$orders})";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2 = "insert {$this->db_prex}action(username,tabName,tabField,notes) values('{$userName}','order','orderIds={$orders}','一键设置成已收货')";
            $res = $model->execute($sql2);
            if($ret && $res){
                errorMsg(200, 'done',$ret);
            }elseif($ret==0){
                errorMsg(400, '没有需要设置为收货的订单数据');
            }else{
                errorMsg(400, '设置失败，请刷新重试');
            }
        }else{
            errorMsg(400, '未选择订单');
        }
    }
    
    //重置商品项号
    public function resetGNum(){
        $orders=I('orders/s')?I('orders/s'):'';
        if($orders){
            $count=0;
            $orders=trim($orders,',');
            $sql="select o.order_id,og.rec_id from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id where o.order_id in ({$orders}) order by o.order_id desc,og.goods_sn asc";
            $model=M('order');
            $datas=$model->query($sql);
            if($datas){
                $result=$data=array();
                foreach($datas as $key=>$value){
                    $data[$value['order_id']]['order_id']=$value['order_id'];
                    $data[$value['order_id']]['goods'][$key]['rec_id']=$value['rec_id'];
                }
                if($data){
                    $result=array_values($data);
                    foreach($result as $value){
                        $num=1;
                        foreach($value['goods'] as $val){
                            $sql="update {$this->db_prex}order_goods set gNum={$num} where rec_id={$val['rec_id']}";
                            $ret=$model->execute($sql);
                            $num++;
                        }
                    }
                    errorMsg(200, 'done');
                }else{
                    errorMsg(400, '重组数据失败');
                }
            }else{
                errorMsg(400, '未查询到数据');
            }
        }else{
            errorMsg(400, '未选择订单');
        }
    }
}
