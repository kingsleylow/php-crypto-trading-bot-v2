<?php
//killl all php.  ps -efw | grep php | grep -v grep | awk '{print $2}' | xargs kill
require_once('init.php');
use Models\Exchanges\Bittrex\SignalR;


//register_shutdown_function('remove_pid_from_file',getmypid(),$bot_settings->all_pids_file);


$cli_args = [];
foreach($argv as $cli_setting){
    $temp = explode("=",$cli_setting);
    if(isset($temp[1])){
        $cli_args[$temp[0]] = $temp[1];
        
    }
}

$filesCls = new Models\BotComponent\FilesWork();

//put all php runtime wranings and erros into pid.log

$simulation_finished_trades = [];
$latest_scan_results=[];
$api;
$client;
print_r($cli_args);

//to web: i am working on this coins: cins array, with these starts: startegy latest scan score and status (2/3 2/4 etc..)


//["BNBBTC","NEOBTC","ETHBTC","XLMBTC","XRPBTC","ARKBTC","TUSDBTC","GASBTC","ONGBTC","LTCBTC","HOTBTC","STORJBTC","KMDBTC","MTLBTC","ADABTC"];
$count = 0;

$bot = new Models\BotComponent\Bot();
$cmnds = new Models\BotComponent\Commands();
//count is used to reload cinfig file from time to time, when count is 1000000, updates are bieng made;
if($GLOBALS['isSimulator']!==true){
$api->loop->addPeriodicTimer(60, function() use (&$bot,$api,$client) { //echk every 60 seconds if open signals waiting for purchase or sell are filled
    //maybe add also checkAndexecNewCommands in diffrent timer, this one runs only on real trade mode
    foreach($bot->signals as $strategy=>$signals){
        foreach($signals as $market_name=>$signal){
            if($signal['status']!=='active'){
				if(!isset($signal['orderId']) && isset($signal['uuid'])){
					$signal['orderId'] = $signal['uuid'];
					// i dont remeber if i use uuid or orderId and when :\
				}
				if($bot->xchnage === "binance"){
                $orderstatus = $api->orderStatus($market_name, $signal['orderId']);
				}
				else if($bot->xchnage === "bittrex"){
						$tmp = json_decode(json_encode($client->getOrder($signal['orderId'])), true);
					   $order['status'] =$tmp['Closed'];
					   if($order['status']!==null){
						    $orderstatus['orderStatus'] = "FILLED";
					   }
					   //$order['clientOrderId'] = $order['uuid'];
					//do the magic here.. check if order is closed and update accordingy
				}
				if(!isset($orderstatus['orderStatus'])){
					//unset($bot->signals[$strategy][$market_name]);
				}
				else{
					
				
                if($orderstatus['orderStatus'] === "FILLED"){
					if($bot->telegram!==null){
						$bot->telegram->sendMessage(" Order executed @  ".$bot->xchnage." Details: ".print_r($bot->signals[$strategy][$market_name]));						
					}

					
                    $bot->signals[$strategy][$market_name]['status'] = $bot->signals[$strategy][$market_name]['status'] === 'WaitingForPurchase' ? 'active' : 'finished';
                    //add here the actual price the trade ended and update into db the sell price, $final_price = $orderstatus['price']; $signal['status'] = $final_price; 
                    if($bot->signals[$strategy][$market_name]['status']==="active"){
                        //send update to db about signal status, $signal['status']="active";
                
                    }
                    else if($bot->signals[$strategy][$market_name]['status']==="finished"){
                        //send update to db before unsetting the var, $signal['status']="finished"
                        unset($bot->signals[$strategy][$market_name]);
                    }
					
					
                }
					}
                					
				


            }
        }
    }
});
}
var_dump($api);
if($bot->xchnage!=="bittrex" && $api->loop != null){
$api->loop->addPeriodicTimer(60*60, function() use (&$bot,$api) {
	global $filesCls;
	             //$api->loop->stop(); 
                //$bot->fillCoinsArr();
                //$filesCls->addContent("Reloaded coins (once an hour)");
}); //reload coins once an hour - for now only binance, bittrex start to run once more each stop, after 3 hours each rade is checked 3 times?
}
if($api->loop != null){
	$api->loop->addPeriodicTimer(2, function() use ($cmnds) { //echk every 60 seconds 
    
		$cmnds->checkAndexecNewCommands();
	});
}


