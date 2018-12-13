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

class Map extends Base {
    public  $db_prex='';
    
    
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        C('TOKEN_ON',false); // 关闭表单令牌验证
        $this->db_prex=C('db_prex');
    }

    public function index(){
        $cateList = DB::query("select m.map_id,m.parent_id,m.name,m.url,m.status,m2.name as parentName from {$this->db_prex}map as m left join {$this->db_prex}map as m2 on m.parent_id=m2.map_id");

        $this->assign('cateList',$cateList);
        return $this->fetch();
    }

    public function addEditMap(){ 
        $cateList = DB::query("select map_id,parent_id,name from {$this->db_prex}map");
        $this->assign('cateList',$cateList);    //导航条目       
        if(IS_GET){
            $id = I('id');
            if($id){
                $map = DB::query("select map_id,parent_id,name,url,status from {$this->db_prex}map where map_id=".$id);
                $this->assign('map',$map[0]);  
            }
            return $this->fetch();
        }
        if(IS_POST){
            $id = I('id');
            $map = M('map');
            $ret = '';
            $arr = array(
                'parent_id'=>I('parent_id'),
                'name'=>I('name'),
                'url'=>I('url'),
                'status'=>I('status')
                );
            if($id){
                $ret = $map->where('map_id='.$id)->save($arr);
            }else{
                $ret = $map->add($arr);
            }
            if($ret){
                errorMsg(200,'提交成功！');
            }else{
                errorMsg(400,'提交失败！');
            }
        }
    }

    public function delMap(){
        $id= I('id');
        $map = M('map');
        $ret = $map->where('map_id',$id)->delete();
        if($ret){
            errorMsg(200,'删除成功！');
        }else{
            errorMsg(200,'删除失败！');
        }
    }

}
