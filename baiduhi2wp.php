<?php
/**
 * baidu-hi2WPforSAE:
 * 转移百度hi博客到基于新浪SAE的WordPress 
 * transmit the blog from baidu hi to wordpress on sae
 * 
 * Copyright (c) 2011, clear
 * All rights reserved.
 * 
 * Licensed under The BSD License
 * Redistributions of files must retain the above copyright notice. 
 * 
 * @copyright http://doophp.sinaapp.com
 * @author clear 
 * @mail jiangchunyi001@gmail.com 
 * @license       BSD License (http://www.opensource.org/licenses/bsd-license.php)
 */

/*xxxxxxxxxxxx配置文件xxxxxxxxxxxxxx*/ 
$_config = array(
	'blog_name' => 'aboutstudy',		//博客用户名（必填）
	'upload'	=> '',					//新浪SAE服务器端处理图片上传程序URL，如不需要本地化图片请保持空
	'local_path' => "D:\\wordpress",	//图片和生成的SQL文件保存位置
	'cate_id' => array(					//博客分类ID，请先建好对应分类
		'默认分类' 	=> 1,
		'mysql' => 2,
		'linux' => 3,
		'php' => 4,
		'nosql' => 5)
);

/*xxxxxxxxxxxx程序入口xxxxxxxxxxxxxx*/
main();

/*xxxxxxxxxxxx函数定义xxxxxxxxxxxxxx*/

function main(){
	global $_config;
	
	echo "\nprocessing... \n";
	$blogs = getCate('http://hi.baidu.com/'.$_config['blog_name'].'/blog/index/0');
	createSQL($blogs);
	echo "\nsucceed!";	
	echo "\n NOTICE:Please remove the 'upload.php' file from the SAE after finished.";
}

/**
 * 获取分类目录下面所有博客
 * @param unknown_type $first_url
 */
function getCate($first_url){
	$html = getRemoteCon($first_url);
	
	//获取当前分类的所有条目
	$pages = getPages($html);	
		
	$arrBlogs = array();	
	foreach($pages as $num => $page_url){
		echo "\npage: ".str_pad($num, 3, ' ', STR_PAD_RIGHT)." - ";
		$arrBlogs = array_merge($arrBlogs, getPageList($page_url));
	}
	$arrBlogs = array_reverse($arrBlogs);
	return $arrBlogs;
}

/**
 * 根据分类列表首页获取当前分类所有页码链接
 * @param $html
 */
function getPages($html){
	$pattern = '/<div id=page>(.*?)下一页/';
	preg_match_all($pattern, $html, $pages);
	
	$pattern = '/href=\'(.*?)\'>/';
	preg_match_all($pattern, $html, $links);
	
	$pages = array_slice($links[1], 0, count($links[1]) - 3);
	array_unshift($pages, substr($pages[0], 0, strrpos($pages[0], '/')) . '/0');
	
	foreach($pages as $key => $page){
		$pages[$key] = 'http://hi.baidu.com' . $pages[$key]; 
	}
	return $pages;
}

/**
 * 按页获取博客内容
 * @param unknown_type $page_url
 */
function getPageList($page_url){
	global $_config;
	$html = getRemoteCon($page_url);
	
	$pattern = '/<div class=tit><a href="\/'.$_config['blog_name'].'\/blog\/item\/(.*?)" target=_blank>(.*?)<\/a><\/div><div class=date>(.*?)<\/div><table style=table-layout:fixed><tr><td><div class=cnt>(.*?)<\/div><\/table><div class=more><a href(.*?)类别：(.*?)<\/a>/';
	preg_match_all($pattern, $html, $result);
		
	$blogs = array();
	foreach($result[1] as $key => $url){
		echo "$key ";
		$content = getContent('http://hi.baidu.com/'.$_config['blog_name'].'/blog/item/' . $url);
		
		//保存远程图片
		$pattern_image = '/src="(.*?)"/';
		preg_match_all($pattern_image, $content, $images);
		if(!empty($images[1])){
			foreach($images[1] as $image_url){
				$local_file = $_config['local_path'] . "\\images\\" . basename($image_url);
				if(!is_file($local_file)){
					$save_status = saveRemoteImage($image_url, $local_file);	
				}	
							
				//保存图片到SAE
				if($_config['upload']){
					$remote_ulr = uploadToSAE($local_file, $_config['upload']);	
				
					//替换博客内容图片路径
					if($remote_ulr){
						$content = str_replace($image_url, $remote_ulr, $content);
					}					
				}				
			}
		}		
		
		$blogs[] = array(
			'url'		=> 'http://hi.baidu.com/'.$_config['blog_name'].'/blog/item/' . $url,
			'title'		=> $result[2][$key],
			'date'		=> $result[3][$key],
			'category'	=> getCateID(strtolower($result[6][$key])),
			'desc'		=> $result[4][$key],
			'content'	=> $content
		);
	}
	return $blogs;
}

