<?php
/**
* [write_log 写入日志]
* @param  [type] $data [写入的数据]
* @return [type]       [description]
*/
function write_log($data){
$years = date('Y-m');
//设置路径目录信息
$url = './public/log/txlog/'.$years.'/'.date('Ymd').'_request_log.txt';
$dir_name=dirname($url);
//目录不存在就创建
if(!file_exists($dir_name))
{
//iconv防止中文名乱码
$res = mkdir(iconv("UTF-8", "GBK", $dir_name),0777,true);
}
$fp = fopen($url,"a");//打开文件资源通道 不存在则自动创建
fwrite($fp,date("Y-m-d H:i:s")."\r\n");//写入文件
fwrite($fp,var_export($data,true)."\r\n");
fclose($fp);//关闭资源通道
}
