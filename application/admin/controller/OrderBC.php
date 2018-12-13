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

class OrderBC extends Base {
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
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $warehouseSql = "select warehouse_id,warehouse_name from {$this->db_prex}warehouse where warehouse_type=2";
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
        return $this->fetch();
    }

    /*
     *订单首页
     */
    public function index(){
    	$begin = date('Y-m-d',strtotime("-1 year"));//30天前
    	$end = date('Y/m/d',strtotime('+1 days'));
        $updateSql = "update {$this->db_prex}order set order_status=7 where pay_status=0 and order_status in(0,1) and deleted!=1 and (unix_timestamp(now())-add_time>86400) and stype=2";
        DB::execute($updateSql);    //未处理中超过一天未支付的订单做超时处理
         	
    	$this->assign('timegap',$begin.'-'.$end);
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        // dump($num);exit;
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);
        return $this->fetch();
    }

    public function order1(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days')); 
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']); 
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }

    public function order2(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }
    public function order3(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days')); 
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']); 
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }

    public function order4(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }
    public function order5(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }

    public function order6(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }
    public function order7(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and (o.payOrderStatus!=2 and o.cusOrderStatus!=2 and o.ciqOrderStatus!=2 and o.isSync!=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(2,3,4,5,6) and o.shipping_status!=1 and (o.payOrderStatus=2 or o.cusOrderStatus=2 or o.ciqOrderStatus=2 or o.pay_status=3 or o.isSync=2) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=2 union all select count(*) as num from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=2";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);  
        $this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }

    /*
     *Ajax首页
     */
    public function ajaxindex(){
        session('ajaxpage',I('get.p'));
        $where = '';
        $status = I('status')?I('status'):session('orderBCStatus');
        session('orderBCStatus',$status);
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
                        $sort_order = ' field(o.payOrderStatus,3,4,0,1,2) desc ';
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
                    default:
                        $sort_order = '';
                        break;
                }
            }else{
                $sort_order = '';
            }
        }
        // dump($_SESSION);    
        // dump($sort_order);exit;
        $where .=' and w.warehouse_type=2 ';
        $sql="select count(*) as count from {$this->db_prex}order as o left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where 0=0 {$where}";
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

        $where .= " AND w.warehouse_type=2 ";
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
                $sql = "select o.*,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_code,r.name as provinceName,r2.name as cityName,r3.name as districtName from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId and w.warehouse_type=2 left join __PREFIX__region as r on r.id=o.province left join __PREFIX__region as r2 on r2.id=o.city left join __PREFIX__region as r3 on r3.id=o.district $where order by o.order_id";  
            }       
        }

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
}


