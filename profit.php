<?php

include 'configs.php';

function requestHive($url, $data = [])
{
  $url = str_replace('FARM_ID', FARM_ID, $url);
  $ch = curl_init('https://api2.hiveos.farm/api/v2' . $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 1200);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . API_KEY
  ]);

  if ($data) {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  }

  $return = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $returnJson = json_decode($return, true);

  return [
    'status'    => $status,
    'response'  => $returnJson
  ];
}

function request($url)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

  $return = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return [
    'status'    => $status,
    'response'  => $return
  ];
}

function main()
{
  if (!API_KEY) {
    echo 'API Key not set.';
    return;
  }

  if (!FARM_ID) {
    echo 'Farm id not set.';
    return;
  }

  $requestWorkers = requestHive('/farms/FARM_ID/workers');

  if ($requestWorkers['status'] != 200) {
    echo 'Error in communication with HiveOS Workers.';
    return;
  }

  $algoMap = ['AUTOLYKOS' => 'al_p', 'BEAMHASHIII' => 'eqb_p', 'CORTEX' => 'cx_p', 'CRYPTONIGHTFASTV2' => 'cnf_p', 'CRYPTONIGHTGPU' => 'cng_p', 'CRYPTONIGHTHAVEN' => 'cnh_p', 'CUCKAROO29S' => 'cr29_p', 'CUCKATOO31' => 'ct31_p', 'CUCKATOO32' => 'ct32_p', 'CUCKOOCYCLE' => 'cc_p', 'EQUIHASH (210,9)' => 'eqa_p', 'EQUIHASHZERO' => 'eqz_p', 'ETCHASH' => 'e4g_p', 'ETHASH' => 'eth_p', 'ETHASH4' => 'e4g_p', 'FIROPOW' => 'fpw_p', 'KAWPOW' => 'kpw_p', 'NEOSCRYPT' => 'ns_p', 'OCTOPUS' => 'ops_p', 'PROGPOW' => 'ppw_p', 'PROGPOWZ' => 'ppw_p', 'RANDOMX' => 'rmx_p', 'UBQHASH' => 'e4g_p', 'VERTHASH' => 'vh_p', 'X25X' => 'x25x_p', 'ZELHASH' => 'zlh_p', 'ZHASH' => 'zh_p'];

  foreach ($requestWorkers['response']['data'] as $worker) {
    $configKey = array_search($worker['name'], array_column(CONFIGS, 'name'));

    if ($configKey === false) {
      continue;
    }

    $config = CONFIGS[$configKey];
    $currentFs = $worker['flight_sheet'];
    $currentCoin = array_search($currentFs['name'], $config['coins']);

    if ($currentCoin === false) {
      continue;
    }

    $whatToMineEndPoint = $config['endpoint'];
    parse_str($whatToMineEndPoint, $params);
    $factor = $params['factor'];
    $powerPrice = $factor['cost'];

    $requestBtc = request('https://api.coindesk.com/v1/bpi/currentprice.json');

    if ($requestBtc['status'] != 200) {
      echo 'Error when trying to get BTC price.';
      return;
    }

    $btcPrice = json_decode($requestBtc['response'], true)['bpi']['USD']['rate_float'] ?? 0.01;

    $requestWTM = request($config['endpoint']);

    if ($requestWTM['status'] != 200) {
      echo 'Error in communication with WhatToMine.';
      return;
    }

    $whatToMine = json_decode($requestWTM['response'], true);

    $coinsProfit = [];
    foreach ($whatToMine['coins'] as $coin) {
      $btcRevenue = $coin['btc_revenue24'] ?? 0.00;
      $algo = strtoupper($coin['algorithm']);

      if ($algo == 'ETHASH' && !in_array($coin['tag'], ['ETH', 'NICEHASH'])) {
        $algo = 'ETHASH4';
      }

      $consumption = $factor[$algoMap[$algo]] ?? 0.00;

      $dailyPowerCost = 24 * ($consumption / 1000) * $powerPrice;
      $dailyRevenue = $btcRevenue * $btcPrice;
      $dailyProfit = $dailyRevenue - $dailyPowerCost;

      $key = $coin['tag'] == 'NICEHASH' ? $coin['tag'] . '-' . $algo : $coin['tag'];
      $coinsProfit[$key] = $dailyProfit;
    }

    arsort($coinsProfit);
    $bestCoinPrice = reset($coinsProfit);
    $bestCoin = key($coinsProfit);

    $currentCoinPrice = $coinsProfit[$currentCoin] ?? 0;
    $currentCoinPrice += $currentCoinPrice * (COIN_DIFFERENCE / 100);

    if ($bestCoin == $currentCoin || $currentCoinPrice > $bestCoinPrice) {
      echo 'Already in best coin.';
      return;
    }

    $newFsId = null;
    $newFsName = $config['coins'][$bestCoin] ?? null;

    if (!$newFsName) {
      echo 'flight Sheet for ' . $bestCoin . ' not configured.';
      return;
    }

    $requestFs = requestHive('/farms/FARM_ID/fs');

    if ($requestFs['status'] != 200) {
      echo 'Error in communication with HiveOS fs.';
      return;
    }

    foreach ($requestFs['response']['data'] as $sheet) {
      if (isset($sheet['name']) && $sheet['name'] == $newFsName) {
        $newFsId = $sheet['id'];
        break;
      }
    }

    if (!$newFsId) {
      echo 'flight Sheet not found.';
      return;
    }

    $updateFs = requestHive('/farms/FARM_ID/workers/' . $worker['id'], ['fs_id' => $newFsId]);

    if ($updateFs['status'] != 200) {
      echo 'Error when trying to update flight sheet.';
      return;
    }

    echo 'flight Sheet updated to ' . $newFsName . '. Estimated profit: $' . $bestCoinPrice;
  }
}

main();