if($bot->xchnage==="bittrex"){
	$api->loop->addPeriodicTimer(60,function() use (&$bot) {
		global $filesCls;
		$bot->fill_btrx_candles();
		$filesCls->addContent("Reloaded OHLVC for all bittrex coins");
		
	});
}
$empry_arr_count = 0;
while(true){
	$banned = false;
    //if backtesting mode
    if(isset($cli_args['--backtesting'])){
        $filesCls->addContent($colors->info("Starting backtesting on ".count($bot->coins_array)." coins ... "));
        //print_r($bot->coins_array);
        foreach($bot->coins_array as $coinName){//$coinName is market
			if($bot->xchnage==="binance"){
				$OHLVC = $api->candlesticks($coinName, $bot->timeframe,2000);
			}
            else if($bot->xchnage==="bittrex"){
				$OHLVC = $bot->btrx_candles[$coinName];
			}
            //print_r($OHLVC);
            //die();
            for($i=100;$i<count($OHLVC);$i++){
                $reply = $bot->checkOHLVCforSignals(array_slice($OHLVC,0,$i),$coinName);
                //print_r($reply);
            }
            $filesCls->addContent($colors->info("DONE: $coinName, candles checked:".count($OHLVC)));
        }
        $filesCls->addContent($colors->info("Finished backtesting on ".count($bot->coins_array)." coins ... Bye now."));
        die();
    }
    else{//if simulation or real trade mode
		if($bot->xchnage === "binance"){
		
    	$api->chart($bot->coins_array, $bot->timeframe, function($api, $symbol, $chart) use(&$count,$bot,&$settings,&$data,&$empry_arr_count,&$banned) {
        global $cli_args,$bot_settings,$filesCls,$cmnds,$api;
        $reply = $bot->checkOHLVCforSignals($chart,$symbol);
			if($reply===null){
				$empry_arr_count++;
			}
			else{
				if($banned){
					$pids_data = $filesCls->getPIDFileCon();
					$pids_data1 = json_decode($pids_data[getmypid()],true);
					unset($pids_data1['no_ws_connection']);
					$filesCls->register_pid_to_file(json_encode($pids_data1));						
				}
				$banned = false;
				$empry_arr_count = 0;
			}
			if($empry_arr_count > 20){
				$filesCls->addContent(" sleeping for 35 minutes, probaly banned from binance, 20+ empy arrays ... ");
				$empry_arr_count = 0;
				if($bot->telegram!==null){
					$bot->telegram->sendMessage(" sleeping for 5 minutes and then connecting again, probaly banned from binance, 20+ empy arrays ... ");
					
				}
				//die();//temp solution
	            $api->loop->stop(); 
				if(!$banned){
					$pids_data = $filesCls->getPIDFileCon();
					$pids_data1 = json_decode($pids_data[getmypid()],true);
					$pids_data1['no_ws_connection'] = "true";
					$filesCls->register_pid_to_file(json_encode($pids_data1));				
				}

				sleep(60*10);
                $bot->fillCoinsArr();
                $filesCls->addContent("Tried to reconnect");
			}
        print_r($reply);
        if($count>400){
        //if coin list has changed, stop loop and the restart it later in code to   update subscriptions;
            // stop bot, reload conf.json, reload coins etc..
            $active_coins = count($bot->coins_array);
            $filesCls->addContent("  still running (400 live trades proccessed, $active_coins active coins) ... ");
            //$settings = json_decode($data);//reload settings
            $count=0;
        
        }
        $count++;
        //echo $reply !== null ? $reply : "nohing to do here.. keep moving\n";
    },2000);
    	sleep(30); 			
		}
		else if($bot->xchnage === "bittrex"){
			
			
			$api->on("corehub", "updateSummaryState", function($data) {
				global $filesCls,$bot;
    			//print_r($data);
				//die();
				//print_r($bot->btrx_coins_format);
				$latest_trades_all_markets = $data->Deltas;
				$latest_trade_per_market = [];
				foreach($latest_trades_all_markets as $trade){
					if(isset($bot->btrx_coins_format[$trade->MarketName])){// check if the trade is in the bot coin list
						$latest_trade_per_market[$trade->MarketName] = $trade;		//check only the latest trade sent per cin.. old ones doesnt matter..			
					}
					
				}
				foreach($latest_trade_per_market as $market=>$trade){
					$tmp = $bot->to_market_format("binance",[$market]);
					$market = $tmp[0];
					$latest_trade = [];
					//print_r(array_keys($bot->btrx_candles));
					//die();
					if(isset($bot->btrx_candles[$market])){
						$bot->btrx_candles[$market][count($bot->btrx_candles[$market])-1]['close'] = $trade->Last;
						$reply = $bot->checkOHLVCforSignals($bot->btrx_candles[$market],$market);
        				print_r($reply);
					//die();					
					}
					else{
						echo "no candle array yet";
					}
					

					
				}
				//print_r($latest_trade_per_market);
				echo "Checked ".count($latest_trade_per_market)." Trades";
				$filesCls->addContent("Checked ".count($latest_trade_per_market)." Trades");
			});
			$api->run();
		}
      
    }

}
?>