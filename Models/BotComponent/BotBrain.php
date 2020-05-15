<?php
namespace Models\BotComponent;

class Bot {
    private $strategies = [];
    private $opens=[];
    private $closes=[];
    private $highs=[];
    private $lows=[];
    private $min_vol=700;
    private $signals_cls;
    private $strategies_cls;
    private $OHLVC=[];
    public $coins_array = [];
    private $ignore_coins =[];
    public $thisInstance = [];
    public $timeframe;
    public $simulator = true;
    private $colors = "";
	public $xchnage = "";
    public $simulation_finished_trades =[];
	public $btrx_candles;
	public $btrx_coins_format;
	public $sim_id;
	public $telegram=null;
    
    function __construct(){
        global $cli_args,$instances_on_start,$bot_settings,$filesCls,$colors,$on_the_fly_file,$api,$client,$user_settings;
        $this->signals_cls = new Models\Signals;
        $this->strategies_cls= new Models\Strategies;
		if(isset($user_settings->comm->telegram->bot_toekn) && isset($user_settings->comm->telegram->tele_user_id)){
			$this->telegram = new Models\Messaging\Telegram($user_settings->comm->telegram->bot_toekn,$user_settings->comm->telegram->tele_user_id);	
			
		}

        //$this->strategies = $this->strategies_cls->active_strategies;
        $this->colors = $colors;
        if(!isset($cli_args['--name'])){
            $filesCls->addContent($this->colors->error("Instance must have a --name argument"));
            die();
        }
        if(isset($cli_args['--backtesting'])){
            $this->thisInstance["simulator"] = true;
            register_shutdown_function('remove_pid_from_file',getmypid(),$bot_settings->all_pids_file);
        }
        //$filesCls->register_pid_to_file($cli_args['--name']."- WSBOT -(".date('H:i, d/m').")");
        $name=$cli_args['--name'];
        $filesCls->addContent("phpCryptoTradingBotV0.1///$name///");
        //print_r($instances_on_start);
        if(isset($instances_on_start[$cli_args['--name']]) || isset($cli_args['--onTheFly'])){
            if(isset($cli_args['--onTheFly'])){
                if(!file_exists($on_the_fly_file)){
                    $filesCls->addContent($this->colors->error("file '$on_the_fly_file' does not exist, new instance shutdown, cannot load --onTheFly"));
                    die();                   
                }
                $instances_on_the_fly = json_decode(file_get_contents($on_the_fly_file),true);
                $this->thisInstance = $instances_on_the_fly[$name];
                $filesCls->addContent($this->colors->info("ON THE FLY Fired up with these settings: ".json_encode($instances_on_the_fly[$name], JSON_PRETTY_PRINT)));
                
                
            }else{
                $this->thisInstance = $instances_on_start[$cli_args['--name']];
            }
            $this->ignore_coins = $this->thisInstance["ignore_coins"];

            $this->include_coins = $this->thisInstance["include_coins"];
            $this->min_vol = $this->thisInstance["min_vol"];
            $this->timeframe = $this->thisInstance["timeframe"];
            $this->sell_only_mode = $this->thisInstance["sell_only_mode"];
            $this->max_open_signals = $this->thisInstance["max_open_signals"];
			$this->max_btc_per_trade = $this->thisInstance["max_btc_per_trade"];
            $this->simulator = $this->thisInstance["simulator"];
			$this->xchnage = $this->thisInstance["exchange"];
			$this->sim_id = mktime().rand().$this->xchnage;
            $GLOBALS['isSimulator'] = $this->thisInstance["simulator"];
            $filesCls->addContent($GLOBALS['isSimulator'] ? $this->colors->warning("SIMULATION MODE") : $this->colors->info("REAL TRADES MODE"));
            
			//check exhange
			if($this->xchnage === "binance"){
				$api = new Binance\API($user_settings->binance->bnkey,$user_settings->binance->bnsecret);
			}
			else if($this->xchnage === "bittrex"){
				$client = new Models\Exchanges\Bittrex\ClientBittrexAPI ($user_settings->bittrex->btkey,$user_settings->bittrex->btsecret);
				$api = new ClientR("wss://socket.bittrex.com/signalr", ["corehub"]);

			}
			$filesCls->addContent($this->colors->info("Exchange: ".$this->xchnage));
            //fill strategies
            $filesCls->addContent($this->colors->info("Loading Strategies ... "));
            foreach($this->thisInstance["strategy"] as $sName){
                if($sName==="allActive"){
                    $this->strategies = $this->strategies_cls->active_strategies; 
                    //print_r($this->strategies);
                    //die();
                }else{
                    if(isset($this->strategies_cls->strategies[$sName]) && !isset($this->strategies[$sName])){
                      $this->strategies[$sName] = $this->strategies_cls->strategies[$sName];        
                       // $filesCls->addContent($this->colors->info("Loaded Strategy:  $sName".PHP_EOL));
                    } 
                    else if(isset($this->strategies[$sName])){
                        $filesCls->addContent($this->colors->warning("Already loaded $sName ..."));
                    }
                    else{
                        $filesCls->addContent($this->colors->error("Could not load strategy $sName ... "));
                    }

                }
            }
            //print_r($this->strategies);
            //die();
            foreach($this->strategies as $strat_name=>$sta){
                        $filesCls->addContent($this->colors->info("Loaded Strategy:  $strat_name".PHP_EOL));
                    }
            //$filesCls->addContent($this->colors->info("Loaded Strategies: ".json_encode($this->strategies, JSON_PRETTY_PRINT).PHP_EOL));
            //$filesCls->addContent($this->colors->info("Loaded Strategies: ".json_encode($loaded_s).PHP_EOL));
            
            
        }else{
            $filesCls->addContent($this->colors->error("could not find instance name on conf.json or other instance file"));
            die();
        }
            //print_r($this->strategies);
        //$this->strategies = $strategies;//for now its taking strategies from $this->strategies_cls->active_strategies
        //$this->min_vol = $min_vol;
        $this->signals = $this->signals_cls->getOpeningSignals($this->strategies,$this->timeframe,$this->xchnage);
        //print_r($this->signals);
        //die();
        //$filesCls->addContent($this->colors->info("Added ".count($this->signals)." signals from file"));
        
        
        
        $this->fillCoinsArr();
		if($this->xchnage==="bittrex"){
			$this->fill_btrx_candles();
		}
		
        //print_r($this->strategies);
        //sleep(90);
        //die('i started and died');
            /*
            {
        "coins": ["BNBBTC","ADABTC","NEOBTC"],
        "strats": ["ema2050_crossover","pSARswtich"]
    }
    */
        

        
        $to_pid['coins'] = $this->coins_array;
        $to_pid['strats'] = array_keys($this->strategies);
        $to_pid['name'] = $cli_args['--name'];
		$to_pid['exchange'] = $this->xchnage;
		$to_pid['isSimulator'] = $GLOBALS['isSimulator'];
		$to_pid['timeframe'] = $this->timeframe;
		if($this->sell_only_mode){
			$to_pid['SOM'] = true;
		}
        $filesCls->register_pid_to_file(json_encode($to_pid));
        $filesCls->addContent($this->colors->success("Bot instance created successfully ..."));
		if($this->telegram!==null){
			$this->telegram->sendMessage("started ".$cli_args['--name']);
		}
		
        echo "started ".$cli_args['--name'].PHP_EOL;
    }
    public function fillCoinsArr(){
        global $filesCls,$cli_args;
        $this->coins_array = [];
        $this->coins_array = $this->get_top_x_coins($this->min_vol);
        //add all binance coins with volume over x
        //add all include coins fron conf file
        //add all coins that have active signals
        //print_r($this->signals);
        //die();
        foreach($this->signals as $strategy_name => $signal){
            $this->coins_array = array_merge($this->coins_array, array_keys($signal));

        }
                    //print_r($this->coins_array);
                //die();
        foreach($this->include_coins as $coin){
            $this->coins_array[]=$coin."BTC";
        }
		foreach($this->ignore_coins as $coin){
			if (($key = array_search($coin."BTC", $this->coins_array)) !== false) {
    			unset($this->coins_array[$key]);
			}
		}
        //remove coins already running on other instances with the exact strategy
        if(isset($cli_args['--backtesting'])){
            return null; // if backtesting, do not take out coins
        }
        ////backtesting doesnt go below this poing in the function!!!!! /////
        $pids_arr =$filesCls->getPIDFileCon();
        if($pids_arr!==null){
                foreach($pids_arr as $pid_num=>$pid_data){
                    if($pid_num!==getmypid()){
                        $pid_data = json_decode($pid_data,true);
                        //print_r($pid_data);
                        //die();
                        if(isset($pid_data["strats"])){ 
                            foreach($pid_data["strats"] as $strategy){
                            if(isset($this->strategies[$strategy])){
                                foreach($pid_data["coins"] as $coin_name){
                                    foreach($this->coins_array as $index => $coin_n){
                                        if($coin_n === $coin_name && $pid_data["timeframe"] === $this->timeframe && $pid_data["exchange"] === $this->xchnage && $GLOBALS['isSimulator'] === $pid_data['isSimulator'] ){
                                            $filesCls->addContent("Ignoring $coin_name, running with same '$strategy' strategy on other instance named '".$pid_data['name']."'. ");
                                            unset($this->coins_array[$index]);
                                        }
                                    }
                                }
                            }
                            
                        }
                       }
                                                
                    }
                }
            }
        $to_pid['coins'] = $this->coins_array;
        $to_pid['strats'] = array_keys($this->strategies);
        $to_pid['name'] = $cli_args['--name'];
		$to_pid['exchange'] = $this->xchnage;
		$to_pid['isSimulator'] = $GLOBALS['isSimulator'];
		$to_pid['timeframe'] = $this->timeframe;
				if($this->sell_only_mode){
			$to_pid['SOM'] = true;
		}
        $filesCls->register_pid_to_file(json_encode($to_pid));
        

        
    }
    public function get_top_x_coins($vol){
        global $bot_settings,$filesCls;
		
		
		
		if($this->xchnage === "bittrex"){
      $nonce=time();
    $uri="https://bittrex.com/api/v1.1/public/getmarketsummaries";
    $sign=@hash_hmac('sha512',$uri);
    $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $execResult = curl_exec($ch);
    $obj = json_decode($execResult, true);
			$obj = $obj['result'];
    $j=0;
        for($i=0;$i<count($obj);$i++){
            $coins[$i] = $obj[$i];
        }
        $coinsa=[];		
        for($i=0;$i<count($coins);$i++){
            if($coins[$i]["BaseVolume"] >= $vol && strpos($coins[$i]["MarketName"],"BTC")===0){
                $ignore = false;
                foreach($this->ignore_coins as $key=>$coin){
                    if(strpos($coins[$i]["MarketName"],$coin)){
                        $ignore=true;
                        //unset($this->ignore_coins[$key]);
                    }
                }
                if(!$ignore){

					
					$tmp_name = explode("-",$coins[$i]["MarketName"]);
					$coins[$i]["MarketName"] = $tmp_name[1].$tmp_name[0];
                    $coinsa[$j] = $coins[$i]["MarketName"];
                    $j++;
                }

            }
        }
			//$this->coins_array = $coinsa;
			
		}
		
		
		
		else if($this->xchnage === "binance"){
        $nonce=time();
        $uri="https://api.binance.com/api/v1/ticker/24hr";
        $sign=@hash_hmac('sha512',$uri);
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $execResult = curl_exec($ch);
        $obj = json_decode($execResult, true);
        //$filesCls->addContent(json_encode($obj));
        $j=0;
        for($i=0;$i<count($obj);$i++){
            $coins[$i] = $obj[$i];
        }
        $coinsa=[];
        for($i=0;$i<count($coins);$i++){
            if($coins[$i]["quoteVolume"] >= $vol && strpos($coins[$i]["symbol"],"BTC") > 2){
                $ignore = false;
                foreach($this->ignore_coins as $key=>$coin){
                    if(strpos($coins[$i]["symbol"],$coin)){
                        $ignore=true;
                        //unset($this->ignore_coins[$key]);
                    }
                }
                if(!$ignore){
                    $coinsa[$j] = $coins[$i]["symbol"];
                    $j++;
                }

            }
        } 
		}
        return count($coinsa) > 0 ? $coinsa : [];

    }
   