/**
 * 获取博客正文
 * @param $url
 */
function getContent($url){
	$html = getRemoteCon($url);

	$pattern = '/<div id=blog_text class=cnt>(.*?)<\/div><\/table><br><div class=opt id=blogOpt/';
	preg_match_all($pattern, $html, $content);
	
	return $content[1][0];
}

/**
 * 生成SQL文件
 * @param unknown_type $blogs
 */
function createSQL($blogs){
	global $_config;
	$directory = $_config['local_path'] . "\\sql\\";
	!is_dir($directory) && mkdir($directory, null, true);
	$post_handle = fopen($directory . 'post.sql', 'w');
	$cate_handle = fopen($directory . 'cate.sql', 'w');
	$post = '';
	$cate = '';
			
	foreach ($blogs as $num => $blog){
		$post .= 'INSERT INTO `wp_posts` (`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES('.(1000+$num).', 1, \''.$blog['date'].'\', \'0000-00-00 00:00:00\', \''.addslashes($blog['content']).'\', \''.$blog['title'].'\', \''.addslashes($blog['desc']).'\', \'publish\', \'open\', \'open\', \'\', \'\', \'\', \'\', \''.$blog['date'].'\', \''.$blog['date'].'\', \'\', 0, \'/?p='.(1000+$num).'\', 0, \'post\', \'\', 0);'."\n";
		$cate .= 'INSERT INTO `wp_term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) VALUES('.(1000+$num).', '.$blog['category'].', 0);'."\n";	
	}
	
	fwrite($post_handle, $post);
	fclose($post_handle);
	
	fwrite($cate_handle, $cate);	
	fclose($cate_handle);	
}

function getCateID($cate_name){
	global $_config;
	$arrCate  = $_config['cate_id'];
	
	if (empty($arrCate[$cate_name])){
		return 1;
	}
	else{
		return $arrCate[$cate_name];
	}
}

/**
 * 保存远程图片
 * @param unknown_type $url
 */
function saveRemoteImage($url, $path){
	ob_start();
	readfile($url);
	$img = ob_get_contents();
	ob_end_clean();
	$size = strlen($img);
		
	//目录处理
	$directory = dirname($path);
	!is_dir($directory) && mkdir($directory, null, true);
	
	$fp2=@fopen($path, "a");
	fwrite($fp2,$img);
	fclose($fp2);
	return true;
}

/**
 * 保存图片到SAE
 * @param $file
 */
function uploadToSAE($file, $post_url){	
	$fields['Filedata'] = '@'.$file;
	
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $post_url );
	curl_setopt($ch, CURLOPT_POST, 1 );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
	$result = curl_exec($ch);
	
	if ($error = curl_error($ch) ) {
	       die($error);
	}
	curl_close($ch);
	return $result;
}

/**
 * 获取远程内容
 * @param $url
 * @param $gbo2utf8
 */
function getRemoteCon($url, $gbk2utf8=true){
	$html = _cURL($url);
	if($gbk2utf8 === true) $html = iconv('GB2312', 'UTF-8//IGNORE', $html);			
	$html = preg_replace("/[\r\n]+/","", $html);
	$html = preg_replace('/\s{2,}/', '', $html);
	return $html;	
}

/**
 * curl()函数
 * @param unknown_type $URL
 * @param unknown_type $params
 * @param unknown_type $is_POST
 */
function _cURL($URL, $params = null, $is_POST = 0){	
	$con = curl_init();
	curl_setopt($con, CURLOPT_URL, $URL);
	curl_setopt($con, CURLOPT_RETURNTRANSFER, 1);
	
	if($is_POST == 1){
		curl_setopt($con, CURLOPT_POST, 1);
		curl_setopt($con, CURLOPT_POSTFIELDS, $params);			
	}
	
	$output = curl_exec($con);
	curl_close($con);							

	return $output;
}