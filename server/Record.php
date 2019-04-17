<?php

namespace Citadel\server;

class Record {

    private $name;
    private $name_byte_position;
    private $type;
    private $class;
    private $ttl;
    private $length;
    private $data;

    public function __construct(string $name = null, int $name_byte_position = null, int $type = null, int $class = null, int $ttl = null, int $length = null, array $data = null) {
        $this->name = $name;
        $this->name_byte_position = $name_byte_position;
        $this->type = $type;
        $this->class = $class;
        $this->ttl = $ttl;
        $this->length = $length;
        $this->data = $data;
    }

    /**
     * @param int $bit_position
     * @param bool $full_preamble
     * @param int[] $bits
     * @return static
     */
    public static function extractFromBits(int &$bit_position, bool $full_preamble = false, int... $bits){
        $name_byte_location = 0;
        $name = static::extractNameFromBitArray($bit_position, $name_byte_location, ...$bits);

        $type = Util::bits2int(...Util::array_extract($bits, $bit_position, $bit_position += 16));
        $class = Util::bits2int(...Util::array_extract($bits, $bit_position, $bit_position += 16));

        if($full_preamble){
            $ttl = Util::bits2int(...Util::array_extract($bits, $bit_position, $bit_position += 32));
            $length = Util::bits2int(...Util::array_extract($bits, $bit_position, $bit_position += 16));
            if($length > 0){
                $data = Util::array_extract($bits, $bit_position, ($bit_position += ($length * 8)));
            }else{
                $data = null;
            }
        }else{
            $ttl = null;
            $length = null;
            $data = null;
        }

        return new static($name, $name_byte_location, $type, $class, $ttl, $length, $data);
    }

    private static function extractNameFromBitArray(int &$bit_position, int &$name_byte_location, int... $bits) : string{
        $name = "";
        $reading = true;
        $name_byte_location = $bit_position / 8;
        while($reading){
            $byte = Util::bits2int(...Util::array_extract($bits, $bit_position, ($bit_position += 8)));
            if($byte === 0){
                $name = substr($name, 0, strlen($name)-1);
                $reading = false;
            }else if($byte === 192){
                $position = Util::bits2int(...Util::array_extract($bits, $bit_position, ($bit_position += 8))) * 8;
                $return_position = $bit_position;
                $bit_position = $position;
                $name .= static::extractNameFromBitArray($bit_position, $name_byte_location, ...$bits);
                $name_byte_location = $position / 8;
                $bit_position = $return_position;
                $reading = false;
            }else{
                for($x = 0; $x < $byte; $x++){
                    $name_char = Util::bits2int(...Util::array_extract($bits, $bit_position, $bit_position += 8));
                    $name .= chr($name_char);
                }
                $name .= ".";
            }
        }
        return $name;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getNameBytePosition(): int {
        return $this->name_byte_position;
    }

    /**
     * @param int $name_byte_position
     */
    public function setNameBytePosition(int $name_byte_position): void {
        $this->name_byte_position = $name_byte_position;
    }

    /**
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getClass(): int {
        return $this->class;
    }

    /**
     * @param int $class
     */
    public function setClass(int $class): void {
        $this->class = $class;
    }

    /**
     * @return int
     */
    public function getTtl(): int {
        return $this->ttl;
    }

    /**
     * @param int $ttl
     */
    public function setTtl(int $ttl): void {
        $this->ttl = $ttl;
    }

    /**
     * @return int
     */
    public function getLength(): int {
        return $this->length;
    }

    /**
     * @param int $length
     */
    public function setLength(int $length): void {
        $this->length = $length;
    }

    /**
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void {
        $this->data = $data;
    }

    /**
     * @param $name_bit_location
     * @return int[]
     */
    public function toBits($name_bit_location = false) : array{
        $bits = [];

        if($name_bit_location === false){
            $name_split = explode(".", $this->name);
            foreach($name_split as $name){
                $name_length = strlen($name);
                $name_length_bits = Util::int2bits($name_length, 8);
                foreach($name_length_bits as $bit){
                    $bits []= $bit;
                }

                for($i = 0; $i < $name_length; $i++){
                    $name_bits = Util::int2bits(ord($name[$i]), 8);
                    foreach($name_bits as $bit){
                        $bits []= $bit;
                    }
                }
            }
            $name_bits = Util::int2bits(0, 8);
            foreach($name_bits as $bit){
                $bits []= $bit;
            }
        }else{
            $name_bits = array_merge([1, 1, 0, 0, 0, 0, 0, 0], Util::int2bits($name_bit_location, 8));
            foreach($name_bits as $bit){
                $bits []= $bit;
            }
        }

        $type_bits = Util::int2bits($this->type, 16);
        foreach($type_bits as $bit){
            $bits []= $bit;
        }

        $class_bits = Util::int2bits($this->class, 16);
        foreach($class_bits as $bit){
            $bits []= $bit;
        }

        if($this->ttl !== null){
            $ttl_bits = Util::int2bits($this->ttl, 32);
            foreach($ttl_bits as $bit){
                $bits []= $bit;
            }
        }

        if($this->length !== null){
            $length_bits = Util::int2bits($this->length, 16);
            foreach($length_bits as $bit){
                $bits []= $bit;
            }
        }

        if($this->data !== null){
            foreach($this->data as $bit){
                $bits []= $bit;
            }
        }

        return $bits;
    }

}