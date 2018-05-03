<?php
/**
 * 照片书模块微站定义
 *
 * @author leyao
 * @url http://bbs.we7.cc/
 */
defined('IN_IA') or exit('Access Denied');
define('M_PATH', IA_ROOT . '/addons/photobook');
include 'tools/delete.class.php';
require_once 'aliyun-oss-php-sdk-2.3.0/autoload.php';
use OSS\OssClient;
use OSS\Core\OssException;
class PhotobookModuleSite extends WeModuleSite {
	//测试
	public function doMobilettt(){
		return 1;
	}
	/**
	 * OSS下载图片到本地服务器
	 */
	public function download($order_id){
		global $_W,$_GPC;

		$accessKeyId = "LTAIZlNllu4E2j6U";
		$accessKeySecret = "S34tVsxyY0cucviwEgKwEBVLjUVNDc";
		$endpoint = "http://oss-cn-beijing.aliyuncs.com";
		try {
			$ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
		} catch (OssException $e) {
			print $e->getMessage();
		}
		mkdir(ATTACHMENT_ROOT."BOOKS/temp_book_".$order_id);
		/**
		 * 从oss上下载所有的照片
		 */

		$photos = pdo_getall('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'main_id'=>$order_id));
		foreach($photos as $index=>$row){
			$object = $row['img_path'];
			$localfile = ATTACHMENT_ROOT."BOOKS/temp_book_".$order_id."/".$object;
			$options = array(
				OssClient::OSS_FILE_DOWNLOAD => $localfile,
			);
			try{
				$ossClient->getObject('demo-photo', $object, $options);
			} catch(OssException $e) {
				printf(__FUNCTION__ . ": FAILED\n");
				printf($e->getMessage() . "\n");
				return;
			}
		}	
	}
	
	/**
	 * trimarray:修剪信息；$data：模板的框图信息；$T_photo:模板图 $ordersub_id:订单页ID
	 */
	// $trimarray,$data,$T_photo,$ordersub_id
	public function Compound($trimarray,$data,$T_photo,$ordersub_id){
		global $_W,$_GPC;
		$template_thumb = $ordersub_id.$T_photo;
		$send_data ='x-oss-process=image/resize,w_360|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
		$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$T_photo.'?x-oss-process', $send_data);
		load()->func('logging');
		logging_run('进入函数'.json_encode($trimarray),'info','compound');
		foreach ($data as $key => $frame){
			
			/**
			 * 判断是否为图片 生成临时
			 */
			if($frame['type'] == 'img'){
				
				$thumb_img = "roate_".$trimarray[$key]['imgurl']; 
				if($trimarray[$key]['roate']==90 || $trimarray[$key]['roate']==-270){
					$send_data ='x-oss-process=image/resize,w_360/rotate,90|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}elseif($trimarray[$key]['roate']==180 || $trimarray[$key]['roate']==-180){
					$send_data ='x-oss-process=image/resize,w_360/rotate,180|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}elseif($trimarray[$key]['roate']==270 || $trimarray[$key]['roate']==-90){
					$send_data ='x-oss-process=image/resize,w_360/rotate,270|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}else{
					$send_data ='x-oss-process=image/resize,w_360|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$trimarray[$key]['imgurl'].'?x-oss-process', $send_data);
				/**
				 * 获取图片的信息
				 */
				$img_info = ihttp_get('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$thumb_img.'?x-oss-process=image/info');
				$de_info =json_decode($img_info['content'],true);
				/**
				 * 缩率图宽高
				 */
				$img_h = $de_info['ImageHeight']['value'];
				$img_w = $de_info['ImageWidth']['value'];
				/**
				 * 框的宽高
				 */
				$frame_w = trim($frame['width'],'px');
				$frame_h = trim($frame['height'],'px');
				$frame_top = trim($frame['top'],'px');
				$frame_left = trim($frame['left'],'px');
			
				/**
				 * 计算缩放那一边
				 */
				if($frame_w / $frame_h > $img_w / $img_h){
					$thumb_type = 'w'; 
					$thumb_value = $frame_w; 
				}else{
					$thumb_type = 'h'; 
					$thumb_value = $frame_h; 
				}
				/**
				 * 如果是新生成的缩略图
				 */
				if($trimarray[$key]['Xtop'] =='' && $trimarray[$key]['Xleft']==''){
					if($thumb_type == 'w'){
						$tailor_x = 0;
						$tailor_y = (($thumb_value / $img_w * $img_h)-$frame_h)/2;
					}elseif($thumb_type == 'h'){
						$tailor_x = (($thumb_value / $img_h * $img_w)-$frame_w)/2;
						$tailor_y = 0;
					}
				}else{
					$tailor_x = abs(trim($trimarray[$key]['Xleft'],'px'));
					$tailor_y = abs(trim($trimarray[$key]['Xtop'],'px'));
				}
			
				/**
				 * 缩放与裁剪
				 */ 
				$tailor_data = ',limit_0/crop,x_'.round($tailor_x).',y_'.round($tailor_y).',w_'.$frame_w.',h_'.$frame_h;
				$send_data ='x-oss-process=image/resize,'.$thumb_type.'_'.$thumb_value.$tailor_data.'|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$thumb_img.'?x-oss-process', $send_data);
				
				/**
				 * 合成模板
				 */
				// var_dump('frame.top=='.$frame_top.' frame.left=='.$frame_left); 
				$compound_data =base64_encode($thumb_img);
				$send_data ='x-oss-process=image/watermark,image_'.$compound_data.',g_nw,x_'.round($frame_left).',y_'.round($frame_top).'|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$template_thumb.'?x-oss-process', $send_data);
				/**
				 * 删除上传的临时图片
				 */
				// var_dump($thumb_img);
				if($trimarray[$key]['roate']==90 || $trimarray[$key]['roate']==-270 || $trimarray[$key]['roate']==-90 || $trimarray[$key]['roate']==270){
					$trimarray[$key]['width']=$img_h.'px';
					$trimarray[$key]['height']=$img_w.'px';
				}else{
					$trimarray[$key]['width']=$img_w.'px';
					$trimarray[$key]['height']=$img_h.'px';
				} 
			}
			$clear = new commonFunction();
			$clear->callInterfaceCommon('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$thumb_img,'DELETE');
		}
		/**
		 * png覆盖
		 */
		$send_data ='x-oss-process=image/watermark,image_'.base64_encode($T_photo).',g_nw,x_0,y_0|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
		$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$template_thumb.'?x-oss-process', $send_data);
		/**
		 * 更新数据表
		 */
		$trim =json_encode($trimarray);
		pdo_update('ly_photobook_order_sub',array('img_path'=>$template_thumb,'trim'=>$trim),array('id'=>$ordersub_id));
	}
	//2018-4-10   新增
	/**
	 * 新增用户手机上传照片到oss服务器
	 */
	public function doMobileupload_user_photo(){
		global $_W,$_GPC;
		$tid = $_GPC['tid'];
		if($_W['isajax']){

			$userid =pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id'];
			$temp =explode('.', $_GPC['org_name']);
			$thum =$temp[0].'_thum.'.$temp[1];
			$data ='x-oss-process=image/resize,w_360|sys/saveas,o_'.base64_encode($thum).',b_'.base64_encode('demo-photo');
			$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$_GPC['org_name'].'?x-oss-process', $data);
						
			if($response['status'] == 'OK'){
				//将原图缩略图录入数据表  需更改
				$insert_data = array(
					'uniacid'=>$_W['uniacid'],
					'user_id'=>$userid,
					'original'=>$_GPC['org_name'],
					'thumb'=>$thum,
					'createtime'=>time()
				);
				$res =pdo_insert('ly_photobook_user_images',$insert_data);
				if(!empty($res)){
					$resArr['code'] =0;
					$resArr['status'] ='success';
				}else{
					$resArr['code'] =1;
					$resArr['status'] ='录入数据出现错误';
				}
			}else{
				$resArr['code'] =2;
				$resArr['status'] =$response['status'];
			} 
			echo json_encode($resArr);exit;
		}
		include $this->template('upload_user_photo');
	}

	private $pageSize=10; 
	// 限制只在微信内打开
	// public function __construct(){
	// 	global $_W;
	// 	if(empty($_W['openid'])){
	// 		message('请在微信内打开','','error');
	// 		exit;
	// 	}
	// }
 
	public function doWebRou() {
		//这个操作被定义用来呈现 规则列表
	}

	private function _exec($do, $web = true){
        global $_GPC;
        $do = strtolower(trim($do));
        if ($web) {
            $file = IA_ROOT . "/addons/photobook/core/web/".$do.".php";
        } else {
            $file = IA_ROOT . "/addons/photobook/core/mobile/".$do.".php";
        }
        if (!is_file($file)) {
            message("文件".$file."不存在，注意文件命名小写");
        }
        include $file;
        exit;
    }


	// ajax方法合成并下载
	public function doWebPrintBook(){
		global $_W,$_GPC;
		$bookid=$_GPC['id'];
		if($_W['isajax']){
			set_time_limit(0);
			$order=pdo_get('ly_photobook_order_main',array('id'=>$bookid),array('zip_url'));
			// if(is_file($order['zip_url'])){
			// 	return json_encode(array('status'=>1,'filename'=>'photobook_'.$bookid.'.zip'));
			// }else{
				$this->download($bookid);
	// 			mkdir(ATTACHMENT_ROOT."BOOKS/temp_book_".$bookid);
	// 			$sql="SELECT * FROM ims_ly_photobook_order_sub WHERE main_id={$bookid} ORDER BY id";
	// 			$res=pdo_fetchall($sql);
	// 			$list=array();
	// 			include "tools/posterTools.php";
	// 			$list=array();
				
	// 			foreach ($res as $key => $value) {
	// 				// $value为order_sub的一条记录
	// 				$trim=$value['trim'];
	// 				$trimarray=json_decode($trim,true);
	// 				// 获取模板
	// 				$sql1="SELECT * FROM ims_ly_photobook_template_sub WHERE id={$value['template_id']} ORDER BY id limit 1";
	// 				$res1=pdo_fetch($sql1);
	// 				// echo '生成'.$value['id'].'图';
	// 				// var_dump($res1);
	// 				$T_photo=$res1['original'];
	// 				// 筐的位置尺寸
	// 				$data = json_decode(str_replace('&quot;', "'", $res1['data']), true);
	// 				// 合成图片的位置
	// 				$img = ATTACHMENT_ROOT."BOOKS/temp_book_".$bookid.'/original_'.$bookid.'_'.$value['id'].".png";
				
	// 				createzLeafWeb($trimarray,$data,$T_photo,$img);
	// /*				$timg=str_replace("www/web/huilife/public_html/","",$img);
	// 				$list[]=array('img'=>$timg,'id'=>$value['id']);*/
	// 			}
				$filename=ATTACHMENT_ROOT.'photobook_'.$bookid.'.zip';
		        exec('zip -r '.$filename.' '.ATTACHMENT_ROOT."BOOKS/temp_book_".$bookid);
		        // 更新zip_url
		        pdo_update('ly_photobook_order_main',array('zip_url'=>$filename),array('id'=>$bookid));
				return json_encode(array('status'=>1,'filename'=>'photobook_'.$bookid.'.zip'));	
			// }
		}else{
			$filename=ATTACHMENT_ROOT.$_GPC['filename'];
            header("Cache-Control: public"); 
            header("Content-Description: File Transfer"); 
            header('Content-disposition: attachment; filename='.basename($filename)); //文件名  
            header("Content-Type: application/zip"); //zip格式的  
            header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件  
            header('Content-Length: '. filesize($filename)); //告诉浏览器，文件大小  
            @readfile($filename);
		}
		
	}
	//首页
	public function doMobileHomea(){
		global $_W,$_GPC;
		$p_title="主页";

		include $this->template("home");
	}
	public function doMobileTexttext(){
		global $_W,$_GPC;
			var_dump($_W['fans']);

		include $this->template("home");
	}

	////////
	// 订单管理 //
	////////
	public function doWebBook_order(){
		global $_W,$_GPC;
		if(checksubmit()){
			// 填写快递信息
			$update=array('express'=>$_GPC['express'],'express_id'=>$_GPC['express_id'],'status'=>3);
			if(pdo_update('ly_photobook_order_main',$update,array('id'=>$_GPC['book_id']))){
				message('修改完成','','success');
			}else{
				message('修改失败','','error');
			}
			exit;
		}
		$pindex = max(1, intval($_GPC['page']));
	    $psize = 20;
		$status=empty($_GPC['status'])?1:$_GPC['status'];
		$where=array('uniacid'=>$_W['uniacid'],'status'=>$status);
		$sql1='select * from '.tablename('ly_photobook_order_main').' where uniacid=:uniacid and status=:status ORDER BY id LIMIT '.($pindex-1)*$psize.','.$psize;
		$list=pdo_fetchall($sql1,$where);
		$account_api = WeAccount::create();
		foreach ($list as $key => $li) {
			$openid=pdo_fetchcolumn('select openid from '.tablename('ly_photobook_user').' where id=:id',array('id'=>$li['user_id']));
			$list[$key]['userInfo']=$account_api->fansQueryInfo($openid);
		}
		$total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_order_main') . " where uniacid=:uniacid and status=:status",$where);
		$pager = pagination($total, $pindex, $psize);
		include $this->template('book_order');
	}

	/**
	 * 订单详情
	 */
	public function doWebBook_Order_detail(){
		global $_GPC,$_W;
		$info=pdo_get('ly_photobook_order_main',array('id'=>$_GPC['id']));
		$info['reciver']=pdo_get('ly_photobook_address',array('id'=>$info['address_id']));
		$account_api = WeAccount::create();
		$openid=pdo_fetchcolumn('select openid from '.tablename('ly_photobook_user').' where id=:id',array('id'=>$info['user_id']));
		$info['userInfo']=$account_api->fansQueryInfo($openid);
		include $this->template('book_order_detail');
	}
	public function doWebDealerlist(){
		global $_W,$_GPC;

		$list=pdo_fetchall('select a.id,a.agent_code,a.commission,b.nickname from '.tablename('ly_photobook_user').' as a left join '.tablename('mc_mapping_fans').' as b on a.openid= b.openid where a.uniacid='.$_W['uniacid'].' and b.uniacid='.$_W['uniacid'].' and dealer=1');
		foreach ($list as $key => $value) {
			$list[$key]['count']=pdo_fetchcolumn('select count(*) from '.tablename('ly_photobook_share').' where uniacid='.$_W['uniacid'].' and parentid='.$list[$key]['id']);
		}
		include $this->template('deallist');
	}

	public function doWebSharelist(){
		global $_W,$_GPC;
		$sharelist=pdo_fetchall('select * from '.tablename('ly_photobook_share').' where uniacid='.$_W['uniacid'].' and parentid='.$_GPC['id']);
		foreach ($sharelist as $key => $value){
			$sharelist[$key]['count']=pdo_fetchcolumn('select count(*) from '.tablename('ly_photobook_share').' where uniacid='.$_W['uniacid'].' and parentid='.$sharelist[$key]['id']);
		}
		include $this->template('sharelist');
	}
   
	//检查当前用户的信息，如没有，则新建。
	public function check_thisUser($openid,$uniacid){
		$user=pdo_get("ly_photobook_user",array("openid"=>$openid,"uniacid"=>$uniacid));
		if(empty($user)){
			$res=pdo_insert("ly_photobook_user",array('openid'=>$openid,"uniacid"=>$uniacid));
			if($res){
				return true;
			}else{
				return false;
			}
		}else{
			return true;
		}
	}
	/**
	 * 设置页
	 */
	public function doWebSetting(){
		global $_W,$_GPC;
		$setting = $this->module['config'];
		if(checksubmit()){
			load()->func('file');
		    $r = true;
		    if(!empty($_GPC['cert'])) {
		        $ret = file_put_contents(M_PATH . '/cert/apiclient_cert.pem.' . $_W['uniacid'], trim($_GPC['cert']));
		        $r = $r && $ret;
		    }
		    if(!empty($_GPC['key'])) {
		        $ret = file_put_contents(M_PATH . '/cert/apiclient_key.pem.' . $_W['uniacid'], trim($_GPC['key']));
		        $r = $r && $ret;
		    }
		    if(!empty($_GPC['ca'])) {
		        $ret = file_put_contents(M_PATH . '/cert/rootca.pem.' . $_W['uniacid'], trim($_GPC['ca']));
		        $r = $r && $ret;
		    }
		    if(!$r) {
		        message('证书保存失败, 请保证 /addons/photobook/cert/ 目录可写');
		    }
		    $input = array_elements(array('appid', 'secret','admin_openid', 'mchid', 'password', 'ip','one_level'), $_GPC);
		    $input['appid'] = trim($input['appid']);
		    $input['secret'] = trim($input['secret']);
		    $input['mchid'] = trim($input['mchid']);
		    $input['password'] = trim($input['password']);
		    $input['one_level'] = trim($input['one_level']);
		    $input['two_level'] = trim($input['two_level']);
		    $input['ip'] = trim($input['ip']);
			$input['admin_openid']=trim($input['admin_openid']);
			$setting['msetting']=$input;
			if($this->saveSettings($setting)) {
    			message('保存参数成功', '','success');
    		}else{
    			message('保存参数失败', '','error');
    		}
		}
		$setting=$setting['msetting'];
		include $this->template('setting');
	}
	/**
	 * 海报列表
	 */
	public function doWebMposter(){
		global $_W,$_GPC;
		$list=pdo_getall('ly_photobook_poster',array('uniacid'=>$_W['uniacid']));
		$lists=array();
		foreach ($list as $key => $value) {
			$value['count']=pdo_fetchcolumn('select count(*) as count from '.tablename('ly_photobook_share').' where posterid='.$value['id']);
			$lists[$key]=$value;
		}
		include $this->template('mposter');
	}
	/**
	 * 创建海报信息
	 */
	public function doWebCreatePoster(){
		global $_W,$_GPC;
		load()->func('tpl');
		if(checksubmit()){
  			$data=array(
					'title' => $_GPC ['title'],
					'bg' => $_GPC ['bg'],
					'kword'=>$_GPC['kword'],
					'data' => htmlspecialchars_decode($_GPC ['data']),
					'uniacid' => $_W ['uniacid'],
					'rtype' => $_GPC ['rtype'],
					'winfo1' => $_GPC ['winfo1'],
					'ftips' => htmlspecialchars_decode(str_replace('&quot;', '&#039;', $_GPC ['ftips']), ENT_QUOTES),
					'utips' => htmlspecialchars_decode(str_replace('&quot;', '&#039;', $_GPC ['utips']), ENT_QUOTES),
					// 'utips2' => htmlspecialchars_decode(str_replace('&quot;', '&#039;', $_GPC ['utips2']), ENT_QUOTES),
					'createtime'=>time(),
  				);
  			$res=pdo_insert('ly_photobook_poster',$data);
  			if($res==1){
  				$this->createRule($_GPC['kword'],pdo_insertid());
  				message('创建成功','','success');
  			}else{
   				message('创建失败','','error');
  			}
  			exit();
		}
		include $this->template('createposter');
	}
	/**
	 * 创建关键字
	 */
	private function createRule($kword, $posterid) {
	    global $_W;
	    $rule = array(
	        'uniacid' => $_W['uniacid'],
	        'name' => '生成海报',
	        'module' => $this->modulename,
	        'status' => 1,
	        'displayorder' => 254,
	        );
	    pdo_insert('rule', $rule);
	    unset($rule['name']);
	    $rule['type'] = 1;
	    $rule['rid'] = pdo_insertid();
	    $rule['content'] = $kword;
	    pdo_insert('rule_keyword', $rule);
	    pdo_update("ly_photobook_poster", array('rule_id' => $rule['rid']), array('id' => $posterid));
	}

	/**
	 * 编辑海报
	 */
	public function doWebEditposter(){
		global $_W,$_GPC;
		load()->func('tpl');
		$op=$_GPC['op'];
		if($op=='delete'){
			// 删除
			$poster = pdo_fetch('select * from ' . tablename('ly_photobook_poster') . " where id='{$_GPC['posterid']}'");
			$result = pdo_delete('ly_photobook_poster', array('id' => $_GPC['posterid']));
			if (!empty($result)) {
				$shares = pdo_fetchall('select id from ' . tablename("ly_photobook_share") . " where posterid='{$_GPC['posterid']}'");
	            foreach ($shares as $value) {
	                @unlink(str_replace('#sid#', $value['id'], "../addons/photobook/poster/mposter#sid#.jpg"));
	            }
	            // 删除分享记录
				pdo_delete("ly_photobook_share", array('posterid' => $_GPC['posterid']));
				// 删除规则与关键字
	            pdo_delete('rule', array('id' => $poster['rule_id']));
	            pdo_delete('rule_keyword', array('rid' => $poster['rule_id']));
	            // 删除qr表记录
	            pdo_delete('qrcode', array('name' => $poster['title'], 'uniacid' => $_W['uniacid'], 'keyword' => $poster['kword']));
				message('删除成功',$this->createWebUrl('mposter'),'success');
			}else{
				message('删除失败','','error');
			}
			exit();
		}else if($op=='edit'){
			$item=pdo_get('ly_photobook_poster',array('id'=>$_GPC['posterid']));
			if(checksubmit()){
	  			$data=array(
						'title' => $_GPC ['title'],
						'bg' => $_GPC ['bg'],
						'kword'=>$_GPC['kword'],
						'data' => htmlspecialchars_decode($_GPC ['data']),
						'uniacid' => $_W ['uniacid'],
						'rtype' => $_GPC ['rtype'],
						'winfo1' => $_GPC ['winfo1'],
						'ftips' => htmlspecialchars_decode(str_replace('&quot;', '&#039;', $_GPC ['ftips']), ENT_QUOTES),
						'utips' => htmlspecialchars_decode(str_replace('&quot;', '&#039;', $_GPC ['utips']), ENT_QUOTES),
						// 'utips2' => htmlspecialchars_decode(str_replace('&quot;', '&#039;', $_GPC ['utips2']), ENT_QUOTES),
						'createtime'=>time(),
	  				);
	  			$res=pdo_update('ly_photobook_poster',$data,array('id'=>$_GPC['id']));
	  			if($res==1){
	  				if (empty($item['rule_id'])) {
				        $this->createRule($_GPC['kword'],$_GPC['posterid']);
				    } elseif ($item['kword'] != $data['kword']) {
				        //修改生成二维码的关键字
				        $rk = pdo_fetch('select * from ' . tablename('rule_keyword') . " where rid='{$item['rule_id']}' and content='{$item['kword']}' limit 1");
				        if (empty($rk)) {
				            $rule = array(
				                'uniacid' => $_W['uniacid'],
				                'module' => $this->modulename,
				                'status' => 1,
				                'displayorder' => 254,
				                'type' => 1,
				                'rid' => $item['rule_id'],
				                'content' => $data['kword'],
				                );
				            pdo_insert('rule_keyword', $rule);
				        } else{
				            pdo_update('rule_keyword', array('content' => $data['kword']), array('rid' => $item['rule_id'], 'content' => $item['kword']));
				        }
				        // ##################################################################################
				        pdo_update('qrcode', array('keyword' => $data['kword']), array('name' => $item['title'], 'keyword' => $item['kword']));
				    }
	  				
	  				message('修改成功','','success');
	  			}else{
	   				message('修改失败','','error');
	  			}
	  			exit();
			}
			$data = json_decode(str_replace('&quot;', "'", $item['data']), true);
			include $this->template('editposter');
		}
	}

	////////////
	// 设置内部代理 //
	////////////
	public function doWebInneragent(){
		global $_GPC,$_W;
		$account_api = WeAccount::create();
		if(checksubmit()){
			$toAgent=pdo_fetchall('select * from '.tablename('ly_photobook_user').' where openid like "%'.$_GPC['openid'].'%"');
			foreach ($toAgent as $key => $value) {
				$toAgent[$key]['userInfo']=$account_api->fansQueryInfo($value['openid']);
			}
		}
		$list=pdo_getall('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'dealer'=>2));
		foreach ($list as $key => $value) {
			$list[$key]['userInfo']=$account_api->fansQueryInfo($value['openid']);
		}
		include $this->template('inneragent');
	}

	////////////////
	// ajax设置内部代理 //
	////////////////
	public function doWebSetinAgent(){
		global $_W,$_GPC;
		if($_W['isajax']){
			if($_GPC['s']!=-1){
				$code=$this->randChar(5);
				$updata=array('dealer'=>$_GPC['s'],'agent_code'=>$code);
			}else{
				$updata=array('dealer'=>$_GPC['s'],'agent_code'=>'');
			}
			if(pdo_update('ly_photobook_user',$updata,array('id'=>$_GPC['id']))){
				return '1';
			}else{
				return '0';
			}
		}
	}
	/**
	 * 分享记录，查看上下级
	 */
	public function doWebPostershare(){
		global $_GPC,$_W;
		$pindex = max(1, intval($_GPC['page']));
	    $psize = 1;
	    $posterid=$_GPC['posterid'];
	    if(isset($_GPC['parentid'])){
	    	// 下级
	    	$sql="SELECT * FROM ".tablename('ly_photobook_share')." WHERE uniacid=:uniacid AND posterid=:posterid AND parentid=:parentid ORDER BY id LIMIT ".($pindex-1)*$psize.",".$psize;
		    $list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid'],'posterid'=>$posterid,'parentid'=>$_GPC['parentid']));
		    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_share') . " where posterid='{$posterid}' AND uniacid={$_W['uniacid']} AND parentid=".$_GPC['parentid']);
	    }else if(isset($_GPC['keyword'])){
	    	// 搜索
	    	$keyword=$_GPC['keyword'];
	    	$sql="SELECT * FROM ".tablename('ly_photobook_share')." WHERE uniacid=:uniacid AND posterid=:posterid AND (nickname=:nickname OR openid=:openid) ORDER BY id LIMIT ".($pindex-1)*$psize.",".$psize;
		    $list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid'],'posterid'=>$posterid,'nickname'=>$keyword,'openid'=>$keyword));
		    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_share') . " where posterid='{$posterid}' AND (nickname='".$keyword."' OR openid='".$keyword."') AND uniacid={$_W['uniacid']}");
	    }else{
	    	// 全部
	    	$sql="SELECT * FROM ".tablename('ly_photobook_share')." WHERE uniacid=:uniacid AND posterid=:posterid ORDER BY id LIMIT ".($pindex-1)*$psize.",".$psize;
		    $list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid'],'posterid'=>$posterid));
		    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_share') . " where posterid='{$posterid}' AND uniacid={$_W['uniacid']}");
	    }
	    $pager = pagination($total, $pindex, $psize);
		include $this->template('postershare');
	}

	/**
	 * 照片书列表，管理
	 */
	public function doWebPhotobook(){
		global $_W,$_GPC;
		$sql='select id,name,price,inner_price,sales,shell,createtime,thumb,size from '.tablename('ly_photobook_template_main').' WHERE uniacid=:uniacid';
		$list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid']));
		include $this->template('photobook');
	}
	/**
	 * 创建照片书
	 */
	public function doWebCreateBook(){
		global $_W,$_GPC;
		if(checksubmit()){
			// 生成缩略图
			include 'tools/imageThumb.class.php';
			$imagePath=ATTACHMENT_ROOT.$_GPC['thumb'];
			$fileInfo=pathinfo($imagePath);
			$fileName=$fileInfo['filename'];
			$fileExt=$fileInfo['extension'];
			// 制作缩略图
			$imageThumb='images/thumb/'.$fileName.'_cover.'.$fileExt;
			if(!file_exists($imageThumb)){
				new ResizeImage($imagePath, '160', '120', '0', ATTACHMENT_ROOT.$imageThumb);
			}
			$data=array(
					'detail'=>$_GPC['detail'],
					'name'=>$_GPC['name'],
					'thumb'=>$imageThumb,
					'shell'=>$_GPC['shell'],
					'size'=>$_GPC['size'],
					'price'=>$_GPC['price'],
					'inner_price'=>$_GPC['inner_price'],
					'createtime'=>time(),
					'uniacid'=>$_W['uniacid'],
					'min_page'=>$_GPC['min_page'],
					'max_page'=>$_GPC['max_page'],
					"booktype"=>$_GPC['booktype']
				);
			if(pdo_insert('ly_photobook_template_main',$data)){
				message('添加成功，去添加模板图',$this->createWebUrl('edittemplateimage',array('t_id'=>pdo_insertid())),'success');
			}else{
				message('添加失败','','error');
			}
			exit;
		}
		load()->func('tpl');
		include $this->template('createbook');
	}
	/**
	 * 上传模板图
	 */
	public function doWebUploadtemplate(){
		set_time_limit(0);
		global $_GPC,$_W;
		/**
		 * 发送请求，上传缩略图到oss
		 */
		if($_W['isajax']){
			$temp =explode('.', $_GPC['org_name']);
			$thum =$temp[0].'_thum.'.$temp[1];
			$data ='x-oss-process=image/resize,w_360|sys/saveas,o_'.base64_encode($thum).',b_'.base64_encode('demo-photo');
			$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$_GPC['org_name'].'?x-oss-process', $data);
			if($response['status'] == 'OK'){		
				$resArr['code'] =0;
				$resArr['filename']=$_GPC['org_name'];
			}else{
				$resArr['code'] =1;
				$resArr['status'] =$response['status'];
			} 
			echo json_encode($resArr);exit;
		}
		if(empty($_GPC['template_id'])){
			message('未知错误','','error');
			exit;
		}
		
		if(checksubmit()){

			$data=array(
				'original'=>$_GPC['filename'],
				'thumb'=>explode('.', $_GPC['filename'])[0].'_thum.'.explode('.', $_GPC['filename'])[1],
				'uniacid'=>$_W['uniacid'],
				'template_id'=>$_GPC['template_id'],
				'imagecount'=>$_GPC['imagecount'],
				'data' => htmlspecialchars_decode($_GPC['data']),
			); 
			pdo_insert('ly_photobook_template_sub',$data);
			message(" 添加成功! ",$this->createWebUrl('Uploadtemplate',array('template_id'=>$_GPC['template_id'])),'success');
			exit;
		}
		$tcount=pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_template_sub') . " where template_id='{$_GPC['template_id']}'");
		$ttitle=pdo_fetchcolumn('select name from ' .tablename('ly_photobook_template_main').' WHERE id='.$_GPC["template_id"]);
		include $this->template('uploadtemplate');
	}

	//////////
	// 暂时保留 //
	//////////

	// $image = "jiequ.jpg"; // 原图
	// $imgstream = file_get_contents($image);
	// $im = imagecreatefromstring($imgstream);
	// $x = imagesx($im);//获取图片的宽
	// $y = imagesy($im);//获取图片的高
	 
	// // 缩略后的大小
	// $xx = 140;
	// $yy = 200;
	 
	// if($x>$y){
	// //图片宽大于高
	//     $sx = abs(($y-$x)/2);
	//     $sy = 0;
	//     $thumbw = $y;
	//     $thumbh = $y;
	// } else {
	// //图片高大于等于宽
	//     $sy = abs(($x-$y)/2.5);
	//     $sx = 0;
	//     $thumbw = $x;
	//     $thumbh = $x;
	//   }
	// if(function_exists("imagecreatetruecolor")) {
	//   $dim = imagecreatetruecolor($yy, $xx); // 创建目标图gd2
	// } else {
	//   $dim = imagecreate($yy, $xx); // 创建目标图gd1
	// }
	// imageCopyreSampled ($dim,$im,0,0,$sx,$sy,$yy,$xx,$thumbw,$thumbh);
	// header ("Content-type: image/jpeg");
	// imagejpeg ($dim, false, 100);
	/**
	 * 用户上传照片页ccbsx
	 */
	//获得当前用户的信息
	public function Oneuser($openid,$uniacid){
		global $_GPC,$_W;
		$Cuser=array(
			'openid'=>$_W['openid'],
			'uniacid'=>$_W['uniacid'],
			'head'=>$_W['fans']['avatar'],
			'nickname'=>$_W['fans']['nickname']
		);
		$user=pdo_get('ly_photobook_user',array('openid'=>$openid,'uniacid'=>$uniacid));
		if(!empty($user)){
			$Cuser['uid']=$user['id'];
		}
		return $Cuser;
	}
	public function generate_password( $length = 8 ) { 
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; 
		$password =""; 
		for ( $i = 0; $i < $length; $i++ ){  
			$password .= $chars[ mt_rand(0, strlen($chars) - 1) ]; 
		} 
		return $password; 
	}
	public function doMobileAPI_newAccept_pictures(){
		global $_GPC,$_W;
		$resdata=array(
			"code"=>0,
			"message"=>""
		); 
		$imgurl=$_FILES['file']['tmp_name'];
		$size=(int)$_FILES['file']['size']/1024;
		$suiji=$this->generate_password(16);
		$exename=substr(strrchr($_FILES['file']['name'], '.'), 1);
		$imhor="images/usersphoto/".$suiji.'.'.$exename;
		$imageSavePath ="../attachment/".$imhor;
		if(move_uploaded_file($imgurl, $imageSavePath)){
			$filename=$imageSavePath;
			list($width, $height)=getimagesize($filename);
			$ix=$width/3;
			$iy=$height/3;
			$fileInfo=pathinfo($imageSavePath);
			// var_dump($height);
			// exit();
			$fileName=$fileInfo['filename'];
			$fileExt=$fileInfo['extension'];
		 	$imageThumb='images/thumb/'.$fileName.'_cover.'.$fileExt;
		 	include 'tools/imageThumb.class.php'; 
		 	new ResizeImage($imageSavePath,(string)$ix,(string)$iy,'0',"../attachment/".$imageThumb);
		 	$user=pdo_get("ly_photobook_user",array("uniacid"=>$_W['uniacid'],"openid"=>$_W['openid']));
		 	if(empty($user)){
		 		$oneuser=array(
					"uniacid"=>2,
					"openid"=>$openid,
					"dealer"=>-1,   
				);
		 		pdo_insert("ly_photobook_user",$oneuser);
		 	}
		 	$Ouser=pdo_get("ly_photobook_user",array("uniacid"=>$_W['uniacid'],"openid"=>$_W['openid']));
		 	$data=array(
				"uniacid"=>2,
				"width"=>$width, 
				"height"=>$height,
				"size"=>$size,
				"thumb"=>$imageThumb,
				"original"=>$imhor,
				"createtime"=>time(),
				"user_id"=>$Ouser['id']
			);
			$resql=pdo_insert("ly_photobook_user_images",$data);
			if(!empty($resql)){
				$resdata['img']=tomedia($imageThumb);
				$resdata['message']="上传成功";
				return json_encode($resdata);
			}else{
				$resdata['code']="1"; 
				$resdata['message']="上传失败，数据插入失败了";
			return json_encode($resdata);
			}
		}
		return json_encode($resdata);
	}

	public function doMobileUserPhotos(){
		global $_GPC,$_W;
		//如果是要替换图片的话userimg、
	
		if($_GPC['type']=="change"){
			$HIDE=true;
		}else{
			$tid=$_GPC['tid'];
			// $timgcount=pdo_fetchcolumn('select sum(imagecount) from '.tablename('ly_photobook_template_sub').' where template_id='.$tid);
			$timgcount=30;
			$imgshell=pdo_get('ly_photobook_template_main',array('id'=>$tid),array('shell'))['shell'];
			if($imgshell==0){
				$timgcount=30;
			}else if($imgshell==1||$imgshell==2){
				$timgcount=22;
			}
			// $timgcount=pdo_get('ly_photobook_template_main',array('id'=>$tid),array('min_page'))['min_page'];

			$HIDE=false;
		}
		$Cuser=$this->Oneuser($_W['openid'],$_W['uniacid']);
		$userimg=pdo_getall('ly_photobook_user_images',array('user_id'=>$Cuser['uid'],"delete"=>0));
		$day=mktime(0,0,0,date('m'),date('d'),date('Y'));//日
		$week=mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y'));//周
		$month=mktime(0,0,0,date('m'),1,date('Y'));//月
		$year=mktime(0,0,0,1,1,date('Y'));//年
		//获取模板id
		$urlnew=$this->createMobileUrl('API_newAccept_pictures');
		$url=$this->createMobileUrl('API_Accept_pictures');
		$url_save=$this->createMobileUrl('API_SaveBook');
		$url_del=$this->createMobileUrl('Del_pic');
		$url_Turn=$this->createMobileUrl('turn');
		$url_ONE=$this->createMobileUrl('API_reONEpicture');
		$url_NEWtouch=$this->createMobileUrl('Newimgtouchit',array("change_images"=>1));
		include $this->template('userphotos');
	}
	/**
	 * 删除图片
	 */
	public function doMobileDel_pic(){
		global $_W,$_GPC;
		$clear = new commonFunction();
		if($_W['isajax']){
			foreach($_GPC['data'] as $index=>$row){	
				$temp =explode('.', $row['img_name']);
				$tem =explode('_', $temp[0]);
				$org = $tem[0].'.'.$temp[1];
				$clear->callInterfaceCommon('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$row["img_name"],'DELETE');
				$clear->callInterfaceCommon('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$org,'DELETE');
				$userimgid=substr($row['pid'],5);	
				$res = pdo_delete('ly_photobook_user_images',array('uniacid'=>$_W['uniacid'],'id'=>$userimgid));
				
			}
			$resArr['code'] = 0;
			$resArr['tid'] = $_GPC['tid'];
			echo json_encode($resArr);exit;
		}
	}
/**
 * 图片处理页
 */
	public function doMobileAPI_Accept_pictures(){
		global $_GPC,$_W;
		 $data=array(
		 	"pid"=>0,
		 	"img"=>$_GPC['image'],
		 	"code"=>0,//code为0则为正确，1为错误。message为错误信息
		 	"message"=>''
		 );
		 $ix=$_GPC['width']/3;
		 $iy=$_GPC['height']/3;
		 if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $data['img'], $result)){
		$type = $result[2];
		$media="images/usersphoto/".$_W['openid'].time().".{$type}";
		$new_file = "../attachment/".$media;
		if (file_put_contents(ATTACHMENT_ROOT.$new_file, base64_decode(str_replace($result[1], '',$_GPC['image'])))){
			// if($this->check_thisUser($_W['openid'],$_W['uniacid'])){
				$user=pdo_get("ly_photobook_user",array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
				include 'tools/imageThumb.class.php';                       
				$fileInfo=pathinfo($new_file);
				$fileName=$fileInfo['filename'];
				$fileExt=$fileInfo['extension'];
				$imageThumb='images/thumb/'.$fileName.'_cover.'.$fileExt;
				new ResizeImage($new_file, (string)$ix,(string)$iy, '0', ATTACHMENT_ROOT.$imageThumb);
				$PhotoData=array(
				"uniacid"=>$_W['uniacid'],
				"size"=>$_GPC['size'],
				"width"=>$_GPC['width'],
				"height"=>$_GPC['height'],
				"thumb"=>$imageThumb,  
				"original"=>$media,
				"createtime"=>time(),
				"user_id"=>$user['id']
				);
				$res1=pdo_insert('ly_photobook_user_images',$PhotoData);
				$res2=pdo_get("ly_photobook_user_images",array("thumb"=>$imageThumb,'user_id'=>$user['id']));
				if($res1){
					$data['pid']=$res2['id']; 
					$data['img']="../attachment/".$imageThumb; 
					$data['message']="成功"; 
				}else{
					$data['message']="失败"; 
				}
		}else{
			$data['code']=1;
			$data['message']="生成失败"; 
		}
	}	
		return json_encode($data);
	}
	/**
	 * 传给前端照片书的信息
	 */
	public function doMobileAPI_reONEpicture(){
		global $_W,$_GPC;
		$img=pdo_get("ly_photobook_user_images",array('id'=>$_GPC['uid']),array('width','height','thumb','id'));
		/**
		 * 获取缩略图的信息
		 */
		$img_info = ihttp_get('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$img['thumb'].'?x-oss-process=image/info');
		$de_info =json_decode($img_info['content'],true);
		/**
		 * 缩率图宽高
		 */
		$img['width'] =$de_info['ImageWidth']['value'];
		$img['height']=$de_info['ImageHeight']['value'];
		$data=array(
			"code"=>0,
			"message"=>"",
			"img"=>$img
		);   
		
		$data['img']['thumb']=$img['thumb'];
		if(!empty($img)){
			$data['message']="成功";
		}else{
			$data['message']="没有找到";
			$data['code']=1;
		}          
		// var_dump(data);
		return json_encode($data);
	}


	/**
	 * 编辑照片书
	 */
	public function doWebEditbook(){
		global $_GPC,$_W;
		if(checksubmit()){
			// 生成缩略图
			include 'tools/imageThumb.class.php';
			if(!(bool)strstr($_GPC['thumb'],'_cover')){
				$imagePath=ATTACHMENT_ROOT.$_GPC['thumb'];
				$fileInfo=pathinfo($imagePath);
				$fileName=$fileInfo['filename'];
				$fileExt=$fileInfo['extension'];
				// 制作缩略图

				$imageThumb='images/thumb/'.$fileName.'_cover.'.$fileExt;
				new ResizeImage($imagePath, '480', '360', '0', ATTACHMENT_ROOT.$imageThumb);
			}else{
				$imageThumb=$_GPC['thumb'];
			}
			$data=array(
					'detail'=>$_GPC['detail'],
					'name'=>$_GPC['name'],
					'thumb'=>$imageThumb,
					'shell'=>$_GPC['shell'],
					'size'=>$_GPC['size'],
					'price'=>$_GPC['price'],
					'inner_price'=>$_GPC['inner_price'],
					'createtime'=>time(),
					'uniacid'=>$_W['uniacid'],
					'max_page'=>$_GPC['max_page'],
					'min_page'=>$_GPC['min_page'],
					"booktype"=>$_GPC['booktype']
				);
			if(pdo_update('ly_photobook_template_main',$data,array('id'=>$_GPC['id']))){
				message('修改成功',$this->createWebUrl('photobook'),'success');
			}else{
				message('修改失败','','error');
			}
			exit;
		}
		load()->func('tpl');
		$book=pdo_get('ly_photobook_template_main',array('id'=>$_GPC['b_id']));
		include $this->template('createbook');
	}

	/**
	 * 模板列表
	 */
	public function doWebEdittemplateimage(){
		// set_time_limit(0);
		global $_GPC,$_W;
		$feng1=pdo_get("ly_photobook_template_sub",array("template_id "=>$_GPC['t_id'],"type"=>1),array("thumb"))["thumb"];
		// var_dump($feng1);
		$feng2=pdo_get("ly_photobook_template_sub",array("template_id "=>$_GPC['t_id'],"type"=>2),array("thumb"))["thumb"];
		$sql='select thumb,id from '.tablename('ly_photobook_template_sub').' where template_id=:template_id AND uniacid=:uniacid AND type=:type';
		$images=pdo_fetchall($sql,array('template_id'=>$_GPC['t_id'],'uniacid'=>$_W['uniacid'],'type'=>0));
	/*	$originals=array();
		foreach ($images as $key => $value) {
			$originals[]=$value['original'];
		}*/
		// var_dump($images);
		$ttitle=pdo_fetchcolumn('select name from ' .tablename('ly_photobook_template_main').' WHERE id='.$_GPC["t_id"]);
		$tcount=pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_template_sub') . " where template_id='{$_GPC['t_id']}'");
		include $this->template('templates');
	}

	/**
	 * 编辑模板
	 */
	public function doWebEditimage(){
		global $_W,$_GPC;
		/**
		 * 2018-4-14
		 * 前端发送请求 后台将图片上传到oss
		 */
		if($_W['isajax']){
			$thum =explode('.', $_GPC['org_name'])[0].'_thum.'.explode('.', $_GPC['org_name'])[1];
			$data ='x-oss-process=image/resize,w_360|sys/saveas,o_'.base64_encode($thum).',b_'.base64_encode('demo-photo');
			$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$_GPC['org_name'].'?x-oss-process', $data);
			if($response['status'] == 'OK'){		
				$resArr['code'] =0;
				$resArr['filename']=$_GPC['org_name'];
			}else{
				$resArr['code'] =1;
				$resArr['status'] =$response['status'];
			} 
			echo json_encode($resArr);exit;
		}
		if(checksubmit()){
	
			if(empty($_GPC['template_id'])&&!empty($_GPC['type'])&&!empty($_GPC['t_id'])){
				//如果是这俩参数，说明这个是要上传封面图的
				//然后查查有没有这个封面了，有的话就改，没有的话就是加。
				$feng=pdo_get("ly_photobook_template_sub",array('template_id'=>$_GPC['t_id'],'type'=>$_GPC['type']));
				$data=array(
					'original'=>$_GPC['filename'],
					'thumb'=>explode('.', $_GPC['filename'])[0].'_thum.'.explode('.', $_GPC['filename'])[1],
					'imagecount'=>$_GPC['imagecount'],
					'uniacid'=>$_W['uniacid'],
					'data' => htmlspecialchars_decode($_GPC['data']),
					"template_id"=>$_GPC['t_id'],
					"type"=>$_GPC['type'],
				);
				if(empty($feng)){
					pdo_insert("ly_photobook_template_sub",$data);	
				}else{
					pdo_update('ly_photobook_template_sub',$data,array('id'=>$_GPC['id']));
				}
				message('操作成功',$this->createWebUrl('edittemplateimage',array("t_id"=>$_GPC['t_id'])),'success');
			}
			$data=array(
					// 'original'=>$_GPC['bg'],
					// 'thumb'=>$imageThumb,
					'original'=>$_GPC['filename'],
					'thumb'=>explode('.', $_GPC['filename'])[0].'_thum.'.explode('.', $_GPC['filename'])[1],
					'imagecount'=>$_GPC['imagecount'],
					'uniacid'=>$_W['uniacid'],
					'data' => htmlspecialchars_decode($_GPC ['data'])
				);
				
			pdo_update('ly_photobook_template_sub',$data,array('id'=>$_GPC['id']));
			message('修改成功',$this->createWebUrl('edittemplateimage',array("t_id"=>$_GPC['t_id'])),'success');
			exit;
		}
		if(empty($_GPC['template_id'])&&!empty($_GPC['type'])&&!empty($_GPC['t_id'])){
			
			$image=pdo_get("ly_photobook_template_sub",array('template_id'=>$_GPC['t_id'],'type'=>$_GPC['type']));
			$image['imagecount']=0;
		}else{
			$image=pdo_get('ly_photobook_template_sub',array('id'=>$_GPC['template_id']));
		}
		

		$data = json_decode(str_replace('&quot;', "'", $image['data']), true);
		include $this->template('editimage');
	}

	/**
	 * ajax删除模板
	 */
	public function doWebDeleteThisT(){
		global $_GPC,$_W;
		if(pdo_delete('ly_photobook_template_sub',array('id'=>$_GPC['id']))){
			return '1';
		}else{
			return '0';
		}
	}

	/**
	 * 删除照片书
	 */
	public function doWebDeletetemplate(){
		global $_GPC,$_W;
		if(isset($_GPC['t_id'])){
			$thumb=pdo_fetchcolumn('select thumb from '.tablename('ly_photobook_template_main').' where id='.$_GPC['t_id']);
			// 删除文件
			// if(is_file(ATTACHMENT_ROOT.$thumb) && file_exists(ATTACHMENT_ROOT.$thumb)){
			// 	unlink(ATTACHMENT_ROOT.$thumb);
			// }
			pdo_delete('ly_photobook_template_main',array('id'=>$_GPC['t_id']));
			$images=pdo_fetchall('select original,thumb from '.tablename('ly_photobook_template_sub').' where template_id='.$_GPC['t_id']);
			// foreach ($images as $key => $value) {
			// 	if(is_file(ATTACHMENT_ROOT.$value['original']) && file_exists(ATTACHMENT_ROOT.$value['original'])){
			// 		unlink(ATTACHMENT_ROOT.$value['original']);
			// 	}
			// 	if(is_file(ATTACHMENT_ROOT.$value['thumb']) && file_exists(ATTACHMENT_ROOT.$value['thumb'])){
			// 		unlink(ATTACHMENT_ROOT.$value['thumb']);
			// 	}
			// }
			pdo_delete('ly_photobook_template_sub',array('template_id'=>$_GPC['t_id']));
			message('删除成功',$this->createWebUrl('photobook'),'success');
		}else{
			message('未知参数错误','','error');
		}
	}

	/**
	 * 代金券管理
	 */
	public function doWebCartlist(){
		global $_GPC,$_W;
		if(checksubmit()){
			$number=$_GPC['number'];
			$data=array(
					'list_price'=>$_GPC['list_price'],
					'dealer_price'=>$_GPC['dealer_price'],
					'uniacid'=>$_W['uniacid'],
					'createtime'=>time(),
					'number'=>$number
				);
			pdo_insert('ly_photobook_codes',$data);
			message('创建成功','','success');
			exit;
		}
		$pindex = max(1, intval($_GPC['page']));
	    $psize = $this->pageSize;
	    $sql="SELECT * FROM ".tablename('ly_photobook_codes')." WHERE uniacid=:uniacid ORDER BY id LIMIT ".($pindex-1)*$psize.",".$psize;
	    $list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid']));
	    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_codes') . " where uniacid='{$_W['uniacid']}'");
		$pager = pagination($total, $pindex, $psize);
		include $this->template('cartlist');
	}

	/**
	 * 删除代金券
	 */
	public function doWebDeleteCart(){
		global $_W,$_GPC;
		if(isset($_GPC['id'])){
			pdo_delete('ly_photobook_codes',array('id'=>$_GPC['id']));
			message('删除成功',$this->createWebUrl('cartlist'),'success');
		}else if(isset($_GPC['se']) && !empty($_GPC['se'])){
			foreach ($_GPC['se'] as $key => $value) {
				pdo_delete('ly_photobook_codes',array('id'=>$value));
			}
			message('删除成功',$this->createWebUrl('cartlist'),'success');
		}else{
			message('未知错误','','error');
		}
	}


	/**
	 * 工单列表
	 */
	public function doWebSer_message(){
		global $_W,$_GPC;
		if(checksubmit()){
			////////
			// 待做 发送模板消息，openid//
			////////
			$openid=pdo_fetchcolumn('select openid from '.tablename('ly_photobook_service').' WHERE id='.$_GPC['post_id']);
			$insert=array(
					'openid'=>'openid',
					'create_time'=>time(),
					'title'=>'回复：'.$openid,
					'detail'=>$_GPC['detail'],
					'user_phone'=>$_GPC['user_phone'],
					'order_id'=>'',
					'status'=>3,
					'uniacid'=>$_W['uniacid']
				);
			pdo_insert('ly_photobook_service',$insert);
			pdo_update('ly_photobook_service',array('status'=>1),array('id'=>$_GPC['post_id']));
			message('回复成功','','success');
		}
		$pindex = max(1, intval($_GPC['page']));
	    $psize = $this->pageSize;
	    if(isset($_GPC['status'])){
	    	$sql="SELECT * FROM ".tablename('ly_photobook_service')." WHERE uniacid=:uniacid AND status=:status ORDER BY id LIMIT ".($pindex-1)*$psize.",".$psize;	
	    	$list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid'],'status'=>$_GPC['status']));
		    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_service') . " where uniacid={$_W['uniacid']} AND status=".$_GPC['status']);
	    }else{
	    	$sql="SELECT * FROM ".tablename('ly_photobook_service')." WHERE uniacid=:uniacid AND status=:status ORDER BY id LIMIT ".($pindex-1)*$psize.",".$psize;	
	    	$list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid'],'status'=>0));
		    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_service') . " where uniacid={$_W['uniacid']}  AND status=0");
	    }
		$pager = pagination($total, $pindex, $psize);
		include $this->template('ser_message');
	}

	/**
	 * 删除工单
	 */
	public function doWebDeleteser_msg(){
		global $_W,$_GPC;
		if(isset($_GPC['id'])){
			pdo_delete('ly_photobook_service',array('id'=>$_GPC['id']));
			message('删除成功',$this->createWebUrl('ser_message'),'success');
		}else{
			message('未知错误','','error');
		}
	}

/*	public function doWebPost_ser_msg(){
		global $_W,$_GPC;
		else{
			message('未知错误','','error');	
		}
	}*/

	/**
	 * 随机字符串
	 */
	private function randChar($length = 10) { 
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$str =''; 
		for($i = 0; $i < $length; $i++){
			$str.= $chars[ mt_rand(0, strlen($chars) - 1) ]; 
		}
		return $str; 
	} 

	////////////
	// 手机端 //
	////////////
	/**
	 * 首页
	 */
	public function doMobileHome(){
		global $_GPC,$_W;
		//获取传来的参数，知道是哪个类别的。
		if(empty($_GPC['booktype'])){
			message("无此类别，请返回");
		}
		// include "TemplateMessage.php";
		// $TM=new TemplateMessage;
		// $opo="oh501wLhqQjYS5XNV0L03ZQMPn5E";
		// $data=array(
		// 	"first"=>"firs22222222222t",
		// 	"orderMoneySum"=>"orderMoneySum",
		// 	"orderProductName"=>"orderProductName",
		// 	"remark"=>"remark"
		// );
		// var_dump($this->createMobileUrl("home"));
		// $TM->OrderOK($opo,$data,$this->createMobileUrl("home"));
		$p_title="照片书列表";
		$m_active=1;
		$this->check_thisUser($_W['openid'],$_W['uniacid']);
		$sql='select id,name,price,sales,thumb from '.tablename('ly_photobook_template_main').' where uniacid=:uniacid AND booktype=:booktype';
		$list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid'],"booktype"=>$_GPC['booktype']));

		include $this->template('index');
	}

	/**
	 * 照片书模板详情页
	 */
	public function doMobileDetail(){
		global $_W,$_GPC;
		$detail=pdo_get('ly_photobook_template_main',array('id'=>$_GPC['id']));
		$tid=$_GPC['id'];
		// echo $tid;
		include $this->template('detail');
	}

	/**
	 * 客服中心
	 */
	public function doMobileService(){
		global $_GPC,$_W;
		if(checksubmit()){
			$insert_data=array(
					'openid'=>$_W['openid'],
					'create_time'=>time(),
					'title'=>$_GPC['title'],
					'detail'=>$_GPC['detail'],
					'user_phone'=>$_GPC['user_phone'],
					'order_id'=>$_GPC['order_id'],
					'status'=>0,
					'uniacid'=>$_W['uniacid']
				);
			if(pdo_insert('ly_photobook_service',$insert_data)){
				message('添加成功',$this->createMobileUrl('service'),'success');
			}else{
				message('添加失败','','error');
			}
			exit();
		}
		$p_title='客服中心';
		include $this->template('service');
	}
	/**
	 * 用户中心
	 */
	public function doMobileUserCenter(){
		global $_W,$_GPC;
		$account_api = WeAccount::create();
		$userInfo = $account_api->fansQueryInfo($_W['openid']);
		$userid = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id'];
		/**
		 * 未支付订单数
		 */
		$pay_count = count(pdo_getall('ly_photobook_order_main',array('uniacid'=>$_W['uniacid'],'user_id'=>$userid,'status'=>0)),0);
		 /**
		  * 待收货订单数
		  */
		$warting_count = count(pdo_getall('ly_photobook_order_main',array('uniacid'=>$_W['uniacid'],'user_id'=>$userid,'status in'=>array(1,2,3))),0);
		/**
		 * 待评价订单数
		 */
		$comment_count = count(pdo_getall('ly_photobook_order_main',array('uniacid'=>$_W['uniacid'],'user_id'=>$userid,'status'=>4)),0);
		$p_title='个人中心';
		 $m_active='3';
		include $this->template('usercenter');
	}
	// 我的下线
	public function doMobileJunior(){
		global $_W,$_GPC;
		$p_title='查看下线';
		$account_api = WeAccount::create();
		$userInfo = $account_api->fansQueryInfo($_W['openid']);
		$oneuserid=pdo_get("ly_photobook_share",array("uniacid"=>$_W['uniacid'],"openid"=>$_W['openid']),array('id'))['id'];
		if(empty($oneuserid)){
			message("找不到当前用户！","","error");
			exit();
		}
		$list=pdo_getall("ly_photobook_share",array("parentid"=>$oneuserid));
		if (empty($list)) {
			$userscount=0;
		}else{
			$userscount=count($list);
		}
		include $this->template('junior');
	}
	
	//检查模板数量是否满足最小为1的限制
	public function checkLimits($type,$list){
		$frameCount1=0;
		$frameCount2=0;
		$frameCount3=0;
		$frameCount4=0;
		$frameCount5=0;
		$frameCount6=0;
		foreach($list as $value){
			if($value['imagecount']==1)
				$frameCount1=$value['count'];
			if($value['imagecount']==2)
				$frameCount2=$value['count'];
			if($value['imagecount']==3)
				$frameCount3=$value['count'];
			if($value['imagecount']==4)
				$frameCount4=$value['count'];
			if($value['imagecount']==5)
				$frameCount5=$value['count'];
			if($value['imagecount']==6)
				$frameCount6=$value['count'];
		}
		load()->func('logging');
		logging_run('传进来的counts数组为：'.json_encode($list),'info','photobook');
		logging_run('在checklimits中，type和framecount分别为：'.$type.', frame1:'.$frameCount1.', frame2:'.$frameCount2.', frame3:'.$frameCount3.', frame4:'.$frameCount4.', frame5:'.$frameCount5.', frame6:'.$frameCount6,'info','photobook');
		if($type==0 && $frameCount1>0 && $frameCount2>0 && $frameCount3>0 && $frameCount4>0 )
			return true;
		else if(($type==1 || $type=2)&& $frameCount2>0 && $frameCount3>0 && $frameCount4>0  && $frameCount5>0  && $frameCount6>0 )
			return true;
		else
			return false;
	}
	
	//填充封面封底
	public function fillCovers($userPics,$tid,$order_main_id){
		global $_W,$_GPC;
		load()->func('logging');
		$tempsql='SELECT * FROM ims_ly_photobook_template_sub WHERE template_id=:tid and type>0';
		$temps=pdo_fetchall($tempsql,array(':tid'=>$tid));
		if(count($temps)!=2 || count($userPics)<2){
			message('封面封底不为2，或者照片书不够2张','','error');
			exit;
		}
		
		//填充封面封底
		$imgIndex=0;
		foreach ($temps as $key => $tep) {
			$this_uimgcount=$tep['imagecount'];
			if($this_uimgcount!=1){
				message('封面封底模板框数不为1','','error');
				exit;				
			}
			
			// 组织数据结构
			$str='[';
			$img=$userPics[$imgIndex];
			logging_run('FillCover中，imgIndex为：'.$imgIndex,'info','photobook');
			$imgIndex+=1;
			$userimgid=substr($img['pid'],5);
			$str.='{"top":"","type":"img","width":"","height":"","left":"","imageId":"'.$userimgid.'","imgurl":"'.$img['img_name'].'","roate":""},';
			$str=rtrim($str,',');//去掉多余的逗号，一个对象一个框图
			$str.=']';
			$Onedata=array(
				'uniacid'=>$_W['uniacid'],
				'template_id'=>$temps[$key]['id'],
				'trim'=>$str,
				'main_id'=>$order_main_id
			);
			//logging_run('FillCover中，插入的数据为：'.json_encode($Onedata),'info','photobook');
			$res=pdo_insert('ly_photobook_order_sub', $Onedata);
			if (empty($res)) {
				return false;
			}
		}
		return true;
	}
	
	//保存照片书的API
	//点击生成照片书，首先进入该函数，然后再进入Turn
	//思路：取出所有图片，取出所有模板页，然后插入一条照片书order_main，然后根据模板页遍历，每次遍历插入一条order_sub
	public function  doMobileAPI_SaveBook(){
		global $_W,$_GPC;
		$arrs=$_GPC['pdatas'];
		load()->func('logging');
		//logging_run('前端返回图片的信息:'.json_encode($arrs),'info','photobook');
		// 图片总数量
		$tid=$_GPC['tid'];
		//logging_run('tid为：'.$tid,'info','photobook');		
		
		
		//开始准备：取出模板的类型
		$templateMain=pdo_get('ly_photobook_template_main',array('id'=>$tid),array('shell'));
		$templateType=$templateMain['shell'];
		//logging_run('照片书类型：'.$templateType,'info','photobook');

		
		
		//步骤1：根据类型检查最少照片书：按照新的要求，软壳最少30张，硬壳最少22张，已经写死了
		$uimgcount=count($arrs);
		$userPiclimit=0;
		$totalPage=0;
		//如果$uimgcount太多，则重新赋值，将来可以在前端加上限制，重复加
		if($templateType==0){
			$userPiclimit=30;
			$totalPage=24;//不含封面封底
			if($uimgcount>68)
				$uimgcount=68;
		}
		else{
			$userPiclimit=22;
			$totalPage=10;//不含封面封底
			if($uimgcount>50)
				$uimgcount=50;
		}
			
		if($uimgcount<$userPiclimit){
			$redata=array(
				"errors"=>'用户图片不够，请选够'.$userPiclimit.'张图',
				'mainid'=>$mainid,
			);
			return json_encode($redata);
		}
		logging_run('步骤1结束','info','photobook');
		
		//步骤2：此处先判断模板中，每种框数的子模板必须大于1（对于软壳和硬壳，方款，要求不一样）		
		$sqlCount='SELECT  imagecount , COUNT( * ) as count FROM  ims_ly_photobook_template_sub WHERE  template_id=:tid GROUP BY  imagecount';
		$CountList=pdo_fetchall($sqlCount,array(':tid'=>$tid));
		logging_run('定位','info','photobook');
		$checkresult=$this->checkLimits($templateType,$CountList);
		logging_run('limits检查限制结果为：'.$checkresult,'info','photobook');
		// if(!$checkresult){
		// 	return json_encode(array('errors'=>'模板子页数量不足，请通知管理员','','error'));
		// }
		logging_run('步骤2结束','info','photobook');
		
		
		//步骤3:根据客户照片数取出对应的预设排版模式
		$modeType=0;//预设中，标识模式的变量
		if($templateType==0)
			$modeType=1;
		else 
			$modeType=2;
		$modeResult=pdo_fetch('SELECT * FROM ims_ly_photobook_modes WHERE userpics=:userpics and type=:modeType',array(':userpics'=>$uimgcount-2,':modeType'=>$modeType));//封面封底用了两张
		if(empty($modeResult)){
			return json_encode(array('errors'=>'找不到这种照片书对应的排版组合，请联系管理员','','error'));

		}
		logging_run('步骤3结束','info','photobook');

		//步骤4：插入订单表主表
		$userid=pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']),array('id'))['id'];
		$main=array(
			'uniacid'=>$_W['uniacid'],
			'createtime'=>time(),
			'user_id'=>$userid,
			'template_id'=>$tid
		);
		$result=pdo_insert('ly_photobook_order_main',$main);
		if (!empty($result)) {
			$mainid = pdo_insertid();
		}else{
			return false;
			exit;
		}
		logging_run('步骤4结束','info','photobook');

		
		//步骤5：首先处理封面封底
		if(!$this->fillCovers($arrs,$tid,$mainid)){
			return json_encode(array('errors'=>'封面封底填充失败'));

		}
		logging_run('步骤5结束','info','photobook');
		
		// 步骤6：开始遍历内页
		$tempsql='SELECT * FROM ims_ly_photobook_template_sub WHERE template_id=:tid and type=0 order by id';
		$temps=pdo_fetchall($tempsql,array(':tid'=>$tid));
		
		$errors=0;
		$imgIndex=2;//封面封底用了两张图，此处从第三张开始
		$safeValve =1; //安全阀值，当前最多为20，避免模板设置失误或其它因素，造成大量循环
		$pageIndex=1;
		
		
		//根据这个模板的imagecount数量添加trim数据
		while($imgIndex<$uimgcount && $safeValve<21 ){//只要客户照片没用完，并且未达到安全阀值，就继续循环，直到把用户照片用完	
			logging_run('safeValve:'.$safeValve,'info','photobook');
			$safeValve=$safeValve+1;

			foreach ($temps as $key => $tep) {
				$this_uimgcount=$tep['imagecount'];		
				
				logging_run('this_uimgcount:'.$this_uimgcount.',本模板页id：'.$tep['id'].',本页框数：'.$this_uimgcount.',当前mode：'.$modeResult['frame'.$this_uimgcount].',ImageIndex:'.$imgIndex,'info','photobook');
				if($modeResult['frame'.$this_uimgcount]>0){//配合未用完				
					if($uimgcount-$imgIndex<$this_uimgcount){//说明客户的图片不够用了//此问题不应该出现，配额未用完，图片不应该用完出现则提示并停止.注意照片从0开始，所以要加1，由于本页可用，所以又要减1
						logging_run('uimgcount为：'.$uimgcount.',imgIndex为：'.$imgIndex.'，this_uimgcount为：'.$this_uimgcount,'info','photobook');
						return json_encode(array('errors'=>'照片不够用了，请联系管理员'));
					}	
					//配额未用完，图片未用完，可以插入一页了
					$str='[';
					for($i=0;$i<$this_uimgcount;$i++){//this_uimgcount模板上框的数量
						$img=$arrs[$imgIndex];
						$imgIndex=$imgIndex+1;
						$userimgid=substr($img['pid'],5);
						$str.='{"top":"","type":"img","width":"","height":"","left":"","imageId":"'.$userimgid.'","imgurl":"'.$img['img_name'].'","roate":""},';
					}					
					$str=rtrim($str,',');//去掉多余的逗号，一个对象一个框图
					$str.=']';
					$Onedata=array(
						'uniacid'=>$_W['uniacid'],
						'template_id'=>$temps[$key]['id'],
						'trim'=>$str,
						'main_id'=>$mainid
					);
					logging_run('插入的数据为：'.json_encode($Onedata),'info','photobook');					
					$res=pdo_insert('ly_photobook_order_sub', $Onedata);
					
					if (!empty($res)) {
						$errors=0;
					}else{
						$errors+=1;
						return json_encode(array('errors'=>'插入照片书页失败，请联系管理员'));
					}
					
					logging_run('当前的页数：'.$pageIndex,'info','photobook');
					$pageIndex++;
					$modeResult['frame'.$this_uimgcount]=$modeResult['frame'.$this_uimgcount]-1;//预设排版样式中，该种框图消耗了一个
					//$imgIndex=$imgIndex+$this_uimgcount;//此处照片已确定使用this_uimgcount个 错误：不能在这里加
					if($imgIndex>=$uimgcount){//由于是两层循环，所以内部循环也得加。此处说明图片用完了
						logging_run('插入记录后，此时imgIndex为：'.$imgIndex.', uimgcount:'.$imgIndex,'info','photobook');
						return json_encode(array('errors'=>0,'mainid'=>$mainid));
					}
				}else{//该模式模板的配额用完，只能看下一个模板
					continue;
				}

				
			}
		}
		if($safeValve>20){//循环20次停止的话，可能刚好完毕，可能还没结束生成，此时阀值为21，不管哪种情况都要提示 
			return json_encode(array('errors'=>'循环次数过多，请特别检查'));

		}
		$redata=array(
			"errors"=>$errors,
			'mainid'=>$mainid
		);
		return json_encode($redata);
	}

	//生成照片书的API
	public function doMobileAPI_TurnBllk(){
		global $_W,$_GPC;
		$bookid=$_GPC['tid'];
		$sql="SELECT * FROM ims_ly_photobook_order_sub WHERE main_id={$bookid} ORDER BY id";
		$res=pdo_fetchall($sql);
	}

		/**
	 * 照片书预览
	 * http://huilife.cnleyao.com/app/index.php?i=2&c=entry&do=turn&m=photobook&tid=143&wid=468&time=1506673487000
	 */ 
	public function doMobileTurn(){
		global $_W,$_GPC;
		load()->func('logging');
		logging_run('进入Turn,bookid:'.$_GPC['tid']);
		$Npage=1;
		if($_GPC['Npage']>1){
			$Npage=$_GPC['Npage'];
		}
		set_time_limit(0);
		$account_api = WeAccount::create();
		$userInfo = $account_api->fansQueryInfo($_W['openid']);
		$bookid=$_GPC['tid'];
		//获取模板的属性
		$template_main_id=pdo_get('ly_photobook_order_main',array('id'=>$bookid),array('template_id'))['template_id'];
		$template_main_type=pdo_get('ly_photobook_template_main',array('id'=>$template_main_id),array('shell'))['shell'];

		$wid=$_GPC['wid'];
		$userid =pdo_get('ly_photobook_user',array("uniacid"=>$_W['uniacid'],"openid"=>$_W['openid']))['id'];
		if(empty($userid)){
			message("无此用户",error);
		}
		$sql="SELECT * FROM ims_ly_photobook_order_sub WHERE main_id={$bookid} ORDER BY id";
		$res=pdo_fetchall($sql);
		$list=array();
		
		include "tools/posterTools.php";
		$list=array();
		//遍历每个订单页，如果缩略合成图有了，则存到数组里，如果没有则生成，并保存
		logging_run('进入函数前----'.date("Y-M-D h:m:s",time()));
		foreach ($res as $key => $value) {
			// $value为order_sub的一条记录
			logging_run('执行order_sub----'.$value['id']);
			$trim=$value['trim'];
			$trimarray=json_decode($trim,true);
			// 获取模板
			$sql1="SELECT * FROM ims_ly_photobook_template_sub WHERE id={$value['template_id']} ORDER BY id limit 1";
			$res1=pdo_fetch($sql1);
			$pageType=$res1['type'];
			$T_photo=$res1['thumb'];
			// var_dump('模板图：'.$T_photo);
			// 筐的位置尺寸
			logging_run('进入函数前=='.str_replace('&quot;', "'", $res1['data']),'info','after');
			$data = json_decode(str_replace('&quot;', "'", $res1['data']), true);
			// 合成图片的位置
			// $img = ATTACHMENT_ROOT."BOOKS/temp_book_".$value['id'].".png";
			// var_dump($value['img_path']);
			logging_run('进入测试前----'.$_GPC['tid']);
			//找不到缩略图就调用函数生成，同时指定ordersub的id，便于更新trim信息
			// if(!is_file($value['img_path'])){
			// 	createzLeaf($trimarray,$data,$T_photo,$img,$value['id'],true);
			// }else{
			// }
			if(empty($value['img_path'])){
				
				$this->Compound($trimarray,$data,$T_photo,$value['id']);
			}
			
			$timg=$value['id'].$T_photo;
			/*$str.='{"top":"","type":"img","width":"","height":"","left":"","imageId":"'.$userimgid.'","imgurl":"'.$img['img'].'","roate":""},';*/
			if($pageType==1){
				$startPage=array('img'=>$timg,'id'=>$value['id']);
			}
			else if($pageType==2){
				$endPage=array('img'=>$timg,'id'=>$value['id']);
			}
			else{
				$list[]=array('img'=>$timg,'id'=>$value['id']);
			}
		}
		logging_run('执行完函数后----'.date("Y-M-D h:m:s",time()));

		// var_dump($list);
		include $this->template('turn2');  
	}
	//更换模板API
	public function doMobileAPI_ChangeTemp(){
		global $_W,$_GPC;
		$orderid=$_GPC['thisOrderId'];
		$Tid=$_GPC['tid'];
		$redata=array(
			"code"=>0,
			"message"=>""
		);
		$res=pdo_update("ly_photobook_order_sub",array("template_id"=>$Tid),array("id"=>$orderid));
		if($res){
			$redata['message']="更新成功了";
		}else{
			$redata['message']="更新失败";
			$redata['code']=1;
		}
		return json_encode($redata);
	}
	///////////////////////     单图操作    /////////////////////////////////////////////////////////////////////////////
	public function doMobileNewimgtouchit(){
		global $_W,$_GPC;
		$Npage=$_GPC['Npage'];
		// var_dump($Npage);
		$url=$this->createMobileUrl("userphotos",array("type"=>"change"));
		$url_reOnesave=$this->createMobileUrl("API_texttext");//对应前端单页保存的按钮，保存时发AJAX到这个链接
		$url_API_Ctep=$this->createMobileUrl("API_Ctep");//应该是更换模板
		$ordersubid=$_GPC['id'];
		$Oneordersub=pdo_get('ly_photobook_order_sub',array('id'=>$ordersubid));
		$ortype=pdo_get('ly_photobook_template_sub',array('id'=>$Oneordersub['template_id']));
		$trimtype=$ortype['type'];//该页类型；
		// var_dump($ortype);
		// exit();
		$url_turn=$this->createMobileUrl('turn',array('tid'=>$Oneordersub['main_id']));
		$trim=$Oneordersub['trim'];//操作信息
		$trimarr=json_decode($trim,true);
		$arrimgt=array();
		foreach ($trimarr as $key => $val) {
			$arrimgt[]=pdo_get("ly_photobook_user_images",array('id'=>$val['imageId']),array('width','height'));
		}
		$arrimgt=json_encode($arrimgt);
		$Onetemplatesub=pdo_get('ly_photobook_template_sub',array('id'=>$Oneordersub['template_id']));
		$tep=$Onetemplatesub['data'];//单图模板信息
		$tepimg=$Onetemplatesub['thumb'];//此模板图
		$template_id=$Onetemplatesub['template_id'];
		$Tlist=pdo_getall("ly_photobook_template_sub",array('template_id'=>$template_id,"type"=>0),array('id','thumb'));
		$enlist=json_encode($Tlist);
		// var_dump($trim);
		// exit();
		include $this->template('Newimgtouchit');
	}
	public function doMobileAPI_touch_it(){
		global $_W,$_GPC;
		$redata=array(
			"code"=>0,
			"Cmessage"=>"",
		);
		return json_encode($redata);
	}
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * 调整每一张图
	 */
	public function doMobileTouchit(){
		global $_W,$_GPC;
		$url=$this->createMobileUrl('API_SaveARR');
		$thisid=$_GPC['id'];
		$Onetouch=pdo_get('ly_photobook_order_sub',array('id'=>$thisid));
		$T_img=pdo_get("ly_photobook_template_sub",array('id'=>$Onetouch['template_id']),array('thumb'))['thumb'];
		$U_img=pdo_get("ly_photobook_user_images",array('id'=>$Onetouch['user_image_id']),array('thumb'))['thumb'];
		$data=pdo_get("ly_photobook_template_sub",array('id'=>$Onetouch['template_id']));
		$size = getimagesize(tomedia($T_img));
		$U_size = getimagesize(tomedia($U_img));
		$imgInfo = json_decode(str_replace('&quot;', "'", $data['data']), true);
		$Mobile_w=(int)($size[0]/318*$imgInfo[0]['width']);
		$Mobile_h=(int)($imgInfo[0]['height']*$size[0]/318);
		$Mobile_x=(int)($size[0]*$imgInfo[0]['left']/318);
		$Mobile_y=(int)($size[0]*$imgInfo[0]['top']/318);
		$BIGimg=$size[0];
		if($Mobile_w/$Mobile_h>$U_size[0]/$U_size[1]){
			$w=$Mobile_w; 
			$h=$U_size[1]*($Mobile_w/$U_size[0]); 
		}else{
			$h=$Mobile_h; 
			$w=$U_size[0]*($Mobile_w/$Mobile_h);
		}
		$img_left=($w-$Mobile_w)/2;
		$img_top=($h-$Mobile_h)/2;
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		$alltimg=pdo_getall("ly_photobook_template_sub",array('template_id'=>$template_id));
		$changeTurl=$this->createMobileUrl('API_ChangeTemp');
		$Touchit=$this->createMobileUrl('touchit',array('id'=>$thisid));
		$urlbook=$this->createMobileUrl('turn',array('tid'=>$Onetouch['main_id']));
		include $this->template('touchit');
	}

	//前端修改后在这里保存
	public function doMobileAPI_SaveARR(){
		global $_W,$_GPC;
		$redata=array(
			"code"=>0,
			"message"=>""
		);
		$arr=$_GPC['arr'];
		$arr=str_replace('&quot;', '"', $arr);
		$bookdata=json_decode($arr,true);
		$HTMLdata=htmlspecialchars_decode($arr);//trim存的是原始数据
		$result=pdo_update("ly_photobook_order_sub",array('trim'=>$HTMLdata),array("id"=>$bookdata['orderId']));
		if (!empty($result)){
			$imgInfo = $arr;
			// {"left":-32.9375,"top":244.34375,"type":"img","width":360,"height":360,"rotate":90,"orderId":187,"ping_width":360}" 
			$userimgid=pdo_get('ly_photobook_order_sub',array('id'=>$bookdata['orderId']));
			// 为了测试写的7
			$sql1="SELECT * FROM ims_ly_photobook_template_sub WHERE template_id=7 ORDER BY id limit 1";
			$res1=pdo_fetch($sql1);
			// var_dump($res1);
			$userid =pdo_get('ly_photobook_user',array("uniacid"=>$_W['uniacid'],"openid"=>$_W['openid']))['id'];
			// 暂时测试
			$userid=9;
			/*array(7) { ["id"]=> string(3) "187" ["uniacid"]=> string(1) "2" ["template_id"]=> string(1) "1" ["user_image_id"]=> string(2) "32" ["trim"]=> string(116) "{"left":-7.390625,"top":259.890625,"type":"img","width":360,"height":360,"rotate":90,"orderId":187,"ping_width":360}" ["main_id"]=> string(3) "117" ["img_path"]=> string(35) "/attachment/BOOKS/temp_book_32.jpeg" }*/
			$sql2="SELECT * FROM ims_ly_photobook_user_images WHERE user_id={$userid} AND id={$userimgid['user_image_id']} ORDER BY id limit 1";
			$res2=pdo_fetch($sql2);
			$U_photo=$res2['thumb'];
			$T_photo=$res1['thumb'];
			$data = json_decode(str_replace('&quot;', "'", $res1['data']), true);
			$size = getimagesize(tomedia($T_photo));
			// var_dump(json_encode($imgInfo));
			$inxy=array(
				(int)$size[0]/318*$data[0]['width'],
				(int)$data[0]['height']*$size[0]/318,
			);
			// 定义用户图在模板上的坐标
			$xy=array(
				'x'=>(int)$size[0]*$bookdata['left']/318,
				'y'=>(int)$size[0]*$bookdata['top']/318
			);
			/*$xy=array(
				'x'=>(int)($size[0]*$data[0]['left']/318),
				'y'=>(int)($size[0]*$data[0]['top']/318)
			);*/
			include "tools/posterTools.php";
			$img = ATTACHMENT_ROOT."BOOKS/temp_book_".$res2['id'].".png";
			createzLeaf_2(tomedia($T_photo),tomedia($U_photo),$inxy,$xy,$img,$bookdata['rotate']);
			$result=pdo_update("ly_photobook_order_sub",array('img_path'=>$img),array("id"=>$bookdata['orderId']));
			$redata["message"]="更新成功！";
		}else{
			$redata['message']="更新有问题"; 
			$redata['code']=1;
		}
		return json_encode($redata);
	}

	//保存操作函数--单图保存，点击保存按钮时，发AJAX到此函数-sun
	//保存新的trim值（trim值经过处理），更新模板页id（如果换了模板的话），更新缩略图（缩略图名称固定，所以不需要在数据库里update）
	public function doMobileAPI_Texttext(){
		global $_W,$_GPC;
		$recode=array(
			"messages"=>"",
			"code"=>0
		);
		$window_W=$_GPC['window_W'];
		load()->func('logging');
		// $this->writelog('前端参数：'.$window_W);
		$rejs=htmlspecialchars_decode($_GPC['Reone']);//前端传过来的单图的图标
		// var_dump($_GPC['Reone']);
		// exit()l
		$order_id=$_GPC['orderid'];

		include "tools/posterTools.php";
		if($_GPC['NewTid']!=-1){
			// 更换模板
			$template_id=$_GPC['NewTid'];
			$template=pdo_get('ly_photobook_template_sub',array('id'=>$template_id),array('thumb','data'));	
		}else{
			$template_id=pdo_get('ly_photobook_order_sub',array('id'=>$order_id),array('template_id'))['template_id'];
			$template=pdo_get('ly_photobook_template_sub',array('id'=>$template_id),array('thumb','data'));	
		}
		$trimarray=json_decode($rejs,true);
		// 合成图片的位置
		// $img = ATTACHMENT_ROOT."BOOKS/temp_book_".$order_id.".png";
		$data=json_decode($template['data'],true);
		// $trimarray=createzLeaf($trimarray,$data,$template['thumb'],$img,$order_id,false);//原来是$window_W，该数据为360，不如统一定为318
		$this->Compound($trimarray,$data,$template['thumb'],$order_id);
		if($_GPC['NewTid']!=-1){
			$ress=pdo_update('ly_photobook_order_sub',array('template_id'=>$_GPC['NewTid']),array('id'=>$order_id));
		}
		$recode['messages']=$ress;
		return json_encode($recode);
	}
	
	
	function writelog($data){
		file_put_contents(IA_ROOT."/addons/photobook/log.txt","\n".date('Y-m-d H:i:s',time())." : ".$data,FILE_APPEND);
	}
	//新的更换模板函数
	public function doMobileAPI_Ctep(){
		global $_W,$_GPC;
		$recode=array(
			"messages"=>"",
			"code"=>0
		);
		$Onelist=pdo_get("ly_photobook_template_sub",array('id'=>$_GPC['tid']));
		if(empty($Onelist)){
			$recode["messages"]="出现问题";
			$recode["code"]=1;
		}else{
			$recode["messages"]="更换模板图成功";
			$recode["tep"]=$Onelist['data'];
			$recode["tepimg"]=$Onelist['thumb'];
			$recode["NewTid"]=$Onelist['id'];
		}
		return json_encode($recode);
	}

	/**
	 * 券列表
	 */
	public function doMobileQuanList(){
		global $_W,$_GPC;
		$pindex = max(1, intval($_GPC['page']));
	    $psize = 10;
	    // 全部
	    $sql="SELECT * FROM ".tablename('ly_photobook_codes')." WHERE uniacid=:uniacid";
	    $list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid']));
	    // $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_codes') . " where status=0 AND uniacid={$_W['uniacid']}");
	    // $pager = pagination($total, $pindex, $psize);
		include $this->template('quanlist');
	}

	//添加地址页
	public function doMobileAddredd_add(){
		global $_W,$_GPC;
		if(checksubmit()){
			/*["receiver"]=> string(8) "32423543" ["phone"]=> string(7) "3453653" ["address"]=> string(26) "北京 北京市 东城区" ["detail"]=> string(10) "3333333333" */
			$user_id=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']),array('id'))['id'];
			$data=array(
				'receiver'=>$_GPC['receiver'],
				'phone'=>$_GPC['receiver'],
				'detail'=>$_GPC['address'].' '.$_GPC['detail'],
				'user_id'=>$user_id,
				'uniacid'=>$_W['uniacid']
			);
			if(pdo_insert('ly_photobook_address',$data)){
				message('地址添加完成','','success');
			}else{
				message('地址添加失败','','error');
			}
			exit();
		}
		include $this->template('addredd_add');
	}

	//地址列表页
	public function doMobileView_address(){
		global $_W,$_GPC;
		$url=$this->createMobileUrl("addredd_add");
		$user_id=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']),array('id'))['id'];
		$address=pdo_getall('ly_photobook_address',array('uniacid'=>$_W['uniacid'],'user_id'=>$user_id));
		include $this->template('view_address');
	}

	//下单页
	public function doMobilePlace_order(){
		global $_W,$_GPC;
		$order_id=(int)$_GPC['order_id'];
		$add_url=$this->createMobileUrl("view_address");
		// 检查上级是否为内部代理：
		$is_inner_sub=0;
		$share=pdo_get('ly_photobook_share',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
		if($share['parentid']){
			$parent_share=pdo_get('ly_photobook_share',array('id'=>$share['parentid']));
			if(pdo_get('ly_photobook_user',array('openid'=>$parent_share['openid'],'dealer'=>2))){
				$is_inner_sub=1;
			}
		}
		$order_id=(int)$_GPC['order_id'];
		$template_sub_id=pdo_get('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'main_id'=>$order_id),array('template_id'))['template_id'];
		$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'))['template_id'];
		$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','thumb','price','inner_price'));
		if(checksubmit()){
		
			if($is_inner_sub){
				$price=(float)$_GPC['count']*(float)$template['inner_price'];
			}else{
				$price=(float)$_GPC['count']*(float)$template['price'];
			}
			$data=array(
				'count'=>$_GPC['count'],
				'price'=>$price,
				'remark'=>$_GPC['remark'],
				'address_id'=>$_GPC['address_id'],
			);
			/**
			 * 是否加入购物车
			 */
			if($_GPC['shopping_cart'] == 1){
				$data['shopping_cart'] = 1;
			}
			if(pdo_update('ly_photobook_order_main',$data,array('id'=>$order_id))){
				header('Location: '.$this->createMobileUrl('Shop_order',array('order_id'=>$order_id)));
			}else{
				message('数据提交失败','','error');
			}
		}
		
		$user_id=pdo_get('ly_photobook_user',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']),array('id'))['id'];
		$address=pdo_getall('ly_photobook_address',array('uniacid'=>$_W['uniacid'],'user_id'=>$user_id));

		include $this->template('place_order');
	}

	//商品订单预览页
	public function doMobileShop_order(){
		global $_W,$_GPC;
		$order_id=$_GPC['order_id'];
		$template_sub_id=pdo_get('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'main_id'=>$order_id),array('template_id'))['template_id'];
		$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'))['template_id'];
		// 模板信息
		$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','inner_price','price','thumb'));
		// 商品数量
		$order_main=pdo_get('ly_photobook_order_main',array('id'=>$order_id),array('count','price','address_id'));
		$number=$order_main['count'];
		// $all_money=(float)$number*(float)$template['price'];
		$address=pdo_get('ly_photobook_address',array('id'=>$order_main['address_id']));
		// 检查上级是否为内部代理：
		$is_inner_sub=0;
		$share=pdo_get('ly_photobook_share',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
		if($share['parentid']){
			$parent_share=pdo_get('ly_photobook_share',array('id'=>$share['parentid']));
			if(pdo_get('ly_photobook_user',array('openid'=>$parent_share['openid'],'dealer'=>2))){
				$is_inner_sub=1;
			}
		}
		include $this->template('shop_order');
	}

	//代金券微信支付页
	public function doMobileQuan_order(){
		global $_W,$_GPC;
		$cid=$_GPC['cid'];
		$code=pdo_get('ly_photobook_codes',array('id'=>$cid));
		if(checksubmit()){
			$user_id=pdo_fetchcolumn('select id from '.tablename('ly_photobook_user').' where openid=:openid',array('openid'=>$_W['openid']));
			$insert=array(
				'uniacid'=>$_W['uniacid'],
				'price'=>$money,
				'codeid'=>$cid,
				'number'=>$_GPC['count'],
				'status'=>0,
				'user_id'=>$user_id
			);
			pdo_insert('ly_photobook_code_order',$insert);
			$money=(float)$code['dealer_price']*(float)$_GPC['count'];
			$tickets='code_'.date('YmdHis').'_'.random(10, 1).'_'.pdo_insertid();
			$params = array(
				'tid' => $tickets,
				'ordersn' => $tickets,
				'title' => '代金券',
				'fee' => $money
			);
			
			$this->pay($params);
			exit;
		}
		include $this->template('quan_order');
	}

	// 订单列表
	public function doMobileOrderList(){
		global $_W,$_GPC;
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid'],'count !='=>0),array(),'','id DESC');
		$Atitle="订单列表";
		foreach ($orders as $key => $order) {
			$template_sub_id=pdo_get('ly_photobook_order_sub',array('main_id'=>$order['id']),array('template_id'))['template_id'];
			$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'));
			// 模板信息
			$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','price','thumb'));
			$orders[$key]['template']=$template;
		}
		include $this->template('orderlist');
	}
	//购物车
	public  function doMobileShoppingCart(){
		global $_W,$_GPC;
		$Atitle="购物车";
		$p_title="购物车";
		$m_active=2;
		/**
		 * 
		 */
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid'],'status'=>0,'shopping_cart'=>1));
		foreach ($orders as $key => $order) {
			$template_sub_id=pdo_get('ly_photobook_order_sub',array('main_id'=>$order['id']),array('template_id'))['template_id'];
			$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'));
			// 模板信息
			$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','price','thumb'));
			$orders[$key]['template']=$template;
		}
		include $this->template('orderlist');
	} 
	//我的作品
	public function doMobileMyWork(){
		global $_W,$_GPC;
		$Atitle="我的作品";
		$p_title="我的作品";
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid']));
		$Atitle="订单列表";
		foreach ($orders as $key => $order){
			$template_sub_id=pdo_get('ly_photobook_order_sub',array('main_id'=>$order['id']),array('template_id'))['template_id'];
			$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'));
			// 模板信息
			$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','price','thumb'));
			$orders[$key]['template']=$template;
		}
		include $this->template('MyWork');
	}
	//我的作品
	/**
	 * 照片书支付
	 */
	public function doMobilePay(){
		global $_W,$_GPC;
		$money=pdo_get('ly_photobook_order_main',array('id'=>$_GPC['order_id']),array('price'))['price'];
		$tickets='book_'.date('YmdHis').'_'.random(10, 1).'_'.$_GPC['order_id'];
		$upData=array(
			'order_id'=>$tickets,
			'ticket'=>$tickets
		);
		pdo_update('ly_photobook_order_main',$upData,array('id'=>$_GPC['order_id']));
		
		$params = array(
			'tid' => $tickets,
			'ordersn' => $tickets,
			'title' => '照片书',
			'fee' => $money
		);
		$this->pay($params);
	}
	//生成随机数，微信小程序后台用到的。
	//微信小程序后台的API
	//获取图片
	//获取用户信息，微信小程序用

	//获取用户的图片，小程序用

	/**
	 * 成为照片书代理商
	 */
	public function doMobileBecomeAgent(){
		global $_W,$_GPC;
		$dealer=pdo_get('ly_photobook_user',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid'],'dealer'=>1));
		if(!empty($dealer)){
			message('你已经是代理！',$this->createMobileUrl('Homea'));
		}else{
			$self_share=pdo_get('ly_photobook_share',array('openid'=>$_W['openid']));
			if(!$self_share){//
				// 跳转到填写上级邀请码
				header('Location: '.$this->createMobileUrl('setparent'));
				exit;
			}
			if(!empty($_GPC['pay']) && $_GPC['pay']==1){
				$tickets='agent_'.date('YmdHis').'_'.random(10, 1);
				$params = array(
					'tid' => $tickets,
					'ordersn' => $tickets,
					'title' => '成为代理商',
					'fee' => 0.02
				);
				$this->pay($params);
				exit;
			}
			include $this->template('becomeagent');
		}	
	}

	///////////////
	// 填写代理商的邀请码 //
	///////////////
	public function doMobileSetparent(){
		global $_W,$_GPC;
		if(checksubmit()){
			$code=$_GPC['code'];
			$parent=pdo_get('ly_photobook_user',array('agent_code'=>$code));
			if($parent){
				// 查上级的shareid
				$prev_share_id=pdo_get('ly_photobook_share',array('openid'=>$parent['openid']),array('id'))['id'];
				if(empty($prev_share_id)){
					message('上级不存在#1','','error');
				}else{
					$account_api = WeAccount::create();
					$userinfo=$account_api->fansQueryInfo($_W['openid']);
					$insert=array(
						'avatar'=>$userinfo['headimgurl'],
						'nickname'=>$userinfo['nickname'],
						'openid'=>$_W['openid'],
						'uniacid'=>$_W['uniacid'],
						'parentid'=>$prev_share_id,
						'createtime'=>time()
					);
					if(pdo_insert('ly_photobook_share',$insert)){
						message('建立上下级关系成功',$this->createMobileUrl('becomeagent'),'success');
					}else{
						message('建立上下级关系失败','','error');
					}
				}

			}else{
				message('邀请码不存在','','error');
			}
			exit;
		}
		// 没有记录
		include $this->template('setparent');
	}

	// 支付回调函数
	public function payResult($params) {
        global $_W,$_GPC;
        ///////////////
        // params数据： //
        ///////////////
        /*rray(14) { ["weid"]=> string(1) "2" ["uniacid"]=> string(1) "2" ["result"]=> string(7) "success" ["type"]=> string(6) "wechat" ["from"]=> string(6) "return" ["tid"]=> string(31) "agent_20171007194847_6462226982" ["uniontid"]=> string(28) "2017100719485200013226668291" ["user"]=> string(1) "1" ["fee"]=> string(4) "0.02" ["tag"]=> array(1) { ["transaction_id"]=> string(28) "4200000023201710076697445140" } ["is_usecard"]=> string(1) "0" ["card_type"]=> string(1) "0" ["card_fee"]=> string(4) "0.02" ["card_id"]=> string(1) "0" }*/
        if($params['result']=='success'){
        	$type=explode('_',$params['tid'])[0];
        	$order_id=end(explode('_',$params['tid']));
        	if($type=='book'){
        		// 购买照片书的
        		pdo_update('ly_photobook_order_main',array('status'=>1),array('id'=>$order_id));
        		$confit = $this->module['config'];
        		$setting=$config['msetting'];
		        ///////////
        		// 给上级返利 //
		        ///////////
        		$prev_share_id=pdo_get('ly_photobook_share',array('openid'=>$_W['openid']),array('parentid'));
        		if($prev_share_id){
        			// 有上级
        			$prev=pdo_get('ly_photobook_share',array('id'=>$prev_share_id['parentid']));
        			$prev_userid=pdo_get('ly_photobook_user',array('openid'=>$prev['openid']),array('id'));
        			$prev_money=$setting['one_level']*$params['fee'];
        			$insert=array(
        				'userid'=>$prev_userid['id'],
        				'uniacid'=>$_W['uniacid'],
        				'createtime'=>time(),
        				'money'=>$prev_money,
        				'remark'=>'下级购买返利',
        				'status'=>0
        			);
        			pdo_insert('ly_photobook_user_rebate',$insert);
        			
        			if($prev['parentid']){
				       //////////////
        			// 给上级上级返利	 //
				       //////////////
        				$prev_prev=pdo_get('ly_photobook_share',array('id'=>$prev['parentid']));
        				$prev_prev_userid=pdo_get('ly_photobook_user',array('openid'=>$prev_prev['openid']),array('id'));
	        			$prev_prev_money=$setting['two_level']*$params['fee'];
	        			$insert=array(
	        				'userid'=>$prev_prev_userid['id'],
	        				'uniacid'=>$_W['uniacid'],
	        				'createtime'=>time(),
	        				'money'=>$prev_money,
	        				'remark'=>'下下级购买返利',
	        				'status'=>0
	        			);
	        			pdo_insert('ly_photobook_user_rebate',$insert);	
        			}
        		}

        		message('照片书支付成功',$this->createMobileUrl('usercenter'),'success');
        	}else if($type=='code'){
        		// 代理商购买代金券的
        		pdo_update('ly_photobook_code_order',array('status'=>1),array('id'=>$order_id));
        		// var_dump($)
        		message('代金券支付成功',$this->createMobileUrl('usercenter'),'success');
        	}else if($type=='agent'){
        		// 成为代理商
        		$code=$this->randChar(5);
        		$id=pdo_fetchcolumn('select id from '.tablename('ly_photobook_user').' where openid=:openid and uniacid=:uniacid',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
        		pdo_update('ly_photobook_user',array('dealer'=>1,'agent_code'=>$code),array('id'=>$id));
        		message('您已成为代理商',$this->createMobileUrl('usercenter'),'success');
        	}
        }else{
        	message('支付失败',$this->createMobileUrl('chart'),'error');
        }
    }
    /////////
    // 代理商代金券列表 //
    /////////
    public function doMobileAgentCardList(){
    	$this->_exec('AgentCardList',false);
    }

    /////////
    // 代理商代下级列表 //
    /////////
    public function doMobileAgentSubList(){
    	$this->_exec('AgentSubList',false);
    }

    ////////////
    // 给下级代金券 //
    ////////////
    public function doMobileGiveHeCard(){
    	global $_GPC,$_W;
		if($_W['isajax']){
			$codeid=pdo_get('ly_photobook_code_order',array('id'=>$_GPC['coid']),array('codeid'))['codeid'];
			$user_id=pdo_get('ly_photobook_user',array('openid'=>$_GPC['openid']))['id'];
			$insert=array(
				'uniacid'=>$_W['uniacid'],
				'user_id'=>$user_id,
				'code_id'=>$codeid,
				'status'=>0,
				'number'=>$_GPC['number']
			);
			if(pdo_insert('ly_photobook_user_code',$insert)){
				// 代理商的减掉
				$agent_code_num=pdo_get('ly_photobook_code_order',array('id'=>$_GPC['coid']),array('number'))['number'];
				$real_num=$agent_code_num-$_GPC['number'];
				pdo_update('ly_photobook_code_order',array('number'=>$real_num),array('id'=>$_GPC['coid']));
				return '1';
			}else{
				return '0';
			}
		}else{
			return '访问错误';
		}
    }

    //////////
    // 返利记录 //
    //////////
    public function doMobileMyRebateList(){
    	$this->_exec('MyRebateList',false);
    }

    //////////
    // 申请提现 //
    //////////
    ///
    public function doMobileApplyrebate(){
    	global $_W,$_GPC;
		$userid=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']))['id'];
		$sql='select sum(money) as sum from '.tablename('ly_photobook_user_rebate').' where uniacid=:uniacid and userid=:userid and status=:status';
		$allmoney=pdo_fetch($sql,array('uniacid'=>$_W['uniacid'],'userid'=>$userid,'status'=>0))['sum'];

		$insert=array(
			'uniacid'=>$_W['uniacid'],
			'userid'=>$userid,
			'createtime'=>time(),
			'status'=>0,
			'money'=>$allmoney
		);

		if(pdo_insert('ly_photobook_apply_rebate',$insert)){
			pdo_update('ly_photobook_user_rebate',array('status'=>1),array('status'=>0,'uniacid'=>$_W['uniacid'],'userid'=>$userid));
			return '1';
		}else{
			return '0';
		}
    }

    //////////
    // 奖励管理 //
    //////////
    public function doWebRebateList(){
    	$this->_exec('RebateList');
    }

    ////////////
    // 发送奖励提现 //
    ////////////
    public function doWebSendRebate(){
    	global $_W,$_GPC;
    	if($_W['isajax']){
    		$apply_rebate=pdo_get('ly_photobook_apply_rebate',array('id'=>$_GPC['id']));
    		$monay=round($apply_rebate['money']*100);
    		$openid=pdo_get('ly_photobook_user',array('id'=>$apply_rebate['userid']),array('openid'));
    		$res=$this->Enfunds($monay,$openid['openid']);
    		if($res['isok']){
    			pdo_update('ly_photobook_apply_rebate',array('status'=>1),array('id'=>$_GPC['id']));
    			return '1';
    		}else{
    			return '0';
    		}
    	}else{
    		echo '非法访问';
    	}
    }

	//////////
	// 发放奖励 //
	//////////
    private function Enfunds($amount,$openid){
		global $_W,$_GPC;
		$url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
		$this_data='timeA'.time().'A';
		load()->func('communication');
		$pars = array();
		$config = $this->module['config'];
		$setting = $config['msetting'];
		$pars = array(
			'mch_appid' => $setting['appid'],
			'mchid' => $setting['mchid'],
			'nonce_str'=>random(32),
			'partner_trade_no'=>$this_data.random(10, 1),
			'openid'=>$openid,
			'check_name'=>'NO_CHECK',
			'amount'=>$amount,
			'desc'=>'您所提现的收益额',
			'spbill_create_ip'=>$_SERVER['SERVER_ADDR']
		);
		ksort($pars, SORT_STRING);
		$string1 = "";
		foreach($pars as $k => $v) {
			$string1 .= "{$k}={$v}&";
		}
		$string1 .= "key={$setting['password']}";
		$pars['sign'] = strtoupper(md5($string1));
		$xml = array2xml($pars);
		$myfile = fopen("../addons/photobook/error.xml", "w") or die("Unable to open file!");
		$extrasa = array();
		$extrasa['CURLOPT_CAINFO'] ='../addons/photobook/cert/rootca.pem.' . $_W['uniacid'];
		$extrasa['CURLOPT_SSLCERT'] ='../addons/photobook/cert/apiclient_cert.pem.' . $_W['uniacid'];
		$extrasa['CURLOPT_SSLKEY'] ='../addons/photobook/cert/apiclient_key.pem.' . $_W['uniacid'];
		$resp = ihttp_request($url,$xml,$extrasa);
		if(is_error($resp)){
			$return_all['isok'] = false;
		}else{
			$xml = '<?xml version="1.0" encoding="utf-8"?>' . $resp['content'];
			$dom = new DOMDocument();
			if($dom->loadXML($xml)){
				$xpath = new DOMXPath($dom);
				$return_code = $xpath->evaluate('string(//xml/return_code)');
				$result_code = $xpath->evaluate('string(//xml/result_code)');
				$return_all=array();
				if(strtolower($return_code) == 'success' && strtolower($result_code) == 'success'){
					$return_all['isok'] = true;
					$return_all['mch_appid'] = $xpath->evaluate('string(//xml/mch_appid)');
					$return_all['mchid'] = $xpath->evaluate('string(//xml/mchid)');
					$return_all['partner_trade_no'] = $xpath->evaluate('string(//xml/partner_trade_no)');
					$return_all['payment_no']=$xpath->evaluate('string(//xml/payment_no)');
					$return_all['payment_time']=$xpath->evaluate('string(//xml/payment_time)');
				}else{
					$return_all['isok'] = false;
					$return_all['return_msg']=$xpath->evaluate('string(//xml/return_msg)');
					$return_all['err_code_des']=$xpath->evaluate('string(//xml/err_code_des)');
				}
				$return_all['return_code']=$return_code;
				$return_all['result_code']=$result_code;

				fwrite($myfile, $resp['content']);
				fclose($myfile);
				return  $return_all;
			}
		}
	}

}





