<?php
//-----------------------------
//   �ð汾Ϊ�첽�ϴ������ļ���ftp�ϴ�ʧ���ݴ�������õ�svn���¼�¼�ϴ���ftp
//-----------------------------
//   ��ʼ������
//-----------------------------
date_default_timezone_set('PRC');
require_once("post-commit-param.php");

$config = unserialize(file_get_contents(dirname(__FILE__)."/cache.ca"));
if(!isset($config) || empty($config))  {
	$config = array("dataIndex"=>1,);
	
}

echo "�ϴ���FTP������";
$result = uploadSvnArray();
while($result == false)
{
	echo "�ϴ��жϣ�10�������ϴ�";
	usleep(10000000);
	$result = uploadSvnArray();
}
function uploadSvnArray()
{
	global $SvnHookArrayTxt, $SvnHookTest;
	$arrayIndex = file_get_contents(dirname(__FILE__)."/arrayIndex.ca");
	$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.txt"; //ִ�и��µ��ļ�
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
* ��FTP�������ϴ���Ŀ¼
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
* ɾ��   FTP ��ָ����Ŀ¼ 
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
* �Ѹ��µ����ļ��б��ϴ�����������
*
* ���µ�ͬʱ��¼������־�������һ���ļ�����Ŀ¼����ʧ�ܣ��򷵻ش���
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

	$ftp_update_logfile="{$log_name}{$dataIndex}.log"; //ִ�и��µ���־�ļ�
	if(file_exists($ftp_update_logfile)) {
		if(filesize($ftp_update_logfile) >= $fileSize ) {//��С����10M,����д��־�ļ�
			
			$dataIndex++;
			$config["dataIndex"] = $dataIndex;
			$ftp_update_logfile="{$log_name}{$dataIndex}.log"; //����ִ�и��µ���־�ļ�
			$contnet = serialize($config);
			file_put_contents(dirname(__FILE__)."/cache.ca",$contnet);
		}
	}
	
	
	$ftp_root_dir = '/';                                              //�������ϵĵ�ַԴ���Ӧ����ʼĿ¼

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
				continue;        //���������һ�е�   Update Reversion NNN Ҫ����
			}
			$file_cmd = trim($file_cmd);
			$cmd = substr($file_cmd, 0, 1);
			$file = trim(substr($file_cmd, 1));
			$log .= date('Y-m-d H:i:s') . " file_cmd: {$file_cmd}  \n";
			$from = $file;
			$file = substr($file, strlen($TMP_UPDATE_DIR) + 1); //ȥ��·���еĿ�ͷ $TMP_UPDATE_DIR ·��
			$tempExplode = explode('/', $file);
			$tempArrayPop = array_pop($tempExplode);
			$filename = is_dir($from) ? null : $tempArrayPop;
			$to = $ftp_root_dir . str_replace("\\", "/", $file);

			//�����·��������FTPĿ¼ (��������ڵĻ�)
			$dir = is_dir($from) ? $to : dirname($to);
			if(!@ftp_chdir($ftp,$dir)){
				$log .= date('Y-m-d H:i:s') . " FTP_MKD\t{$dir}\n";
				echo "creatingdir ".$dir."\n";
				ftp_create_dir($dir);                                  //����Ŀ¼
			}
			if(is_dir($from)) continue;

			$rrr = false;
			//���»򴴽��ļ�
			if($cmd == "U" || $cmd == "A"){
				echo "uploading ".$file_cmd."\n";
				$rrr = ftp_put($ftp, $to, $from, FTP_BINARY);
				$result = $result && $rrr;
				//��¼��־
				$log .= date('Y-m-d H:i:s') . " FTP_PUT\t{$from}\t{$to}" . ($rrr ? "\tSUCCESS\n" : "\tFALSE\n");
				if($rrr == false)
				{//����ϴ�ʧ�ܣ����ټ����ϴ�
					$isBreaked = true;
				}
				//ɾ���ļ���Ŀ¼
			}else if($cmd == "D"){
				//-------------------------------
				//   ����Ҫ�ж�Ŀ����Ŀ¼�����ļ�
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
					//��¼��־
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

	//��¼���һ�θ��³ɹ��İ汾
	if($result){
		$log .= date('Y-m-d H:i:s') . " UPDATE SUCCESS\n";
	}else{
		$log .= date('Y-m-d H:i:s') . " UPDATE FALSE\n";
	}
	$log .= date('Y-m-d H:i:s') . " END UPDATE\n\n";

	$arrayIndex = file_get_contents(dirname(__FILE__)."/arrayIndex.ca");
	$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.txt"; //ִ�и��µ��ļ�
	file_put_contents($ftp_update_logfile, $log, FILE_APPEND);
	if($connectSuccess){
		file_put_contents($ftp_array_txtfile, $newstr);
	}
	return $result;
}

?>