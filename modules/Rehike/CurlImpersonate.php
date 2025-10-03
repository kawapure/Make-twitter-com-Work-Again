<?php
namespace Rehike;

use InvalidArgumentException;
use Error;
/**

 * Escape a single value in accordance with CommandLineToArgV()

 * https://docs.microsoft.com/en-us/previous-versions/17w5ykft(v=vs.85)

 */

 function escape_win32_argv(string $value): string

 {
 
     static $expr = '(
 
         [\x00-\x20\x7F"] # control chars, whitespace or double quote
 
       | \\\\++ (?=("|$)) # backslashes followed by a quote or at the end
 
     )ux';
 
 
 
     if ($value === '') {
 
         return '""';
 
     }
 
 
 
     $quote = false;
 
     $replacer = function($match) use($value, &$quote) {
 
         switch ($match[0][0]) { // only inspect the first byte of the match
 
 
 
             case '"': // double quotes are escaped and must be quoted
 
                 $match[0] = '\\"';
 
             case ' ': case "\t": // spaces and tabs are ok but must be quoted
 
                 $quote = true;
 
                 return $match[0];
 
 
 
             case '\\': // matching backslashes are escaped if quoted
 
                 return $match[0] . $match[0];
 
 
 
             default: throw new InvalidArgumentException(sprintf(
 
                 "Invalid byte at offset %d: 0x%02X",
 
                 strpos($value, $match[0]), ord($match[0])
 
             ));
 
         }
 
     };
 
 
 
     $escaped = preg_replace_callback($expr, $replacer, (string)$value);
 
 
 
     if ($escaped === null) {
 
         throw preg_last_error() === PREG_BAD_UTF8_ERROR
 
             ? new InvalidArgumentException("Invalid UTF-8 string")
 
             : new Error("PCRE error: " . preg_last_error());
 
     }
 
 
 
     return $quote // only quote when needed
 
         ? '"' . $escaped . '"'
 
         : $value;
 
 }

class CurlImpersonate {
    private $url;
    private $method = 'GET';
    private $headers = array();
    private $cookieFile;
    private $cookieJar;
    private $data;
    private $includeHeaders = false; 
    private $engineCurl = "curl"; 
    private $handle;

    public function setopt($option, $value) {
        switch ($option) {
            case CURLCMDOPT_URL:
                $this->url = $value;
                break;
            case CURLCMDOPT_METHOD:
                $this->method = strtoupper($value);
                break;
            case CURLCMDOPT_POSTFIELDS:
                $this->data = $value;
                break;
            case CURLCMDOPT_HTTP_HEADERS:
                $this->headers = array_merge($this->headers, $value);
                break;
            case CURLCMDOPT_HEADER:
                $this->includeHeaders = (bool)$value;
                break;
            case CURLCMDOPT_ENGINE:
                $this->engineCurl = $value;
                break;
            case CURLCMDOPT_COOKIEFILE:
                $this->cookieFile = $value;
                break;
            case CURLCMDOPT_COOKIEJAR:
                $this->cookieJar = $value;
                break;
            default:
                throw new \InvalidArgumentException("Invalid option: {$option}");
        }
    }

    private function prepareData() {
        if (is_array($this->data) || is_object($this->data)) {
            $this->data = json_encode($this->data);
        }
    }

    public function exec() {
        $this->prepareData();

        $curlCommand = $this->engineCurl;
        $curlCommand .= " --ca-native";
        $curlCommand .= ' -X ' . escapeshellarg($this->method);

        if ($this->cookieFile !== null) {
            $curlCommand .= ' --cookie ' . escapeshellarg($this->cookieFile);
        }

        if ($this->cookieJar !== null) {
            $curlCommand .= ' --cookie-jar ' . escapeshellarg($this->cookieJar);
        }

        if ($this->data !== null) {
            $curlCommand .= ' -d ' . escape_win32_argv($this->data);
        }

        foreach ($this->headers as $header) {
            $escaped =escape_win32_argv($header);
            if (!str_starts_with($escaped, "\""))
            {
                $escaped = "\"$escaped\"";
            }
            
            $curlCommand .= ' -H ' . $escaped;
        }

        if ($this->includeHeaders) {
            $curlCommand .= ' -i';
        }

        $curlCommand .= ' ' . escape_win32_argv($this->url);
        
        echo $curlCommand;

        return $curlCommand;
    }

    public function execStandard($output = null) {
        $command = $this->exec();
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );

        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            echo stream_get_contents($pipes[0]);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            echo $error;
            fclose($pipes[2]);
            proc_close($process);
        }

        return $output;
    }

    public function execStream() {
        $this->prepareData();

        $command = $this->exec();
        $this->handle = popen($command, 'r');
    }

    public function readStream($chunkSize = 4096) {
        if ($this->handle) {
            $output = fread($this->handle, $chunkSize);
            if ($output === false || feof($this->handle)) {
                $this->closeStream();
            }
            return $output;
        }
        return false;
    }

    public function closeStream() {
        if ($this->handle) {
            pclose($this->handle);
            $this->handle = null;
        }
    }
}


define('CURLCMDOPT_URL', 1);
define('CURLCMDOPT_METHOD', 2);
define('CURLCMDOPT_POSTFIELDS', 3);
define('CURLCMDOPT_HTTP_HEADERS', 4);
define('CURLCMDOPT_HEADER', 5);
define('CURLCMDOPT_ENGINE', 6);
define('CURLCMDOPT_COOKIEFILE', 7);
define('CURLCMDOPT_COOKIEJAR', 8);