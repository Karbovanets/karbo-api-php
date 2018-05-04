<?php namespace Karbo;

class RPCClient
{

  private $address = '';
  private $service_host = null;
  private $service_port = null;
  private $service_port_ssl = false;
  private $service_type = null;
  private $id_connection = '';
  private $wd_offset = null;

  const RPC_HOST = '127.0.0.1';
  const RPC_SW_PORT = '15000';
  const RPC_WD_PORT = '8888';
  const RPC_V = '2.0';
  const RPC_TIMER = 50000;
  const WD_OFFSET = 100;
  const DECIMAL_POINT = 12;
  const PREC_POINT = 4;
  const ID_CONN = 'EWF8aIFX0y9w';
  const KRB_FEE = 100000000;
  const KRB_MIXIN = 0;
  const TX_CONF = 6;


  public function __construct($address, $rpc_host = '', $rpc_port = '', $rpc_ssl = false, $service_type = null, $id_connection = ''){

    $this->address = $address;
    $this->service_host = self::RPC_HOST;
    $this->service_port = self::RPC_SW_PORT;
    $this->service_type = 0;
    $this->id_connection = self::ID_CONN;
    $this->tx_conf = self::TX_CONF;
    $this->wd_offset = self::WD_OFFSET;
    if ($service_type != null){
      if ($service_type == 0){
        $this->service_type = 0;
        $this->service_host = self::RPC_SW_PORT;
      }
      if ($service_type == 1 or $service_type == 2){
        $this->service_type = $service_type;
        $this->service_host = self::RPC_WD_PORT;
      }
    }
    if ($rpc_host != "" and $rpc_port != ""){
      $this->service_host = $rpc_host;
      $this->service_port = $rpc_port;
    }
    if ($rpc_ssl) $this->service_port_ssl = true;
    if ($id_connection != '') $this->id_connection = $id_connection;

    return true;
  }

  private function apiCall($req){
    static $ch = null;
    if ($this->service_port_ssl){
      $url = 'https://';
      } else {
      $url = 'http://';
    }
    $url .= $this->service_host . ':' . $this->service_port . '/json_rpc';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    usleep(self::RPC_TIMER);
    $res = curl_exec($ch);
    if(curl_errno($ch) > 0){
      curl_close($ch);
      //echo 'error connection';
      return false;
      } else {
      curl_close($ch);
      $result = json_decode($res, true);
      //print_r($result);
      if($result != NULL){
        if(!isset($result['error'])){
          return $result;
        }
      }
      //echo 'internal error';
      return false;
    }
  }

  private function BalanceFormat($balance_src, $mode){
    $balance_res = 0;
    if ($balance_src > 0){
      if ($mode){
        $balance_res = round($balance_src * pow(10, self::DECIMAL_POINT), 0);
        } else {
        $balance_res = round($balance_src / pow(10, self::DECIMAL_POINT), self::PREC_POINT);
      }
    }
    return $balance_res;
  }

