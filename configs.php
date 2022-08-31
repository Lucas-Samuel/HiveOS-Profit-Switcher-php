<?php

// Your HiveOS api key
const API_KEY = '';

// ID of your farm in HiveOS
const FARM_ID = '';

// How much percent the new coin should be more profitable
const COIN_DIFFERENCE = 5;

/*
  Yours workers configs.
  [
    'name' => 'The name of worker exactly like in HiveOS.',
    'endpoint' => 'Your whattomine json with selected coins.',
    'coins' => [
      'coin ticker' => 'flight sheet name',
    ],
  ],
*/
const CONFIGS = [
  [
    'name' => 'workerName',
    'endpoint' => 'https://whattomine.com/coins.json?...',
    'coins' => [
      'ETH' => 'ETH Flight sheet',
    ],
  ],
];
