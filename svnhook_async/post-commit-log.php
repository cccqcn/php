<?php
//-----------------------------
//   �ð汾Ϊ�첽�ϴ������ļ���cleanup�ݴ�svn�ύ�󽫸��¼�¼���浽�ļ�
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
//  ����
//-----------------------------

function updateLog($str)
{
	global $SvnHookData;
	$fh = fopen($SvnHookData, "w");
	fwrite($fh, $str);
	fclose($fh);
}


//-----------------------------
//   �жϲ�ִ��ͬ������
//-----------------------------


//ȡ�õ�ǰ�������ʻ������ж��Ƿ���Ȩ�޸��µ����з�����
$author_name = $author[count($author) -1];

if(true){

//�鿴log���Ƿ���� [UPTO_RUN_SERVER] ָ����
//if(strpos(implode("\n", $log), '[UPTO_RUN_SERVER]') !== false){
	if(1)
	{
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
		
		$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.txt"; //ִ�и��µ��ļ�
		if(file_exists($ftp_array_txtfile)) {
			$arrayTxtstr = file_get_contents($ftp_array_txtfile);
			$arrayTxtarr = explode("\r\n", $arrayTxtstr);
			if(count($arrayTxtarr) >= $arraySize ) {//��С����,����д�ļ�
				
				$arrayIndex++;
				$ftp_array_txtfile="{$SvnHookArrayTxt}{$arrayIndex}.log"; //����ִ�и��µ��ļ�
				file_put_contents(dirname(__FILE__)."/arrayIndex.ca",$arrayIndex);
			}
		}
		

		file_put_contents($SvnHookTest, "\r\n".$arrayIndex."\r\n".$ftp_array_txtfile);
		file_put_contents($ftp_array_txtfile, $logtest, FILE_APPEND);
		
	}
}
?>