    private function intiate_OHLVC($OHLVC){
        $this->opens = [];
        $this->closes = [];
        $this->highs= [];
        $this->lows= [];
        $this->vols= [];
        $fixedIndexes = array_values($OHLVC);
        //print_r($fixedIndexes);
        foreach($fixedIndexes as $arr){
            $this->opens[] = $arr['open'];
            $this->closes[] = $arr['close'];
            $this->highs[]=$arr['high'];
            $this->lows[]=$arr['low']; 
            $this->vols[]=$arr['volume'];   
        
        }
        $this->OHLVC['opens'] = $this->opens;
        $this->OHLVC['closes'] = $this->closes;
        $this->OHLVC['highs'] = $this->highs;
        $this->OHLVC['lows'] = $this->lows;
        $this->OHLVC['vols'] = $this->vols;
    }
	
	public function fill_btrx_candles(){
		//$this->btrx_candles pass this var to  intiate_OHLVC when its time to check this coin latest trade
		$names = $this->to_market_format("bittrex",$this->coins_array);
		//print_r($names);
		$timeframes_unifier = ["1m" => "oneMin", "5m" => "fiveMin", "30m" => "thirtyMin", "1h" => "hour", "1d"=>"day"];
		foreach($names as $id=>$market){
			$this->btrx_coins_format[$market] = $id;
        	$nonce=time();
    		$uri="https://international.bittrex.com/Api/v2.0/pub/market/GetTicks?marketName=$market&tickInterval=".$timeframes_unifier[$this->timeframe];
			echo $uri;
    		$sign=@hash_hmac('sha512',$uri);
    
    		$ch = curl_init($uri);
        	curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
        	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    		$execResult = curl_exec($ch);
    		$obj = json_decode($execResult, true);
			//print_r($obj);
			$obj = $obj['result'];
			if(!is_array($obj)){
				var_dump($execResult);
				//die();
			}
			foreach($obj as $index=>$candle){
				$this->btrx_candles[$this->coins_array[$id]][$index]['open'] = $candle['O'];
				$this->btrx_candles[$this->coins_array[$id]][$index]['close'] = $candle['C'];
				$this->btrx_candles[$this->coins_array[$id]][$index]['high'] = $candle['H'];
				$this->btrx_candles[$this->coins_array[$id]][$index]['low'] = $candle['L'];
				$this->btrx_candles[$this->coins_array[$id]][$index]['volume'] = $candle['BV'];
			}

			
			//sleep(60);
		}
					print_r(array_keys($this->btrx_candles));
			//die();
	}
	
