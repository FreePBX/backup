<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Handlers;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Status implements MessageComponentInterface{
  public function __construct(){
    $this->conn = null;
    $this->clients = new \SplObjectStorage;
  }
  public function onOpen(ConnectionInterface $conn) {
    $this->clients->attach($conn);
  }

  public function onMessage(ConnectionInterface $from, $msg) {
  }

  public function onClose(ConnectionInterface $conn) {
  }

  public function onError(ConnectionInterface $conn, \Exception $e) {
  }

  public function messageHandler(){
    if(!empty($this->conn)){
      $message = \FreePBX::Hooks()->processHooks();
      dbug($message);
      foreach ($this->clients as $client) {
        $client->send($message[1]);
      }
    }
  }

}
