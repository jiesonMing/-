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

class Port extends Base{
    
    //仓库列表
    public function index(){
        return $this->fetch();
    }
   
    //配置详情
    public function ajaxPortList(){
        $model=M('port');
        $count = $model->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        $postList=$model->alias('p')
                ->join('settings s','s.id=p.settingId')
                ->join('platform pf','pf.id=p.platId')
                ->field('p.id,p.name,p.settingId,p.cusOrderCode,p.ciqOrderCode,p.interfaceUrl,s.settingName,pf.name as fpName')
                ->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('portList',$postList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }
    
    //添加、修改关区
    public function editPort(){
        $id=I('id/d')?I('id/d'):0;
        if(IS_POST){
            $data['name']=I("name/s")?I('name/s'):'';
            if($data['name']=='') errorMsg (400, '关区名称不能为空');
            $data['interfaceUrl']=I("interfaceUrl/s")?I('interfaceUrl/s'):'';
            $data['settingId']=I("settingId/d")?I('settingId/d'):0;
            if($data['settingId']<=0) errorMsg (400, '请选择配置');
            $data['platId']=I("platId/d")?I('platId/d'):0;
            if($data['platId']<=0) errorMsg (400, '请选择平台');
            $data['cusOrderCode']=I("cusOrderCode/s")?I('cusOrderCode/s'):'';
            $data['ciqOrderCode']=I("ciqOrderCode/s")?I('ciqOrderCode/s'):'';
            if(!$data['ciqOrderCode']) errorMsg (400, '订单国检组织编码不能为空');
            $organiConf=I('organiConf/s')?I('organiConf/s'):'';
            if(!$organiConf) errorMsg(400,'请选择支付单组织编码');
            $model=M('port');
            $model->startTrans();
            if($id>0){
                $ret=$model->where('id='.$id)->save($data);
            }else{
                $id=$ret=$model->add($data);
            }
            if($ret || $ret===0) {
                $db_prex = C('db_prex');
                $sql = "delete from {$db_prex}organization_conf where portId={$id}";
                $model->execute($sql);
                $organiConf = trim($organiConf, ',');
                $organiArr = explode(',', $organiConf);
                $str = '';
                foreach ($organiArr as $organInfo) {
                    $sql = "select name from {$db_prex}organization_info where id={$organInfo}";
                    $organInfoRes = $model->query($sql);
                    if ($organInfoRes) {
                        if ($organInfoRes[0]['name'] != '不填') {
                            $organArr['organInfo'] = $organInfo;
                            $str .= ",({$id},{$organInfo},1)";
                        }
                    }
                }
                $str = trim($str, ',');
                if ($str) {
                    $sql = "insert {$db_prex}organization_conf(portId,organInfo,status) values{$str}";
                    $oret = $model->execute($sql);
                    if ($oret) {
                        $model->commit();
                        errorMsg(200,'操作成功');
                    }else{
                        $model->rollback();
                        errorMsg(400,'添加支付单配置失败');
                    }
                }else {
                    $model->commit();
                    errorMsg(200, '操作成功');
                }
            }else{
                $model->rollback();
                errorMsg(400, '网络繁忙，请刷新重试');
            }
        }else{
            $model=M('port');
            if($id>0){
                $data=$model->where('id='.$id)->find();
                if($data){
                    $this->assign('data',$data);
                }

            }
            $settings=M('settings')->select();
            $platforms=M('platform')->select();
            $payShops=M('pay_shop')->select();
            $payOrderStr='';
            foreach($payShops as $payShopVal){
                $ret=self::payOrderCardInfoQuery($id,$payShopVal['id']);
                if($ret['retCode']==200){
                    $payOrderStr.='<dl class="row"><dt class="tit"><span>*</span>';
                    $payOrderStr.='<label>'.$payShopVal['code'].'支付单组织编码</label></dt>';
                    $payOrderStr.='<dd class="opt payOrderCard">'.$ret['retMessage'].'</dd></dl>';
                }
            }
            $this->assign('id',$id);
            $this->assign('settings',$settings);
            $this->assign('platforms',$platforms);
            $this->assign('payOrderStr',$payOrderStr);
            return $this->fetch();
        }
    }

    //查询组织结构
    private function payOrderCardInfoQuery($id,$payShopId){
        $db_prex=C('db_prex');
        $result=array();
        if($payShopId) {
            if ($id) {
                $sql="SELECT oi.id,oi.name,oi.psCode,oi.code,oi.payCode,oc.id AS ocId FROM {$db_prex}organization_info AS oi LEFT JOIN {$db_prex}pay_shop AS ps ON ps.id={$payShopId} LEFT JOIN {$db_prex}organization_conf AS oc ON oc.organInfo=oi.id AND oc.portId={$id} AND oc.status=1 WHERE oi.status=1 AND oi.payCode=ps.code order by oi.id";
            } else {
                $sql = "select oi.id,oi.name,oi.psCode,oi.code,oi.payCode from {$db_prex}organization_info as oi left join {$db_prex}pay_shop as ps on ps.id={$payShopId} where oi.status=1 and oi.payCode=ps.code order by oi.id";
            }
            $data=M('organization_info')->query($sql);
            if($data){
                $str=$cusPayCard=$ciqPayCard='';
                foreach($data as $val){
                    if($val['code']=='cus'){
                        if($val['ocId']) {
                            $cusPayCard .= "<label for='cus{$val['psCode']}{$payShopId}'><input type='radio' name='cusPayCard{$payShopId}' value='{$val['id']}' id='cus{$val['psCode']}{$payShopId}' checked='checked' />{$val['name']}</label>";
                        }else{
                            $cusPayCard .= "<label for='cus{$val['psCode']}{$payShopId}'><input type='radio' name='cusPayCard{$payShopId}' value='{$val['id']}' id='cus{$val['psCode']}{$payShopId}' />{$val['name']}</label>";
                        }
                    }elseif($val['code']=='ciq'){
                        if($val['ocId']) {
                            $ciqPayCard .= "<label for='cus{$val['psCode']}{$payShopId}'><input type='radio' name='ciqPayCard{$payShopId}' value='{$val['id']}' id='cus{$val['psCode']}{$payShopId}'  checked='checked' />{$val['name']}</label>";
                        }else{
                            $ciqPayCard .= "<label for='cus{$val['psCode']}{$payShopId}'><input type='radio' name='ciqPayCard{$payShopId}' value='{$val['id']}' id='cus{$val['psCode']}{$payShopId}' />{$val['name']}</label>";
                        }
                    }
                }
                $str.='<div><span>海关支付单：</span><div>'.$cusPayCard.'</div></div>';
                $str.='<div><span>国检支付单：</span><div>'.$ciqPayCard.'</div></div>';
                $result['retCode']='200';
                $result['retMessage']=$str;
            }else{
                $result['retCode']='400';
                $result['retMessage']='未查询到组织结构信息';
            }
        }else{
            $result['retCode']='400';
            $result['retMessage']='请选择支付配置';
        }
        return $result;
    }
}