	public function to_market_format($xchange,$names_arr){
		switch ($xchange){
			case 'bittrex':
				foreach($names_arr as $id => $name){
					$tmp_name = explode("BTC",$name);
						$names_arr[$id] = "BTC-".$tmp_name[0];
					}
				
				break;
			case 'binance':
				foreach($names_arr as $id => $name){
					$tmp_name = explode("-",$name);
						$names_arr[$id] = $tmp_name[1].$tmp_name[0];
					}	
				break;
		}
		return $names_arr;
	}
    
    public function latest_scans(){
        global $latest_scan_results;
        return $latest_scan_results;
        
    }
    
    function checkOHLVCforSignals($OHLVC,$market){
        global $bot_settings,$filesCls,$latest_scan_results;
        $this->intiate_OHLVC($OHLVC);
        $respond = [];
        foreach($this->strategies as $starteg){
            $start = $starteg['name'];
			$starteg['market'] = $market;
            $what_to_do1 = $this->buy_sell_nothing($starteg);
            $what_to_do=$what_to_do1[0];
            if($what_to_do==="no array error"){
				if($bot_settings->debug){
                $filesCls->addContent($this->colors->error("$market passed empty array for indicators/OHLVC.")); 
				}
				return null;
            }
            $what_to_do1['timeframe'] = $this->timeframe;
			$what_to_do1['exchange'] = $this->xchnage;
            $latest_scan_results[$start][$market]=$what_to_do1;
            $latest_scan_results[$start][$market]['price'] = $this->closes[count($this->closes)-1];
            $dca_data=[null,null];
            //exmpale dca settings: DCA: 10|instant|max_dca_times or DCA: 10|strat|max_dca_times
            /* how buy signal_arr looks  add scheme here, for buy ans sell */
            if($starteg['DCA'] !== false){ //
				$dca_data = explode("|",$starteg['DCA']);
				//if all condidons met{
				if(isset($this->signals[$strat['name']][$strat['market']]['profit'])){
					if($this->signals[$strat['name']][$strat['market']]['profit'] < $dca_data[0]){
						$this->signals[$start][$market]['DCA']=true; //DCA when there is buy signal and loss is greater than DCA setting
					}
					else{
						//if loss was greater then dca and became lower without purchase, cancel dca
						unset($this->signals[$start][$market]['DCA']);
					}
				}
				
				//}
			
			}
            
            $signal_arr = ["price" => $this->closes[count($this->closes)-1],"market" => $market, "strat" => $start];
            //change price to bidqask intead of close;
            if ($what_to_do==='buy'  || isset($this->signals[$start][$market]['DCA'])){
				if(isset($this->signals[$start][$market]['DCA'])){
					if(isset($dca_data[2])){
						if($dca_data[2] >= $this->signals[$strat['name']][$market]['DCAed']){
							unset($this->signals[$start][$market]['DCA']);
							return null;
							
						}
					}
					if($dca_data[1]==="instant"){
						$signal_arr['DCA'] = "yes";
					}
					else if($dca_data[1]==="strat"){
						if($what_to_do==='buy'){
							$signal_arr['DCA'] = "yes";
						}
						else{
							return null;
						}
					}
					else{
						$signal_arr['DCA'] = "yes";
					}
				}
                $respond[$market][$start]['action'] = 'buy';
                $signal_arr['type'] = 'buy';
                $signal_arr['max_spread'] = $starteg['buyCon']["maxSpreadCheckedPriceToAsk"];
                if($this->signals_cls->proccess_buySignal($signal_arr)){
                    $filesCls->addContent($this->colors->buy("BUY SIGNAL Proccessed".json_encode($signal_arr)));
					
                }

// check if i already have open signal for this market, if so check DCA or skup
                
            }
            else if ($what_to_do==='sell' || isset($this->signals[$start][$market]['forceSell'])){
				if(isset($this->signals[$start][$market]['forceSell'])){
					$signal_arr['forceSell'] = "yes";
				}
                $respond[$market][$start]['action'] = 'sell';
                $signal_arr['type'] = 'sell';
                $signal_arr['max_spread'] = $starteg['sellCon']["maxSpreadCheckedPriceToBid"];
                if($this->signals_cls->proccess_sellSignal($signal_arr)){
                    $filesCls->addContent($this->colors->sell("SELL SIGNAL Proccessed".json_encode($signal_arr)));                   
                }

                
            }
            else{
                $respond[$market][$start]['action'] = 'nothing';
                //print_r($respond);
            }
        }
        $filesCls->msgToWeb(json_encode(['latestScansData',$this->latest_scans()]),$market.$start);
        return $respond;
    }
    
