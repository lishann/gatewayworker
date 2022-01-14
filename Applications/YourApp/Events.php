<?php

use Workerman\Timer;
use \GatewayWorker\Lib\Gateway;
use Workerman\Redis\Client;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class Events
{

  public static $connections  = [];
  /**
   * Undocumented function
   *当客户端连接上gateway进程时(TCP三次握手完毕时)触发的回调函数。
   * @param [type] $client_id
   * @return void
   */
  public static function onConnect($client_id)
  {
    self::$connections[$client_id] = $client_id;
    // 向当前client_id发送数据 
    // Gateway::sendToClient($client_id, "Hello $client_id\r\n");
    // // 向所有人发送
    // Gateway::sendToAll("$client_id login\r\n");
  }

  /**
   * Undocumented function
   *当客户端连接上gateway完成websocket握手时触发的回调函数。
   * @param [type] $client_id
   * @param [type] $data
   * @return void
   */
  public static function onWebSocketConnect($client_id, $data)
  {
    if (isset($data['get']['uid'])) {
      Gateway::bindUid($client_id, $data['get']['uid']);
      self::$connections['uid'][] = $data['get']['uid'];
    } else {
      var_dump("请传递uid");
    }
    // var_dump($client_id);
    // var_dump($data['get']['uid']);
    // if (!isset($data['get']['token'])) {
    //      Gateway::closeClient($client_id);
    // }
  }

  /**
   * Undocumented function
   *当客户端发来数据(Gateway进程收到数据)后触发的回调函数
   * @param [type] $client_id
   * @param [type] $recv_data
   * @return void
   */
  public static function onMessage($connection, $data)
  {
    // var_dump($connection . $data . '：' . date("H:i:s", time()));
    // if ($data != null) {
    //   self::$connections['connection'][] = time();
    //   //self::$connections['connection'][] = $data;
    // }
  }

  /**
   * Undocumented function
   * 当businessWorker进程启动时触发。每个进程生命周期内都只会触发一次。
   * @param [type] $worker
   * @return void
   */
  public static function onWorkerStart($worker)
  {

    $redis = new Client('redis://127.0.0.1:6379');
    Timer::add(1, function () use ($redis) {
      if (isset(self::$connections['uid'])) {
        // var_dump(self::$connections['uid']);
        foreach (self::$connections['uid'] as $k => $v) {
          //判断$uid是否在线
          // 如果某uid绑定的client_id都已经下线，那么对该uid调用Gateway::isUidOnline($uid)将返回0。
          //如果某uid绑定的client_id有至少有一个在线，那么对该uid调用 Gateway::isUidOnline($uid)将返回1。
          $isOnline  = Gateway::isUidOnline($v);
          if ($isOnline != 0) {
            $redis->lPop("$v", function ($result) use ($v) {
              if ($result != null) {
                $result = json_encode(unserialize($result), JSON_UNESCAPED_UNICODE);
                Gateway::sendToUid($v, $result);
              } else {
                var_dump("没有获取到数据");
              }
            });
          }
        }
      }
    });
  }

  /**
   * Undocumented function
   *客户端与Gateway进程的连接断开时触发。不管是客户端主动断开还是服务端主动断开，都会触发这个回调。一般在这里做一些数据清理工作。
   * @param [type] $client_id
   * @return void
   */
  public static function onClose($client_id)
  {
    echo " 心跳停止\n";
    echo " 用户退出\n";
    if (isset(self::$connections[$client_id])) {
      unset(self::$connections[$client_id]);
    }
  }
}
