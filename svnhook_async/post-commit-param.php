<?php
//��������
//��ʱ����Ŀ¼������svn checkout �õ����µ��ļ����ϴ���FTP
$TMP_UPDATE_DIR = "D:\\SvnHookData\\TestHook\\svn";



//��Ȩ�޸��µ����з�������svn�ʻ�E
$upto_run_managers = array('test', 'Everyone');

//����״̬��¼�ļ�
$update_status_file = "D:\\SvnHookData\\TestHook\\temple_update_status.txt";

//��־�����ļ���С10M
$fileSize = 8 * 1024;

//�ϴ������ļ�����10000��
$arraySize = 1000;

//ftpԶ��Ip
$ftp_ip = '127.0.0.1';

//ftpԶ�˶˿�
$ftp_port = '5000';

//ftp�û���
$ftp_username = 'testhook';

//ftp����
$ftp_pass = 'testhook';

//���ftp��������Ҫʹ�ñ���ģʽ 
$isPasv = false;	

//����log
$SvnHookData = "D:\\SvnHookData\\TestHook\\log.txt";	

//test
$SvnHookTest = "D:\\SvnHookData\\TestHook\\test.txt";	

//�ϴ���ջ
$SvnHookArrayTxt = "D:\\SvnHookData\\TestHook\\array";	

//svnlook.exe��·��
$svnlook_path= "\"D:\\Program Files\\VisualSVN Server\\bin\\svnlook.exe\"";

//svn.exe��·��
$svn_path = "\"D:\\Program Files\\VisualSVN Server\\bin\\svn.exe\"";

//svn�ύ�˻�
$svn_name = "test";
//svn�ύ����
$svn_pass = "test";

//log����
$log_name = "D:\\SvnHookData\\TestHook\\ftp_temple_upload_";

?>