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
include 'TemplateMessage.php';
include 'tools/aliyun-dysms-php-sdk/api_demo/SmsDemo.php';
class PhotobookModuleSite extends WeModuleSite {
	//测试
	public function doMobileGetdata(){
		
	}
	private $maxTeamCount;//团队最多人数
	public function doWebDown_list(){
		global $_W,$_GPC;
		
		if($_GPC['op'] == 'down'){
			$value = pdo_get('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['one_id']));
			// $value为order_sub的一条记录
			$trim = $value['trim'];
			//修剪信息
			$trimarray=json_decode($trim,true);
			// 获取模板
			$sql1="SELECT * FROM ims_ly_photobook_template_sub WHERE id = ".$value['template_id']." ORDER BY id limit 1";
			$res1=pdo_fetch($sql1);
			//模板图原图
			$T_photo=$res1['original'];
			// 筐的位置尺寸
			$data = json_decode(str_replace('&quot;', "'", $res1['data']), true);
			if(empty($value['down_img']))
				$this->Compound_org($trimarray,$data,$T_photo,$value['id'],$_GPC['index']);
			$value = pdo_get('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['one_id']));
			/**
			 * 检查订单中是否全部下载完 如果全部下载完 状态改为打印中
			 */
			$all_order = pdo_getall('ly_photobook_order_sub',array('uniacid'=>$W['uniacid'],'main_id'=>$value['main_id']));
			$is_done = 0;
			foreach($all_order as $index=>$row){
				if(empty($row['down_img']))
					$is_done++;
			}
			if($is_done == 0){
				pdo_update('ly_photobook_order_main',array('status'=>2),array('status'=>1,'id'=>$value['main_id']));
			}
			$resArr['code']=0;
			$resArr['id']=$_GPC['one_id'];
			$resArr['file']=$value['down_img'];
			echo json_encode($resArr);exit;			
		}else{
			$list = pdo_getall('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'main_id'=>$_GPC['order_id']));
			$count = count($list);
		}
		include $this->template('down_list');
	}
	/**
	 * 合成原图,提供下载打印
	 * trimarray:修剪信息；$data：模板的框图信息；$T_photo:模板原图 $ordersub_id:订单页ID
	 */
	// $trimarray,$data,$T_photo,$ordersub_id
	public function Compound_org($trimarray,$data,$T_photo,$ordersub_id,$index){
		global $_W,$_GPC;
		/**
		 * 获取模板原图的信息
		 */
		$org_info = ihttp_get('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$T_photo.'?x-oss-process=image/info');
		$de_org_info =json_decode($org_info['content'],true);
		/**
		 * 模板原图宽高
		 */
		$org_h = $de_org_info['ImageHeight']['value'];
		$org_w = $de_org_info['ImageWidth']['value'];
		/**
		 * 先生成模板图的副本
		 */
		$temp =explode('.',$T_photo);
		$index++;
		/**
		 *   将模板图副本格式保存为jpg  
		 *   1. 在png模板图片上，覆盖原图png模板图片有问题
		 *   2 .如果图片超过20M，将进行质量变换（只支持jpg格式或webp）功能暂时没有
		 */
		$template_thumb = $ordersub_id."_".$index.'.jpg';
		$send_data ='x-oss-process=image/resize,p_100/format,jpg|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
		$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$T_photo.'?x-oss-process', $send_data);
		load()->func('logging');
		foreach ($data as $key => $frame){
			/**
			 * 判断是否为图片 生成临时
			 */
			if($frame['type'] == 'img'){
				//创建原图的副本
				
				$org =str_replace("_thum","",$trimarray[$key]['imgurl']);
				$thumb_img = "roate_".$org; 

				//如果图片的宽高 其中一个超过4096 无法生成缩略图 在此进行转换
				$img_org_info = ihttp_get('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$org.'?x-oss-process=image/info');
				$de_org_info =json_decode($img_org_info['content'],true);
				if($de_org_info['ImageHeight']['value'] > 4096 || $de_org_info['ImageWidth']['value'] > 4096){
					//如果原图有一边超过4096 将原图按边缩放到4096  替换原图
					if($de_org_info['ImageHeight']['value'] > 4096 && $de_org_info['ImageWidth']['value'] > 4096){
						if($de_org_info['ImageHeight']['value'] > $de_org_info['ImageWidth']['value'])
							$side = 'h';
						else
							$side = 'w';
						$send_data ='x-oss-process=image/resize,'.$side.'_4096|sys/saveas,o_'.base64_encode($org).',b_'.base64_encode('demo-photo');
					}elseif($de_org_info['ImageHeight']['value'] > 4096){
						$send_data ='x-oss-process=image/resize,h_4096|sys/saveas,o_'.base64_encode($org).',b_'.base64_encode('demo-photo');
					}else
						$send_data ='x-oss-process=image/resize,w_4096|sys/saveas,o_'.base64_encode($org).',b_'.base64_encode('demo-photo');
					$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$org.'?x-oss-process', $send_data);
					logging_run($response,'info','active');
				}
				if($trimarray[$key]['roate']==90 || $trimarray[$key]['roate']==-270){
					$send_data ='x-oss-process=image/rotate,90|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}elseif($trimarray[$key]['roate']==180 || $trimarray[$key]['roate']==-180){
					$send_data ='x-oss-process=image/rotate,180|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}elseif($trimarray[$key]['roate']==270 || $trimarray[$key]['roate']==-90){
					$send_data ='x-oss-process=image/rotate,270|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}else{
					$send_data ='x-oss-process=image/resize,p_100|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				}
				//在原图基础上,是否进行旋转
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$org.'?x-oss-process', $send_data);
				//记录文本日志
				
				/**
				 * 获取图片的信息
				 */
				$img_info = ihttp_get('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$thumb_img.'?x-oss-process=image/info');
				$de_info =json_decode($img_info['content'],true);
				/**
				 * 原图宽高
				 */
				$img_h = $de_info['ImageHeight']['value'];
				$img_w = $de_info['ImageWidth']['value'];
				
				/**
				 * 框的宽高
				 */
				
				$coefficient = $org_w / 360;
				$frame_w = trim($frame['width'],'px') * $coefficient;
				$frame_h = trim($frame['height'],'px') * $coefficient;
				$frame_top = trim($frame['top'],'px') * $coefficient;
				$frame_left = trim($frame['left'],'px') * $coefficient;

				if($frame_left > 4096)
					$frame_left = 4096;
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
					$tailor_x = abs(trim($trimarray[$key]['Xleft'],'px')) * $coefficient;
					$tailor_y = abs(trim($trimarray[$key]['Xtop'],'px')) * $coefficient;
				}
				/**
				 * 将要水印的图片缩放与裁剪
				 */ 
				$tailor_data = ',limit_0/crop,x_'.round($tailor_x).',y_'.round($tailor_y).',w_'.round($frame_w).',h_'.round($frame_h);
				$send_data ='x-oss-process=image/resize,'.$thumb_type.'_'.round($thumb_value).$tailor_data.'|sys/saveas,o_'.base64_encode($thumb_img).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$thumb_img.'?x-oss-process', $send_data);
				
				/**
				 * 合成模板
				 * 将图片转换为jpg格式
				 */
				
				$compound_data =base64_encode($thumb_img."?x-oss-process=image/format,jpg");
				$compound_data =str_replace('+', '-', $compound_data);
				$compound_data =str_replace('/', '_', $compound_data);

				$send_data ='x-oss-process=image/watermark,image_'.$compound_data.',t_100,g_nw,x_'.round($frame_left).',y_'.round($frame_top).'|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$template_thumb.'?x-oss-process', $send_data);
				logging_run($response,'info','active');
				/**
				 * 删除上传的临时图片
				 */
				$clear = new commonFunction();
				//从oss删除临时图片
				$clear->callInterfaceCommon('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$thumb_img,'DELETE');
			}

		}
		/**
		 * png覆盖
		 */
		$send_data ='x-oss-process=image/watermark,image_'.base64_encode($T_photo).',g_nw,x_0,y_0|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
		$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$template_thumb.'?x-oss-process', $send_data);
		/**
		 * 最后查找文字加上水印
		 */
		foreach ($data as $key => $frame){
			
			/**
			 * 判断是否为图片 生成临时
			 */
			if($frame['type'] == 'name'){
				load()->func('logging');
				
				//处理的图片进行编码

				$text_data =base64_encode($trimarray[$key]['text']);
				$size_data = round(trim($frame['size'],'px')* $coefficient) ;
				if($frame['color'] == '#000')
					$color_data = '000000';
				else
					$color_data = trim($frame['color'],'#');
				$x_data = round($frame['left'] * $coefficient);
				$y_data = round($frame['top']* $coefficient);
				$send_data ='x-oss-process=image/watermark,type_d3F5LXplbmhlaQ,text_'.$text_data.',size_'.$size_data.',color_'.$color_data.',g_nw,x_'.$x_data.',y_'.$y_data.'|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$template_thumb.'?x-oss-process', $send_data);
				// logging_run('id===='.json_encode($response),'info','compo333333und');
			}
		}
		/**
		 * 更新数据表
		 */
		pdo_update('ly_photobook_order_sub',array('down_img'=>$template_thumb),array('id'=>$ordersub_id));
		
	}
	/**
	 * 首页轮播图
	 */
	public function doWebCarousel(){
		global $_GPC,$_W;
		empty($_GPC['op'])? $operation = 'list' : $operation = $_GPC['op'];

		if($operation == 'list'){
			$pictures = pdo_getall('ly_photobook_carousel',array('uniacid'=>$_W['uniacid']));
		}elseif($operation == 'add'){
			if(checksubmit()){
				$data = [
					'name'=>$_GPC['picture'],
					'url'=>$_GPC['url'],
					'uniacid'=>$_W['uniacid']
				];
				if(!empty($_GPC['id']))
					$res = pdo_update('ly_photobook_carousel',$data,array('id'=>$_GPC['id']));
				else
					$res = pdo_insert('ly_photobook_carousel',$data);
				if($res)
					message('操作成功',$this->createWebUrl('carousel'),'success');
				else
					message('操作失败',$this->createWebUrl('carousel',array('op'=>'add')),'error');
			}else{

				$pic = pdo_get('ly_photobook_carousel',array('id'=>$_GPC['id']));
			}
		}elseif($operation == 'del'){
			if(!empty($_GPC['id'])){
				$res = pdo_delete('ly_photobook_carousel',array('id'=>$_GPC['id']));
				if($res)
					message('删除成功',$this->createWebUrl('carousel'),'success');
				else
					message('删除失败',$this->createWebUrl('carousel'),'error');
			}
		}
		include $this->template('carousel');
	}
	/**
	 * 分类管理
	 */
	public function doWebKind(){
		include_once 'inc/kind.php';
	}
	private function getnickname($openid){
		load()->model('mc');
		return mc_fetch($openid)['nickname'];
	}
	/**
	 * 用户管理
	 */
	public function doWebClient_mag(){
		include_once 'inc/client_mag.php';
	}
	/**
	 * 文档管理后台
	 */
	public function doWebDocument_mag(){
		global $_W,$_GPC;

		empty($_GPC['op'])? $operation = 'list' : $operation = $_GPC['op'];

		if($operation == 'list'){
			$list = pdo_getall('ly_photobook_documents',array('uniacid'=>$_W['uniacid']),array(),'','id desc');
		}elseif($operation == 'add'){
			if(checksubmit()){
				$data = [
					'uniacid'=>$_W['uniacid'],
					'content'=>$_GPC['content'],
					'title'=>$_GPC['title'],
					'insert_time'=>time()
				];
				if(empty($_GPC['id'])){
					$res =  pdo_insert('ly_photobook_documents',$data);
				}else{
					$res =  pdo_update('ly_photobook_documents',$data,array('id'=>$_GPC['id']));
				}
				if($res)
					message('操作成功',$this->createWebUrl('document_mag'),'success');
				else
					message('操作失败',$this->createWebUrl('document_mag'),'success');	
			}else{
				$document = pdo_get('ly_photobook_documents',array('id'=>$_GPC['id']));
			}
		}elseif($operation == 'del'){
			if(!empty($_GPC['id']))
				$res = pdo_delete('ly_photobook_documents',array('id'=>$_GPC['id']));
			if($res)
				message('操作成功',$this->createWebUrl('document_mag'),'success');
			else
				message('操作失败',$this->createWebUrl('document_mag'),'success');	
		}
		include $this->template('document_mag');
	}
	/**
	 * 文档列表
	 */
	public function doMobileDocument_list(){
		global $_W,$_GPC;
		$p_title='文档管理';

		$documents = pdo_getall('ly_photobook_documents',array('uniacid'=>$_W['uniacid']),array(),'','id desc');
		include $this->template('document_list');
	}
	/**
	 * 文档明细
	 */
	public function doMobileDocument_detail(){
		global $_W,$_GPC;
		$p_title='文档管理';

		$document = pdo_get('ly_photobook_documents',array('id'=>$_GPC['id']));
		include $this->template('document_detail');
	}
	/**
	*基本设置
	*/
	public function doWebDeal_setting(){
		global $_W,$_GPC;
		$cards = pdo_getall('ly_photobook_codes',array('uniacid'=>$_W['uniacid']));
		if(checksubmit()){
			$insert_data = [
				'deal_price'=>$_GPC['price'],
				'take_price'=>$_GPC['take_price'],
				'parent_price'=>$_GPC['parent_price'],
				'remote_price'=>$_GPC['remote_price'],
				'send_card_type'=>$_GPC['send_card_type'],
				'send_card_count'=>$_GPC['send_card_count'],
				'team_direct_cout'=>$_GPC['team_direct_cout'],
				'team_team_count'=>$_GPC['team_team_count'],
				'team_rebate_price'=>$_GPC['team_rebate_price'],
				'partner_direct_cout'=>$_GPC['partner_direct_cout'],
				'partner_partner_cout'=>$_GPC['partner_partner_cout'],
				'team_max_count'=>$_GPC['team_max_count'],
				'has_team_price'=>$_GPC['has_team_price'],
				'no_team_price'=>$_GPC['no_team_price'],
				'partner_parent_price'=>$_GPC['partner_parent_price'],
				'partner_welfare'=>$_GPC['partner_welfare'],
				'isrebate'=>$_GPC['isrebate'],
				'rebate_agin_price'=>$_GPC['rebate_agin_price'],
				'fans_nocard_price'=>$_GPC['fans_nocard_price'],
				'agent_nocard_price'=>$_GPC['agent_nocard_price'],
				'agent_nocard_parent'=>$_GPC['agent_nocard_parent'],
				'parent_fans_price'=>$_GPC['parent_fans_price'],
				'uniacid'=>$_W['uniacid']
			];
			if(empty($_GPC['id'])){
				$res = pdo_insert('ly_photobook_setting',$insert_data);
			}else{
				$res = pdo_update('ly_photobook_setting',$insert_data,array('id'=>$_GPC['id']));
			}
			if($res){
				$this->change_condition_check();//改变配置重新检查会员身份
				message('操作成功',$this->createWebUrl('deal_setting'),'success');
			}
			else
				message('操作失败',$this->createWebUrl('deal_setting'),'error');
		}else{
			$deal_setting = pdo_get('ly_photobook_setting',array('uniacid'=>$_W['uniacid']));
		}
		include $this->template('deal_setting');
	}
	/**
	 * 改变设置条件重新检查用户身份
	 */
	public function change_condition_check(){
		global $_W;
		$userlist = pdo_getall('ly_photobook_user',array('uniacid'=>$_W['uniacid']));
		foreach ($userlist as $key => $value) { 
			$this->isUpgrade($value['id']);
		}
	}
	/**
	 * trimarray:修剪信息；$data：模板的框图信息；$T_photo:模板图 $ordersub_id:订单页ID
	 */
	// $trimarray,$data,$T_photo,$ordersub_id
	public function Compound($trimarray,$data,$T_photo,$ordersub_id,$compound_name){
		global $_W,$_GPC;
		$template_thumb = $compound_name;
		$send_data ='x-oss-process=image/resize,w_360|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
		$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$T_photo.'?x-oss-process', $send_data);

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
				 * 将图片转换为jpg格式
				 */
				$compound_data =base64_encode($thumb_img."?x-oss-process=image/format,jpg");
				$compound_data =str_replace('+', '-', $compound_data);
				$compound_data =str_replace('/', '_', $compound_data);

				$send_data ='x-oss-process=image/watermark,image_'.$compound_data.',t_100,g_nw,x_'.round($frame_left).',y_'.round($frame_top).'|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$template_thumb.'?x-oss-process', $send_data);
				
				/**
				 * 删除上传的临时图片
				 */
				
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
		 * 最后查找文字加上水印
		 */
		foreach ($data as $key => $frame){
			
			/**
			 * 判断是否为图片 生成临时
			 */
			if($frame['type'] == 'name'){
				load()->func('logging');
				
				//处理的图片进行编码

				$text_data =base64_encode($trimarray[$key]['text']);
				$size_data = abs(trim($frame['size'],'px'));
				if($frame['color'] == '#000')
					$color_data = '000000';
				else
					$color_data = trim($frame['color'],'#');
				$send_data ='x-oss-process=image/watermark,type_d3F5LXplbmhlaQ,text_'.$text_data.',size_'.$size_data.',color_'.$color_data.',g_nw,x_'.round($frame['left']).',y_'.round($frame['top']).'|sys/saveas,o_'.base64_encode($template_thumb).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$template_thumb.'?x-oss-process', $send_data);
			}
		}
		/**
		 * 更新数据表
		 */
		$trim =json_encode($trimarray);
		pdo_update('ly_photobook_order_sub',array('img_path'=>$template_thumb,'trim'=>$trim),array('id'=>$ordersub_id));
	}
	public function getImageSize($imagename){
		$img_info = ihttp_get('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$imagename.'?x-oss-process=image/info');
		$de_info =json_decode($img_info['content'],true);
		return $de_info['FileSize']['value'];
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
					'size'=>$this->getImageSize($_GPC['org_name']),
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
	//首页
	public function doMobileHomea(){
		global $_W,$_GPC;
		$carousels= pdo_getall('ly_photobook_carousel',array('uniacid'=>$_W['uniacid']));
		include $this->template("home");
	}

	/**
	 * 订单管理
	 */
	public function doWebBook_order(){
		global $_W,$_GPC;
		
		if(checksubmit()){
			// 填写快递信息
			$update=array('express'=>$_GPC['express'],'express_id'=>$_GPC['express_id'],'status'=>3);
			if(pdo_update('ly_photobook_order_main',$update,array('id'=>$_GPC['book_id']))){
				/**
				 * 发送模板消息
				 */
				$userid = pdo_get('ly_photobook_order_main',array('id'=>$_GPC['book_id']))['user_id'];
				$openid = pdo_get('ly_photobook_user',array('id'=>$userid))['openid'];
				$send_mess = new templatemessage();
				$send_arr = [
					'first'=>'您好，您定制的宝贝，正快马加鞭赶到您身边~',
					'k1'=>$_GPC['book_id'],
					'k2'=>$_GPC['express'],
					'k3'=>$_GPC['express_id'],
					'rem'=>'收到宝贝后如有质量问题，请第一时间点击右下角【我的时光印】-【客服中心】,提交售后工单，随时为您提供售后服务~',
					'openid'=>$openid,
					'mid1'=>'K2bMqNCetsXXeZTcKFsw_lq4_7gSXOUBAa95Zjpmgyk',
					'url'=>''
				];
				$send_mess->order_send($send_arr);
				message('修改完成',$this->createWebUrl('book_order'),'success');
			}else{
				message('修改失败','','error');
			}
			exit;
		}
		$pindex = max(1, intval($_GPC['page']));
		$psize = 20;
		$status=empty($_GPC['status'])?1:$_GPC['status'];
		$where=array('uniacid'=>$_W['uniacid'],'status'=>$status);
		$sql1='select * from '.tablename('ly_photobook_order_main').' where uniacid=:uniacid and status=:status ORDER BY id DESC LIMIT '.($pindex-1)*$psize.','.$psize;
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
		$pindex = max(1, intval($_GPC['page']));
		$psize = 20;
		if($_W['isajax']){
			$status = 0;
			if(!empty($_GPC['uid']))
				$status = pdo_update('ly_photobook_user',array('name'=>$_GPC['name'],'phone'=>$_GPC['phone']),array('id'=>$_GPC['uid']));
			echo json_encode($status);exit;
		}
		if($_GPC['op'] == 'cancel'){
			/**
			 * 将自己的parentid与下级的parentid改为0，脱离上下级关系
			 */
			$openid = pdo_get('ly_photobook_user',array('id'=>$_GPC['id']))['openid'];
			//step 1:查找取消代理人员
			$selfid = pdo_get('ly_photobook_share',array('openid'=>$openid,'uniacid'=>$_W['uniacid']));
			if($selfid){
				//step 2:重新检查团长或合伙人身份
				$this->check_identity_agin($this->sid2uid($selfid['id']));
				//step 3:将自己上级的parentid改为0
				pdo_update('ly_photobook_share',array('parentid'=>0),array('openid'=>$openid,'uniacid'=>$_W['uniacid']));
				//step 4:将自己下级的parentid改为0
				pdo_update('ly_photobook_share',array('parentid'=>0),array('parentid'=>$selfid['id']));
				$res['code'] = 0;
			}
			$res = pdo_update('ly_photobook_user',array('dealer'=>-1,'agent_code'=>''),array('id'=>$_GPC['id']));
			if($res)
				message('代理取消成功',$this->createWebUrl('dealerlist'),'success');
			else
				message('代理取消失败',$this->createWebUrl('dealerlist'),'error');
		}
		if(checksubmit()){
			$list = pdo_fetchall('select a.id,a.openid,a.agent_code,a.phone,a.name,a.identity,b.nickname from '.tablename('ly_photobook_user').' as a left join '.tablename('mc_mapping_fans').' as b on a.openid= b.openid where a.uniacid='.$_W['uniacid'].' and b.uniacid='.$_W['uniacid'].' and a.dealer != -1 and b.nickname like "%'.$_GPC['keyword'].'%"');
		}else{
			$list=pdo_fetchall('select a.id,a.openid,a.agent_code,a.phone,a.name,a.identity,b.nickname from '.tablename('ly_photobook_user').' as a left join '.tablename('mc_mapping_fans').' as b on a.openid= b.openid where a.uniacid='.$_W['uniacid'].' and b.uniacid='.$_W['uniacid'].' and a.dealer != -1 ORDER BY a.id DESC LIMIT '.($pindex-1)*$psize.','.$psize);
			foreach ($list as $key => $value) {
				$share_id = pdo_get('ly_photobook_share',array('uniacid'=>$_W['uniacid'],'openid'=>$value['openid']))['id'];
				$list[$key]['count']=pdo_fetchcolumn('select count(*) from '.tablename('ly_photobook_share').' where uniacid='.$_W['uniacid'].' and parentid='.$share_id);
			}
			$total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('ly_photobook_user') . " where uniacid=:uniacid and dealer != -1",array(':uniacid'=>$_W['uniacid']));
			$pager = pagination($total, $pindex, $psize);
		}
		include $this->template('deallist');
	}
	/**
	 *  取消代理后，改变上下级关系，重新检查身份
	 * @return 取消代理本人id
	 */
	public function check_identity_agin($uid){
		$cancel_userinfo = $this->id2info($uid);//取消的是否为代理
		pdo_update('ly_photobook_user',array('self_count'=>0,'team_count'=>0,'new_add'=>0,'day'=>time()),array('id'=>$uid));//将取消关系人的直接代理，团队人数清空
		if($cancel_userinfo['dealer'] != -1){ //取消代理身份时，遍历团队人数递减
			$sid = $this->uid2sid($uid);
			$parentid = $this->parentid($sid); //获取上级
			$subtract_num = empty($cancel_userinfo['team_count'])? 1 : $cancel_userinfo['team_count']+1; //取消代理团队人数+1
			$flag = true; //直接上级开关
			while(!empty($parentid)){
				$uid = $this->sid2uid($parentid);
				$userInfo = $this->id2info($uid);
				if($flag){
					pdo_update('ly_photobook_user',array('team_count -='=>$subtract_num,'self_count -='=>1),array('id'=>$uid));//直接上级 自己直接代理-1,团队数减去代理的团队数+1（自己）
					$flag = false; //直接上级关闭
				}else
					pdo_update('ly_photobook_user',array('team_count -='=>$subtract_num),array('id'=>$uid));//上级的上级团队数减去代理的团队数+1（自己）
				if($userInfo['identity']){//如果是团长或合伙人身份，重新检查
					$this->isUpgrade($uid);
				}
				$parentid = $this->parentid($parentid);
			}
		}	
	}
	public function doWebSharelist(){
		global $_W,$_GPC;
		$s2uid = pdo_get('ly_photobook_share',array('id'=>$_GPC['id']))['openid'];
		$sharelist=pdo_fetchall('select * from '.tablename('ly_photobook_share').' where uniacid='.$_W['uniacid'].' and parentid='.$this->uid2sid($_GPC['id']));
		foreach ($sharelist as $key => $value){
			$share_id = pdo_get('ly_photobook_share',array('uniacid'=>$_W['uniacid'],'openid'=>$value['openid']))['id'];
			$sharelist[$key]['count']=pdo_fetchcolumn('select count(*) from '.tablename('ly_photobook_share').' where uniacid='.$_W['uniacid'].' and parentid='.$value['id']);
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
			$input = array_elements(array('appid', 'secret','admin_openid', 'mchid', 'password', 'ip','one_level','two_level'), $_GPC);
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
	/**
	 * 设置内部代理
	 */
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
	/**
	 * 代理中心
	 */
	public function doMobileAgency_center(){
		global $_W,$_GPC;
		$p_title='代理中心';
		$all_count = count(pdo_getall('ly_photobook_user',array('uniacid'=>$_W['uniacid'])));
		$user = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']));
		$userid = $user['id'];
		$poster = pdo_get('ly_photobook_poster',array('uniacid'=>$_W['uniacid']))['kword'];
		/**
		 * 分享海报
		 */
		$share_id = pdo_get('ly_photobook_share',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id'];
		/**
		 * 代理二维码
		 */
		$ticketid = pdo_get('ly_photobook_share',array('uniacid'=>$_W['uniacid'],'openid'=>$user['openid']))['ticketid'];
		/**
		 * 可提现金额
		 */
		$sql='select sum(money) as sum from '.tablename('ly_photobook_user_rebate').' where uniacid=:uniacid and userid=:userid and status=:status';
		$money_count=pdo_fetch($sql,array('uniacid'=>$_W['uniacid'],'userid'=>$userid,'status'=>0))['sum'];
		/**
		 * 累计收入
		 */
		$sql1='select sum(money) as sum from '.tablename('ly_photobook_user_rebate').' where uniacid=:uniacid and userid=:userid and status != -1';
		$lj_count=pdo_fetch($sql1,array('uniacid'=>$_W['uniacid'],'userid'=>$userid))['sum'];
		/**
		 * 拉粉奖励
		 */
		$sql2='select sum(money) as sum from '.tablename('ly_photobook_user_rebate').' where uniacid='.$_W['uniacid'].' and userid='.$userid.' and type =1';
		$lf_count=pdo_fetch($sql2)['sum'];
		/**
		*　销售奖励
		*/
		$sql3='select sum(money) as sum from '.tablename('ly_photobook_user_rebate').' where uniacid='.$_W['uniacid'].' and userid='.$userid.' and type !=1';
		$xs_count=pdo_fetch($sql3)['sum'];
		include $this->template('agency_center');
	}

	/**
	 * ajax设置内部代理
	 */
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
		$psize = 20;
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
				"booktype"=>$_GPC['booktype'],
				"kind"=>$_GPC['kind']
			);
			if(pdo_insert('ly_photobook_template_main',$data)){
				message('添加成功，去添加模板图',$this->createWebUrl('edittemplateimage',array('t_id'=>pdo_insertid())),'success');
			}else{
				message('添加失败','','error');
			}
			exit;
		}
		load()->func('tpl');
		$kind_list = pdo_getall('ly_photobook_kind',array('uniacid'=>$_W['uniacid']));
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

	/**
	 * 用户上传照片页ccbsx
	 */
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
	/**
	 * 获取照片书中用到的图片
	 */
	public function getUsedImage($ordersubid){
		global $_W;

		$mainId = pdo_get('ly_photobook_order_sub',array('id'=>$ordersubid))['main_id'];
		$allPages = pdo_getall('ly_photobook_order_sub',array('main_id'=>$mainId));
		foreach ($allPages as $key => $value) {
			$frameData = json_decode($value['trim'],true);
			foreach ($frameData as $key => $value) {
				$usePhotos[] = $value['imgurl'];
			}
		}
		return $usePhotos;
	}
	public function doMobileUserPhotos(){
		global $_GPC,$_W;
		//如果是要替换图片的话userimg、
		if($_GPC['type']=="change"){
			$HIDE=true;
			$usedImages = json_encode($this->getUsedImage($_GPC['ordersubid']));
		}else{
			$tid=$_GPC['tid'];
			$timgcount=30;
			$imgshell=pdo_get('ly_photobook_template_main',array('id'=>$tid),array('shell'))['shell'];
			if($imgshell==0){
				$timgcount=30;
			}else if($imgshell==1||$imgshell==2){
				$timgcount=22;
			}
			$HIDE=false;
		}
		$Cuser=$this->Oneuser($_W['openid'],$_W['uniacid']);
		$userimg=pdo_getall('ly_photobook_user_images',array('user_id'=>$Cuser['uid'],"delete"=>0),array(),'','createtime desc');
		//如果没有缩略图 发送请求oss生成缩略图
		foreach ($userimg as $key => $value) {
			if(empty($value['thumb'])){
				$temp =explode('.', $value['original']);
				$thum =$temp[0].'_thum.'.$temp[1];
				$data ='x-oss-process=image/resize,w_360|sys/saveas,o_'.base64_encode($thum).',b_'.base64_encode('demo-photo');
				$response = ihttp_post('http://demo-photo.oss-cn-beijing.aliyuncs.com/'.$value['original'].'?x-oss-process', $data);

				if($response['status'] == 'OK'){
				//将原图缩略图录入数据表  需更改
					pdo_update('ly_photobook_user_images',array('thumb'=>$thum),array('id'=>$value['id']));
				}
			}
		}
		$userimg=pdo_getall('ly_photobook_user_images',array('user_id'=>$Cuser['uid'],"delete"=>0),array(),'','createtime desc');
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
				new ResizeImage($imagePath, '360', '360', '0', ATTACHMENT_ROOT.$imageThumb);
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
				"booktype"=>$_GPC['booktype'],
				"kind"=>$_GPC['kind']
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
		$kind_list = pdo_getall('ly_photobook_kind',array('uniacid'=>$_W['uniacid']));
		include $this->template('createbook');
	}

	/**
	 * 模板列表
	 */
	public function doWebEdittemplateimage(){
		// set_time_limit(0);
		global $_GPC,$_W;
		$feng1=pdo_get("ly_photobook_template_sub",array("template_id "=>$_GPC['t_id'],"type"=>1),array("thumb"))["thumb"];
		$feng2=pdo_get("ly_photobook_template_sub",array("template_id "=>$_GPC['t_id'],"type"=>2),array("thumb"))["thumb"];
		$sql='select thumb,id from '.tablename('ly_photobook_template_sub').' where template_id=:template_id AND uniacid=:uniacid AND type=:type order by id';
		$images=pdo_fetchall($sql,array(':template_id'=>$_GPC['t_id'],':uniacid'=>$_W['uniacid'],'type'=>0));

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
			if(empty($image))
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
			pdo_delete('ly_photobook_template_main',array('id'=>$_GPC['t_id']));
			$images=pdo_fetchall('select original,thumb from '.tablename('ly_photobook_template_sub').' where template_id='.$_GPC['t_id']);

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
				'name'=>$_GPC['name'],
				'list_price'=>$_GPC['list_price'],
				'dealer_price'=>$_GPC['dealer_price'],
				'pt_dealer_price'=>$_GPC['pt_dealer_price'],
				'uniacid'=>$_W['uniacid'],
				'pic'=>$_GPC['pic'],
				'createtime'=>time(),
				'type'=>$_GPC['type'],
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
	 * 修改代金券
	 */
	public function doWebEdit_code(){
		global $_GPC,$_W;

		if(checksubmit()){
			$number=$_GPC['number'];
			$data=array(
				'name'=>$_GPC['name'],
				'list_price'=>$_GPC['list_price'],
				'dealer_price'=>$_GPC['dealer_price'],
				'pt_dealer_price'=>$_GPC['pt_dealer_price'],
				'uniacid'=>$_W['uniacid'],
				'pic'=>$_GPC['pic'],
				'type'=>$_GPC['type'],
				'number'=>$number
			);
			if(pdo_update('ly_photobook_codes',$data,array('id'=>$_GPC['cid'])))
				message('修改成功',$this->createWebUrl('cartlist'),'success');
			else
				message('修改失败',$this->createWebUrl('cartlist'),'error');
		}else{
			$code = pdo_get('ly_photobook_codes',array('id'=>$_GPC['id']));
		}
		include $this->template('edit_code');
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
			/**
			 * 发送工单处理的模板消息
			 */
			$work_order = pdo_get('ly_photobook_service',array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['post_id']));
			$mess_arr=array(
				"first"=>"您好，您提交的问题已经处理完成",
				"k1"=>$work_order['id'],
				"k2"=>$work_order['title'],
				"k3"=>date('Y-m-d H:i',time()),
				"rem"=>$_GPC['detail'],
				"openid"=>$work_order['openid'],
				"mid1"=>"IUdAawAikNI99D7fqDhlEDnb6KC9NIUDamkS7w9i9iA"
			);			
			$over_mess= new templatemessage();
			$over_mess->work_order_done($mess_arr);
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
	/**
	 * 随机验证码
	 */
	private function randNum($length = 10) { 
		$chars = '0123456789';
		$str =''; 
		for($i = 0; $i < $length; $i++){
			$str.= $chars[mt_rand(0, strlen($chars) - 1)]; 
		}
		return $str; 
	} 
	/**
	 * 首页
	 */
	public function doMobileHome(){
		global $_GPC,$_W;
		//获取传来的参数，知道是哪个类别的。
		if($_W['isajax']){
			if($_GPC['kind_id'] != -1)
				$resArr['data'] = pdo_getall('ly_photobook_template_main',array('uniacid'=>$_W['uniacid'],'kind'=>$_GPC['kind_id'],'booktype'=>$_GPC['booktype']));
			else
				$resArr['data'] = pdo_getall('ly_photobook_template_main',array('uniacid'=>$_W['uniacid'],'booktype'=>$_GPC['booktype']));
			$resArr['kind_id'] = $_GPC['kind_id'];
			foreach($resArr['data'] as $index=>$val)
				$resArr['data'][$index]['thumb'] = tomedia($resArr['data'][$index]['thumb']);
			if(empty($resArr['data']))
				$resArr['code'] = 1;
			else	
				$resArr['code'] = 0;
			echo json_encode($resArr);exit;
		}
		if(empty($_GPC['booktype'])){
			message("无此类别，请返回");
		}

		$p_title="照片书列表";
		$m_active=1;
		$this->check_thisUser($_W['openid'],$_W['uniacid']);
		$sql='select id,name,price,sales,thumb from '.tablename('ly_photobook_template_main').' where uniacid=:uniacid AND booktype=:booktype';
		$list=pdo_fetchall($sql,array('uniacid'=>$_W['uniacid'],"booktype"=>$_GPC['booktype']));
		/**
		 * 遍历照片书的模板类型
		 */
		$kind_list = pdo_getall('ly_photobook_kind',array('uniacid'=>$_W['uniacid']));
		include $this->template('index');
	}
	/**
	 * 可使用卡卷的照片书
	 */
	public function doMobileUsable(){
		global $_W,$_GPC;
		$p_title="可使用照片书列表";
		$cardinfo = pdo_get('ly_photobook_codes',array('id'=>$_GPC['id']));
		$sql='select id,name,price,sales,thumb from '.tablename('ly_photobook_template_main').' where uniacid ='.$_W['uniacid'].' AND booktype = '.$cardinfo['type'].' AND price = '.trim($cardinfo['list_price']);
		$list=pdo_fetchall($sql);
		include $this->template('usable');
	}
	public function shareid2uid($openid){
		global $_W;
		return pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$openid))['id'];
	}
	/**
	 * 团队购卷量
	 */
	public function team_count($parentid){
		global $_W,$_GPC;
		$res['total'] = 0;
		$res['fans'] = 0;
		$res['dl_count'] = 0;
		//二级代理(中是代理人数)
		$sql = 'select u.openid,s.avatar,s.nickname,s.id from ims_ly_photobook_share as s left join ims_ly_photobook_user as u on s.openid = u.openid where s.parentid = '.$parentid.' and u.dealer != -1 and s.uniacid = '.$_W['uniacid'].' and u.uniacid='.$_W['uniacid'];
		$three_dl = pdo_fetchall($sql);
		$res['dl_count'] += count($three_dl);
		//二级代理全部下级
		$three_all_dl = pdo_fetch('select count(*) as count from ims_ly_photobook_share where uniacid = '.$_W['uniacid'].' and parentid='.$parentid)['count'];
		$res['fans'] += $three_all_dl - count($three_dl);
		foreach ($three_dl as $key => $value) {
			$num = pdo_fetch('select sum(number) as count from ims_ly_photobook_code_order where uniacid = '.$_W['uniacid'].' and status = 1 and user_id = '.$this->shareid2uid($value['openid']))['count'];
			if(!empty($num))
				$res['total'] += $num;
			//三级代理
			$sql2 = 'select u.openid,s.avatar,s.nickname,s.id from ims_ly_photobook_share as s left join ims_ly_photobook_user as u on s.openid = u.openid where s.parentid = '.$value['id'].' and u.dealer != -1 and s.uniacid = '.$_W['uniacid'].' and u.uniacid='.$_W['uniacid'];
			$four_dl = pdo_fetchall($sql2);
			$res['dl_count'] += count($four_dl);
			//三级代理全部下级
			$four_all_dl = pdo_fetch('select count(*) as count from ims_ly_photobook_share where uniacid = '.$_W['uniacid'].' and parentid='.$value['id'])['count'];
			$res['fans'] += $four_all_dl - count($four_dl);
			foreach ($four_dl as $key => $value) {
				$num2 = pdo_fetch('select sum(number) as count from ims_ly_photobook_code_order where uniacid = '.$_W['uniacid'].' and status = 1 and user_id = '.$this->shareid2uid($value['openid']))['count'];
				if(!empty($num2))
					$res['total'] += $num2;
				//四级代理
				$sql３ = 'select u.openid,s.avatar,s.nickname,s.id from ims_ly_photobook_share as s left join ims_ly_photobook_user as u on s.openid = u.openid where s.parentid = '.$value['id'].' and u.dealer != -1 and s.uniacid = '.$_W['uniacid'].' and u.uniacid='.$_W['uniacid'];
				$five_dl = pdo_fetchall($sql3);
				$res['dl_count'] += count($five_dl);
				//四级代理全部下级
				$five_all_dl = pdo_fetch('select count(*) as count from ims_ly_photobook_share where uniacid = '.$_W['uniacid'].' and parentid='.$value['id'])['count'];
				$res['fans'] += $five_all_dl - count($five_dl);
				foreach ($five_dl as $key => $value) {
					$num3 = pdo_fetch('select sum(number) as count from ims_ly_photobook_code_order where uniacid = '.$_W['uniacid'].' and status = 1 and user_id = '.$this->shareid2uid($value['openid']))['count'];
					if(!empty($num3))
						$res['total'] += $num3;
				}
			}
		}
		return $res;
	}
	/**
	*我的团队 
	*/
	public function doMobileTeam(){
		global $_W,$_GPC; 

		//我的上级
		$self = pdo_get('ly_photobook_share',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']));
		$parent = pdo_get('ly_photobook_share',array('uniacid'=>$_W['uniacid'],'id'=>$self['parentid']));
		//下级粉丝数
		$childrens = pdo_fetchall('select * from ims_ly_photobook_share where parentid = '.$self['id'].' and uniacid = '.$_W['uniacid']);
		//下级是代理的列表
		$sql = 'select s.avatar,s.nickname,u.team_count,u.self_count from ims_ly_photobook_share as s left join ims_ly_photobook_user as u on s.openid = u.openid where s.parentid = '.$self['id'].' and u.dealer != -1 and s.uniacid = '.$_W['uniacid'].' and u.uniacid='.$_W['uniacid'];
		$children_dl = pdo_fetchall($sql);
		//我的直推人数　（是自己下级中是代理的人数）
		$count = count($children_dl);
		//团队人数　（自己下级中代理的代理的代理的人数）
		$self_info = $this->id2info($this->sid2uid($self['id']));
		$team_count = $self_info['team_count'];
		//我的合伙人
		
		//新增人数
		$new_add = count($childrens) - $count;
		include $this->template('team');
	}
	/**
	 * 照片书模板详情页
	 */
	public function doMobileDetail(){
		global $_W,$_GPC;

		$account_api = WeAccount::create();
		$userInfo = $account_api->fansQueryInfo($_W['openid']);

		$detail=pdo_get('ly_photobook_template_main',array('id'=>$_GPC['id']));
		$tid=$_GPC['id'];
		$sql = 'SELECT * FROM ims_ly_photobook_comment AS c LEFT JOIN ims_ly_photobook_user AS u ON c.user_id = u.id WHERE c.uniacid = '.$_W['uniacid'].' AND c.template_id = '.$tid;
		$template_comment = pdo_fetchall($sql);
		foreach($template_comment as $index=>$row){
			$template_comment[$index]['headimgurl'] = $account_api->fansQueryInfo($row['openid'])['headimgurl'];
			$template_comment[$index]['nickname'] = $account_api->fansQueryInfo($row['openid'])['nickname'];
			$template_comment[$index]['comment'] = preg_replace_callback('/[em_[0-9]+]/',function ($ss) { $temp = substr($ss[0],4,2); 
				return '<img src="../addons/photobook/template/images/arclist/'.$temp.'.gif"> ' ;
			},$row['comment']);
		}
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
				/**
				 * 发送模板消息给客服人员
				 */
				$setting = $this->module['config'];
				$mess_arr=array(
					"first"=>$_W['fans']['nickname'].",提交了一份新的工单",
					"k1"=>pdo_insertid(),
					"k2"=>$_W['fans']['nickname'],
					"k3"=>$_GPC['title'],
					"rem"=>$_GPC['detail'],
					"openid"=>$setting['msetting']['admin_openid'],
					"mid1"=>"M76oSi8l15GitcQlcaQ5ZMhhsPiSyNwjZ2dyxkumdTA"
				);			
				$over_mess= new templatemessage();
				$over_mess->work_order_done($mess_arr);
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
		$user = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']));
		$userid = $user['id'];
		/**
		 * 可提现金额
		 */
		$sql='select sum(money) as sum from '.tablename('ly_photobook_user_rebate').' where uniacid=:uniacid and userid=:userid and status=:status';
		$money_count=pdo_fetch($sql,array('uniacid'=>$_W['uniacid'],'userid'=>$userid,'status'=>0))['sum'];
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
		/**
		 * 优惠券数量
		 */
		$card_count = 0;
		$count = pdo_fetchall('SELECT b.name,a.*,b.dealer_price,b.list_price FROM ims_ly_photobook_user_code AS a LEFT JOIN ims_ly_photobook_codes AS b ON a.code_id = b.id WHERE a.uniacid = '.$_W['uniacid'].' AND b.uniacid = '.$_W['uniacid'].' AND a.status =0 AND a.number >0 AND a.user_id ='.$user['id'].' GROUP BY a.code_id');
		foreach($count as $index=>$row)
			$card_count +=$row['number'];
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
	
	/**
	 * 检查模板数量是否满足最小为1的限制
	 */
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
	
	/**
	 * 填充封面封底 
	 */
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
		logging_run('前端返回图片的信息:'.json_encode($arrs),'info','photobook222222');
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
				if($modeResult['frame'.$this_uimgcount]>0){//配合未用完				
					if($uimgcount-$imgIndex<$this_uimgcount){//说明客户的图片不够用了//此问题不应该出现，配额未用完，图片不应该用完出现则提示并停止.注意照片从0开始，所以要加1，由于本页可用，所以又要减1
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
					$res=pdo_insert('ly_photobook_order_sub', $Onedata);
					
					if (!empty($res)) {
						$errors=0;
					}else{
						$errors+=1;
						return json_encode(array('errors'=>'插入照片书页失败，请联系管理员'));
					}
					
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

	/**
	 * 生成照片书的API
	 */
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
		if($_W['isajax']){
			if(!empty($_GPC['order_id'])){
				$is_add = pdo_get('ly_photobook_order_main',array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['order_id']));
				if(empty($is_add['shopping_cart']) && $is_add['status'] == 0){
					$shopping_car_status = pdo_update('ly_photobook_order_main',array('shopping_cart'=>1,'count'=>1),array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['order_id']));
					if($shopping_car_status){
						$resArr['code'] = 0;
					}else{
						$resArr['code'] = 1;
					}
				}else{
					if($is_add['status'] == 0)
						$resArr['code'] =2;
					else	
						$resArr['code'] = 3;
				}
				echo json_encode($resArr);exit;
			}
		}
		$Npage=1;
		if($_GPC['Npage']>1){
			$Npage=$_GPC['Npage'];
		}
		set_time_limit(0);
		$account_api = WeAccount::create();
		$fromOpenid = empty($_GPC['share'])? $_W['openid'] : $_GPC['share'];
		$userInfo = $account_api->fansQueryInfo($fromOpenid);
		$shareUrl = $_W['siteroot'].'app/index.php?i='.$_W['uniacid'].'&c=entry&do=turn&m=photobook&tid='.$_GPC['tid'].'&share='.$fromOpenid;//分享链接
		$bookid=$_GPC['tid'];
		//该照片书信息
		$is_buy = pdo_get('ly_photobook_order_main',array('id'=>$bookid,'uniacid'=>$_W['uniacid']))['status'];
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
			// 筐的位置尺寸
			logging_run('进入函数前=='.str_replace('&quot;', "'", $res1['data']),'info','after');
			$data = json_decode(str_replace('&quot;', "'", $res1['data']), true);
			// 合成图片的位置
			logging_run('进入测试前----'.$_GPC['tid']);
			//找不到缩略图就调用函数生成，同时指定ordersub的id，便于更新trim信息
			$timg = $value['img_path'];
			if(empty($value['img_path'])){
				$timg = time().$T_photo;
				$this->Compound($trimarray,$data,$T_photo,$value['id'],$timg);
			}
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
		include $this->template('turn2');  
	}
	/**
	 * 更换模板API
	 */
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
	//单图编辑页
	public function doMobileNewimgtouchit(){
		global $_W,$_GPC;

		$Npage=$_GPC['Npage'];
		$url=$this->createMobileUrl("userphotos",array("type"=>"change"));
		$url_reOnesave=$this->createMobileUrl("API_texttext");//对应前端单页保存的按钮，保存时发AJAX到这个链接
		$url_API_Ctep=$this->createMobileUrl("API_Ctep");//应该是更换模板
		$ordersubid=$_GPC['id'];
		$Oneordersub=pdo_get('ly_photobook_order_sub',array('id'=>$ordersubid));
		$ortype=pdo_get('ly_photobook_template_sub',array('id'=>$Oneordersub['template_id']));
		$trimtype=$ortype['type'];//该页类型；
		$url_turn=$this->createMobileUrl('turn',array('tid'=>$Oneordersub['main_id']));
		$trim=$Oneordersub['trim'];//操作信息
		$trimarr=json_decode($trim,true);
		$arrimgt=array();
		foreach ($trimarr as $key => $val) {
			$arrimgt[]=pdo_get("ly_photobook_user_images",array('id'=>$val['imageId']),array('width','height'));
		}
		$arrimgt=json_encode($arrimgt);
		$Onetemplatesub=pdo_get('ly_photobook_template_sub',array('id'=>$Oneordersub['template_id']));
		$tep=$Onetemplatesub['data'];//单图模板图信息
		$tepimg=$Onetemplatesub['thumb'];//此模板图
		$template_id=$Onetemplatesub['template_id'];
		$Tlist=pdo_getall("ly_photobook_template_sub",array('template_id'=>$template_id,"type"=>0),array('id','thumb'));
		$enlist=json_encode($Tlist);
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
		$this->Compound($trimarray,$data,$template['thumb'],$order_id,time().$template['thumb']);
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
		$p_title="卡卷";
		$pindex = max(1, intval($_GPC['page']));
		$psize = 10;
		//是内部代理还是普通代理
		$is_agent = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['dealer'];
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
				'phone'=>$_GPC['phone'],
				'address'=>$_GPC['address'],
				'detail'=>$_GPC['detail'],
				'user_id'=>$user_id,
				'uniacid'=>$_W['uniacid']
			);
			if(empty($_GPC['id']))
				$res = pdo_insert('ly_photobook_address',$data);
			else
				$res = pdo_update('ly_photobook_address',$data,array('id'=>$_GPC['id']));
			if($res){
				message('操作完成',$this->createMobileUrl('view_address').'&order_id='.$_GPC['order_id'].'&aid='.$_GPC['sel_id'],'success');
			}else{
				message('操作失败','','error');
			}
			exit();
		}else{
			if(!empty($_GPC['id'])){
				if($_GPC['op'] == 'del'){
					$res = pdo_delete('ly_photobook_address',array('id'=>$_GPC['id']));
					if($res){
						message('操作完成',$this->createMobileUrl('view_address').'&order_id='.$_GPC['order_id'].'&aid='.$_GPC['sel_id'],'success');
					}else{
						message('操作失败','','error');
					}
				}elseif($_GPC['op'] == 'edit')
				$address = pdo_get('ly_photobook_address',array('id'=>$_GPC['id']));
			}
		}
		include $this->template('addredd_add');
	}

