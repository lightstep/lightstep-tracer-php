<?php
namespace LightStepBase\Client\Transports;

class TransportHTTPJSON {

    protected $_host = '';
    protected $_port = 0;
    protected $_verbose = 0;

    public function ensureConnection($options) {
        $this->_verbose = $options['verbose'];

        $this->_host = $options['collector_host'];
        $this->_port = $options['collector_port'];

        // The prefixed protocol is only needed for secure connections
        if ($options['collector_secure'] == True) {
            $this->_host = "ssl://" . $this->_host;
        }
    }

    public function flushReport($auth, $report) {
        if (is_null($auth) || is_null($report)) {
            if ($this->_verbose > 0) {
                error_log("Auth or report not set.");
            }
            return NULL;
        }

        $thriftReport = $report->toThrift();

        if ($this->_verbose >= 3) {
            var_dump($thriftReport);
        }

        $content = json_encode($thriftReport);
        $content = gzencode($content);

        $header = "Host: " . $this->_host . "\r\n";
        $header .= "User-Agent: LightStep-PHP\r\n";
        $header .= "LightStep-Access-Token: " . $auth->getAccessToken() . "\r\n";
        $header .= "Content-Type: application/json\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "Content-Encoding: gzip\r\n";
        $header .= "Connection: keep-alive\r\n\r\n";

        // Use a persistent connection when possible
        $fp = @pfsockopen($this->_host, $this->_port, $errno, $errstr);
        if (!$fp) {
            if ($this->_verbose > 0) {
                error_log($errstr);
            }
            return NULL;
        }
        @fwrite($fp, "POST /api/v0/reports HTTP/1.1\r\n");
        @fwrite($fp, $header . $content);
        @fflush($fp);
        @fclose($fp);

        return NULL;
    }
}
