<?php

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Connection\AsyncTcpConnection;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/config.php';


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
    global $PROXY_USERNAME, $PROXY_PASS;
    var_dump('onMessage');
    var_dump($connection->stage);
    switch ($connection->stage) {
        case STAGE_INIT://协商版本以及验证方式
            write_log('协商版本以及验证方式');
            /**
             * +---------+-----------------+------------------+
             * |协议版本  | 支持的验证式数量   | 验证方式          |
             * +---------+-----------------+------------------+
             * |1个字节   | 1个字节          | 1种式占一个字节    |
             * +---------+-----------------+------------------+
             * |0x05     |0x02             |0x00,0x02         |
             * +---------+-----------------+------------------+
             */
            $methods = [];
            $msgLength = count($msg);
            if ($msgLength < 3) {
                echo "客户端协议版本或者验证方式有误，必须使用用户名、密码方式验证";
            }
            for ($i = 2; $i < strlen($msg); $i++) {
                write_log(ord($msg[$i]));
                $methods[] = ord($msg[$i]);
            }
            if (in_array(METHOD_USER_PASS, $methods)) {
                /**
                 * 0x00 无验证需求
                 * 0x01 通用安全服务应用程序接口(GSSAPI)
                 * 0x02 用户名/密码(USERNAME/PASSWORD)
                 * 0x03 至 X’7F’ IANA 分配(IANA ASSIGNED)
                 * 0x80 至 X’FE’ 私人方法保留(RESERVED FOR PRIVATE METHODS)
                 * 0xFF 无可接受方法(NO ACCEPTABLE METHODS)
                 */
                $connection->send("\x05\x02");//我们使用用户名密码验证方式
                $connection->stage = STAGE_AUTH;
                return;
            } else {
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
             * |协议版本 | 用户名长度  |用户名                    | 密码长度 |  密码                          |
             * +--------+-----------+-------------------------+---------+-------------------------------+
             * |1个字节  | 1个字节    |用户名字节数据             | 1个字节  |  密码字节数据                   |
             * +--------+-----------+-------------------------+---------+-------------------------------+
             * |0x01    | 0x05      |0x70,0x61,0x6b,0x6f,0x72 | 0x06    |  0x31,0x32,0x33,0x34,0x35,0x36|
             * +--------+-----------+-------------------------+---------+-------------------------------+
             */
            $usernameLength = ord($msg[1]);
            write_log('用户名长度：' . $usernameLength);
            //解析用户名
            $username = '';
            for ($i = 2; $i < $usernameLength + 2; $i++) {
                $username .= ($msg[$i]);
            }
            write_log('用户名：' . $username);
            //解析密码
            $password = '';
            $passLength = ord($msg[$usernameLength + 2]);
            write_log('密码长度：' . $passLength);
            $msgRev = strrev($msg);
            for ($i = 0; $i < $passLength; $i++) {
                $password .= ($msgRev[$i]);
            }
            $password = strrev($password);
            write_log('密码：' . $password);
            if ($username == $PROXY_USERNAME && $password == $PROXY_PASS) {
                //返回正确报文
                $connection->send("\x05\x00");
                $connection->stage = STAGE_ADDR;
            } else {
                //校验失败，返回错误信息并关闭连接。
                $connection->send("\x05\x01");
                $connection->stage = STAGE_DESTROYED;
                $connection->close();
            }
            break;
        case STAGE_ADDR://建立代理连接
            write_log('建立代理连接');
            write_log($msg);
            /**建立连接数据报文
             * +----------+------------+---------+-----------+-----------------------+------------+
             * |协议版本号  | 请求的类型  |保留字段   |  地址类型  |  地址数据              |  地址端口    |
             * +----------+------------+---------+-----------+-----------------------+------------+
             * |1个字节    | 1个字节     |1个字节   |  1个字节   |  变长                  |  2个字节    |
             * +----------+------------+---------+-----------+-----------------------+------------+
             * |0x05      | 0x01       |0x00     |  0x01     |  0x0a,0x00,0x01,0x0a  |  0x00,0x50 |
             * +----------+------------+---------+-----------+-----------------------+------------+
             */
            /**请求类型有三种，我们这里只使用connect。
             * CONNECT : 0x01, 建立代理连接
             * BIND : 0x02,告诉代理服务器监听目标机器的连接,也就是让代理服务器创建socket监听来自目标机器的连接。FTP这类需要服务端主动联接客户端的的应用场景。
             * 1. 只有在完成了connnect操作之后才能进行bind操作
             * 2. bind操作之后，代理服务器会有两次响应, 第一次响应是在创建socket监听完成之后，第二次是在目标机器连接到代理服务器上之后。
             * UDP ASSOCIATE : 0x03, udp 协议请求代理。
             */
            $requestType = ord($msg[1]);
            if (CMD_CONNECT !== $requestType) {
                var_dump('非法的请求类型');
                $connection->send("\x05\x07");
                $connection->stage = STAGE_DESTROYED;
                $connection->close();
                return;
            }
        //提取需要访问的地址
            $headerData = parse_socks5_header($msg);
            if(!$headerData){
                write_log('提取代理地址失败');
                $connection->close();
            }
            //数据包转发
            /**
             +----+------+------+----------+----------+----------+
            |RSV | FRAG | ATYP | DST.ADDR | DST.PORT |   DATA   |
            +----+------+------+----------+----------+----------+
            | 2  |  1   |  1   | Variable |    2     | Variable |
            +----+------+------+----------+----------+----------+
             */
            $remote_connection = new AsyncTcpConnection('tcp://'.$headerData[1].':'.$headerData[2]);
            $remote_connection->onConnect = function($remote_connection)use($connection)
            {
                $connection->state = STAGE_STREAM;
                $connection->send("\x05\x00\x00\x01\x00\x00\x00\x00\x10\x10");
                $connection->pipe($remote_connection);//将当前连接的流量导向workerman代理。
                $remote_connection->pipe($connection);//将workerman代理返回的数据导向客户连接
            };
            $remote_connection->connect();

    }


};

function parse_socks5_header($buffer) {
    $addr_type = ord($buffer[3]);
    switch ($addr_type) {
        case ADDRTYPE_IPV4:
            if (strlen($buffer) < 10) {
                echo bin2hex($buffer) . "\n";
                echo "buffer too short\n";
                return false;
            }
            $dest_addr = ord($buffer[4]) . '.' . ord($buffer[5]) . '.' . ord($buffer[6]) . '.' . ord($buffer[7]);
            $port_data = unpack('n', substr($buffer, -2));
            $dest_port = $port_data[1];
            $header_length = 10;
            break;
        case ADDRTYPE_HOST:
            $addrlen = ord($buffer[4]);
            if (strlen($buffer) < $addrlen + 5) {
                echo $buffer . "\n";
                echo bin2hex($buffer) . "\n";
                echo "buffer too short\n";
                return false;
            }
            $dest_addr = substr($buffer, 5, $addrlen);
            $port_data = unpack('n', substr($buffer, -2));
            $dest_port = $port_data[1];
            $header_length = $addrlen + 7;
            break;
        case ADDRTYPE_IPV6:
            if (strlen($buffer) < 22) {
                echo "buffer too short\n";
                return false;
            }
            echo "todo ipv6\n";
            return false;
        default:
            echo "unsupported addrtype $addr_type\n";
            return false;
    }
    return array($addr_type, $dest_addr, $dest_port, $header_length);
}


Worker::runAll();