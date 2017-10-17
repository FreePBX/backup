<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
include '/etc/freepbx.conf';
include __DIR__.'/../Handlers/Status.php';
$server = IoServer::factory(
  new HttpServer(
      new WsServer(
          new FreePBX\modules\Backup\Handlers\Status()
      )
  ),
  9999
  );

$server->run();