	//地址列表页
	public function doMobileView_address(){
		global $_W,$_GPC;

		$url=$this->createMobileUrl("addredd_add");
		$user_id=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']),array('id'))['id'];
		$address=pdo_getall('ly_photobook_address',array('uniacid'=>$_W['uniacid'],'user_id'=>$user_id),array(),'','id desc');
		include $this->template('view_address');
	}

	//下单页
	public function doMobilePlace_order(){
		global $_W,$_GPC;
		$order_id=(int)$_GPC['order_id'];

		//订单信息
		$order_info = pdo_get('ly_photobook_order_main',array('uniacid'=>$_W['uniacid'],'id'=>$order_id));

		$add_url=$this->createMobileUrl("view_address");
		
		$template_sub_id=pdo_get('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'main_id'=>$order_id),array('template_id'))['template_id'];
		$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'))['template_id'];
		$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','thumb','price','inner_price','booktype'));
		$user_info=pdo_get('ly_photobook_user',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
		$user_id = $user_info['id'];
		//检查是否内部代理
		$user_info['dealer'] == 2? $is_inner_sub = 1 : $is_inner_sub = 0;
		/**
		最新的收货地址
		*/
		if(empty($_GPC['sel_addid']))
			$a = pdo_get('ly_photobook_address',array('uniacid'=>$_W['uniacid'],'user_id'=>$user_id),array(),'','id desc');
		else
			$a = pdo_get('ly_photobook_address',array('id'=>$_GPC['sel_addid']));
		/**
		 * 查找卡卷列表
		 */
		$card_list = pdo_fetchall('SELECT a.*,b.list_price,b.name FROM ims_ly_photobook_user_code AS a LEFT JOIN ims_ly_photobook_codes AS b ON a.code_id = b.id WHERE a.uniacid='.$_W['uniacid'].' AND b.uniacid='.$_W['uniacid'].' AND a.user_id ='.$user_id.' AND a.number >0 AND b.list_price ='.$template['price'].' and b.type = '.$template['booktype'].' group by a.code_id');
		if(checksubmit()){
			if($is_inner_sub){			
				$price=((float)$_GPC['count'])*(float)$template['inner_price'];		
			}else{
				$price=(float)$_GPC['count']*(float)$template['price'];
			}
			if(!empty($_GPC['card']))
				$select_card_id = $_GPC['select_card'];
			else
				$select_card_id = 0;
			$data=array(
				'count'=>$_GPC['count'],
				'price'=>$price,
				'remark'=>$_GPC['remark'],
				'address_id'=>$_GPC['address_id'],
				'card_id'=>$select_card_id,
				'phone'=>$_GPC['phone']
			);
			if(empty($data['count'])){
				load()->func('logging');
				//记录文本日志
				logging_run($data,'info','count=0');
				messsage('订单生成有错误',$this->createMobileUrl('Shop_order'),array('order_id'=>$order_id));
			}
			//判断是否是返回键返回
			$has_data = pdo_get('ly_photobook_order_main',array('id'=>$order_id),array('count','price','remark','address_id','card_id','phone'));
			$has_data['price'] = (float)$has_data['price'];
			$has_data['card_id'] = (int)$has_data['card_id'];
			if($has_data == $data){
				header('Location: '.$this->createMobileUrl('Shop_order',array('order_id'=>$order_id)));
			}elseif(pdo_update('ly_photobook_order_main',$data,array('id'=>$order_id)) ){
				header('Location: '.$this->createMobileUrl('Shop_order',array('order_id'=>$order_id)));
			}
		}
		include $this->template('place_order');
	}

	function CardByBook($orderid){
		$orderInfo = $this->getOrderInfo($orderid);//订单信息
		if(!empty($orderInfo['card_id'])){
			$userInfo = $this->id2info($orderInfo['user_id']);//用户信息
			$cardCount = $this->getCardCount($orderInfo['card_id']);
			$bookInfo = $this->getBookInfo($orderInfo['template_id']);
			if($orderInfo['count'] >= $cardCount){//订单数量大于自己卡卷数量  多出来的订单数量要原价
				$price = $userInfo['dealer'] == 2? ($orderInfo['count'] - $cardCount) * $bookInfo['inner_price'] : ($orderInfo['count'] - $cardCount) * $bookInfo['price'];
				$useCardCount = $cardCount;
			}elseif($orderInfo['count'] < $cardCount){//订单数小于卡卷数
				$price = 0;
				$useCardCount = $orderInfo['count'];
			}
			pdo_update('ly_photobook_order_main',array('price'=>$price,'card_count'=>$useCardCount),array('id'=>$orderid));
		}		
	}
	function getOrderInfo($orderid){//获取订单信息
		return pdo_get('ly_photobook_order_main',array('id'=>$orderid));
	}
	function getCardCount($cardid){//获取用户持有卡卷数量
		return pdo_get('ly_photobook_user_code',array('id'=>$cardid))['number'];
	}
	function getBookInfo($bookid){
		return pdo_get('ly_photobook_template_main',array('id'=>$bookid));
	}
	//商品订单预览页
	public function doMobileShop_order(){
		global $_W,$_GPC;
		$order_id=$_GPC['order_id'];
		/**
		 * 判断是否使用卡卷，多款照片书扣除多张卡卷
		*/
		$this->CardByBook($order_id);

		$template_sub_id=pdo_get('ly_photobook_order_sub',array('uniacid'=>$_W['uniacid'],'main_id'=>$order_id),array('template_id'))['template_id'];
		$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'))['template_id'];
		// 模板信息
		$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','inner_price','price','thumb'));
		// 商品数量
		$order_main=pdo_get('ly_photobook_order_main',array('id'=>$order_id),array('count','price','address_id'));
		if(empty($order_main['count']))
				messsage('订单生成有错误',$this->createMobileUrl('Shop_order'),array('order_id'=>$order_id));
		$address=pdo_get('ly_photobook_address',array('id'=>$order_main['address_id']));
		//邮费
		$remote_price = pdo_get('ly_photobook_setting',array('uniacid'=>$_W['uniacid']))['remote_price'];
		/**
		 * 检查是否包邮（新疆、西藏、青海、内蒙、宁夏）
		*/
		if(preg_match('/^(新疆|西藏|青海|内蒙|宁夏)/',trim($address['address']))){
			$free = 0;
			$order_main['price'] += $remote_price;
		}else
			$free = 1;  
		$number=$order_main['count'];
		// 检查是否为内部代理：
		$user_info=pdo_get('ly_photobook_user',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
		$user_info['dealer'] == 2? $is_inner_sub = 1 : $is_inner_sub = 0;
		include $this->template('shop_order');
	}

	//代金券微信支付页
	public function doMobileQuan_order(){
		global $_W,$_GPC;
		/**
		 * 判断是否为代理  只有代理有权购买代金券
		 */
		$p_title="卡卷";
		$is_agent = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['dealer'];
		$cid=$_GPC['cid'];
		$code=pdo_get('ly_photobook_codes',array('id'=>$cid));
		if(checksubmit()){
			$user_id=pdo_fetchcolumn('select id from '.tablename('ly_photobook_user').' where openid=:openid',array('openid'=>$_W['openid']));
			if($is_agent == 2){
				//内部代理价格
				$money=(float)$code['dealer_price']*(float)$_GPC['count'];
			}elseif($is_agent == 1){
				//普通代理价格
				$money=(float)$code['pt_dealer_price']*(float)$_GPC['count'];
			}
			$insert=array(
				'uniacid'=>$_W['uniacid'],
				'price'=>$money,
				'codeid'=>$cid,
				'number'=>$_GPC['count'],
				'status'=>0,
				'user_id'=>$user_id,
				'createtime'=>time()
			);
			//录入订单表
			pdo_insert('ly_photobook_code_order',$insert);
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
		//取消购物车
		if($_W['isajax']){
			if(!empty($_GPC['order_id'])){
				$shopping_car_status = pdo_update('ly_photobook_order_main',array('shopping_cart'=>0),array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['order_id']));
				if($shopping_car_status){
					$resArr['code'] = 0;
				}else{
					$resArr['code'] = 1;
				}
				echo json_encode($resArr);exit;
			}
		}
		$Atitle="购物车";
		$p_title="购物车";
		$m_active=2;
		/**
		 * 
		 */
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid'],'status'=>0,'shopping_cart'=>1),array(),'','id DESC');
		foreach ($orders as $key => $order) {
			$template_sub_id=pdo_get('ly_photobook_order_sub',array('main_id'=>$order['id']),array('template_id'))['template_id'];
			$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'));
			// 模板信息
			$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','price','thumb'));
			$orders[$key]['template']=$template;
		}
		include $this->template('orderlist');
	} 
	/**
	 * 未支付订单
	 */
	public function doMobileOrder_not_pay(){
		global $_W,$_GPC;
		$Atitle="未支付订单";
		$p_title ="未支付订单";
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid'],'status'=>0),array(),'','id DESC');
		foreach ($orders as $key => $order) {
			$template_sub_id=pdo_get('ly_photobook_order_sub',array('main_id'=>$order['id']),array('template_id'))['template_id'];
			$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'));
			// 模板信息
			$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','price','thumb'));
			$orders[$key]['template']=$template;
		}
		include $this->template('orderlist');
	}
	/**
	 * 待收货订单
	 */
	public function doMobileOrder_take(){
		global $_W,$_GPC;
		$Atitle="待收货订单";
		$p_title ="待收货订单";

		if($_W['isajax']){
			if(!empty($_GPC['order_id'])){
				$order_status = pdo_update('ly_photobook_order_main',array('status'=>4),array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['order_id']));
				if($order_status){
					$resArr['code'] = 0;
				}else{
					$resArr['code'] = 1;
				}
				echo json_encode($resArr);exit;
			}
		}
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid'],'status'=>array(1,2,3)),array(),'','id DESC');
		foreach ($orders as $key => $order) {
			$template_sub_id=pdo_get('ly_photobook_order_sub',array('main_id'=>$order['id']),array('template_id'))['template_id'];
			$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'));
			// 模板信息
			$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','price','thumb'));
			$orders[$key]['template']=$template;
		}
		include $this->template('orderlist');
	}
	/**
	 * 待评论订单
	 */
	public function doMobileOrder_comment(){
		global $_W,$_GPC;
		$Atitle="订单评论";
		$p_title ="订单评论";
		
		if($_W['isajax']){
			if(!empty($_GPC['order_id'])){
				$userid = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id'];
				$order_status = pdo_get('ly_photobook_comment',array('uniacid'=>$_W['uniacid'],'order_main'=>$_GPC['order_id'],'user_id'=>$userid));
				if($order_status){
					$resArr['code'] = 1;
				}else{
					$resArr['code'] = 0;
				}
				$resArr['order_id'] =$_GPC['order_id'];
				echo json_encode($resArr);exit;
			}
		}
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid'],'status'=>4),array(),'','id DESC');
		foreach ($orders as $key => $order) {
			$template_sub_id=pdo_get('ly_photobook_order_sub',array('main_id'=>$order['id']),array('template_id'))['template_id'];
			$template_id=pdo_get('ly_photobook_template_sub',array('id'=>$template_sub_id),array('template_id'));
			// 模板信息
			$template=pdo_get('ly_photobook_template_main',array('id'=>$template_id),array('name','price','thumb'));
			$orders[$key]['template']=$template;
		}
		include $this->template('orderlist');
	}
	/**
	 * 评论
	 */
	public function doMobileComment(){
		global $_W,$_GPC;
		$Atitle="评论";
		$p_title ="评论";

		if($_W['isajax']){

			if(!empty($_GPC['order_id'])){
				$userid = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id'];
				$templateid = pdo_get('ly_photobook_order_main',array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['order_id']))['template_id'];
				$order_status = pdo_insert('ly_photobook_comment',array('uniacid'=>$_W['uniacid'],'order_main'=>$_GPC['order_id'],'user_id'=>$userid,'comment'=>$_GPC['comment'],'createtime'=>time(),'template_id'=>$templateid));
				if($order_status){
					$resArr['code'] = 0;
				}else{
					$resArr['code'] = 1;
				}
				echo json_encode($resArr);exit;
			}
		}

		include $this->template('comment');
	}
	//我的作品
	public function doMobileMyWork(){
		global $_W,$_GPC;
		$Atitle="我的作品";
		$p_title="我的作品";
		$url_Preview=$this->createMobileUrl("turn");
		$url_details=$this->createMobileUrl("shop_order");
		$user=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']));
		$orders=pdo_getall('ly_photobook_order_main',array('user_id'=>$user['id'],'uniacid'=>$_W['uniacid']),array(),'','id DESC');
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
		/**
		 * 是否有运费
		 */
		if(!empty($_GPC['remote_price']))
			$money += $_GPC['remote_price'];
		$params = array(
			'tid' => $tickets,
			'ordersn' => $tickets,
			'title' => '照片书',
			'fee' => $money
		);
		$orderData = [ //录入订单表
			'uniacid'=>$_W['uniacid'],
			'from_user'=>$_W['openid'],
			'ticket'=>$tickets,
			'fee'=>$money,
			'kind'=>1,
			'status'=>0,
			'create_time'=>time()
		];
		pdo_insert('ly_photobook_orderInfo',$orderData);
		$this->pay($params);
	}

	/**
	 * 成为照片书代理商
	 */
	public function doMobileBecomeAgent(){
		global $_W,$_GPC;
		$p_title="注册总代理";
		$dealer=pdo_get('ly_photobook_user',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid'],'dealer'=>array(1,2)));
		$price = pdo_get('ly_photobook_setting',array('uniacid'=>$_W['uniacid']))['deal_price'];
		if(!empty($dealer)){
			header('Location: '.$this->createMobileUrl('agency_center'));
		}else{
			$self_share=pdo_get('ly_photobook_share',array('openid'=>$_W['openid']));

			if(!empty($_GPC['pay']) && $_GPC['pay']==1){
				$tickets='agent_'.date('YmdHis').'_'.random(10, 1);
				$params = array(
					'tid' => $tickets,
					'ordersn' => $tickets,
					'title' => '成为代理商',
					'fee' => $price
				); 
				$orderData = [ //录入订单表
					'uniacid'=>$_W['uniacid'],
					'from_user'=>$_W['openid'],
					'ticket'=>$tickets,
					'fee'=>$price,
					'kind'=>2,
					'status'=>0,
					'create_time'=>time()
				];
				pdo_insert('ly_photobook_orderInfo',$orderData);
				$this->pay($params);
				exit;
			}
			include $this->template('becomeagentt');
		}	
	}

	/**
	 * 填写代理商的邀请码  弃用
	 */
	public function doMobileSetparent(){
		global $_W,$_GPC;

		$dealer=pdo_get('ly_photobook_user',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid'],'dealer'=>array(1,2)));
		if(!empty($dealer)){
			header('Location: '.$this->createMobileUrl('agency_center'));
		}
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
	private function randFloat($min=0, $max=1){
		return $min + mt_rand()/mt_getrandmax() * ($max-$min);
	}
	//重新生成海报
	public function refreshPoster($openid){
		global $_W;
		load()->model('mc');
		$mc = mc_fetch($openid);
		include 'tools/posterTools.php';
		$poster = pdo_fetch('select * from '.tablename('ly_photobook_poster')." where uniacid = ".$_W['uniacid']);
		$img = createMPoster($mc,$poster,'ly_photobook',0);
	}
	//验证码保存session
	public function StoreSession($code){
		session_start();
		$session_data = [];  
        $session_data['code'] = $code;  
        $session_data['expire'] = time()+900;  
        $_SESSION['verify'] = $session_data;
	}
	/**
	 * 代理注册后，填写微信号与手机号，完成注册
	 */
	public function doMobileAgent_register(){
		global $_W,$_GPC;
		if($_W['isajax']){//发送手机验证码
			$sendMsg = new SmsDemo();
			$verifyNum = $this->randNum(4);
			$this->StoreSession($verifyNum);
			$res = $sendMsg->sendSms($_GPC['phone'],$verifyNum);
			echo json_encode($res);exit();
		}
		if(checksubmit()){//提交信息
			session_start();
			if(time() > $_SESSION['verify']['expire'])
				message('验证码已失效',$this->createMobileUrl('agent_register'),'error');
			elseif($_SESSION['verify']['code'] != trim($_GPC['verify_num']))
				message('验证码错误',$this->createMobileUrl('agent_register'),'error');
			$update_data = [
				'name'=>$_GPC['account'],
				'phone'=>$_GPC['phone']
			];
			$res = pdo_update('ly_photobook_user',$update_data,array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
			if($res)
				$this->refreshPoster($_W['openid']);
			$res? message('信息录入成功',$this->createMobileUrl('agency_center'),'success') :  message('信息录入失败',$this->createMobileUrl('agent_register'),'error');
		}
		include $this->template('agent_register');
	}
	
	// 支付回调函数
	public function payResult($params) {
		global $_W,$_GPC;
		load()->func('logging');					
		logging_run('支付参数'.json_encode($params),'info','pay');
		if($params['result'] == 'success'){
			$type = explode('_',$params['tid'])[0];
			$order_id = end(explode('_',$params['tid']));
			$this->resetNotifyInfo($type,$params['tid']);//notify返回值没有openid，重构openid
			if($type == 'book'){
				if(!pdo_get('ly_photobook_order_main',array('id'=>$order_id))['status']){
					$this->buyLog($params['fee'],'照片书');	//记录日志
					$card_main = pdo_get('ly_photobook_order_main',array('id'=>$order_id));
					$card_id = $card_main['card_id'];
					pdo_update('ly_photobook_order_main',array('status'=>1),array('id'=>$order_id));//购买照片书的订单状态
					pdo_update('ly_photobook_orderInfo',array('status'=>1),array('ticket'=>$params['tid']));//更改订单表中的订单状态
					pdo_update('ly_photobook_template_main',array('sales +='=>$card_main['count']),array('uniacid'=>$_W['uniacid'],'id'=>$card_main['template_id']));//更新照片书购买数量
					if(!empty($card_id)){//如果用卡卷 更新卡卷数量
						pdo_update('ly_photobook_user_code',array('number -='=>$card_main['card_count']),array('id'=>$card_id));
					}
					if(!empty($params['fee'])){
						$this->buyBookSuperiorRebate($params['fee'],$order_id);//购买照片书二级返利
						$this->noCard_rebate($card_main['user_id'],$params['fee']);//无卷购买平台产品返利
						$this->partner_rwelfare($card_main['user_id'],$params['fee']);//合伙人福利返利
					}
					message('照片书支付成功',$this->createMobileUrl('usercenter'),'success');	
				}else
					message('照片书支付成功',$this->createMobileUrl('usercenter'),'success');	
			}else if($type =='code'){
				if(!pdo_get('ly_photobook_code_order',array('id'=>$order_id))['status']){
					$this->buyLog($params['fee'],'代金券');	//记录日志
					pdo_update('ly_photobook_code_order',array('status'=>1),array('id'=>$order_id));// 代理商购买代金券的
					$this->buyCodeUpdateInfo($order_id); //更新用户代金券信息
					$parentid = $this->parentid($this->uid2sid($this->openid2id($_W['openid'])));//上级shareid
					if(!empty($parentid)){
						$num = $this->getRoundNum();//生成大于0.01的随机数，进行上级返利
						$this->add_rebate($_W['openid'],$parentid,$num,2,true);//上级返利
						$this->sendRebateTepMsg($this->id2openid($this->sid2uid($parentid)),$num,2,$_W['openid']);//上级代理或非代理随机返利，模板消息  
					}
					$this->partner_rwelfare($order_info['user_id'],$params['fee']);//合伙人福利返利	
				}else
					message('代金券支付成功',$this->createMobileUrl('usercenter'),'success');
			}else if($type=='agent'){
        		// 成为代理商
				if(pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['dealer'] != 1){
					$this->buyLog($params['fee'],'代理');	//记录日志
					$id=pdo_fetchcolumn('select id from '.tablename('ly_photobook_user').' where openid=:openid and uniacid=:uniacid',array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
					$res = pdo_update('ly_photobook_user',array('dealer'=>1),array('id'=>$id));
					pdo_update('ly_photobook_orderInfo',array('status'=>1),array('ticket'=>$params['tid']));//更改订单表中的订单状态
					if($res){
						$parentid = pdo_get('ly_photobook_share',array('openid'=>$_W['openid']),array('parentid'))['parentid'];
						$parent_openid = pdo_get('ly_photobook_share',array('id'=>$parentid))['openid'];
						$parent_user = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$parent_openid));
						if($parent_user['dealer'] != -1){ //上级为代理才享受返利
							$parent_userid = $parent_user['id'];
							if(!empty($parent_openid)){
								$num = pdo_get('ly_photobook_setting',array('uniacid'=>$_W['uniacid']))['parent_price'];
								$this->sendRebateTepMsg($parent_openid,$num,1,$_W['openid']);
								$this->add_rebate($_W['openid'],$parentid,$num,3);//上级代理直接返利
							}
						}else
							$this->parent_fans_rebate($id,$params['fee'],true);//上级为粉丝返利
						$this->send_card($id);//购买新代理，赠送4张软皮卡卷
						$this->identity($id);//有新代理加入，更改团队各上级人员代理数量，并检查是否有人升级为团长或合伙人
						$this->identity_rebate($id);//给团长或合伙人返利
						$this->multiple_rebate($id);//检测是否开启直接下级代理数是5的倍数返利；
						$this->partner_rwelfare($id,$params['fee']);//合伙人福利返利
					}else
						logging_run('代理购买成功，但更新数据表错误'.json_encode($params),'info','agent_error');
				}else
					message('恭喜你成为代理',$this->createMobileUrl('agent_register'),'success');
			}
		}else{
			message('支付失败',$this->createMobileUrl('chart'),'error');
		}
	}
	/**
	 * 保存购买日志
	 */
	public function buyLog($fee,$type){
		global $_W,$_GPC;
		$account_api = WeAccount::create();
		$nickname = $account_api->fansQueryInfo($_W['openid'])['nickname'];
		$fee = empty($fee)? 0 : $fee;
		$data =[
			'uniacid'=>$_W['uniacid'],
			'fromuser'=>$_W['openid'],
			'time'=>time(),
			'content'=>$nickname.'在'.date('Y-m-d H:i:s').'花'.$fee.'元购买了'.$type
		];
		pdo_insert('ly_photobook_order_log',$data);
	}
	//重置notify通知信息没有openid 与 uniacid
	public function resetNotifyInfo($type,$val){
		global $_W,$_GPC;
		if($type == 'code'){
			$order_id = end(explode('_',$val));
			$userid = pdo_get('ly_photobook_code_order',array('id'=>$order_id))['user_id'];
			$userInfo = $this->id2info($userid);
			$_W['openid'] = $userInfo['openid'];
			$_W['uniacid'] = $userInfo['uniacid'];
		}else{
			$orderInfo = pdo_get('ly_photobook_orderInfo',array('ticket'=>$val));
			$_W['openid'] = $orderInfo['from_user'];
			$_W['uniacid'] = $orderInfo['uniacid'];
		}
	}

	//购买照片书二级返利
	public function buyBookSuperiorRebate($fee,$order_id){
		global $_W;
		$config = $this->module['config'];
		$setting=$config['msetting'];
		$selfid = $this->openid2id($_W['openid']);
		$parentid = $this->parentid($this->uid2sid($selfid));
		if($parentid){//上级是否存在
			load()->func('logging');					
			logging_run('支付参数'.json_encode($parentid),'info','TEST');
			$remote_price = pdo_get('ly_photobook_setting',array('uniacid'=>$_W['uniacid']))['remote_price'];
			$address_id = pdo_get('ly_photobook_order_main',array('id'=>$order_id))['address_id'];
			$address = pdo_get('ly_photobook_address',array('id'=>$address_id));
			if(preg_match('/^(新疆|西藏|青海|内蒙|宁夏)/',trim($address['address'])))
				$fee -= $remote_price;//如果加收邮费,返利扣除邮费
			$parentInfo = $this->id2info($this->sid2uid($parentid));
			if($parentInfo['dealer'] != -1){//代理身份返利
				$rebateMoney = $setting['one_level'] * $fee;
				$this->add_rebate($_W['openid'],$parentid,$rebateMoney,0);
				$this->sendRebateTepMsg($this->sid2openid($parentid),$rebateMoney,0,$_W['openid']);
				$grandfartherId = $this->parentid($parentid);
				if($grandfartherId){//上上级是否存在
					$grandfartherInfo = $this->id2info($this->sid2uid($grandfartherId));
					if($grandfartherInfo['dealer'] != -1){
						$prev_prev_money = $setting['two_level'] * $fee;
						$this->add_rebate($_W['openid'],$grandfartherId,$prev_prev_money,0);	
						$this->sendRebateTepMsg($this->sid2openid($grandfartherId),$prev_prev_money,0,$_W['openid']);
					}		
				}
			}else
				$this->parent_fans_rebate($card_main['user_id'],$params['fee']);//上级是粉丝返利
		}
	}
	//购买代金券更新数量
	public function buyCodeUpdateInfo($order_id){
		global $_W;

		$order_info =pdo_get('ly_photobook_code_order',array('uniacid'=>$_W['uniacid'],'id'=>$order_id));
		$count = pdo_get('ly_photobook_codes',array('uniacid'=>$_W['uniacid'],'id'=>$order_info['codeid']))['number'];
		$data =array(
			'uniacid'=>$_W['uniacid'],
			'user_id'=>$order_info['user_id'],
			'number'=>$order_info['number'] * $count,
			'code_id'=>$order_info['codeid'],
			'status'=>0
		);
		$ishas = pdo_get('ly_photobook_user_code',array('uniacid'=>$_W['uniacid'],'user_id'=>$order_info['user_id'],'code_id'=>$order_info['codeid']));
		if(empty($ishas))//插入代金券
			pdo_insert('ly_photobook_user_code',$data);
		else{//更新代金券
			$data['number'] += $ishas['number'];
			pdo_update('ly_photobook_user_code',$data,array('id'=>$ishas['id']));
		}
	}
	public function getRoundNum(){
		$num = $this->randFloat()/2;
		$num = round($num,2);
		while($num < 0.01){
			$num = $this->randFloat()/10;
			$num = round($num,2);
		}
		return $num;
	}
	/**
	 * 上级不是代理，是粉丝返利
	 */
	public function parent_fans_rebate($uid,$fee,$agent=false){
		$setVal = $this->getSetting();
		$info = $this->id2info($uid);
		if($info['dealer'] == -1 || $agent){//粉丝消费
			$sid = $this->uid2sid($uid);
			$parentid = $this->parentid($sid);
			$parentuid = $this->sid2uid($parentid);
			$parent_info = $this->id2info($parentuid);
			if($parent_info['dealer'] == -1){
				$this->add_rebate($info['openid'],$parentid,$setVal['parent_fans_price']*$fee,9,true);
				$this->sendRebateTepMsg($parent_info['openid'],$setVal['parent_fans_price']*$fee,6,$info['openid']);//模板消息
			}
		}
	}
	/**
	 * 合伙人福利
	 * 最上一级合伙人福利，1购买平台产品 2购买代金券 3 注册代理
	 */
	public function partner_rwelfare($uid,$fee){
		$setVal = $this->getSetting();
		$sid = $this->uid2sid($uid);
		$parentid = $this->parentid($sid);
		
		while(!empty($parentid)) {
			$parentuid = $this->sid2uid($parentid);
			$parentinfo = $this->id2info($parentuid);
			if($parentinfo['identity'] == 2){
				$this->add_rebate($this->id2openid($uid),$parentid,$setVal['partner_welfare']*$fee,11);
				$this->sendRebateTepMsg($this->id2openid($parentuid),$setVal['partner_welfare']*$fee,10,$this->id2openid($uid));//模板消息
				break;
			}
			$parentid = $this->parentid($parentid);
		}
	}
	/**
	 * 是否开启直接下级代理5的倍数返利
	 */
	public function multiple_rebate($uid){
		$setVal = $this->getSetting();

		if($setVal['isrebate']){//开启倍数返利功能
			$sid = $this->uid2sid($uid);
			$parentid = $this->parentid($sid);//获取上级sid
			$parentuid = $this->sid2uid($parentid);//上级sid 2 uid
			$info = $this->id2info($parentuid);
			if($info['self_count'] % 5 == 0){
				$this->add_rebate($this->id2openid($uid),$parentid,$setVal['rebate_agin_price'],10);//倍数返利
				$this->sendRebateTepMsg($info['openid'],$setVal['rebate_agin_price'],9,$this->id2openid($uid));//模板消息
			}
		}
	}
	/**
	 * 无卡卷购买平台照片书
	 */
	public function noCard_rebate($uid,$fee){
		global $_W;
		$setVal = $this->getSetting();
		$info = $this->id2info($uid);
		$sid = $this->uid2sid($uid);
		$parentid = $this->parentid($sid);
		if($info['dealer'] == -1){//粉丝无卡卷购买商品，上级代理返利	
			if(!empty($parentid)){
				$this->add_rebate($info['openid'],$parentid,$setVal['fans_nocard_price']*$fee,7);
				$this->sendRebateTepMsg($this->sid2openid($parentid),$setVal['fans_nocard_price']*$fee,6,$info['openid']);
			}
		}else{
			$this->add_rebate($info['openid'],$sid,$setVal['agent_nocard_price']*$fee ,8); //代理无卡卷购买商品，自己返利
			$this->sendRebateTepMsg($this->sid2openid($sid),$setVal['agent_nocard_price']*$fee,7,$info['openid']);
			if(!empty($parentid)){
				$this->add_rebate($info['openid'],$parentid,$setVal['agent_nocard_parent']*$fee,9);//代理无卡卷购买商品，上级代理返利
				$this->sendRebateTepMsg($this->sid2openid($parentid),$setVal['agent_nocard_parent']*$fee,8,$info['openid']);
			}
		}
	}
	/**
	 *  获取设置信息
	 */
	public function getSetting(){
		global $_W;
		return pdo_get('ly_photobook_setting',array('uniacid'=>$_W['uniacid']));
	}
	/**
	 * 新代理注册，赠送卡卷
	 */
	public function send_card($sid){
		global $_W;
		$setVal = $this->getSetting();
		$data = [
			'user_id'=>$sid,
			'uniacid'=>$_W['uniacid'],
			'code_id'=>$setVal['send_card_type'],
			'number'=>$setVal['send_card_count'],
			'status'=>0
		];
		$ishas = pdo_get('ly_photobook_user_code',array('code_id'=>$setVal['send_card_type'],'user_id'=>$sid,'uniacid'=>$_W['uniacid'],'status'=>0));
		if($ishas)
			pdo_update('ly_photobook_user_code',array('number +='=>$setVal['send_card_count']),array('id'=>$ishas['id']));
		else
			pdo_insert('ly_photobook_user_code',$data);
	}
	/**
	 * 新代理加入，团长或合伙人返利
	 * 
	 * @join  user表id
	 */
	public function identity_rebate($join){
		$setVal = $this->getSetting();
		//查找上级
		$sid = $this->parentid($this->uid2sid($join));
		$teamflag = $partnerflag1 = $partnerflag2 = 1;//开关变量
		while($sid){
			$uid = $this->sid2uid($sid);
			$info = $this->id2info($uid);
			if($info['identity'] == 2){	//检查是否合伙人
				if($partnerflag1 == 1){ 
					if($teamflag){//先找到合伙人，无团长返利，返利20元
						$this->add_rebate($this->id2openid($join),$sid,$setVal['no_team_price'],5);
						$this->sendRebateTepMsg($info['openid'],$setVal['no_team_price'],4,$this->id2openid($join));//模板消息
					}else{//先找到团长，合伙人返利10元
						$this->add_rebate($this->id2openid($join),$sid,$setVal['has_team_price'],5);
						$this->sendRebateTepMsg($info['openid'],$setVal['has_team_price'],4,$this->id2openid($join));
					}
					$partnerflag1 = 0; //关闭合伙人返利
				}elseif($partnerflag2 == 1){//合伙人返利后上级合伙人返利1元
					$this->add_rebate($this->id2openid($join),$sid,$setVal['partner_parent_price'],6);
					$this->sendRebateTepMsg($info['openid'],$setVal['partner_parent_price'],5,$this->id2openid($join));
					break; //返利完退出
				}
			}elseif($info['identity'] == 1 && $teamflag == 1 && $partnerflag1 == 1){ //合伙人返利后，团长不返利
				$this->add_rebate($this->id2openid($join),$sid,$setVal['team_rebate_price'],4);
				$this->sendRebateTepMsg($info['openid'],$setVal['team_rebate_price'],3,$this->id2openid($join));
				$teamflag = 0;//关闭团长返利
			}
			$sid = pdo_get('ly_photobook_share',array('id'=>$sid))['parentid'];
		}
	}
	/**
	 * 添加返利记录
	 */
	public function add_rebate($from_user,$user,$money,$type,$fans=false){
		global $_W;
		$uid = $this->sid2uid($user);

		$data = [
			'from_user'=>$from_user,
			'userid'=>$uid,
			'uniacid'=>$_W['uniacid'],
			'money'=>$money,
			'createtime'=>time(),
			'type'=>$type
		];
		switch ($type) {
			case 0:$data['remark']="下级购买照片书";break;
			case 2:$data['remark']="下级购买代金券";break;
			case 3:$data['remark']="下级购买代理";break;
			case 4:$data['remark']="下级购买代理,团长返利";break;
			case 5:$data['remark']="下级购买代理,合伙人返利";break;
			case 6:$data['remark']="合伙人提成";break;
			case 7:$data['remark']="粉丝无卡卷消费返利";break;
			case 8:$data['remark']="无卡卷消费,自己返利";break;
			case 9:$data['remark']="下级代理无卡卷消费返利";break;
			case 10:$data['remark']="直接下级代理数倍数返利";break;
			case 11:$data['remark']="合伙人福利";break;
			default:break;
		}

		if(pdo_get('ly_photobook_user',array('id'=>$uid))['dealer'] != -1)	
			pdo_insert('ly_photobook_user_rebate',$data);
		else{
			if($fans)//上级粉丝返利
				pdo_insert('ly_photobook_user_rebate',$data);
		}
	}
	/**
	 * 向上遍历团队，检查是否有人升级团长或合伙人
	 * @join user表id
	 */
	public function identity($join){
		
		$this->isUpgrade($join);//先检查自己是否升级为团长或合伙人
		$flag = 1;//直接上级开关
		$sid = pdo_get('ly_photobook_share',array('id'=>$this->uid2sid($join)))['parentid'];

		while($sid){//一直向上遍历，直到上级为平台 更改团队teamcount人数
			$uid = $this->sid2uid($sid);
			if($flag){//直接上级需更改  self_count(直接上级)与team_count(团队人数)
				pdo_update('ly_photobook_user',array('team_count +='=>1,'self_count +='=>1),array('id'=>$uid));
				$flag = 0;
			}else{//直接上级以上人员 需更改team_count
				pdo_update('ly_photobook_user',array('team_count +='=>1),array('id'=>$uid));
			}
		
			if(date('Y-m-d',$this->id2info($uid)['day']) == date('Y-m-d',time()) || empty($this->id2info($uid)['day']))
				pdo_update('ly_photobook_user',array('new_add +='=>1,'day'=>time()),array('id'=>$uid));
			else
				pdo_update('ly_photobook_user',array('new_add'=>1,'day'=>time()),array('id'=>$uid));
			$this->isUpgrade($uid);//检查是否升级为团长或合伙人 
			$sid = pdo_get('ly_photobook_share',array('id'=>$sid))['parentid'];
		}
	}

	/**
	 * 判断是否成为合伙人或团长
	 * @join  user表id
	 */
	public function isUpgrade($join){
		$setVal = $this->getSetting();
		$info = $this->id2info($join);
		if($info['team_count'] >= $setVal['partner_partner_cout'] && $info['self_count'] >= $setVal['partner_direct_cout'] && $info['dealer'] != -1){//合伙人身份 
			$shareid = $this->uid2sid($join);
			$this->maxTeamCount = 0;//清空小团长团队人数最多计数器
			$this->get_maxTeamCount($shareid);
			if($info['team_count'] - $this->maxTeamCount >= $setVal['team_max_count']){//减去团队团长小团队最多人数
				pdo_update('ly_photobook_user',array('identity'=>2),array('id'=>$join));
			}
		}elseif($info['team_count'] >= $setVal['team_team_count'] && $info['self_count'] >= $setVal['team_direct_cout'] && $info['dealer'] != -1){//团长身份
			pdo_update('ly_photobook_user',array('identity'=>1),array('id'=>$join));
		}else{
			pdo_update('ly_photobook_user',array('identity'=>0),array('id'=>$join));//取消代理或上下级关系时，会用到
		}
	}
	/**
	 * 查找团队中团长人数最多的数量
	 * @join  share表id
	 * 团长人数最多的值存入 属性值maxTeamCount值中
	 */
	public function get_maxTeamCount($join){

		$sublist = pdo_getall('ly_photobook_share',array('parentid'=>$join));
		if(empty($sublist)){
			return ;
		}else{
			foreach ($sublist as $key => $value) {
				$uid = $this->sid2uid($value['id']);
				$user = pdo_get('ly_photobook_user',array('id'=>$uid));
				if($user['dealer'] != -1 && $user['identity'] == 1){//团长身份
					if($this->maxTeamCount > $value['team_count'])
						$this->maxTeamCount = $value['team_count'];
				}
				$this->get_maxTeamCount($value['id']);
			}
		}
	}
	/**
	 * id查用户user信息
	 */
	public function id2info($join){
		return pdo_get('ly_photobook_user',array('id'=>$join));
	}
	/**
	 * sid获取openid
	 */
	public function sid2openid($sid){
		return pdo_get('ly_photobook_share',array('id'=>$sid))['openid'];
	}
	/**
	 * 获取parentid
	 */
	public function parentid($sid){
		return pdo_get('ly_photobook_share',array('id'=>$sid))['parentid'];
	}
	/**
	 * shareid 转 userid
	 */
	public function sid2uid($sid){
		$openid = pdo_get('ly_photobook_share',array('id'=>$sid))['openid'];
		return pdo_get('ly_photobook_user',array('openid'=>$openid))['id'];
	}
	/**
	 * openid 转 userid
	 */
	public function openid2id($openid){
		global $_W;
		return pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$openid))['id'];
	}
	/**
	 *  userid 转shareid
	 * @param  [type] $uid
	 * @return [type] shareid
	 */
	public function uid2sid($uid){
		global $_GPC,$_W; 
		$openid = pdo_get('ly_photobook_user',array('id'=>$uid))['openid'];
		return pdo_get('ly_photobook_share',array('uniacid'=>$_W['uniacid'],'openid'=>$openid))['id'];
	}
	/**
	 * 封装发送佣金模板消息
	 */
	public function sendRebateTepMsg($openid,$money,$type,$from_openid){
		global $_W;
		load()->model('mc');
		$mc = mc_fetch($from_openid);
		switch ($type) {
			case 0:$remark="恭喜您,您有粉丝购买照片书三级返利，您获得了".$money."元的佣金";break;
			case 1:$remark="恭喜您,您有粉丝已经注册为代理了，您获得了".$money."元的佣金";break;
			case 2:$remark="恭喜您,您的下级代理购买了代金券，您获得了".$money."元的佣金";break;
			case 3:$remark="恭喜您,您有团队粉丝已经注册为代理了，团长返利，您获得了".$money."元的佣金";break;
			case 4:$remark="恭喜您,您有团队粉丝已经注册为代理了，合伙人返利，您获得了".$money."元的佣金";break;
			case 5:$remark="恭喜您,您有团队粉丝已经注册为代理了，合伙人提成，您获得了".$money."元的佣金";break;
			case 6:$remark="恭喜您,下级粉丝无卡卷消费返利，您获得了".$money."元的佣金";break;
			case 7:$remark="自己无卡卷消费返利，您获得了".$money."元的佣金";break;
			case 8:$remark="恭喜您,下级代理无卡卷消费返利，您获得了".$money."元的佣金";break;
			case 9:$remark="恭喜您,直接代理数倍数返利，您获得了".$money."元的佣金";break;
			case 10:$remark="恭喜您,合伙人福利，您获得了".$money."元的佣金";break;
			default:break;
		}
		$send_mess = new templatemessage();
		$send_arr = [
			'first'=>$remark,
			'k1'=>$mc['nickname'],
			'k2'=>$money.'元',
			'k3'=>date('Y-m-d H:i:s',time()),
			'rem'=>'你可以到【发现】-【照片书总代】-销售奖励 中查看更多信息',
			'openid'=>$openid,
			'mid1'=>'2D5D0-Pq7WE7ngtUID7HsMXAM5u3GbFBdHYo8cw6eMY',
			'url'=>''
		];
		$send_mess->send_momey_mess($send_arr);
	}
	public function doWebLy_count(){
		global $_W;
		$users = pdo_getall('ly_photobook_user',array('uniacid'=>$_W['uniacid']));
		foreach ($users as $key => $value) {
			$this->maxTeamCount = 0;
			$this->Count($this->uid2sid($value['id']));
			$self_count = pdo_fetchall('select * from ims_ly_photobook_share as s left join ims_ly_photobook_user as u on s.openid = u.openid where s.parentid='.$this->uid2sid($value['id']).' and u.dealer !=-1');
			pdo_update('ly_photobook_user',array('team_count'=>$this->maxTeamCount,'self_count'=>count($self_count)),array('id'=>$value['id']));
		}
	}
	public function Count($join){
		$sublist = pdo_getall('ly_photobook_share',array('parentid'=>$join));
		if(empty($sublist)){
			return true;
		}else{
			foreach ($sublist as $key => $value) {
				$uid = $this->sid2uid($value['id']);
				$user = pdo_get('ly_photobook_user',array('id'=>$uid));
				if($user['dealer'] != -1){//代理人数
					$this->maxTeamCount++;
				}
				$this->Count($value['id']);
			}
		}
	}
	public function id2openid($id){
		global $_W,$_GPC;

		return pdo_get('ly_photobook_user',array('id'=>$id,'uniacid'=>$_W['uniacid']))['openid'];
	}
    /**
     * 代理商代金券列表
     */
	public function doMobileAgentCardList(){
		$this->_exec('AgentCardList',false);
	}
	/**
	 * 代金券购买记录
	 */
	public function doWebCode_buy(){
		$this->_exec('Code_buy',true);
	}
	/**
	 * 代金券分享明细
	 */
	public function doWebCard_sharelog(){
		$this->_exec('card_sharelog',true);
	}
	/**
	 * 代金券分享统计
	 */
	public function doWebCard_sharemag(){
		$this->_exec('card_sharemag',true);
	}
    /**
     * 代理商代下级列表
     */
	public function doMobileAgentSubList(){
		$this->_exec('AgentSubList',false);
	}

    /**
     * 给下级代金券
     */
	public function doMobileGiveHeCard(){
		global $_GPC,$_W;
		if($_W['isajax']){
			/**
			 * 后台判断卡卷数时候大于0
			 */
			$code_info = pdo_get('ly_photobook_user_code',array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['coid']));
			$card_count = pdo_get('ly_photobook_user_code',array('id'=>$_GPC['coid']))['number'];
			if($card_count < $_GPC['number']){
				return '0';
			}
			$user_id=pdo_get('ly_photobook_user',array('openid'=>$_GPC['openid']))['id'];
			$data=array(
				'uniacid'=>$_W['uniacid'],
				'user_id'=>$user_id,
				'code_id'=>$code_info['code_id'],
				'status'=>0,
				'number'=>$_GPC['number']
			);
			/**
			 * 判断是更新还是插入
			 */
			$ishas = pdo_get('ly_photobook_user_code',array('uniacid'=>$_W['uniacid'],'user_id'=>$user_id,'code_id'=>$code_info['code_id']));
			if(empty($ishas))
				$result = pdo_insert('ly_photobook_user_code',$data);
			else{
				$data['number'] +=$ishas['number'];
				$result = pdo_update('ly_photobook_user_code',$data,array('id'=>$ishas['id']));
			}

			if($result){
				// 代理商的减掉
				$res = pdo_update('ly_photobook_user_code',array('number -='=>$_GPC['number']),array('id'=>$_GPC['coid']));
				/**
				 * 记录中间过程  上级 分享给了下级  多少代金券  什么时间
				 */
				if($res){
					$parent = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id'];
					$insert_data = [
						'uniacid'=>$_W['uniacid'],
						'parent'=>$parent,
						'code_kind'=>$code_info['code_id'],
						'number'=>$_GPC['number'],
						'children'=>$user_id,
						'insert_time'=>time()
					];
					if(pdo_insert('ly_photobook_share_code_log',$insert_data)){
						return '1';
					}
				}
			}else{
				return '0';
			}
		}else{
			return '访问错误';
		}
	}

    /**
     * 返利记录
     */
	public function doMobileMyRebateList(){
		$this->_exec('MyRebateList',false);
	}
	/**
	 * 代理中心---佣金明细
	 */
	public function doMobileYj_detail(){
		global $_W,$_GPC;
		$p_title="佣金明细";
		$userid = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id']; 
		if(empty($_GPC['id']))
			$rebate = pdo_getall('ly_photobook_user_rebate',array('uniacid'=>$_W['uniacid'],'userid'=>$userid),array(),'','id desc');
		else{
			$num_list = pdo_get('ly_photobook_apply_rebate',array('id'=>$_GPC['id']))['num_list'];
			$num_list = json_decode($num_list,true);
			foreach ($num_list as $key => $value) {
				$rebate[] = pdo_get('ly_photobook_user_rebate',array('id'=>$value));
			}
		}
		include $this->template('yj_detail');
	}
	/**
	 * 代理中心---提现记录
	 */
	public function doMobileReturn_detail(){
		global $_W,$_GPC;
		$p_title="提现记录";

		$userid = pdo_get('ly_photobook_user',array('uniacid'=>$_W['uniacid'],'openid'=>$_W['openid']))['id']; 
		$rebates = pdo_getall('ly_photobook_apply_rebate',array('uniacid'=>$_W['uniacid'],'userid'=>$userid),array(),'','id desc');
		include $this->template('return_detail');
	}
    /**
     * 申请提现
     */
	public function doMobileApplyrebate(){
		global $_W,$_GPC;
		$userid=pdo_get('ly_photobook_user',array('openid'=>$_W['openid']))['id'];
		$rebate_list = pdo_getall('ly_photobook_user_rebate',array('uniacid'=>$_W['uniacid'],'userid'=>$userid,'status'=>0));
		foreach ($rebate_list as $key => $value) {
			$rebate_num[] = $value['id'];
		}

		$insert=array(
			'uniacid'=>$_W['uniacid'],
			'userid'=>$userid,
			'createtime'=>time(),
			'status'=>0,
			'money'=>$_GPC['money'],
			'num_list'=>json_encode($rebate_num)
		);

		if(pdo_insert('ly_photobook_apply_rebate',$insert)){
			foreach ($rebate_list as $key => $value) {
				pdo_update('ly_photobook_user_rebate',array('status'=>1),array('id'=>$value['id']));
			}
			return '1';
		}else{
			return '0';
		}
	}

    /**
     * 奖励管理
     */
	public function doWebRebateList(){
		$this->_exec('RebateList');
	}
	/**
	 * 奖励申请发放明细
	 */
	public function doWebRebate_detail(){
		$this->_exec('rebate_detail');
	}
    /**
     * 发送奖励提现
     */
	public function doWebSendRebate(){
		global $_W,$_GPC;
		if($_W['isajax']){
			
			$apply_rebate=pdo_get('ly_photobook_apply_rebate',array('id'=>$_GPC['id']));
			$monay=round($apply_rebate['money']*100);
			$openid=pdo_get('ly_photobook_user',array('id'=>$apply_rebate['userid']),array('openid'));
			$res=$this->Enfunds($monay,$openid['openid']);

			if($res['isok']){
				pdo_update('ly_photobook_apply_rebate',array('status'=>1),array('id'=>$_GPC['id']));
				/**
				 * 将所有提现的金额状态改为已经提现
				 */
				$rebate_list = json_decode($apply_rebate['num_list'],true);
				foreach ($rebate_list as $key => $value) {
					pdo_update('ly_photobook_user_rebate',array('status'=>2),array('id'=>$value));
				}
				return '1';
			}else{
				return '0';
			}
		}else{
			echo '非法访问';
		}
	}

	/**
	 * 发放奖励
	 */
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
		$myfile = fopen("../addons/photobook/newfile.xml", "w") or die("Unable to open file!");

		$extrasa = array();
		$extrasa['CURLOPT_CAINFO'] ='../addons/photobook/cert/rootca.pem';
		$extrasa['CURLOPT_SSLCERT'] ='../addons/photobook/cert/apiclient_cert.pem';
		$extrasa['CURLOPT_SSLKEY'] ='../addons/photobook/cert/apiclient_key.pem';
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





