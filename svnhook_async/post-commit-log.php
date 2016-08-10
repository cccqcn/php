<?php
//-----------------------------
//   该版本为异步上传，该文件有cleanup容错，svn提交后将更新记录保存到文件
//-----------------------------
//   初始化设置
//-----------------------------
date_default_timezone_set('PRC');
require_once("post-commit-param.php");


//获得当前更新的log
$svnlook_log = "{$svnlook_path} log -r {$argv[2]} {$argv[1]}";
//echo "{$argv[2]} {$argv[1]}";
//获得当前更新者帐户名
$svnlook_author = "{$svnlook_path} author -r {$argv[2]} {$argv[1]}";

//更新最新的源码到临时更新目录
$svn_update = "{$svn_path} update -r {$argv[2]} \"{$TMP_UPDATE_DIR}\"  --username {$svn_name} --password {$svn_pass} --trust-server-cert   --non-interactive";

//clean up
$svn_cleanup = "{$svn_path} cleanup \"{$TMP_UPDATE_DIR}\"  --username {$svn_name} --password {$svn_pass} --trust-server-cert   --non-interactive";

$output = array();
exec($svnlook_log, $output);
$svnlook_logarr = $output;

$output = array();
exec($svnlook_author, $output);
$author = $output;

//updateLog("test");
//-----------------------------
//  函数
//-----------------------------

function updateLog($str)
{
	global $SvnHookData;
	$fh = fopen($SvnHookData, "w");
	fwrite($fh, $str);
	fclose($fh);
}


//-----------------------------
//   判断并执行同步更新
//-----------------------------


//取得当前更新者帐户名，判断是否有权限更新到运行服务器
$author_name = $author[count($author) -1];

if(true){

//查看log中是否包含 [UPTO_RUN_SERVER] 指令标记
//if(strpos(implode("\n", $log), '[UPTO_RUN_SERVER]') !== false){
	if(1)
	{
		//-------------------------------------------
		//   准备开始同步更新在线服务器上的文件
		//-------------------------------------------

		$new_rev = $argv[2];

		if(!file_exists($update_status_file)){
			file_put_contents($update_status_file, '1|1');
		}
		$update_status = explode('|', file_get_contents($update_status_file));

		//当记录的更新状态为更新成功的版本号{$update_status[0]} 比最后一次更新的版本号 {$update_status[1]} 小时
		//还原临时更新目录到最后一次更新成功的版本 {$update_status[0]}，然后再 update 获得更新文件列表
		//然后提交到在线服务器

		if($update_status[0] < $update_status[1]){
			$svn_update_r = "{$svn_path} update -r {$update_status[0]} {$TMP_UPDATE_DIR} --username {$svn_name} --password {$svn_pass} --trust-server-cert   --non-interactive"; 
			exec($svn_update_r);
		}
	   
		$output = array();
		exec($svn_update, $output);
		if(count($output) == 0)
		{
			exec($svn_cleanup, $cleanup);
			exec($svn_update, $output);
		}
		$update = $output;
		$svnlook_logstr = "";
		foreach($svnlook_logarr as $file_cmd){ 
			$svnlook_logstr = $svnlook_logstr."\r\n".$file_cmd;
		}
		$logtest = "";
		foreach($output as $file_cmd){ 
			$logtest = $logtest."\r\n".$file_cmd;
		}
		updateLog($author_name."\r\n".$svnlook_log."\r\n".$svnlook_logstr."\r\n".$svn_update."\r\n".$logtest."\r\n".count($output));

		$arrayIndex = file_get_contents(dirname(__FILE__)."/arrayIndex.ca");
		
		$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.txt"; //执行更新的文件
		if(file_exists($ftp_array_txtfile)) {
			$arrayTxtstr = file_get_contents($ftp_array_txtfile);
			$arrayTxtarr = explode("\r\n", $arrayTxtstr);
			if(count($arrayTxtarr) >= $arraySize ) {//大小超过,重新写文件
				
				$arrayIndex++;
				$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.log"; //重新执行更新的文件
				file_put_contents(dirname(__FILE__)."/arrayIndex.ca",$arrayIndex);
			}
		}
		

		file_put_contents($SvnHookTest, "\r\n".$arrayIndex."\r\n".$ftp_array_txtfile);
		file_put_contents($ftp_array_txtfile, $logtest, FILE_APPEND);
		
	}
}
?>