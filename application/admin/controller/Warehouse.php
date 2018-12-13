<?php
/* 
 * 仓库管理，不同仓库的不同商品，店铺，api
 * 邓小明，2017.07.05
 * 
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use think\AjaxPage;
use think\Page;
use think\Db;

class Warehouse extends Base{
    //仓库列表
    public function warehouseList(){
        return $this->fetch();
    }
    public function ajaxWarehouseList(){
        $where='deleted=0';
        $order='addTime desc';
        $model=M('warehouse');
        $count = $model->where($where)->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();       
        //$warehouseList=$model->where($where)->order($order)->limit($Page->firstRow.','.$Page->listRows)->select();
        $warehouseList=$model->alias('w')
                ->join('shop sh','sh.id=w.apiId','left')
                ->join('customs c','c.id=w.customsId','left')
                ->field('w.deleted,w.warehouse_id,w.warehouse_name,w.warehouse_addr,w.warehouse_code,w.warehouse_type,w.companyName,w.contactsName,w.contactsMobile,w.addTime,sh.shopName,c.name as customsName')
                ->where('w.deleted=0')
                ->select();        
        $sql=$model->getLastSql();
        //echo $sql;die;
        /*
        foreach ($warehouseList as $key => $value) {
            $shopName = M('shop')->field('shopName')->where('id='.$value['apiId'])->find();
            $warehouseList[$key]['shopName'] =  $shopName['shopName'];
            $customsName = M('customs')->field('name')->where('id='.$value['customsId'])->find();
            $warehouseList[$key]['customsName'] =  $customsName['name'];
        }*/
        $this->assign('warehouseList',$warehouseList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }
    //添加修改仓库
    public function addEditWarehouse(){
        if((I('is_ajax') == 1) && IS_POST){
            $model=M('warehouse');
            $type = I('warehouse_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
            if($type==2){
                $res=$model->where('warehouse_id',I('post.warehouse_id'))->save(I('post.'));
            }else{
                $res=$model->add(I('post.'));
            }
            if($res){
                $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功','data'=>''));
            }else{
                $this->ajaxReturn(array('status'=>-1,'msg'=>'操作失败','data'=>''));
            }
        }
        $apiList=M('shop')->select();
        $this->assign('apiList',$apiList);
        $cusList = M('customs')->field('id,code,name')->select();
        $this->assign('cusList',$cusList);
        $warehouseList=M('warehouse')->where('warehouse_id',I('post.warehouse_id'))->find();
        $this->assign('warehouseInfo',$warehouseList);
        return $this->fetch();
    }
    public function delWarehouse(){
        M('warehouse')->where('warehouse_id='.I('warehouse_id'))->save(array('deleted'=>1));
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'');        
        $this->ajaxReturn($return_arr);
    }

    //店铺列表
    //public function storeList(){
    //    return $this->fetch();
    //}
    
    //api列表
    public function apiList(){
        return $this->fetch();
    }
    
    public function ajaxApiList(){
        $model=M('shop');
        $count = $model->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();        
        //$apiList=$model->where($where)->order($order)->limit($Page->firstRow.','.$Page->listRows)->select();
        $apiList=$model->select();
        $this->assign('apiList',$apiList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }
    //添加修改api
    public function addEditApi(){
        $model=M('shop');
        if((I('is_ajax') == 1) && IS_POST){
            $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
            // 数据验证            
//            $validate = \think\Loader::validate('Warehouse');
//            if(!$validate->batch()->check(input('post.'))){                          
//                $error = $validate->getError();
//                $error_msg = array_values($error);
//                $return_arr = array('status' => -1,'msg' => $error_msg[0],'data' => $error);
//                $this->ajaxReturn($return_arr);
//            }
            if($type==2){
                $res=$model->where('id',I('post.id'))->save(I('post.'));
            }else{
                $res=$model->add(I('post.'));
            }
            if($res){
                $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功','data'=>''));
            }else{
                $this->ajaxReturn(array('status'=>-1,'msg'=>'操作失败','data'=>''));
            }
        }
        $apiInfo=M('shop')->where('id',I('id'))->find();
        $this->assign('apiInfo',$apiInfo);
        return $this->fetch();
    }
    public function delApi(){
        M('shop')->where('id',I('id'))->delete();
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);        
        $this->ajaxReturn($return_arr);
    }

    public function defaultCus(){
        return $this->fetch();
    }
}
