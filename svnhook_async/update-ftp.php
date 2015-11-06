<?php
//-----------------------------
//   该版本为异步上传，该文件有ftp上传失败容错，将保存好的svn更新记录上传到ftp
//-----------------------------
//   初始化设置
//-----------------------------
date_default_timezone_set('PRC');
require_once("post-commit-param.php");

$config = unserialize(file_get_contents(dirname(__FILE__)."/cache.ca"));
if(!isset($config) || empty($config))  {
	$config = array("dataIndex"=>1,);
	
}

echo "上传到FTP服务器";
$result = uploadSvnArray();
while($result == false)
{
	echo "上传中断，10秒后继续上传";
	usleep(10000000);
	$result = uploadSvnArray();
}
function uploadSvnArray()
{
	global $SvnHookArrayTxt, $SvnHookTest;
	$arrayIndex = file_get_contents(dirname(__FILE__)."/arrayIndex.ca");
	$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.txt"; //执行更新的文件
	file_put_contents($SvnHookTest, "\r\n".$arrayIndex."\r\n".$ftp_array_txtfile);
	$arrayTxt = file_get_contents($ftp_array_txtfile);
	$array = explode("\r\n", $arrayTxt);
	$result = update_to_ftp($array);
	return $result;
}

function updateLog($str)
{
	global $SvnHookData;
	$fh = fopen($SvnHookData, "w");
	fwrite($fh, $str);
	fclose($fh);
}

/**
* 在FTP服务器上创建目录
*
* @author terry39
*/
function ftp_create_dir($path)
{
	global $ftp;

	$dir=split("/", $path);
	$path="";
	$ret = true;

	for ($i=0;$i<count($dir);$i++)
	{
		$path.="/".$dir[$i];
		if(!@ftp_chdir($ftp,$path))
		{
			@ftp_chdir($ftp,"/");
			if(!@ftp_mkdir($ftp,$path))
			{
				$ret=false;
				break;
			}
		}
	}
	return $ret;
}

/**
* 删除   FTP 中指定的目录 
*
* @param resource $ftp_stream The link identifier of the FTP connection
* @param string $directory The directory to delete
*
* @author terry39
*/
function ftp_rmdirr($ftp_stream, $directory)
{
	if (!is_resource($ftp_stream) ||
		get_resource_type($ftp_stream) !== 'FTP Buffer')
	{
		return false;
	}

	// Init
	$i          = 0;
	$files       = array();
	$folders    = array();
	$statusnext = false;
	$currentfolder = $directory;

	// Get raw file listing
	$list = ftp_rawlist($ftp_stream, $directory, true);

	foreach ($list as $current) {

		if (empty($current)) {
			$statusnext = true;
			continue;
		}

		if ($statusnext === true) {
			$currentfolder = substr($current, 0, -1);
			$statusnext = false;
			continue;
		}

		$split = preg_split('[ ]', $current, 9, PREG_SPLIT_NO_EMPTY);
		$entry = $split[8];
		$isdir = ($split[0]{0} === 'd') ? true : false;

		// Skip pointers
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		if ($isdir === true) {
			$folders[] = $currentfolder . '/' . $entry;
		} else {
			$files[] = $currentfolder . '/' . $entry;
		}

	}

	foreach ($files as $file) {
		ftp_delete($ftp_stream, $file);
	}

	rsort($folders);
	foreach ($folders as $folder) {
		ftp_rmdir($ftp_stream, $folder);
	}

	return ftp_rmdir($ftp_stream, $directory);
}

