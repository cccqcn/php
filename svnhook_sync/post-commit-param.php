<?php
//参数变量
//临时更新目录，用于svn checkout 得到最新的文件后，上传到FTP
$TMP_UPDATE_DIR = "D:\\SvnHookData\\TestHook\\svn";



//有权限更新到运行服务器的svn帐户E
$upto_run_managers = array('test', 'Everyone');

//更新状态记录文件
$update_status_file = "D:\\SvnHookData\\TestHook\\temple_update_status.txt";

//日志限制文件大小10M
$fileSize = 8 * 1024;

//ftp远端Ip
$ftp_ip = '127.0.0.1';

//ftp远端端口
$ftp_port = '5000';

//ftp用户名
$ftp_username = 'testhook';

//ftp密码
$ftp_pass = 'testhook';

//这个ftp服务器需要使用被动模式 
$isPasv = false;	

//测试log
$SvnHookData = "D:\\SvnHookData\\TestHook\\log.txt";	

//svnlook.exe的路径
$svnlook_path= "\"D:\\Program Files\\VisualSVN Server\\bin\\svnlook.exe\"";

//svn.exe的路径
$svn_path = "\"D:\\Program Files\\VisualSVN Server\\bin\\svn.exe\"";

//svn提交账户
$svn_name = "test";
//svn提交密码
$svn_pass = "test";

//log名字
$log_name = "D:\\SvnHookData\\TestHook\\ftp_temple_upload_";

?>