  public function WDgetStatus(){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'getStatus';
    $result = array();
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          $result['status'] = true;
          if (isset($data['result']['blockCount'])){
            if ($data['result']['blockCount'] > 0){
              $result['status'] = true;
              $result['height'] = $data['result']['blockCount'];
              $result['lastHash'] = $data['result']['lastBlockHash'];
            }
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function WDgetBalance($address){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'getBalance';
    $args['params']['address'] = $address;
    $result = array();
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          $result['status'] = true;
          if (isset($data['result']['availableBalance'])){
            $result['status'] = true;
            $result['availableBalance'] = $this->BalanceFormat($data['result']['availableBalance'], false);
            $result['lockedAmount'] = $this->BalanceFormat($data['result']['lockedAmount'], false);
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function WDgetUnconfirmedTransaction($address){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'getUnconfirmedTransactionHashes';
    $args['params']['address'] = $address;
    $result = array();
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          if (isset($data['result']['transactionHashes'])){
            $transactionHashes = array();
            $transactionHashes = $data['result']['transactionHashes'];
            if (count($transactionHashes) > 0){
              $result['status'] = true;
              $result['transactionHashes'] = $transactionHashes;
            }
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function WDgetTransactions($address, $paymentId = ''){
    $status_arr = $this->WDgetStatus();
    $height = $status_arr['height'];
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'getTransactions';
    $args['params']['blockCount'] = $this->wd_offset;
    $args['params']['firstBlockIndex'] = $height - $this->wd_offset;
    $args['params']['addresses'][0] = $address;
    $args['params']['paymentId'] = $paymentId;
    $result = array();
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          $result['status'] = true;
          if (isset($data['result']['items'])){
            $blockArr = array();
            $blockArr = $data['result']['items'];
            //print_r($blockArr);
            $transfer_n = 0;
            foreach ($blockArr as $block){
              if(count($block['transactions']) > 0){
                $transactions = $block['transactions'];
                foreach ($transactions as $transaction){
                  //print_r($transaction);
                  if ($paymentId == '' or $paymentId == $transaction['paymentId']){
                    $result['transaction'][$transfer_n]['paymentId'] = $transaction['paymentId'];
                    $result['transaction'][$transfer_n]['blockIndex'] = $transaction['blockIndex'];
                    $result['transaction'][$transfer_n]['transactionHash'] = $transaction['transactionHash'];
                    $transfers = array();
                    $transfers = $transaction['transfers'];
                    foreach ($transfers as $transfer){
                      if ($transfer['address'] == $address){
                        $result['transaction'][$transfer_n]['amount'] = $this->BalanceFormat($transfer['amount'], false);
                      }
                    }
                    $transfer_n++;
                  } 
                }
              }
            }
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function WDgetTransaction($hash){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'getTransaction';
    $args['params']['transactionHash'] = $hash;
    $result = array();
    $data = $this->apiCall($args);
    $result['status'] = false;
    print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          if (isset($data['result']['transaction'])){
            $result['status'] = true;
            $result['amount'] = $data['result']['transaction']['amount'];
            $result['paymentId'] = $data['result']['transaction']['paymentId'];
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function SWgetHeight(){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'get_height';
    $result = array();
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          $result['status'] = true;
          if (isset($data['result']['height'])){
            $result['height'] = $data['result']['height'];
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function SWgetBalance(){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'getbalance';
    $result = array();
    $result['available_balance'] = 0;
    $result['locked_amount'] = 0;
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          $result['status'] = true;
          if (isset($data['result']['available_balance'])){
            $result['available_balance'] = $this->BalanceFormat($data['result']['available_balance'], false);
          }
          if (isset($data['result']['locked_amount'])){
            $result['locked_amount'] = $this->BalanceFormat($data['result']['locked_amount'], false);
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function SWgetPayments($payment_id){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'get_payments';
    $args['params']['payment_id'] = $payment_id;
    $payments = array();
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          $result['status'] = true;
          if (isset($data['result']['payments'])){
            $payments = array();
            $payments = $data['result']['payments'];
            $payment_n = 0;
            if (count($payments) > 0){
              foreach ($payments as $payment){
                if (isset($payment['amount'])){
                  $result['payment'][$payment_n]['amount'] = $this->BalanceFormat($payment['amount'], false);
                }
                if (isset($payment['block_height'])){
                  $result['payment'][$payment_n]['height'] = $payment['block_height'];
                }
                if (isset($payment['tx_hash'])){
                  $result['payment'][$payment_n]['tx_hash'] = $payment['tx_hash'];
                }
                $payment_n++;
              }
            }
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function SWgetTransfers(){
    $args = array();
    $args['jsonrpc'] = self::RPC_V;
    $args['id'] = $this->id_connection;
    $args['method'] = 'get_transfers';
    $data = $this->apiCall($args);
    $result['status'] = false;
    //print_r($data);
    if (!$data === false){
      if (isset($data['id'])){
        if ($data['id'] == $this->id_connection){
          $result['status'] = true;
          if (isset($data['result']['transfers'])){
            $transfers = array();
            $transfers = $data['result']['transfers'];
            if (count($transfers) > 0){
              $transfer_n = 0;
              foreach ($transfers as $transfer){
                if (isset($transfer['amount'])){
                  $result['transfer'][$transfer_n]['amount'] = $this->BalanceFormat($transfer['amount'], false);
                }
                if (isset($transfer['transactionHash'])){
                  $result['transfer'][$transfer_n]['transactionHash'] = $transfer['transactionHash'];
                }
                if (isset($transfer['time'])){
                  $result['transfer'][$transfer_n]['time'] = $transfer['time'];
                }
                if (isset($transfer['output'])){
                  $result['transfer'][$transfer_n]['output'] = $transfer['output'];
                }
                $result['transfer'][$transfer_n]['paymentId'] = '';
                if (isset($transfer['paymentId'])){
                  if ($transfer['paymentId'] != ''){
                    $result['transfer'][$transfer_n]['paymentId'] = $transfer['paymentId'];
                  }
                }
                $result['transfer'][$transfer_n]['address'] = '';
                if (isset($transfer['address'])){
                  if ($transfer['address'] != ''){
                    $result['transfer'][$transfer_n]['address'] = $transfer['address'];
                  }
                }
                $transfer_n++;
              }
            }
          }
        }
      }
      return $result;
    }
    return false;
  }

  public function setTxConf($tx_conf){
    $this->tx_conf = $tx_conf;
  }

  public function setOffsetWD($offset){
    $this->wd_offset = $offset;
  }

  public function getStatus(){
    $data = array();
    $result = array();
    $result['status'] = false;
    $result['height'] = 0;
    $result['lastHash'] = '';
    if ($this->service_type == 0){
      $data = $this->SWgetHeight();
      if ($data !== false){
        if ($data['status'] == true){
          if (isset($data['height'])){
            $result['status'] = true;
            $result['height'] = $data['height'];
          }
        }
      }
    }
    if ($this->service_type == 1 or $this->service_type == 2){
      $data = $this->WDgetStatus();
      if ($data !== false){
        if ($data['status'] == true){
          if (isset($data['height'])){
            $result['status'] = true;
            $result['height'] = $data['height'];
          }
          if (isset($data['lastHash'])){
            $result['lastHash'] = $data['lastHash'];
          }
        }
      }
    }
    return $result;
  }

  public function getStatusPayment($payment_id){
    $result = array();
    $payment = array();
    $payment_trig = false;
    $service_status = $this->getStatus();
    $result['status'] = false;
    $result['status_txt'] = 'Unconfirmed';
    $result['amount'] = 0;
    $result['height'] = 0;
    $result['tx_conf'] = 0;
    $result['tx_hash'] = '';
    $result['payment_id'] = '';
    $result['address'] = '';
    if ($this->service_type == 0 and $service_status['status'] == true){
      $payment = $this->SWgetPayments($payment_id);
      if ($payment !== false){
        if ($payment['status'] == true){
          if (isset($payment['payment'])){
            if (count($payment['payment']) == 1){
              $tx_conf_now = $service_status['height'] - $payment['payment'][0]['height'] + 1;
              if ($tx_conf_now >= $this->tx_conf){
                $result['status'] = true;
                $result['status_txt'] = 'Confirmed';
                $result['tx_conf'] = $tx_conf_now;
                } else {
                $result['status'] = false;
                $result['status_txt'] = 'Unconfirmed';
                $result['tx_conf'] = $tx_conf_now;
              }
              $result['amount'] = $payment['payment'][0]['amount'];
              $result['height'] = $payment['payment'][0]['height'];
              $result['tx_hash'] = $payment['payment'][0]['tx_hash'];
              $result['payment_id'] = $payment_id;
              $result['address'] = $this->address;
            }
          }
        }
        $payment_trig = true;
      }
    }
    if (($this->service_type == 1 or $this->service_type == 2) and $service_status['status'] == true){
      $payment = $this->WDgetTransactions($this->address, $payment_id);
      if ($payment !== false){
        if ($payment['status'] == true){
          if (isset($payment['transaction'])){
            if (count($payment['transaction']) == 1){
              $tx_conf_now = $service_status['height'] - $payment['transaction'][0]['blockIndex'];
              if ($tx_conf_now >= $this->tx_conf){
                $result['status'] = true;
                $result['status_txt'] = 'Confirmed';
                $result['tx_conf'] = $tx_conf_now;
                } else {
                $result['status'] = false;
                $result['status_txt'] = 'Unconfirmed';
                $result['tx_conf'] = $tx_conf_now;
              }
              $result['amount'] = $payment['transaction'][0]['amount'];
              $result['height'] = $payment['transaction'][0]['blockIndex'];
              $result['tx_hash'] = $payment['transaction'][0]['transactionHash'];
              $result['payment_id'] = $payment['transaction'][0]['paymentId'];
              $result['address'] = $this->address;
            }
          }
        }
        $payment_trig = true;
      }
    }
    if ($payment_trig) return $result;
    return false;
  }

  public static function genPaymentId(){
    $buff = '';
    $buff = bin2hex(openssl_random_pseudo_bytes(32));
    return $buff;
  }

}

?>
