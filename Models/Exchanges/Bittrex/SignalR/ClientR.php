<?php

namespace Models\Exchanges\Bittrex\SignalR;

class ClientR
{
    private $base_url;
    private $hubs;
    private $connectionToken;
    private $connectionId;
    public $loop;
    private $callbacks;
    public function __construct($base_url, $hubs)
    {
        $this->base_url = $base_url;
        $this->hubs = $hubs;
        $this->callbacks = [];
		$this->loop = \React\EventLoop\Factory::create();
    }
    public function run()
    {
        if(!$this->negotiate()) {
            throw new \RuntimeException("Cannot negotiate");
        }
        $this->connect();
        if(!$this->start()) {
            throw new \RuntimeException("Cannot start");
        }
        $this->loop->run();
    }
    public function on($hub, $method, $function)
    {
        $this->callbacks[strtolower($hub . "." . $method)] = $function;
    }
    private function connect()
    {
        
        $connector = new \Ratchet\Client\Connector($this->loop);
        $connector($this->buildConnectUrl())->then(function(\Ratchet\Client\WebSocket $conn) {
			//$conn->emit('SubscribeToSummaryDeltas');

			$conn->send('{"M": "SubscribeToSummaryDeltas", "H": "corehub"}');
			//$conn->emit($object);
			//$conn->send($object);
						
            $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
				//echo $msg;
                $data = json_decode($msg);
				//print_r($data);
                if(\property_exists($data, "M")) {
                    foreach($data->M as $message) {
                        $hub = $message->H;
                        $method = $message->M;
                        $callback = \strtolower($hub.".".$method);
                        if(array_key_exists($callback, $this->callbacks)) {
                            foreach($message->A as $payload) {
                                $this->callbacks[$callback]($payload);
                            }
                        }
                    }
                }
            });
                
        }, function(\Exception $e) {
            echo "Could not connect: {$e->getMessage()}\n";
            $this->loop->stop();
        });
    }
    private function buildNegotiateUrl()
    {
        $base = str_replace("wss://", "https://", $this->base_url);
        
        $hubs = [];
        foreach($this->hubs as $hubName) {
            $hubs[] = (object)["name" => $hubName];
        }
        $query = [
            "clientProtocol" => 1.5,
            "connectionData" => json_encode($hubs)
        ];
        return $base . "/negotiate?" . http_build_query($query); 
    }
    private function buildStartUrl()
    {
        $base = str_replace("wss://", "https://", $this->base_url);
        
        $hubs = [];
        foreach($this->hubs as $hubName) {
            $hubs[] = (object)["name" => $hubName];
        }
        $query = [
            "transport" => "webSockets",
            "clientProtocol" => 1.5,
            "connectionToken" => $this->connectionToken,
            "connectionData" => json_encode($hubs)
        ];
        return $base . "/start?" . http_build_query($query); 
    }
    private function buildConnectUrl()
    {
        $hubs = [];
        foreach($this->hubs as $hubName) {
            $hubs[] = (object)["name" => $hubName];
        }
        $query = [
            "transport" => "webSockets",
            "clientProtocol" => 1.5,
            "connectionToken" => $this->connectionToken,
            "connectionData" => json_encode($hubs)
        ];
        return $this->base_url . "/connect?" . http_build_query($query); 
    }
    private function negotiate()
    {
        try {
            $url = $this->buildNegotiateUrl();
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url);
            $body = json_decode($res->getBody());
            
            $this->connectionToken = $body->ConnectionToken;
            $this->connectionId = $body->ConnectionId;
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }
    private function start()
    {
        try {
            $url = $this->buildStartUrl();
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url);
            $body = json_decode($res->getBody());
            
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }
}