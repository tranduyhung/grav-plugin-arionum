<?php
namespace Grav\Plugin\Arionum;

use Grav\Common\Grav;

class GravArionum
{
  public $grav;

  public $response;

  public function __construct(Grav $grav)
  {
    $this->grav = $grav;
    $this->response = [
      'success' => true,
      'message' => '',
      'data' => ''
    ];
  }

  public function response($success = true, $message = '', $data = null)
  {
    $this->response = [
      'success' => $success,
      'message' => $message,
      'data' => $data
    ];

    echo json_encode($this->response);
    die;
  }

  public function getExchanges()
  {
    $lang = $this->grav['language'];
    $cfgCurrencies = $this->grav['config']->get('plugins.arionum.currencies', []);
    $currencies = [];

    if (count($cfgCurrencies) > 0)
    {
      foreach ($cfgCurrencies as $key => $value)
      {
        if ($value == 1)
          $currencies[] = $key;
      }
    }

    $url = 'https://mercatox.com/public/json24';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($response, true);

    if (!isset($response['pairs']) || !isset($response['pairs']['ARO_BTC']))
    {
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.EXCHANGE_CONNECTION_FAILED'));
    }

    $btcValue = $response['pairs']['ARO_BTC']['last'];
    $data = [];

    $data[] = [
      'currency' => 'BTC',
      'value' => $btcValue,
    ];

    if (in_array('ETH', $currencies))
    {
      $data[] = [
        'currency' => 'ETH',
        'value' => $response['pairs']['ARO_ETH']['last'],
      ];
    }

    if (count($currencies) == 0)
      $this->response(true, '', $data);

    $url = 'https://blockchain.info/ticker';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($response, true);

    if (empty($response))
    {
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.FIAT_CONNECTION_FAILED'));
    }

    foreach ($response as $key => $values)
    {
      if (in_array($key, $currencies))
      {
        $data[] = [
          'currency' => $key,
          'value' => round($btcValue * $values['last'], 6),
        ];
      }
    }

    $this->response(true, '', $data);
  }

  public function getBalance($address)
  {
    $lang = $this->grav['language'];

    if (!$this->isAddressValid($address))
    {
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.INVALID_ADDRESS'));
    }

    $response = $this->connect('/api.php?q=getPendingBalance', ['account' => $address]);

    if ($response === false)
    {
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.ARIONUM_CONNECTION_FAILED'));
    }
    elseif ($response['status'] != 'ok')
    {
      $this->response(false, $response['data']);
    }
    else
    {
      $this->response(true, null, $response['data']);
    }
  }

  public function getTransactions($address)
  {
    $grav = $this->grav;
    $lang = $grav['language'];

    if (!$this->isAddressValid($address))
    {
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.INVALID_ADDRESS'));
    }

    $response = $this->connect('/api.php?q=getTransactions', ['account' => $address]);

    if ($response === false)
    {
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.ARIONUM_CONNECTION_FAILED'));
    }
    elseif ($response['status'] != 'ok')
    {
      $this->response(false, $response['data']);
    }
    else
    {
      $transactions = [];
      $dateFormat = $grav['config']->get('system.pages.dateformat.long');

      foreach ($response['data'] as $transaction)
      {
        $type = ($transaction['dst'] == $address) ? $lang->translate('PLUGIN_ARIONUM.RECEIVE') : $lang->translate('PLUGIN_ARIONUM.SEND');

        $transactions[] = [
          'id' => $transaction['id'],
          'type' => $type,
          'date' => date($dateFormat, $transaction['date']),
          'value' => $transaction['val'],
        ];
      }

      $this->response(true, null, $transactions);
    }
  }

  public function getTransaction($transactionId)
  {
    $grav = $this->grav;
    $lang = $grav['language'];

    if ($transactionId == '')
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.INVALID_TRANSACTION_ID'));

    $response = $this->connect('/api.php?q=getTransaction', ['transaction' => $transactionId]);

    if ($response === false)
    {
      $this->response(false, $lang->translate('PLUGIN_ARIONUM.ARIONUM_CONNECTION_FAILED'));
    }
    elseif ($response['status'] != 'ok')
    {
      $this->response(false, $response['data']);
    }
    else
    {
      $data = [];
      $dateFormat = $grav['config']->get('system.pages.dateformat.long');

      foreach ($response['data'] as $key => $value)
      {
        if ($key == 'date')
        {
          $value = date($dateFormat, $value);
        }

        $data[] = [
          'name' => $lang->translate('PLUGIN_ARIONUM.TRANSACTION_' . strtoupper($key)),
          'value' => $value
        ];
      }

      $this->response(true, null, $data);
    }
  }

  public function isAddressValid($address)
  {
    return preg_match('/^[a-z0-9]+$/i', $address);
  }

  public function connect($query, $data = [])
  {
    $peers = file('http://api.arionum.com/peers.txt');
    shuffle($peers);

    foreach($peers as $p)
    {
      if (strlen(trim($p)) > 5)
      {
        $peer = trim($p);
        break;
      }
    }

    if (empty($peer))
      return false;

    $url = $peer . $query;

    $postData = http_build_query([
      'data' => json_encode($data),
      'coin' => 'arionum'
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($response, true);

    return $response;
  }
}
