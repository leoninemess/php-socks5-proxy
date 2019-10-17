# socks5-proxy
本项目是基于workerman开发的socks5代理。我这边的主要作用就是挂在服务器上代理请求隐藏信息。

## 目录结构
```
├─composer.json
├─composer.lock
├─config.php		//配置文件
├─log.php			//日志写入方法
├─proxy.php			//代理脚本
├─README.md
├─vendor
|   ├─autoload.php
|   ├─workerman		//依赖workerman
|   ├─composer
├─public
|   ├─log 			//日志目录
```
## 使用说明
* 打开config.php文件配置账号密码。
* 命令行键入 `php proxy.php start`。如果要在以守护进程后台运行 `php proxy.php start -d`
* 有请求过来的话日志就会打印在控制台上了。
