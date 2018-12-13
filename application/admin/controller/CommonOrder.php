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

class CommonOrder extends Base {
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
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);

        $warehouseSql = "select warehouse_id,warehouse_name from {$this->db_prex}warehouse where warehouse_type=3";
        $warehouse = DB::query($warehouseSql);
        $this->assign('warehouse',$warehouse);
        // echo $sql;exit;
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
        $updateSql = "update {$this->db_prex}order set order_status=7 where pay_status=0 and order_status in(0,1) and deleted!=1 and (unix_timestamp(now())-add_time>86400) and stype=3";
        DB::execute($updateSql);    //未处理中超过一天未支付的订单做超时处理
        
    	$this->assign('timegap',$begin.'-'.$end);
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);
        return $this->fetch();
    }


    public function common_order1(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));  
        $this->assign('timegap',$begin.'-'.$end);
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);
        return $this->fetch();
    }

    public function common_order2(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
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
    public function common_order3(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
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

    public function common_order4(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
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
    public function common_order5(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
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

    public function common_order6(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
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
    public function common_order7(){
        $begin = date('Y-m-d',strtotime("-1 year"));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
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
        $status = I('status')?I('status'):session('commonOrderStatus');
        session('commonOrderStatus',$status);
        switch ($status) {
            case 1://未支付
                $where .= ' and o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 ';
                break;
            case 2://处理中
                $where .= ' and o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 ';
                break;
            case 3://异常
                $where .= ' and (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3';
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
                $where .= ' and (o.deleted=1 or o.order_status=5) ';
                break;                        
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
        
        if($begin && $end){
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

        $level = I('level_one');
        $level_two = I('level_two');
        $userIdArr= $pid = $gid = array();
        if($level){
            $pidSql = "select user_id from __PREFIX__users where parentUser={$level}";
            $pid = DB::query($pidSql);//查询二级用户ID
            foreach($pid as $value) {
                $gidSql = "select user_id from __PREFIX__users where parentUser={$value['user_id']}";
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

        I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        I('pay_code') != ''? $where.=" and o.user_id='".trim(I('user_id'))."' ":false;
        $type=I('type/d')?I('type/d'):0;
        session('type',$type);
        if($status == 10){
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
        $where .=' and w.warehouse_type=3 ';
        // dump($where);exit;
        $sql="select count(*) as count from {$this->db_prex}order as o left join {$this->db_prex}users as u on u.user_id=o.user_id left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId where 0=0 {$where}";
        //$count = M('order')->alias('o')->join("{$ware} w on w.warehouse_id=o.wareId")->count();
        $countArr=M('order')->query($sql);
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

        foreach ($orderList as $key => $value) {
            $sql = "select nickname from __PREFIX__users where user_id={$value['user_id']}";
            $user = DB::query($sql);
            $orderList[$key]['nickname'] = $user[0]['nickname'];
        }
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
        //清楚订单用户的myLevel
        unset($_SESSION['orderUserLevel']);
        
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
        $model = M('action');

        $userName = session('userName');
        $shipping_code = I('shipping_code');
        $orderId = I('order_id');
        $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId},shipping_code={$shipping_code}','快递单号修改')";
        $model->execute($sql);
        M('order')->where('order_id',I('order_id'))->save(array('shipping_code'=>I('shipping_code')));
        echo 1;
    }
    //快递公司名称编辑
    public function editShippingName(){
        $arr= explode(',', I('post.newShippingName'));
        $shipping_name=$arr[0];
        $express_code=$arr[1];
        $model = M('action');
        $userName = session('userName');
        $orderId = I('order_id');
        $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$orderId},shipping_name={$shipping_name},express_code={$express_code}','快递快速编辑修改')";
        $model->execute($sql);
        M('order')->where('order_id',I('order_id'))->save(array('shipping_name'=>$shipping_name,'express_code'=>$express_code));
        echo $shipping_name;
    }
    /**
     * 订单编辑
     * @param int $id 订单id
     */
    public function edit_order(){
    	$order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        if($order['shipping_status'] != 0){
            $this->error('已发货订单不允许编辑');
            exit;
        } 
        //编辑订单，根据订单的userId 来知道user的会员等级，从而去获取会员等级对应的商品价格
        $orderUserLevel=M('users')->where("user_id = ".$order['user_id'])->field('myLevel')->find();
        session('orderUserLevel',$orderUserLevel['myLevel']);
        
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
            if($goods_id_arr){
            	$new_goods = $orderLogic->get_spec_goods($goods_id_arr);
            	foreach($new_goods as $key => $val)
            	{
            		$val['order_id'] = $order_id;
            		$rec_id = M('order_goods')->add($val);//订单添加商品
            		if(!$rec_id)
            			$this->error('添加失败');
            	}
            }
            
            //################################订单修改删除商品
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
            		$old_goods_arr[] = $val;
            	}
            }
            
            $goodsArr = array_merge($old_goods_arr,$new_goods);
            $result = calculate_price($order['user_id'],$goodsArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0,0);
            if($result['status'] < 0)
            {
            	$this->error($result['msg']);
            }
       
            //################################修改订单费用
            $order['goods_price']    = $result['result']['goods_price']; // 商品总价
            $order['shipping_price'] = $result['result']['shipping_price'];//物流费
            $order['order_amount']   = $result['result']['order_amount']; // 应付金额
            $order['total_amount']   = $result['result']['total_amount']; // 订单总价

            //写日志
            $actionData = json_encode($order);
            $model = M('action');
            $userName = session('userName');
            $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},set={$actionData}','订单修改')";
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
    	
    	foreach ($orderGoods as $val){
    		$brr[$val['rec_id']] = array('goods_num'=>$val['goods_num'],'goods_name'=>getSubstr($val['goods_name'], 0, 35).$val['spec_key_name']);
    	}
        $orderSn = $order['order_sn'];
        $model = M('action');
        $userName = session('userName');
        $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderSn={$orderSn}','拆分订单')";
        $model->execute($sql);

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
                $model = M('action');
                $userName = session('userName');
                $actionData = json_encode($update);
                $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},set={$actionData}','价钱修改')";
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
        $data = array(
            'deleted'=>1
            );
        $del = M('order')->where('order_id='.$order_id)->save($data);
        //写日志
        $model = M('action');
        $userName = session('userName');
        $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},deleted=1','订单状态删除')";
        $ret = $model->execute($sql);  
        if($del && $ret){
            $this->success('删除订单成功');
        }else{
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
                $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},set={$actionData}','订单取消付款')";
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
        $shipping = DB::query("select * from __PREFIX__shipping where enabled=1");
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
        //写日志
        $model = M('action');
        $userName = session('userName');
        $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','return_goods','id={$id}','删除某个退换货申请')";
        $model->execute($sql); 
        M('return_goods')->where("id = $id")->delete(); 
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
                $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','return_goods','id={$id}','退换货操作')";
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
            $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order|return_goods','orderId={$order_id},goodsId={$goods_id}','管理员生成申请退货单')";
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
            $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_id},set={$action}','{$notes}')";
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
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);
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

    public function export_order()
    {
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
        $ids = I('ids');
        $orderList = $userIdArr= $pid = $gid = array();
        $userIdStr = '';
        if($ids){
            $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_id in({$ids})"; 
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
                    $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($userIdStr$level) order by o.order_id";
                }elseif($level && $level_two){
                    switch ($level_two) {
                        case 'two':
                            $pidStr = '';
                            foreach ($pid as $value) {
                                $pidStr .= $value['user_id'].',';
                            }
                            $pidStr = trim($pidStr,',');
                            $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($pidStr) order by o.order_id";
                            break;
                        case 'three':
                            $gidStr = '';
                            foreach ($gid as $value) {
                                $gidStr .=$value['user_id'];
                            }
                            $gidStr = trim($gidStr,',');
                            $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($gidStr) order by o.order_id";
                            break;
                        case 'two_three':
                            $userIdStr = trim($userIdStr,',');
                            $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where and o.user_id in($userIdStr) order by o.order_id";
                            break;    
                        default:
                            break;
                    }
                }
            }else{
                $sql = "select o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as create_time,w.warehouse_name from __PREFIX__order as o left join __PREFIX__order_goods as og on o.order_id=og.order_id left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId $where order by o.order_id";  
            }       
        }

        $orderList = DB::query($sql);
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">仓库名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">电话号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">支付状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">订单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">付款时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品编码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品单价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">付款单价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">积分金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">应付金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="150px">购买人-身份证</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100px">收件人-姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="200px">收件人-地址</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="20px">运费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="50px">支付单号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="50px">支付方式</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            $region = M('region')->getField('id,name');
            foreach($orderList as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['warehouse_name'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['buyerName'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].' </td>';
                $order_status = '';
                switch($val['order_status']){
                    case 0:
                        $order_status="待确认";
                        break;
                    case 1:
                        $order_status="已确认";
                        break;
                    case 2:
                        $order_status="已收货";
                        break;
                    case 3:
                        $order_status="已取消";
                        break;
                    case 4:
                        $order_status="已完成";
                        break;
                    case 5:
                        $order_status="已作废";
                        break;
                    case 6:
                        $order_status="退款";
                        break;               
                    default:
                        $order_status="其他";
                        break;
                }
                $buyerIdNumber = "&nbsp;".$val['buyerIdNumber'];
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$order_status.'</td>';
                $pay_status = '';
                switch($val['pay_status']){
                    case 0:
                        $pay_status = '未支付';
                        break;
                    case 1:
                        $pay_status = '已支付';
                        break;
                    case 2:
                        $pay_status = '支付中';
                        break;
                    case 3:
                        $pay_status = '支付失败';
                        break;
                    default:
                        $pay_status = '未知异常';
                        break;
                }
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$pay_status.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['create_time'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['pay_time']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['member_goods_price'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_num'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['member_goods_price']*$val['goods_num'].'</td>';              
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['integral_money'].'</td>';                      
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$buyerIdNumber.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'."{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}".'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['freight'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['payOrderNo'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_name'].'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'order');
        exit();
    }
    
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
           $order['wareId']=2;//普通仓
            // 添加订单
            $order_id = M('order')->add($order);

            $order_insert_id = DB::getLastInsID();
            //写日志
            $model = M('action');
            $userName = session('userName');
            $actionData = json_encode($order);
            $sql="insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderId={$order_insert_id},set={$actionData}','添加一笔订单')";
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
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);   
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


    //一键删除
    public function delSome(){
        $orders=I('orders')?I('orders'):'';
        if($orders){
            $orders=trim($orders,',');
            $sql="update __PREFIX__order set deleted=1 where order_id in ({$orders})";
            $ret=M('Order')->execute($sql);
            //写日志
            $model = M('action');
            $userName = session('userName');
            $sql2 = "insert __PREFIX__action(username,tabName,tabField,notes) values('{$userName}','order','orderIds={$orders}','一键删除')";
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
        $sql = "select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.pay_status=0 and o.order_status in(0,1) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.pay_status in(1,2) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where (o.order_status not in(3,4,5,6) and o.shipping_status!=1 and o.deleted!=1) and o.pay_status=3 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.shipping_status=1 and o.order_status not in(2,4,5) and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=2 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=4 and o.deleted!=1 and w.warehouse_type=3 union all select count(*) as num from __PREFIX__order as o left join __PREFIX__warehouse as w on w.warehouse_id=o.wareId where o.order_status=6 and o.deleted!=1 and w.warehouse_type=3";
        $num = DB::query($sql);
        // echo $sql;exit;
        $this->assign('num1',$num[0]['num']);
        $this->assign('num2',$num[1]['num']);
        $this->assign('num3',$num[2]['num']);
        $this->assign('num4',$num[3]['num']);
        $this->assign('num5',$num[4]['num']);
        $this->assign('num6',$num[5]['num']);
        $this->assign('num7',$num[6]['num']);
        $this->assign('timegap',$begin.'-'.$end);
        $this->assign('level',$level);
        return $this->fetch();
    }

    //同步到SDMS B2C订单
    public function syncOrder(){
        $model=M('order');
        $sql="select s.warehouseCode,s.shopCode,s.shopSercet,o.order_id,o.order_sn,o.consignee,o.address,o.mobile,o.buyerRegNo,o.buyerName,o.buyerIdNumber,o.goods_price,o.coupon_price,o.integral_money,o.order_amount,o.add_time,o.pay_time,og.gNum,og.goods_name,og.goods_sn,og.goods_num,og.goods_price as ogoods_price,og.member_goods_price,r.name as province,o.city,o.district from {$this->db_prex}order as o left join {$this->db_prex}order_goods as og on og.order_id=o.order_id left join {$this->db_prex}region as r on r.id=o.province left join {$this->db_prex}warehouse as w on w.warehouse_id=o.wareId left join {$this->db_prex}shop as s on s.id=w.apiId where w.warehouse_type=3 and o.order_status=0 and o.pay_status=1 and o.isSync=0";
        $datas=$model->query($sql);
        $sqlArr=$back=$data=$goodsInfo=array();
        $result='';
        if($datas){
            foreach($datas as $key=>$value){
                $data[$value['order_id']]['wareCode']=$value['warehouseCode'];
                $data[$value['order_id']]['shopCode']=$value['shopCode'];
                $data[$value['order_id']]['shopSercet']=$value['shopSercet'];
                $data[$value['order_id']]['orderSn']=$value['order_sn'];
                $data[$value['order_id']]['consignee']=$value['consignee'];
                $data[$value['order_id']]['province']=$value['province'];
                $sql="select name as city from {$this->db_prex}region where id={$value['city']}";   
                $cityArr=$model->query($sql);
                $data[$value['order_id']]['city']=$cityArr[0]['city'];
                $sql="select name as district from {$this->db_prex}region where id={$value['district']}";   
                $districtArr=$model->query($sql);
                $data[$value['order_id']]['district']=$districtArr[0]['district'];
                $data[$value['order_id']]['address']=$value['address'];
                $data[$value['order_id']]['mobile']=$value['mobile'];
                $data[$value['order_id']]['buyerRegNo']=$value['buyerRegNo'];
                $data[$value['order_id']]['goodsValue']=$value['goods_price'];
                $orderDate=date('Y-m-d H:i:s',$value['add_time']);
                $data[$value['order_id']]['orderdate']=$orderDate;
                $data[$value['order_id']]['info']='';
                $goodsInfo[$value['order_id']][$value['gNum']]['gNum']=$value['gNum'];
                $goodsInfo[$value['order_id']][$value['gNum']]['goodsName']=$value['goods_name'];
                $goodsInfo[$value['order_id']][$value['gNum']]['goodsCode']=$value['goods_sn'];
                $goodsInfo[$value['order_id']][$value['gNum']]['qty']=$value['goods_num'];
                $goodsInfo[$value['order_id']][$value['gNum']]['price']=$value['member_goods_price'];                
            }
            foreach($data as $key=>$value){
                $data[$key]['goods']=array_values($goodsInfo[$key]);
            }

            $result['orders']=json_encode(array_values($data));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_URL,'http://a.gdyyb.com/Home/Customs/orderImportB2C');
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
                $back['success']=0;
                $back['error']=array();
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
                if($sqlArr){
                    $ret=true;
                    foreach($sqlArr as $sql){
                        $model->execute($sql);
                    }
                }
                errorMsg(200, 'done',$back);
            }else{
                errorMsg(400, '同步数据出错，请联系接口开发');
            }
        }else{
            errorMsg(400, '没有需要同步的订单');
        }
    }
}
