<?php
class RouterosAPI {
    var $debug = false;
    var $conn;
    var $host;
    var $port;
    var $username;
    var $password;
    
    function connect($ip, $user, $pass, $port = 8728) {
        $this->host = $ip;
        $this->username = $user;
        $this->password = $pass;
        $this->port = $port;
        
        $this->conn = fsockopen($ip, $port, $errno, $errstr, 10);
        if (!$this->conn) {
            $this->debug('Error: ' . $errstr);
            return false;
        }
        
        // Login to RouterOS
        $this->write('/login');
        $response = $this->read(false);
        if (isset($response[0]) {
            $this->write('/login', false);
            $this->write('=name=' . $this->username, false);
            $this->write('=password=' . $this->password);
            $response = $this->read(true);
            if (isset($response[0]) {
                return true;
            }
        }
        
        return false;
    }
    
    function disconnect() {
        fclose($this->conn);
    }
    
    function write($command, $param = true) {
        if ($param) {
            $com = explode(" ", $command);
            $command = '';
            foreach ($com as $com_) {
                if (strpos($com_, "=") === 0) {
                    $command .= ' ' . $com_;
                } else {
                    $command .= '/'.$com_;
                }
            }
            $command = substr($command, 1);
        }
        
        fwrite($this->conn, $this->encodeLength(strlen($command)) . $command);
    }
    
    function read($parse = true) {
        $response = array();
        $i = 0;
        
        while (true) {
            $len = $this->decodeLength();
            if ($len === null) break;
            
            $word = fread($this->conn, $len);
            $response[$i] = $word;
            $i++;
            
            if ($this->conn && feof($this->conn)) {
                break;
            }
        }
        
        if ($parse) {
            $parsed = array();
            $i = 0;
            
            foreach ($response as $res) {
                $lines = explode("\n", $res);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false) {
                        list($name, $value) = explode("=", $line, 2);
                        $parsed[$i][$name] = $value;
                    }
                }
                $i++;
            }
            
            return $parsed;
        }
        
        return $response;
    }
    
    function encodeLength($len) {
        if ($len < 0x80) {
            return chr($len);
        } else if ($len < 0x4000) {
            $len |= 0x8000;
            return chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } else if ($len < 0x200000) {
            $len |= 0xC00000;
            return chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } else if ($len < 0x10000000) {
            $len |= 0xE0000000;
            return chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } else {
            return chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        }
    }
    
    function decodeLength() {
        $byte = ord(fread($this->conn, 1));
        
        if (($byte & 0x80) == 0x00) {
            $len = $byte & 0x7F;
        } else if (($byte & 0xC0) == 0x80) {
            $byte2 = ord(fread($this->conn, 1));
            $len = (($byte & 0x3F) << 8) + $byte2;
        } else if (($byte & 0xE0) == 0xC0) {
            $byte2 = ord(fread($this->conn, 1));
            $byte3 = ord(fread($this->conn, 1));
            $len = (($byte & 0x1F) << 16) + ($byte2 << 8) + $byte3;
        } else if (($byte & 0xF0) == 0xE0) {
            $byte2 = ord(fread($this->conn, 1));
            $byte3 = ord(fread($this->conn, 1));
            $byte4 = ord(fread($this->conn, 1));
            $len = (($byte & 0x0F) << 24) + ($byte2 << 16) + ($byte3 << 8) + $byte4;
        } else if (($byte & 0xF8) == 0xF0) {
            $byte2 = ord(fread($this->conn, 1));
            $byte3 = ord(fread($this->conn, 1));
            $byte4 = ord(fread($this->conn, 1));
            $byte5 = ord(fread($this->conn, 1));
            $len = (($byte & 0x07) << 32) + ($byte2 << 24) + ($byte3 << 16) + ($byte4 << 8) + $byte5;
        } else {
            return null;
        }
        
        return $len;
    }
    
    function debug($msg) {
        if ($this->debug) {
            echo $msg . "\n";
        }
    }
}