    private function buy_sell_nothing($strat){
        //echo $strat;$latest_scan_results
        if(!is_array($this->closes)){
            return ["no array error",[],[]];
            
        }
        //maybe add buy_price built-in indicator, pass it in $strat var
        $price = array_reverse($this->closes);
		$vol = array_reverse($this->vols);
        $buy_min_score = $strat['buyCon']['min_score'];
        $sell_min_score = $strat['sellCon']['min_score'];
		$profit = [0,-500];
		//added profit var, check if signal is open, if yes, check for profit and check against max profit, for trailing purposes, if max profit if larger then profit meaning down?
		if($this->signals_cls->isOpenPositionMarket($strat['market'],$strat['name'])){
			$profit = ($price[0]-$this->signals[$strat['name']][$strat['market']]['price'])/$this->signals[$strat['name']][$strat['market']]['price']*100;
			$this->signals[$strat['name']][$strat['market']]['profit'] = $profit;
			if(!isset($this->signals[$strat['name']][$strat['market']]['max_profit'])){
				$this->signals[$strat['name']][$strat['market']]['max_profit'] = $profit;
			}
			else{
				if($this->signals[$strat['name']][$strat['market']]['max_profit'] < $profit){
					$this->signals[$strat['name']][$strat['market']]['max_profit'] = $profit;
					
				}
			}
			$profit = [$profit,$this->signals[$strat['name']][$strat['market']]['max_profit']];
		}
        $buy_cons_results=[];
        $sell_cons_results=[];
        foreach($strat['indicators'] as $indicator_name=>$value){
            $indicators_arr[$indicator_name] = fill_indicators([$indicator_name=>$value],$this->OHLVC);
            if(!is_array($indicators_arr[$indicator_name])){
                return ["no array error",[],[]];
                
            }
            $$indicator_name = array_reverse($indicators_arr[$indicator_name]);
            //$indicator_name_prev = $indicator_name."_prev";
            //$$indicator_name_prev = $indicators_arr[$indicator_name][count($indicators_arr[$indicator_name])-2];
        }
        
        $score=0;
        //print_r($indicators_arr);
        $cons_results = [];
		if($profit[1]===-500){// if there is no open signal, check for buy
        foreach($strat['buyCon']['conditions'] as $condition){
            //ho $condition;
            $tmp = $condition;
            $buy_cons_results[$tmp] = false;
            
            $condition = "return $condition;";
            if(eval($condition)){
                $buy_cons_results[$tmp] = true;
               $score++; 
            }
        }
        if($buy_min_score <= $score){
            return ['buy',$buy_cons_results,[]];
        }
		}
        else {//if there is open signal, check for sell
        $score=0;
        foreach($strat['sellCon']['conditions'] as $condition){
            //echo $condition;
            $tmp = $condition;
            $sell_cons_results[$tmp] = false;
            
            $condition = "return $condition;";
            if(eval($condition)){
                $sell_cons_results[$tmp] = true;
               $score++; 
            }
        }
        if($sell_min_score <= $score){
            return ['sell',[],$sell_cons_results];
        }
        			
		}

        return [null,$buy_cons_results,$sell_cons_results];
        //echo $score;
        
        
        /*switch($strat){
            case 'pSARswtich':
                $pSAR_arr = trader_sar($this->highs,$this->lows);
                $pSAR_latest = $pSAR_arr[count($pSAR_arr)-1];
                $pSAR_latest_prev = $pSAR_arr[count($pSAR_arr)-2];
                if($pSAR_latest < $latest_close && $pSAR_latest_prev > $latest_close_prev){
                    return 'buy';
                }
                elseif($pSAR_latest > $latest_close && $pSAR_latest_prev < $latest_close_prev){
                    return 'sell';
                }
                else{
                    return null;
                }
                
                break;
            case 'ema2050_cross':
                $pSAR_arr = trader_ema($this->closes,20);
                $pSAR_arr1 = trader_ema($this->closes,50);
                $pSAR_latest = $pSAR_arr[count($pSAR_arr)-1];
                $pSAR_latest_prev = $pSAR_arr[count($pSAR_arr)-2];
                $pSAR_latest1 = $pSAR_arr[count($pSAR_arr1)-1];
                $pSAR_latest_prev1 = $pSAR_arr[count($pSAR_arr1)-2];
                //echo "$latest_close, $pSAR_latest < $pSAR_latest_prev, $pSAR_latest1 > $pSAR_latest_prev1";
                if($pSAR_latest < $pSAR_latest1 && $pSAR_latest_prev > $pSAR_latest_prev1){
                    return 'sell';
                }
                elseif($pSAR_latest > $pSAR_latest1 && $pSAR_latest_prev < $pSAR_latest_prev1){
                    return 'buy';
                }
                else{
                    return null;
                }
                break;
            default:
                break;
        }*/
            
    }
}