<?php
//-----------------------------
//   �ð汾��δ���ݴ���cleanup��ftp�ϴ�ʧ�ܣ����������ύsvn������ͬ��
//-----------------------------
//   ��ʼ������
//-----------------------------
date_default_timezone_set('PRC');
require_once("post-commit-param.php");


//��õ�ǰ���µ�log
$svnlook_log = "{$svnlook_path} log -r {$argv[2]} {$argv[1]}";
//echo "{$argv[2]} {$argv[1]}";
//��õ�ǰ�������ʻ���
$svnlook_author = "{$svnlook_path} author -r {$argv[2]} {$argv[1]}";

//�������µ�Դ�뵽��ʱ����Ŀ¼
$svn_update = "{$svn_path} update -r {$argv[2]} \"{$TMP_UPDATE_DIR}\"  --username {$svn_name} --password {$svn_pass} --trust-server-cert   --non-interactive";

$config = unserialize(file_get_contents(dirname(__FILE__)."/cache.ca"));
if(!isset($config) || empty($config))  {
	$config = array("dataIndex"=>1,);
	
}

$output = array();
exec($svnlook_log, $output);
$log = $output;

$output = array();
exec($svnlook_author, $output);
$author = $output;
//updateLog("test");
//-----------------------------
//  ����
//-----------------------------

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
		   if(!@ftp_chdir($ftp,$path)){
			 @ftp_chdir($ftp,"/");
			 if(!@ftp_mkdir($ftp,$path)){
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
       get_resource_type($ftp_stream) !== 'FTP Buffer') {

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
	
	global $argv, $ftp, $author_name, $TMP_UPDATE_DIR, $fileSize, $config, $isPasv, $ftp_ip, $ftp_port, $ftp_username , $ftp_pass, $log_name;
	
	$dataIndex = $config["dataIndex"];


	
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

	$log = "{$update_list}\n";
	$log .= date('Y-m-d H:i:s') . " BEGIN UPDATE TO FTP\n";
	$log .= date('Y-m-d H:i:s') . " Reversion: {$argv[2]}\n";
	$log .= date('Y-m-d H:i:s') . " Author: {$author_name}\n";


#	$ftp = ftp_connect('192.168.0.163','5000');
	$ftp = ftp_connect($ftp_ip,$ftp_port);
	$ftp_login = ftp_login($ftp, $ftp_username, $ftp_pass);
	

	$result = true;
	if($ftp && $ftp_login){
		   $log .= date('Y-m-d H:i:s') . " Connected to FTP Server Success\n";
		   if(isPasv){
				ftp_pasv($ftp, true);
		   }
		   
			
			foreach($update_list as $file_cmd){ 
				   if(substr($file_cmd, 0, 8) == 'Updating') continue;
				   if(substr($file_cmd, 0, 6) == 'Update') continue;        //���������һ�е�   Update Reversion NNN Ҫ����
				   $file_cmd = trim($file_cmd);
				   $cmd = substr($file_cmd, 0, 1);
				   $file = trim(substr($file_cmd, 1));
	$log .= date('Y-m-d H:i:s') . " file_cmd: {$file_cmd}  \n";
				   $from = $file;
				   $file = substr($file, strlen($TMP_UPDATE_DIR) + 1); //ȥ��·���еĿ�ͷ $TMP_UPDATE_DIR ·��
				   $filename = is_dir($from) ? null : array_pop(explode('/', $file));
				   $to = $ftp_root_dir . str_replace("\\", "/", $file);

				   //�����·��������FTPĿ¼ (��������ڵĻ�)
				   $dir = is_dir($from) ? $to : dirname($to);
				   if(!@ftp_chdir($ftp,$dir)){
					 $log .= date('Y-m-d H:i:s') . " FTP_MKD\t{$dir}\n";
					 ftp_create_dir($dir);                                  //����Ŀ¼
				   }
				   if(is_dir($from)) continue;

				   //���»򴴽��ļ�
				   if($cmd == "U" || $cmd == "A"){
				   	$rrr = ftp_put($ftp, $to, $from, FTP_BINARY);
					 $result = $result && $rrr;
					 //��¼��־
					 $log .= date('Y-m-d H:i:s') . " FTP_PUT\t{$from}\t{$to}" . ($result ? "\tSUCCESS\n" : "\tFALSE\n");

				   //ɾ���ļ���Ŀ¼
				   }else if($cmd == "D"){
					 //-------------------------------
					 //   ����Ҫ�ж�Ŀ����Ŀ¼�����ļ�
					 //-------------------------------

					 if(@ftp_chdir($ftp,$to)){
						$r = ftp_rmdirr($ftp, $to);
						$log .= date('Y-m-d H:i:s') . " FTP_RMD\t{$to}" . ($r ? "\tSUCCESS\n" : "\tFALSE\n");
					 }else{
					 	$rrr = ftp_delete($ftp, $to);
						$result = $result && $rrr;
						//��¼��־
						$log .= date('Y-m-d H:i:s') . " FTP_DEL\t{$to}" . ($result ? "\tSUCCESS\n" : "\tFALSE\n");
					 }
				   }else{
					 $log .= date('Y-m-d H:i:s') . " UNKNOWN CMD\t{$cmd}\n";
					 continue;
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

	file_put_contents($ftp_update_logfile, $log, FILE_APPEND);
	return $result;
}

//-----------------------------
//   �жϲ�ִ��ͬ������
//-----------------------------


//ȡ�õ�ǰ�������ʻ������ж��Ƿ���Ȩ�޸��µ����з�����
$author_name = $author[count($author) -1];

if(true){

//�鿴log���Ƿ���� [UPTO_RUN_SERVER] ָ����
//if(strpos(implode("\n", $log), '[UPTO_RUN_SERVER]') !== false){
	if(1){
		   //-------------------------------------------
		   //   ׼����ʼͬ���������߷������ϵ��ļ�
		   //-------------------------------------------
		  
		   $new_rev = $argv[2];

		   if(!file_exists($update_status_file)){
			 file_put_contents($update_status_file, '1|1');
		   }
		   $update_status = explode('|', file_get_contents($update_status_file));

		   //����¼�ĸ���״̬Ϊ���³ɹ��İ汾��{$update_status[0]} �����һ�θ��µİ汾�� {$update_status[1]} Сʱ
		   //��ԭ��ʱ����Ŀ¼�����һ�θ��³ɹ��İ汾 {$update_status[0]}��Ȼ���� update ��ø����ļ��б�
		   //Ȼ���ύ�����߷�����

		   if($update_status[0] < $update_status[1]){
			 $svn_update_r = "{$svn_path} update -r {$update_status[0]} {$TMP_UPDATE_DIR}"; 
			 exec($svn_update_r);
		   }
		   
		   $output = array();
		   exec($svn_update, $output);
		   $update = $output;
$logtest = "";
			foreach($output as $file_cmd){ 
				$logtest = $logtest."\r\n".$file_cmd;
			}
$fh = fopen($SvnHookData, "w");
fwrite($fh, $svn_update."\r\n".$logtest);    // �����6

fclose($fh);
		   //�������update�б���·������ϵ��ļ�
		   $update_success = true;
		  
		   $update_success = update_to_ftp($update);

		   //��¼���º�ĸ���״̬�����ļ�
		   if($update_success){
			 file_put_contents($update_status_file, "{$new_rev}|{$new_rev}");
		   }else{          
			 file_put_contents($update_status_file, "{$update_status[0]}|{$new_rev}");
		   }
		  
	}
}
?>