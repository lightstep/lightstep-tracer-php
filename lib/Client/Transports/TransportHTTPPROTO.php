<?php
namespace LightStepBase\Client\Transports;

use LightStepBase\Client\SystemLogger;
use Psr\Log\LoggerInterface;

class TransportHTTPPROTO {

    const DEFAULT_SCHEME = 'tls://';

    protected $_scheme = '';
    protected $_host = '';
    protected $_port = 0;
    protected $_verbose = 0;
    protected $_timeout;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(?LoggerInterface $logger = null) {

        $this->logger = $logger ?: new SystemLogger;
        $this->_timeout = ini_get("default_socket_timeout");
    }

    /**
     * Assigns the variables that are required for connectivity.
     *
     * @param array $options Options for how to configure the transport. Expected keys are:
     * 'verbose' - if true, payloads and errors will be logged
     * 'collector_host' - hostname of the collector for the url
     * 'collector_port' - port of the collector for the url
     * 'collector_secure' - if true, will use SSL
     */
    public function ensureConnection($options) {
        $this->_verbose = $options['verbose'];

        $this->_host = $options['collector_host'];
        $this->_port = $options['collector_port'];

        // The prefixed protocol is only needed for secure connections
        if ($options['collector_secure'] == true) {
            if (isset($options['collector_scheme'])) {
                $this->_scheme = $options['collector_scheme'];
            } else {
                $this->_scheme = self::DEFAULT_SCHEME;
            }
        }

        if (isset($options['http_connection_timeout'])) {
            $this->_timeout = $options['http_connection_timeout'];
        }
    }

    public function flushReport($auth, $report) {
        if (is_null($auth) || is_null($report)) {
            if ($this->_verbose > 0) {
                $this->logger->error("Auth or report not set.");
            }
            return NULL;
        }

        $content = $report->toProto($auth)->serializeToString();

        if ($this->_verbose >= 3) {
            $this->logger->debug('Report to be flushed', ['content' => $content]);
        }

        $header = "Host: " . $this->_host . "\r\n";
        $header .= "User-Agent: LightStep-PHP\r\n";
        $header .= "Accept: application/octet-stream\r\n";
        $header .= "Content-Type: application/octet-stream\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "Connection: keep-alive\r\n\r\n";

        // Use a persistent connection when possible
        $fp = @pfsockopen($this->_scheme . $this->_host, $this->_port, $errno, $errstr, $this->_timeout);
        if (!$fp) {
            if ($this->_verbose > 0) {
                $this->logger->error($errstr);
            }
            return NULL;
        }

        @fwrite($fp, "POST /api/v2/reports HTTP/1.1\r\n");
        @fwrite($fp, $header . $content);
        @fflush($fp);
        // Wait and read first line of the response e.g. (HTTP/1.1 2xx OK)
        // otherwise the connection will close before the request is complete,
        // leading to a context cancellation down stream.
        fgets($fp);
        @fclose($fp);

        return NULL;
    }
}
