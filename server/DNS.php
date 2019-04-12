<?php

namespace Citadel\server;

use \Exception;

final class DNS {

    private $resolver;
    private $error_callback;
    private $socket;

    private $ip = "0.0.0.0";
    private $port = 53;

    public function __construct(Resolver $resolver) {
        $this->resolver = $resolver;
    }

    public function getIp(): string {
        return $this->ip;
    }

    public function setIp(string $ip): void {
        $this->ip = $ip;
    }

    public function getPort(): int {
        return $this->port;
    }

    public function setPort(int $port): void {
        $this->port = $port;
    }

    public function setErrorCallback($error_callback){
        $this->error_callback = $error_callback;
    }

    /**
     * @throws Exception
     */
    public function start(){
        if(!($this->socket = socket_create(AF_INET, SOCK_DGRAM, 0))){
            $errorcode = socket_last_error();
            $errormessage = socket_strerror($errorcode);
            throw new Exception($errormessage, $errorcode);
        }

        if(!socket_bind($this->socket, $this->ip, $this->port)){
            $errorcode = socket_last_error();
            $errormessage = socket_strerror($errorcode);
            throw new Exception($errormessage, $errorcode);
        }

        while(true){
            if(($data = socket_recvfrom($this->socket, $buffer, 512, 0, $remote_ip, $remote_port)) !== false) {
                try{
                    $bytes = unpack("C*", $buffer);
                    $packet = new Packet($remote_ip, $remote_port, ...$bytes);
                    $this->resolver->resolve($this, $packet);
                }catch (Exception $e){
                    $error_callback = $this->error_callback;
                    $error_callback($e);
                }
            }
        }
    }

    public function sendPacket(Packet $packet){
        try{
            $packet_bytes = Util::bits2bytes(...$packet->toBits());
            $index_shifted_bytes = [];
            for ($i = 0; $i < sizeof($packet_bytes); $i++) {
                $index_shifted_bytes[$i + 1] = $packet_bytes[$i];
            }
            $response_buffer = call_user_func_array("pack", array_merge(["C*"], $index_shifted_bytes));
            socket_sendto($this->socket, $response_buffer, 512, 0, $packet->getRemoteIp(), $packet->getRemotePort());
        }catch (Exception $e){
            $error_callback = $this->error_callback;
            $error_callback($e);
        }
    }

}