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
 * Author: IT宇宙人     
 * Date: 2015-09-09
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use think\AjaxPage;
use think\Page;
use think\Db;

class Goods extends Base {
    
    /**
     *  商品分类列表
     */
    public function categoryList(){                
        $GoodsLogic = new GoodsLogic();               
        $cat_list = $GoodsLogic->goods_cat_list();
        $this->assign('cat_list',$cat_list);        
        return $this->fetch();
    }
    
    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'), 
     * select * from tp_goods_category where id = 393
        select * from tp_goods_category where parent_id = 393
        update tp_goods_category  set parent_id_path = concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
        insert into `tp_goods_category` (`parent_id`,`name`) values 
        ('393','时尚饰品'),
     */
    public function addEditCategory(){
        
            $GoodsLogic = new GoodsLogic();        
            if(IS_GET)
            {
                $goods_category_info = D('GoodsCategory')->where('id='.I('GET.id',0))->find();
                $level_cat = $GoodsLogic->find_parent_cat($goods_category_info['id']); // 获取分类默认选中的下拉框
                
                $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单                
                $this->assign('level_cat',$level_cat);                
                $this->assign('cat_list',$cat_list);                 
                $this->assign('goods_category_info',$goods_category_info);      
                return $this->fetch('_category');
                exit;
            }

            $GoodsCategory = D('GoodsCategory'); //

            $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新                        
            //ajax提交验证
            if(I('is_ajax') == 1)
            {
                // 数据验证            
                $validate = \think\Loader::validate('GoodsCategory');
                if(!$validate->batch()->check(input('post.')))
                {                          
                    $error = $validate->getError();
                    $error_msg = array_values($error);
                    $return_arr = array(
                        'status' => -1,
                        'msg' => $error_msg[0],
                        'data' => $error,
                    );
                    $this->ajaxReturn($return_arr);
                } else {
                     
                    $GoodsCategory->data(input('post.'),true); // 收集数据
                    $GoodsCategory->parent_id = I('parent_id_1');
                    input('parent_id_2') && ($GoodsCategory->parent_id = input('parent_id_2'));
                    //编辑判断
                    if($type == 2){
                        $children_where = array(
                            'parent_id_path'=>array('like','%_'.I('id')."_%")
                        );
                        $children = M('goods_category')->where($children_where)->max('level');
                        if (I('parent_id_1')) {
                            $parent_level = M('goods_category')->where(array('id' => I('parent_id_1')))->getField('level', false);
                            if (($parent_level + $children) > 3) {
                                $return_arr = array(
                                    'status' => -1,
                                    'msg'   => '商品分类最多为三级',
                                    'data'  => '',
                                );
                                $this->ajaxReturn($return_arr);
                            }
                        }
                        if (I('parent_id_2')) {
                            $parent_level = M('goods_category')->where(array('id' => I('parent_id_2')))->getField('level', false);
                            if (($parent_level + $children) > 3) {
                                $return_arr = array(
                                    'status' => -1,
                                    'msg'   => '商品分类最多为三级',
                                    'data'  => '',
                                );
                                $this->ajaxReturn($return_arr);
                            }
                        }
                    }
                    
                    if($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id)
                    {
                        //  编辑
                        $return_arr = array(
                            'status' => -1,
                            'msg'   => '上级分类不能为自己',
                            'data'  => '',
                        );
                        $this->ajaxReturn($return_arr);                        
                    }
                    if($GoodsCategory->commission_rate > 100)
                    {
                        //  编辑
                        $return_arr = array(
                            'status' => -1,
                            'msg'   => '分佣比例不得超过100%',
                            'data'  => '',
                        );
                        $this->ajaxReturn($return_arr);                        
                    }   
                   
                    if ($type == 2)
                    {
                        $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库
                        $GoodsLogic->refresh_cat(I('id'));
                    }
                    else
                    {
                        $GoodsCategory->save(); // 写入数据到数据库
                        $insert_id = $GoodsCategory->getLastInsID();
                        $GoodsLogic->refresh_cat($insert_id);
                    }
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',
                        'data'  => array('url'=>U('Admin/Goods/categoryList')),
                    );
                    $this->ajaxReturn($return_arr);

                }  
            }

    }
    
    /**
     * 获取商品分类 的帅选规格 复选框
     */
    public function ajaxGetSpecList(){
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_spec');        
        $filter_spec_arr = explode(',',$filter_spec);        
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'],$filter_spec_arr);  
        $str = $str ? $str : '没有可帅选的商品规格';
        exit($str);        
    }
 
    /**
     * 获取商品分类 的帅选属性 复选框
     */
    public function ajaxGetAttrList(){
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_attr');        
        $filter_attr_arr = explode(',',$filter_attr);        
        $str = $GoodsLogic->GetAttrCheckboxList($_REQUEST['type_id'],$filter_attr_arr);          
        $str = $str ? $str : '没有可帅选的商品属性';
        exit($str);        
    }    
    
    /**
     * 删除分类
     */
    public function delGoodsCategory(){
        $id = $this->request->param('id');
        // 判断子分类
        $GoodsCategory = M("goods_category");
        $count = $GoodsCategory->where("parent_id = {$id}")->count("id");
        $count > 0 && $this->error('该分类下还有分类不得删除!',U('Admin/Goods/categoryList'));
        // 判断是否存在商品
        $goods_count = M('Goods')->where("cat_id = {$id}")->count('1');
        $goods_count > 0 && $this->error('该分类下有商品不得删除!',U('Admin/Goods/categoryList'));
        // 删除分类
        DB::name('goods_category')->where('id',$id)->delete();
        $this->success("操作成功!!!",U('Admin/Goods/categoryList'));
    }
    
    
    /**
     *  商品列表
     */
    public function goodsList(){      
        $GoodsLogic = new GoodsLogic();        
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $userRole=M('user_role')->field('role_name,role_level')->order('role_id')->select();
        //保存搜索条件
        $searchCondition=session('searchCondition')?session('searchCondition'):'';
        if(!empty($searchCondition))
            $this->assign('searchCondition',$searchCondition);
        
        $this->assign('userRole',$userRole);
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        return $this->fetch();
    }
    #导入商品价格
    public function inGoodsPrice(){
        if(count($_FILES) > 0){
            $f = $_FILES['file'];
            $filename =C('public').'/goodsPrice/'.$f['name']; 
            //$filename="F:/wamp/www/tpshop/public/goodsPrice/".$f['name'];
            move_uploaded_file($f['tmp_name'],$filename);
            
            $goodsPriceData=getExcelData($filename);
            
            //组装数据
            foreach($goodsPriceData as $k=>$v){
                $goods_sn= str_replace(array("\r\n", "\r", "\n"), "", $v[0]);
                $goods_sn= trim($goods_sn);
                $goodsName=trim($v[1]);
                $firstMemberPrice=trim($v[2]);
                $secondMemberPrice=trim($v[3]);
                $thirdMemberPrice=trim($v[4]);
                $shop_price=trim($v[5]);
                if($goods_sn && $goodsName){
                    $sqlArr[]="update __PREFIX__goods set firstMemberPrice={$firstMemberPrice},secondMemberPrice={$secondMemberPrice},thirdMemberPrice={$thirdMemberPrice},shop_price={$shop_price} where goods_sn='{$goods_sn}' or goods_name='{$goodsName}'";
                }
            }
            //更新
            foreach($sqlArr as $sql){
                $res=M('goods')->execute($sql);
            }
            if($res){
                errorMsg(200, 'success','更新完成');
            }else{
                errorMsg(400, 'failed','有更新失败的价格，请查看表格数据！');
            }
        }else{
            errorMsg(400, 'failed','表格没数据，请查看表格数据！');
        }       
    }


    /**
     *  商品列表
     */
    public function ajaxGoodsList(){            
        
        $where = ' 1 = 1 '; // 搜索条件                
        I('intro')    && $where = "$where and g.".I('intro')." = 1" ;        
        I('brand_id') && $where = "$where and g.brand_id = ".I('brand_id') ;
        (I('is_on_sale') !== '') && $where = "$where and g.is_on_sale = ".I('is_on_sale') ;                
        $cat_id = I('cat_id');
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (g.goods_name like '%$key_word%' or g.goods_sn like '%$key_word%')" ;
        }
        
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandson($cat_id); 
            $where .= " and g.cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }
        
        $ajaxpage = I('p');
        if($ajaxpage){
            session('ajaxpage',$ajaxpage);
        }
        $model = M('Goods');
        $count = $model->alias('g')->join('warehouse w','w.warehouse_id=g.wareId','left')->where($where)->count();
        $Page  = new AjaxPage($count,20);

        /**  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        */
        $show = $Page->show();
        
        $goodsList = $model->alias('g')->join('warehouse w','w.warehouse_id=g.wareId','left')->field('g.*,w.warehouse_type')->where($where)->order('goods_id','desc')->limit($Page->firstRow.','.$Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $portList = M('port')->select();
        $portList = convert_arr_key($portList, 'id');
        //输出搜索的条件
        session('searchCondition',I('post.'));
        $searchCondition=session('searchCondition')?session('searchCondition'):'';
        $this->assign('searchCondition',$searchCondition);
        $this->assign('catList',$catList);
        $this->assign('portList',$portList);
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }


    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {     
        
        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\Goods(); //
        $type = I('goods_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
          
            // 数据验证            
            $validate = \think\Loader::validate('Goods');
            if(!$validate->batch()->check(input('post.')))
            {                          
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            } else {
                
                $Goods->data(input('post.'),true); // 收集数据
                $Goods->on_time = time(); // 上架时间
                //$Goods->cat_id = $_POST['cat_id_1'];
                I('cat_id_2') && ($Goods->cat_id = I('cat_id_2'));
                I('cat_id_3') && ($Goods->cat_id = I('cat_id_3'));

                I('extend_cat_id_2') && ($Goods->extend_cat_id = I('extend_cat_id_2'));
                I('extend_cat_id_3') && ($Goods->extend_cat_id = I('extend_cat_id_3'));
                $Goods->shipping_area_ids = implode(',',I('shipping_area_ids/a',[]));
                $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
                $Goods->spec_type = $Goods->goods_type;
                $Goods->goods_name = strip_tags(trim(htmlspecialchars(I('goods_name'))));    
                if ($type == 2) {
                    $goods_id = I('goods_id');
                    $Goods->isUpdate(true)->save(); // 写入数据到数据库                 
                    // 修改商品后购物车的商品价格也修改一下
                    M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                            'market_price'=>I('market_price'), //市场价
                            'goods_price'=>I('shop_price'), // 本店价
                            'member_goods_price'=>I('shop_price'), // 会员折扣价                        
                            ));                    
                } else {
                    $Goods->save(); // 写入数据到数据库                    
                    $goods_id = $insert_id = $Goods->getLastInsID();
                }
                $Goods->afterSave($goods_id);
                $GoodsLogic->saveGoodsAttr($goods_id,I('goods_type')); // 处理商品 属性

                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => U('admin/Goods/goodsList')),
                );
                $this->ajaxReturn($return_arr);
            }
        }

        $goodsInfo = M('Goods')->alias('g')->join('warehouse w','g.wareId=w.warehouse_id','left')
        ->field('g.*,w.warehouse_type')->where('goods_id=' . I('GET.id', 0))->find();
        //$cat_list = $GoodsLogic->goods_cat_list(); // 已经改成联动菜单
        $level_cat = $GoodsLogic->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        $level_cat2 = $GoodsLogic->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $brandList = $GoodsLogic->getSortBrands();
        $goodsType = M("GoodsType")->select();
        $suppliersList = M("suppliers")->select();
        $plugin_shipping = M('plugin')->where(array('type'=>array('eq','shipping')))->select();//插件物流
        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',',$goodsInfo['shipping_area_ids']);
        $country=M('country')->field('id,name,code as code')->select();//国家
        $unit=M('unit')->field('id,unitCode,unitName')->select();//计量单位
        $warehouseList=M('warehouse')->where('deleted=0')->field('warehouse_id,warehouse_type,warehouse_name')->select();
        $ports=M('port')->select();
        $this->assign('warehouseList',$warehouseList);
        $this->assign('goods_shipping_area_ids',$goods_shipping_area_ids);
        $this->assign('shipping_area',$shipping_area);
        $this->assign('plugin_shipping',$plugin_shipping);
        $this->assign('suppliersList',$suppliersList);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $this->assign('ports',$ports);
        $goodsImages = M("GoodsImages")->where('goods_id =' . I('GET.id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $this->assign('country',$country);
        $this->assign('unit',$unit);
        $this->initEditor(); // 编辑器
        $user_role=M('user_role')->select();
        //添加修改会员价显示的名称
        foreach($user_role as $v){
            if($v['role_level']==3)
                $firstMemberName=$v['role_name'].'价';
            if($v['role_level']==2)
                $secondMemberName=$v['role_name'].'价';
            if($v['role_level']==1)
                $thirdMemberName=$v['role_name'].'价';
        }
        
        $this->assign('firstMemberName',$firstMemberName);
        $this->assign('secondMemberName',$secondMemberName);
        $this->assign('thirdMemberName',$thirdMemberName);
        return $this->fetch('_goods');
    } 
          
    /**
     * 商品类型  用于设置商品的属性
     */
    public function goodsTypeList(){
        $model = M("GoodsType");                
        $count = $model->count();        
        $Page = $pager = new Page($count,14);
        $show  = $Page->show();
        $goodsTypeList = $model->order("id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch('goodsTypeList');
    }
    
    
    /**
     * 添加修改编辑  商品属性类型
     */
    public function addEditGoodsType()
    {
        $id = $this->request->param('id', 0);
        $model = M("GoodsType");
        if (IS_POST) {
            $data = $this->request->post();
            if ($id)
                DB::name('GoodsType')->update($data);
            else
                DB::name('GoodsType')->insert($data);

            $this->success("操作成功!!!", U('Admin/Goods/goodsTypeList'));
            exit;
        }
        $goodsType = $model->find($id);
        $this->assign('goodsType', $goodsType);
        return $this->fetch('_goodsType');
    }
    
    /**
     * 商品属性列表
     */
    public function goodsAttributeList(){       
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch();
    }   
    
    /**
     *  商品属性列表
     */
    public function ajaxGoodsAttributeList(){            
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件                        
        I('type_id')   && $where = "$where and type_id = ".I('type_id') ;                
        // 关键词搜索               
        $model = M('GoodsAttribute');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        $goodsTypeList = M("GoodsType")->getField('id,name');
        $attr_input_type = array(0=>'手工录入',1=>' 从列表中选择',2=>' 多行文本框');
        $this->assign('attr_input_type',$attr_input_type);
        $this->assign('goodsTypeList',$goodsTypeList);        
        $this->assign('goodsAttributeList',$goodsAttributeList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }   
    
    /**
     * 添加修改编辑  商品属性
     */
    public  function addEditGoodsAttribute(){
                        
            $model = D("GoodsAttribute");                      
            $type = I('attr_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新         
            $attr_values = str_replace('_', '', I('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符            
            $attr_values = trim($attr_values);
            
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;
            
            if((I('is_ajax') == 1) && IS_POST)//ajax提交验证
            {                                
                    // 数据验证            
                    $validate = \think\Loader::validate('GoodsAttribute');
                    if(!$validate->batch()->check($post_data))
                    {                          
                        $error = $validate->getError();
                        $error_msg = array_values($error);
                        $return_arr = array(
                            'status' => -1,
                            'msg' => $error_msg[0],
                            'data' => $error,
                        );
                        $this->ajaxReturn($return_arr);
                    } else {     
                             $model->data($post_data,true); // 收集数据
                            
                             if ($type == 2)
                             {                                 
                                 $model->isUpdate(true)->save(); // 写入数据到数据库                         
                             }
                             else
                             {
                                 $model->save(); // 写入数据到数据库
                                 $insert_id = $model->getLastInsID();                        
                             }
                             $return_arr = array(
                                 'status' => 1,
                                 'msg'   => '操作成功',                        
                                 'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
                             );
                             $this->ajaxReturn($return_arr);
                }  
            }                
           // 点击过来编辑时                 
           $attr_id = I('attr_id/d',0);  
           $goodsTypeList = M("GoodsType")->select();           
           $goodsAttribute = $model->find($attr_id);           
           $this->assign('goodsTypeList',$goodsTypeList);                   
           $this->assign('goodsAttribute',$goodsAttribute);
           return $this->fetch('_goodsAttribute');
    }  
    
    /**
     * 更改指定表的指定字段
     */
    public function updateField(){
        $primary = array(
                'goods' => 'goods_id',
                'goods_category' => 'id',
                'brand' => 'id',            
                'goods_attribute' => 'attr_id',
        		'ad' =>'ad_id',            
        );        
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];        
        $model->save();   
        $return_arr = array(
            'status' => 1,
            'msg'   => '操作成功',                        
            'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
        );
        $this->ajaxReturn($return_arr);
    }
    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'],$_REQUEST['type_id']);
        exit($str);
    }
        
    /**
     * 删除商品
     */
    public function delGoods()
    {
        $goods_id = $_GET['id'];
        $error = '';
        
        // 判断此商品是否有订单
        $c1 = M('OrderGoods')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有订单,不得删除! <br/>';
        
        
         // 商品团购
        $c1 = M('group_buy')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有团购,不得删除! <br/>';   
        
         // 商品退货记录
        $c1 = M('return_goods')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有退货记录,不得删除! <br/>';
        
        if($error)
        {
            $return_arr = array('status' => -1,'msg' =>$error,'data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
            $this->ajaxReturn($return_arr);
        }
        
        // 删除此商品        
        M("Goods")->where('goods_id ='.$goods_id)->delete();  //商品表
        M("cart")->where('goods_id ='.$goods_id)->delete();  // 购物车
        M("comment")->where('goods_id ='.$goods_id)->delete();  //商品评论
        M("goods_consult")->where('goods_id ='.$goods_id)->delete();  //商品咨询
        M("goods_images")->where('goods_id ='.$goods_id)->delete();  //商品相册
        M("spec_goods_price")->where('goods_id ='.$goods_id)->delete();  //商品规格
        M("spec_image")->where('goods_id ='.$goods_id)->delete();  //商品规格图片
        M("goods_attr")->where('goods_id ='.$goods_id)->delete();  //商品属性     
        M("goods_collect")->where('goods_id ='.$goods_id)->delete();  //商品收藏          
                     
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
        $this->ajaxReturn($return_arr);
    }
    
    /**
     * 删除商品类型 
     */
    public function delGoodsType()
    {
        // 判断 商品规格
        $id = $this->request->param('id');
        $count = M("Spec")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品规格不得删除!',U('Admin/Goods/goodsTypeList'));
        // 判断 商品属性        
        $count = M("GoodsAttribute")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品属性不得删除!',U('Admin/Goods/goodsTypeList'));        
        // 删除分类
        M('GoodsType')->where("id = {$id}")->delete();
        $this->success("操作成功!!!",U('Admin/Goods/goodsTypeList'));
    }    

    /**
     * 删除商品属性
     */
    public function delGoodsAttribute()
    {
        $id = input('id');
        // 判断 有无商品使用该属性
        $count = M("GoodsAttr")->where("attr_id = {$id}")->count("1");
        $count > 0 && $this->error('有商品使用该属性,不得删除!',U('Admin/Goods/goodsAttributeList'));
        // 删除 属性
        M('GoodsAttribute')->where("attr_id = {$id}")->delete();
        $this->success("操作成功!!!",U('Admin/Goods/goodsAttributeList'));
    }            
    
    /**
     * 删除商品规格
     */
    public function delGoodsSpec()
    {
        $id = input('id');
        // 判断 商品规格项
        $count = M("SpecItem")->where("spec_id = {$id}")->count("1");
        $count > 0 && $this->error('清空规格项后才可以删除!',U('Admin/Goods/specList'));
        // 删除分类
        M('Spec')->where("id = {$id}")->delete();
        $this->success("操作成功!!!",U('Admin/Goods/specList'));
    } 
    
    /**
     * 品牌列表
     */
    public function brandList(){  
        $model = M("Brand"); 
        $where = "";
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";
        $count = $model->where($where)->count();
        $Page = $pager = new Page($count,10);        
        $brandList = $model->where($where)->order("`sort` asc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $show  = $Page->show(); 
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
        $this->assign('cat_list',$cat_list);       
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('brandList',$brandList);
        return $this->fetch('brandList');
    }
    
    /**
     * 添加修改编辑  商品品牌
     */
    public  function addEditBrand(){        
            $id = I('id');            
            if(IS_POST)
            {
                    $data = input('post.');
                    if($id)
                        M("Brand")->update($data);
                    else
                        M("Brand")->insert($data);
                    
                    $this->success("操作成功!!!",U('Admin/Goods/brandList',array('p'=>input('p'))));
                    exit;
            }           
           $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
           $this->assign('cat_list',$cat_list);           
           $brand = M("Brand")->find($id);             
           $this->assign('brand',$brand);
           return $this->fetch('_brand');
    }    
    
    /**
     * 删除品牌
     */
    public function delBrand()
    {        
        // 判断此品牌是否有商品在使用
        $goods_count = M('Goods')->where("brand_id = {$_GET['id']}")->count('1');
        if($goods_count)
        {
            $return_arr = array('status' => -1,'msg' => '此品牌有商品在用不得删除!','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
            $this->ajaxReturn($return_arr);
        }
        
        $model = M("Brand"); 
        $model->where('id ='.$_GET['id'])->delete(); 
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
        $this->ajaxReturn($return_arr);
    }      
    
    /**
     * 初始化编辑器链接     
     * 本编辑器参考 地址 http://fex.baidu.com/ueditor/
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('admin/Ueditor/imageUp',array('savepath'=>'goods'))); // 图片上传目录
        $this->assign("URL_imageUp", U('admin/Ueditor/imageUp',array('savepath'=>'article'))); //  不知道啥图片
        $this->assign("URL_fileUp", U('admin/Ueditor/fileUp',array('savepath'=>'article'))); // 文件上传s
        $this->assign("URL_scrawlUp", U('admin/Ueditor/scrawlUp',array('savepath'=>'article')));  //  图片流
        $this->assign("URL_getRemoteImage", U('admin/Ueditor/getRemoteImage',array('savepath'=>'article'))); // 远程图片管理
        $this->assign("URL_imageManager", U('admin/Ueditor/imageManager',array('savepath'=>'article'))); // 图片管理        
        $this->assign("URL_getMovie", U('admin/Ueditor/getMovie',array('savepath'=>'article'))); // 视频上传
        $this->assign("URL_Home", "");
    }    
    
    
    
    /**
     * 商品规格列表    
     */
    public function specList(){       
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch();
    }
    
    
    /**
     *  商品规格列表
     */
    public function ajaxSpecList(){ 
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件                        
        I('type_id')   && $where = "$where and type_id = ".I('type_id') ;        
        // 关键词搜索               
        $model = D('spec');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $specList = $model->where($where)->order('`type_id` desc')->limit($Page->firstRow.','.$Page->listRows)->select();        
        $GoodsLogic = new GoodsLogic();        
        foreach($specList as $k => $v)
        {       // 获取规格项     
                $arr = $GoodsLogic->getSpecItem($v['id']);
                $specList[$k]['spec_item'] = implode(' , ', $arr);
        }
        
        $this->assign('specList',$specList);
        $this->assign('page',$show);// 赋值分页输出
        $goodsTypeList = M("GoodsType")->select(); // 规格分类
        $goodsTypeList = convert_arr_key($goodsTypeList, 'id');
        $this->assign('goodsTypeList',$goodsTypeList);        
        return $this->fetch();
    }      
    /**
     * 添加修改编辑  商品规格
     */
    public  function addEditSpec(){
                        
            $model = D("spec");                      
            $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新             
            if((I('is_ajax') == 1) && IS_POST)//ajax提交验证
            {                
                // 数据验证
                $validate = \think\Loader::validate('Spec');
                $post_data = input('post.');
                if ($type == 2) {
                    //更新数据
                    $check = $validate->scene('edit')->batch()->check($post_data);
                } else {
                    //插入数据
                    $check = $validate->batch()->check($post_data);
                }
                if (!$check) {
                    $error = $validate->getError();
                    $error_msg = array_values($error);
                    $return_arr = array(
                        'status' => -1,
                        'msg' => $error_msg[0],
                        'data' => $error,
                    );
                    $this->ajaxReturn($return_arr);
                }
                $model->data($post_data, true); // 收集数据
                if ($type == 2) {
                    $model->isUpdate(true)->save(); // 写入数据到数据库
                    $model->afterSave(I('id'));
                } else {
                    $model->save(); // 写入数据到数据库
                    $insert_id = $model->getLastInsID();
                    $model->afterSave($insert_id);
                }
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => U('Admin/Goods/specList')),
                );
                $this->ajaxReturn($return_arr);
            }                
           // 点击过来编辑时                 
           $id = I('id/d',0);
           $spec = $model->find($id);
           $GoodsLogic = new GoodsLogic();  
           $items = $GoodsLogic->getSpecItem($id);
           $spec[items] = implode(PHP_EOL, $items); 
           $this->assign('spec',$spec);
           
           $goodsTypeList = M("GoodsType")->select();           
           $this->assign('goodsTypeList',$goodsTypeList);           
           return $this->fetch('_spec');
    }  
    
    
    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect(){
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;        
        $GoodsLogic = new GoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('Spec')->where("type_id = ".I('get.spec_type/d'))->order('`order` desc')->select();
        foreach($specList as $k => $v)        
            $specList[$k]['spec_item'] = M('SpecItem')->where("spec_id = ".$v['id'])->order('id')->getField('id,item'); // 获取规格项                
        
        $items_id = M('SpecGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);       
        
        // 获取商品规格图片                
        if($goods_id)
        {
           $specImageList = M('SpecImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');                 
        }        
        $this->assign('specImageList',$specImageList);
        
        $this->assign('items_ids',$items_ids);
        $this->assign('specList',$specList);
        return $this->fetch('ajax_spec_select');        
    }    
    
    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */    
    public function ajaxGetSpecInput(){     
         $GoodsLogic = new GoodsLogic();
         $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
         $str = $GoodsLogic->getSpecInput($goods_id ,I('post.spec_arr/a',[[]]));
         exit($str);   
    }
    
    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename','');
        M('goods_images')->where("image_url = '$path'")->delete();
    }
    
    public function changeShopPrice(){
        $goods_id=I('goods_id')?I('goods_id'):0;
        if($goods_id){
            $shop_price=I("shop_price")?I('shop_price'):0;
            $db_prex=C('db_prex');
            if($shop_price){
                $sql="update {$db_prex}goods set shop_price={$shop_price} where goods_id={$goods_id}";
                $ret=M('goods')->execute($sql);
                if($ret){
                    errorMsg(200, 'done');
                }elseif($ret==0){
                    errorMsg(300, '网络错误，请刷新重试');
                }else{
                    errorMsg(400, '网络错误，请刷新重试');
                }
            }else{
                errorMsg(400, '最新价格有误，请重新填写');
            }
        }else{
            errorMsg(400, '商品信息有误，请刷新重试');
        }
    }
    public function firstMemberPrice(){
        $goods_id=I('goods_id')?I('goods_id'):0;
        if($goods_id){
            $firstMemberPrice=I("firstMemberPrice")?I('firstMemberPrice'):0;
            $db_prex=C('db_prex');
            if($firstMemberPrice){
                $sql="update {$db_prex}goods set firstMemberPrice={$firstMemberPrice} where goods_id={$goods_id}";
                $ret=M('goods')->execute($sql);
                if($ret){
                    errorMsg(200, 'done');
                }elseif($ret==0){
                    errorMsg(300, '网络错误，请刷新重试');
                }else{
                    errorMsg(400, '网络错误，请刷新重试');
                }
            }else{
                errorMsg(400, '最新价格有误，请重新填写');
            }
        }else{
            errorMsg(400, '商品信息有误，请刷新重试');
        }
    }
    public function secondMemberPrice(){
        $goods_id=I('goods_id')?I('goods_id'):0;
        if($goods_id){
            $secondMemberPrice=I("secondMemberPrice")?I('secondMemberPrice'):0;
            $db_prex=C('db_prex');
            if($secondMemberPrice){
                $sql="update {$db_prex}goods set secondMemberPrice={$secondMemberPrice} where goods_id={$goods_id}";
                $ret=M('goods')->execute($sql);
                if($ret){
                    errorMsg(200, 'done');
                }elseif($ret==0){
                    errorMsg(300, '网络错误，请刷新重试');
                }else{
                    errorMsg(400, '网络错误，请刷新重试');
                }
            }else{
                errorMsg(400, '最新价格有误，请重新填写');
            }
        }else{
            errorMsg(400, '商品信息有误，请刷新重试');
        }
    }
    public function thirdMemberPrice(){
        $goods_id=I('goods_id')?I('goods_id'):0;
        if($goods_id){
            $thirdMemberPrice=I("thirdMemberPrice")?I('thirdMemberPrice'):0;
            $db_prex=C('db_prex');
            if($thirdMemberPrice){
                $sql="update {$db_prex}goods set thirdMemberPrice={$thirdMemberPrice} where goods_id={$goods_id}";
                $ret=M('goods')->execute($sql);
                if($ret){
                    errorMsg(200, 'done');
                }elseif($ret==0){
                    errorMsg(300, '网络错误，请刷新重试');
                }else{
                    errorMsg(400, '网络错误，请刷新重试');
                }
            }else{
                errorMsg(400, '最新价格有误，请重新填写');
            }
        }else{
            errorMsg(400, '商品信息有误，请刷新重试');
        }
    }

    //批量上架
    public function upSale(){
        $ids = I('ids')?I('ids'):0;
        if($ids){
            $db_prex=C('db_prex');
            $time = time();
            $sql="update {$db_prex}goods set is_on_sale=1,on_time={$time} where goods_id in({$ids})";
            $ret=M('goods')->execute($sql);
            if($ret){
                errorMsg(200, '商品批量上架成功！');
            }elseif($ret==0){
                errorMsg(300, '网络错误，请刷新重试');
            }else{
                errorMsg(400, '网络错误，请刷新重试');
            }
        }else{
            errorMsg(400, '获取信息有误，请刷新重试');
        }
    }
    //批量下架
    public function downSale(){
        $ids = I('ids')?I('ids'):0;
        if($ids){
            $db_prex=C('db_prex');
            $sql="update {$db_prex}goods set is_on_sale=0 where goods_id in({$ids})";
            $ret=M('goods')->execute($sql);
            if($ret){
                errorMsg(200, '商品批量下架成功！');
            }elseif($ret==0){
                errorMsg(300, '网络错误，请刷新重试');
            }else{
                errorMsg(400, '网络错误，请刷新重试');
            }
        }else{
            errorMsg(400, '获取信息有误，请刷新重试');
        }
    }
    //批量设置库存
    public function setStock(){
        $ids = I('ids')?I('ids'):0;
        if($ids){
            $num = I('num')?I('num'):0;
            // errorMsg(400,$num);
            $db_prex=C('db_prex');
            $sql="update {$db_prex}goods set store_count={$num} where goods_id in({$ids})";
            $ret=M('goods')->execute($sql);
            if($ret){
                errorMsg(200, '批量设置库存成功！');
            }elseif($ret==0){
                errorMsg(300, '网络错误，请刷新重试');
            }else{
                errorMsg(400, '网络错误，请刷新重试');
            }
        }else{
            errorMsg(400, '获取信息有误，请刷新重试');
        }
    }

    //批量删除
    public function delSome(){
        $ids = I('ids')?I('ids'):0;
        if($ids){
            $idArr = explode(',',$ids);
            $result = array();
            $successNum = 0;
            $result['success'] = '';
            foreach ($idArr as $goods_id){
                // 判断此商品是否有订单
                $c1 = M('OrderGoods')->where("goods_id = $goods_id")->count('1');
                // 商品团购
                $c2 = M('group_buy')->where("goods_id = $goods_id")->count('1');
                // 商品退货记录
                $c3 = M('return_goods')->where("goods_id = $goods_id")->count('1');

                if($c1){
                    $result['error'][$goods_id]['goodsId']=$goods_id;
                    $result['error'][$goods_id]['msg']='此商品有订单,不得删除!';
                    continue;
                }elseif($c2){
                    $result['error'][$goods_id]['goodsId']=$goods_id;
                    $result['error'][$goods_id]['msg']='此商品有团购,不得删除!';
                    continue;
                }elseif($c3){
                    $result['error'][$goods_id]['goodsId']=$goods_id;
                    $result['error'][$goods_id]['msg']='此商品有退货记录,不得删除!';
                    continue;
                }else{
                    // 删除此商品        
                    M("Goods")->where('goods_id ='.$goods_id)->delete();  //商品表
                    M("cart")->where('goods_id ='.$goods_id)->delete();  // 购物车
                    M("comment")->where('goods_id ='.$goods_id)->delete();  //商品评论
                    M("goods_consult")->where('goods_id ='.$goods_id)->delete();  //商品咨询
                    M("goods_images")->where('goods_id ='.$goods_id)->delete();  //商品相册
                    M("spec_goods_price")->where('goods_id ='.$goods_id)->delete();  //商品规格
                    M("spec_image")->where('goods_id ='.$goods_id)->delete();  //商品规格图片
                    M("goods_attr")->where('goods_id ='.$goods_id)->delete();  //商品属性     
                    M("goods_collect")->where('goods_id ='.$goods_id)->delete();  //商品收藏 
                    $successNum++;
                }
            }
            $result['success']['num']=$successNum;
            errorMsg(200,'success',$result);
        }else{
            errorMsg(400, '获取信息有误，请刷新重试');
        }
    }

    //导出商品
    public function ex_goods(){
        $goods = M('goods');
        $orderList = $goods->field('goods_sn,goods_name,firstMemberPrice,secondMemberPrice,thirdMemberPrice,shop_price')->select();
        $level = M('user_role')->select();
        $strTable ='<table width="1000px" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">商品自编码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:400px;">商品名称</td>';
        $arr = array();
        foreach ($level as $key => $value) {
            switch ($value['role_level']) {
                case 3:
                    $arr[0] = $value['role_name'];
                    break;
                case 2:
                    $arr[1] = $value['role_name'];
                    break;
                case 1:
                    $arr[2] = $value['role_name'];
                    break;
                case 0:
                    $arr[3] = $value['role_name'];
                    break;        
                default:
                    break;
            }
        }
        // dump($arr);exit;
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px">'.$arr[0].'价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px">'.$arr[1].'价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px">'.$arr[2].'价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px">'.$arr[3].'价</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            foreach($orderList as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['goods_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_name'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['firstMemberPrice'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['secondMemberPrice'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['thirdMemberPrice'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['shop_price'].'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'goods');
        exit();
    }
}
