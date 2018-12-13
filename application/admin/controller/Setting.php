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

class Setting extends Base{

    //配置信息
    public function index(){
        return $this->fetch();
    }
    
    //配置详情
    public function ajaxSettingList(){
        $model=M('settings');
        $count = $model->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();       
        $settingList=$model->limit($Page->firstRow.','.$Page->listRows)->select();
        
        $this->assign('settingList',$settingList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }
        
    //添加、修改配置信息
    public function editSetting(){
        $id=I('id/d')?I('id/d'):0;
        if(IS_POST){
            $data['settingName']=I('settingName/s')?I('settingName/s'):'';
            if($data['settingName']=='') errorMsg (400, '配置名称不能为空');
            $data['companyName']=I("companyName/s")?I('companyName/s'):'';
            if($data['companyName']=='') errorMsg (400, '电商企业名称不能为空');
            $data['customsNo']=I('customsNo/s')?I('customsNo/s'):'';
            if($data['customsNo']=='') errorMsg (400,'电商海关十位编码不能为空');
            $data['gzeportcode']=I('gzeportcode/s')?I('gzeportcode/s'):'';
            $data['ciqCode']=I("ciqCode/s")?I('ciqCode/s'):'';
            $data['ciqNo']=I('ciqNo/s')?I('ciqNo/s'):'';
            $data['ciqBNo']=I('ciqBNo/s')?I('ciqBNo/s'):'';
            $data['shopDomain']=I('shopDomain/s')?I('shopDomain/s'):'';
            if($data['shopDomain']=='') errorMsg (400,'电商平台地址不能为空');
            $data['DXPId']=I('DXPId/s')?I('DXPId/s'):'';
            if($data['DXPId']=='') errorMsg (400,'海关总署dxpId不能为空');
            $data['ciqHost']=I('ciqHost/s')?I('ciqHost/s'):'';
            $data['ciqPort']=I('ciqPort/s')?I('ciqPort/s'):'';
            $data['ciqUser']=I('ciqUser/s')?I('ciqUser/s'):'';
            $data['ciqPass']=I('ciqPass/s')?I('ciqPass/s'):'';
            $data['ciqIn']=I('ciqIn/s')?I('ciqIn/s'):'';
            $data['ciqOut']=I('ciqOut/s')?I('ciqOut/s'):'';
            $data['cusHost']=I('cusHost/s')?I('cusHost/s'):'';
            if($data['cusHost']=='') errorMsg (400,'海关ftphost不能为空');
            $data['cusPort']=I('cusPort/s')?I('cusPort/s'):'';
            if($data['cusPort']=='') errorMsg (400,'海关ftp端口不能为空');
            $data['cusUser']=I('cusUser/s')?I('cusUser/s'):'';
            if($data['cusUser']=='') errorMsg (400,'海关ftp账号不能为空');
            $data['cusPass']=I('cusPass/s')?I('cusPass/s'):'';
            if($data['cusPass']=='') errorMsg (400,'海关ftp密码不能为空');
            $data['cusIn']=I('cusIn/s')?I('cusIn/s'):'';
            if($data['cusIn']=='') errorMsg (400,'海关ftp上传目录不能为空');
            $data['cusOut']=I('cusOut/s')?I('cusOut/s'):'';
            if($data['cusOut']=='') errorMsg (400,'海关ftp下载目录不能为空');
            $model=M('settings');
            $model->startTrans();
            if($id>0){
                $ret=$model->where('id='.$id)->save($data);
            }else{
                $ret=$model->add($data);
            }
            if($ret){
                $model->commit();
                errorMsg(200, '操作成功');
            }elseif($ret===0){
                $model->rollback();
                errorMsg(400, '没有任何修改');
            }else{
                $model->rollback();
                errorMsg(400, '网络繁忙，请刷新重试');
            }
        }else{
            $model=M('settings');
            if($id>0){
                $data=$model->where('id='.$id)->find();
                if($data){
                    $this->assign('data',$data);
                }
            }
            $this->assign('id',$id);
            return $this->fetch();
        }
    }
    
    //删除配置
    public function delSetting(){
        $id=I('id/d')?I('id/d'):0;
        if($id){
            $model=M('settings');
            $model->startTrans();
            $ret=$model->where('id='.$id)->delete();
            if($ret){
                $model->commit();
                errorMsg(200, '删除成功');
            }elseif($ret===0){
                $model->rollback();
                errorMsg(400, '没有需要删除的配置');
            }else{
                $model->rollback();
                errorMsg(400, '网络错误，请刷新重试');
            }
        }else{
            errorMsg(400, '配置信息错误，请刷新重试');
        }
    }
    
    //支付配置信息
    public function paySetting(){
        return $this->fetch();
    }
    
    //支付配置信息详情
    public function ajaxPaySettingList(){
        $model=M('pay_shop');
        $count = $model->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();       
        $paySettingList=$model->limit($Page->firstRow.','.$Page->listRows)->select();
        
        $this->assign('paySettingList',$paySettingList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }
    
    //添加、修改配置信息
    public function editPaySetting(){
        $id=I('id/d')?I('id/d'):0;
        if(IS_POST){
            $data['payShopName']=I("payShopName/s")?I('payShopName/s'):'';
            if($data['payShopName']=='') errorMsg (400, '支付配置名称不能为空');
            $data['code']=I("code/s")?I('code/s'):'';
            if($data['code']=='') errorMsg (400, '支付方式不能为空');
            $data['payCompanyName']=I('payCompanyName/s')?I('payCompanyName/s'):'';
            if($data['payCompanyName']=='') errorMsg (400,'支付企业名称不能为空');
            $data['payCusCode']=I('payCusCode/s')?I('payCusCode/s'):'';
            if($data['payCusCode']=='') errorMsg (400,'支付企业海关十位编码不能为空');
            $data['payurl']=I('payurl/s')?I('payurl/s'):'';
            if($data['payurl']=='') errorMsg (400,'支付接口不能为空');
            $data['payid']=I('payid/s')?I('payid/s'):'';
            if($data['payid']=='') errorMsg (400,'支付id(国内)不能为空');
            $data['paytoken']=I('paytoken/s')?I('paytoken/s'):'';
            $data['intelpayid']=I('intelpayid/s')?I('intelpayid/s'):'';
            if($data['intelpayid']=='') errorMsg (400,'支付id(国际)不能为空');
            $data['intelpaytoken']=I('intelpaytoken/s')?I('intelpaytoken/s'):'';
            $data['payType']=I('payType/s')?I('payType/s'):'';
            if($data['payType']=='') errorMsg (400,'支付类型不能为空');
            $data['customspaytype']=I('customspaytype/s')?I('customspaytype/s'):'';
            $data['orderFlowType']=I('orderFlowType/s')?I('orderFlowType/s'):'';
            $data['bizTypeCode']=I('bizTypeCode/s')?I('bizTypeCode/s'):'';
            $data['customsCode']=I('customsCode/s')?I('customsCode/s'):'';
            if($data['customsCode']=='') errorMsg (400,'支付单海关编码不能为空');
            $data['ciqCode']=I('ciqCode/s')?I('ciqCode/s'):'';
            $data['currno']=I('currno/s')?I('currno/s'):'';
            if($data['currno']=='') errorMsg (400,'币制编码不能为空');
            $data['limit']=I('limit/d')?I('limit/d'):0;
            if($data['limit']<=0) errorMsg (400,'支付限额不能小于0');
            $model=M('pay_shop');
            $result=$model->where("code='{$data['code']}'")->find();
            if($result){
                if($id!=$result['id']){
                    errorMsg(400,'该支付公司已经存在，请查证后再改！');
                }
            }
            $model->startTrans();
            if($id>0){
                $ret=$model->where('id='.$id)->save($data);
            }else{
                $ret=$model->add($data);
            }
            if($ret){
                $model->commit();
                errorMsg(200, '操作成功');
            }elseif($ret===0){
                $model->rollback();
                errorMsg(400, '没有任何修改');
            }else{
                $model->rollback();
                errorMsg(400, '网络繁忙，请刷新重试');
            }
        }else{
            $model=M('pay_shop');
            if($id>0){
                $data=$model->where('id='.$id)->find();
                if($data){
                    $this->assign('data',$data);
                }
            }
            $this->assign('id',$id);
            return $this->fetch();
        }
    }
    
    //删除配置
    public function delPaySetting(){
        $id=I('id/d')?I('id/d'):0;
        if($id){
            $model=M('pay_shop');
            $model->startTrans();
            $ret=$model->where('id='.$id)->delete();
            if($ret){
                $model->commit();
                errorMsg(200, '删除成功');
            }elseif($ret===0){
                $model->rollback();
                errorMsg(400, '没有需要删除的配置');
            }else{
                $model->rollback();
                errorMsg(400, '网络错误，请刷新重试');
            }
        }else{
            errorMsg(400, '配置信息错误，请刷新重试');
        }
    }
    
}
