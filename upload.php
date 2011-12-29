<?php
/**
 * baidu-hi2WPforSAE:
 * 转移百度hi博客到基于新浪SAE的WordPress 
 * transmit the blog from baidu hi to wordpress on sae
 * 
 * The function of this script is deal with image save process.
 * NOTICE: You should remove the file after finished.
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

$tmp_name = $_FILES['Filedata']['tmp_name'];
//获得文件扩展名
$temp_arr = explode(".", $_FILES['Filedata']['name']);
$file_ext = array_pop($temp_arr);
$file_ext = trim($file_ext);
$file_ext = strtolower($file_ext);

$file_path = 'uploads/old/'.date("YmdHis") . '_' . rand(10000, 99999) . '.' . $file_ext; 

$stor = new SaeStorage();
echo $file_url = $stor->upload('wordpress', $file_path, $tmp_name);