/**
* 把更新到的文件列表上传到服务器上
*
* 更新的同时记录更新日志，如过有一个文件或者目录更新失败，则返回错误
*
* @param array $update_list
* @return bool
*
* @author terry39
*/
function update_to_ftp($update_list)
{
	global $SvnHookArrayTxt, $argv, $ftp, $author_name, $TMP_UPDATE_DIR, $fileSize, $config, $isPasv, $ftp_ip, $ftp_port, $ftp_username , $ftp_pass, $log_name;
	
	$dataIndex = $config["dataIndex"];

	$newstr = "";

	$ftp_update_logfile="{$log_name}{$dataIndex}.log"; //执行更新的日志文件
	if(file_exists($ftp_update_logfile)) {
		if(filesize($ftp_update_logfile) >= $fileSize ) {//大小超过10M,重新写日志文件
			
			$dataIndex++;
			$config["dataIndex"] = $dataIndex;
			$ftp_update_logfile="{$log_name}{$dataIndex}.log"; //重新执行更新的日志文件
			$contnet = serialize($config);
			file_put_contents(dirname(__FILE__)."/cache.ca",$contnet);
		}
	}
	
	
	$ftp_root_dir = '/';                                              //服务器上的地址源码对应的起始目录

	$log = "";
	$log .= date('Y-m-d H:i:s') . " BEGIN UPDATE TO FTP\n";


#	$ftp = ftp_connect('192.168.0.163','5000');
	$ftp = ftp_connect($ftp_ip,$ftp_port);
	$ftp_login = ftp_login($ftp, $ftp_username, $ftp_pass);
	

	$result = true;
	$connectSuccess = false;
	if($ftp && $ftp_login){
		$connectSuccess = true;
		$log .= date('Y-m-d H:i:s') . " Connected to FTP Server Success\n";
		if($isPasv){
			ftp_pasv($ftp, true);
		}
		
		$isBreaked = false;
		$total = count($update_list);
		$current = 0;
		foreach($update_list as $file_cmd){ 
			$current++;
			if($isBreaked == true || $file_cmd == '' || substr($file_cmd, 0, 8) == 'Updating' || substr($file_cmd, 0, 6) == 'Update' || substr($file_cmd, 0, 6) == 'Finish') 
			{
				if($file_cmd != '')
				{
					$newstr = $newstr."\r\n".$file_cmd;
					echo $file_cmd."(".$current."/".$total.")"."\n";
				}
				continue;        //这里有最后一行的   Update Reversion NNN 要忽略
			}
			$file_cmd = trim($file_cmd);
			$cmd = substr($file_cmd, 0, 1);
			$file = trim(substr($file_cmd, 1));
			$log .= date('Y-m-d H:i:s') . " file_cmd: {$file_cmd}  \n";
			$from = $file;
			$file = substr($file, strlen($TMP_UPDATE_DIR) + 1); //去掉路径中的开头 $TMP_UPDATE_DIR 路径
			$tempExplode = explode('/', $file);
			$tempArrayPop = array_pop($tempExplode);
			$filename = is_dir($from) ? null : $tempArrayPop;
			$to = $ftp_root_dir . str_replace("\\", "/", $file);

			//计算出路径并创建FTP目录 (如果不存在的话)
			$dir = is_dir($from) ? $to : dirname($to);
			if(!@ftp_chdir($ftp,$dir)){
				$log .= date('Y-m-d H:i:s') . " FTP_MKD\t{$dir}\n";
				echo "creatingdir ".$dir."\n";
				ftp_create_dir($dir);                                  //创建目录
			}
			if(is_dir($from)) continue;

			$rrr = false;
			//更新或创建文件
			if($cmd == "U" || $cmd == "A"){
				echo "uploading ".$file_cmd."\n";
				$rrr = ftp_put($ftp, $to, $from, FTP_BINARY);
				$result = $result && $rrr;
				//记录日志
				$log .= date('Y-m-d H:i:s') . " FTP_PUT\t{$from}\t{$to}" . ($rrr ? "\tSUCCESS\n" : "\tFALSE\n");
				if($rrr == false)
				{//如果上传失败，不再继续上传
					$isBreaked = true;
				}
				//删除文件或目录
			}else if($cmd == "D"){
				//-------------------------------
				//   这里要判断目标是目录还是文件
				//-------------------------------

				if(@ftp_chdir($ftp,$to)){
					echo "removingdir ".$file_cmd."\n";
					$rrr = ftp_rmdirr($ftp, $to);
					$log .= date('Y-m-d H:i:s') . " FTP_RMD\t{$to}" . ($rrr ? "\tSUCCESS\n" : "\tFALSE\n");
				}else{
					echo "deleting ".$file_cmd."\n";
					$rrr = ftp_delete($ftp, $to);
					$rrr = true;
					$result = $result && $rrr;
					//记录日志
					$log .= date('Y-m-d H:i:s') . " FTP_DEL\t{$to}" . ($rrr ? "\tSUCCESS\n" : "\tFALSE\n");
				}
			}else{
				$log .= date('Y-m-d H:i:s') . " UNKNOWN CMD\t{$cmd}\n";
			}
			echo ($rrr ? "\tSUCCESS" : "\tFALSE")."(".$current."/".$total.")\n";
			if($rrr)
			{
				$newstr = $newstr."\r\nFinished ".$file_cmd;
			}
			else
			{
				$newstr = $newstr."\r\n".$file_cmd;
			}
		}
	}else{
		$log .= date('Y-m-d H:i:s') . " Connected to FTP Server False\n";
		$log .= date('Y-m-d H:i:s') . " UPDATE FALSE\n";
		$log .= date('Y-m-d H:i:s') . " QUIT UPDATE\n\n";
		//return false;
	}

	//记录最后一次更新成功的版本
	if($result){
		$log .= date('Y-m-d H:i:s') . " UPDATE SUCCESS\n";
	}else{
		$log .= date('Y-m-d H:i:s') . " UPDATE FALSE\n";
	}
	$log .= date('Y-m-d H:i:s') . " END UPDATE\n\n";

	$arrayIndex = file_get_contents(dirname(__FILE__)."/arrayIndex.ca");
	$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.txt"; //执行更新的文件
	file_put_contents($ftp_update_logfile, $log, FILE_APPEND);
	if($connectSuccess){
		file_put_contents($ftp_array_txtfile, $newstr);
	}
	return $result;
}

?>