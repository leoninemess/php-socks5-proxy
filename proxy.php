<?php

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Connection\AsyncTcpConnection;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/log.php';
require_once __DIR__.'/config.php';


define('STAGE_INIT', 0);
define('STAGE_AUTH', 1);
define('STAGE_ADDR', 2);
define('STAGE_UDP_ASSOC', 3);
define('STAGE_DNS', 4);
define('STAGE_CONNECTING', 5);
define('STAGE_STREAM', 6);
define('STAGE_DESTROYED', -1);

define('CMD_CONNECT', 1);
define('CMD_BIND', 2);
define('CMD_UDP_ASSOCIATE', 3);

define('ADDRTYPE_IPV4', 1);
define('ADDRTYPE_IPV6', 4);
define('ADDRTYPE_HOST', 3);

define('METHOD_NO_AUTH', 0);
define('METHOD_GSSAPI', 1);
define('METHOD_USER_PASS', 2);


$worker = new Worker('tcp://0.0.0.0:1090');
$worker->onConnect = function ($connection) {
    var_dump('connect');
    $connection->stage = STAGE_INIT;
};

$worker->onMessage = function ($connection, $msg) {
    global $PROXY_USERNAME,$PROXY_PASS;
    var_dump('onMessage');
    var_dump($connection->stage);
    switch ($connection->stage) {
        case STAGE_INIT://协商版本以及验证方式
            write_log('协商版本以及验证方式');
            /**
             +---------+-----------------+------------------+
            |协议版本  | 支持的验证式数量   | 验证方式          |
            +---------+-----------------+------------------+
            |1个字节   | 1个字节          | 1种式占一个字节    |
            +---------+-----------------+------------------+
            |0x05     |0x02             |0x00,0x02         |
            +---------+-----------------+------------------+
             */
            $methods = [];
            $msgLength = count($msg);
            if($msgLength<3){
                echo "客户端协议版本或者验证方式有误，必须使用用户名、密码方式验证";
            }
            for ($i = 2; $i < strlen($msg); $i++) {
                write_log(ord($msg[$i]));
                $methods[] = ord($msg[$i]);
            }
            if (in_array(METHOD_USER_PASS, $methods)) {
                /**
                0x00 无验证需求
                0x01 通用安全服务应用程序接口(GSSAPI)
                0x02 用户名/密码(USERNAME/PASSWORD)
                0x03 至 X’7F’ IANA 分配(IANA ASSIGNED)
                0x80 至 X’FE’ 私人方法保留(RESERVED FOR PRIVATE METHODS)
                0xFF 无可接受方法(NO ACCEPTABLE METHODS)
                 */
                $connection->send("\x05\x02");//我们使用用户名密码验证方式
                $connection->stage = STAGE_AUTH;
                return;
            }else{
                echo "客户端不支持用户名密码的验证方式";
                $connection->send("\x05\xff");
                $connection->stage = STAGE_DESTROYED;
                $connection->close();
            }

            break;
        case STAGE_AUTH://验证账号密码
            write_log('验证账号名密码。');
            write_log($msg);
            /**使用账号密码验证协议
             * +--------+-----------+-------------------------+---------+-------------------------------+
            |协议版本 | 用户名长度  |用户名                    | 密码长度 |  密码                          |
            +--------+-----------+-------------------------+---------+-------------------------------+
            |1个字节  | 1个字节    |用户名字节数据             | 1个字节  |  密码字节数据                   |
            +--------+-----------+-------------------------+---------+-------------------------------+
            |0x01    | 0x05      |0x70,0x61,0x6b,0x6f,0x72 | 0x06    |  0x31,0x32,0x33,0x34,0x35,0x36|
            +--------+-----------+-------------------------+---------+-------------------------------+
             */
            $usernameLength = ord($msg[1]);
            write_log($usernameLength);
            $username = '';
            for($i = 2;$i<$usernameLength+2;$i++){
                $username.=$msg[$i];
            }


    }


};

Worker::runAll();