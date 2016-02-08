<?php
namespace LightStepBase\Client\Transports;

require_once(dirname(__FILE__) . "/THttpClientAsync.php");
require_once(dirname(__FILE__) . "/../../../thrift/CroutonThrift/Types.php");
require_once(dirname(__FILE__) . "/../../../thrift/CroutonThrift/ReportingService.php");

class TransportThrift {

    protected $_thriftClient = null;

    public function ensureConnection($options) {
        if (!is_null($this->_thriftClient)) {
            return;
        }
        $this->_thriftClient = $this->createConnection($options);
    }

    protected function createConnection($options) {
        $host = $options['service_host'];
        $port = $options['service_port'];
        $secure = $options['secure'];
        $debug = $options['debug'];

        if ($debug) {
            error_log("Connecting to $host:$port");
        }

        $socket = new \LightStepBase\Client\Transports\THttpClientAsync($host, $port, '/_rpc/v1/crouton/binary', $secure, $debug);
        $protocol = new \Thrift\Protocol\TBinaryProtocol($socket);
        $client = new \CroutonThrift\ReportingServiceClient($protocol);

        return $client;
    }

    public function flushReport($auth, $report) {
        return $this->_thriftClient->Report($auth, $report);
    }
}
