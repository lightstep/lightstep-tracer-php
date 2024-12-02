<?php
namespace LightStepBase\Client;

use LightStepBase\Span;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

require_once(dirname(__FILE__) . "/../api.php");
require_once(dirname(__FILE__) . "/ClientSpan.php");
require_once(dirname(__FILE__) . "/NoOpSpan.php");
require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/Transports/TransportUDP.php");
require_once(dirname(__FILE__) . "/Transports/TransportHTTPJSON.php");
require_once(dirname(__FILE__) . "/Transports/TransportHTTPPROTO.php");
require_once(dirname(__FILE__) . "/Version.php");
require_once(dirname(__FILE__) . "/Auth.php");
require_once(dirname(__FILE__) . "/Runtime.php");
require_once(dirname(__FILE__) . "/KeyValue.php");
require_once(dirname(__FILE__) . "/ReportRequest.php");
require_once(dirname(__FILE__) . "/LogRecord.php");

define('CARRIER_TRACER_STATE_PREFIX', 'ot-tracer-');
define('CARRIER_BAGGAGE_PREFIX', 'ot-baggage-');

/**
 * Main implementation of the Tracer interface
 */
class ClientTracer implements \LightStepBase\Tracer, LoggerAwareInterface {

    use LoggerAwareTrait;

    protected $_util = NULL;
    protected $_options = [];
    protected $_enabled = true;
    protected $_debug = false;

    protected $_guid = "";
    protected $_startTime = 0;
    protected $_auth = NULL;
    protected $_runtime = NULL;
    protected $_transport = NULL;

    protected $_reportStartTime = 0;
    protected $_spanRecords = [];
    protected $_counters = [
        'dropped_counters' => 0,
    ];

    protected $_lastFlushMicros = 0;
    protected $_minFlushPeriodMicros = 0;
    protected $_maxFlushPeriodMicros = 0;

    public function __construct($options = []) {
        $this->_util = new Util();

        $defaults = [
            'collector_host'            => 'collector.lightstep.com',
            'collector_port'            => 443,
            'collector_secure'          => true,

            'transport'                 => 'http_json',
            'max_log_records'           => 1000,
            'max_span_records'          => 1000,
            'min_reporting_period_secs' => 0.1,
            'max_reporting_period_secs' => 5.0,

            // PHP-specific configuration
            //
            // TODO: right now any payload with depth greater than this is simply
            // rejected; it is not trimmed.
            'max_payload_depth'     => 10,

            // Internal debugging flag that enables additional logging and
            // tracer checks. Not intended to run in production as it may add
            // logging "noise" to the calling code.
            'verbose'               => 0,

            // Flag intended solely to unit testing convenience
            'debug_disable_flush'   => false,

            // default tracer attributes
            'attributes'            => [
                'lightstep.tracer_platform' => 'php',
                'lightstep.tracer_platform_version' => phpversion(),
                'lightstep.tracer_version'  => LIGHTSTEP_VERSION,
            ]
        ];

        // Modify some of the interdependent defaults based on what the user-specified
        if (isset($options['collector_secure'])) {
            $defaults['collector_port'] = $options['collector_secure'] ? 443 : 80;
        }

        // UDP has significantly lower size contraints
        if (isset($options['transport']) && $options['transport'] == 'udp') {
            $defaults['max_log_records'] = 16;
            $defaults['max_span_records'] = 16;
        }

        // combine default and custom tracer attributes
        if (isset($options['attributes'])) {
            $options['attributes'] = array_merge($defaults['attributes'], $options['attributes']);
        }

        // Set the options, merged with the defaults
        $this->options(array_merge($defaults, $options));

        if ($this->_options['transport'] == 'udp') {
            $this->_transport = new Transports\TransportUDP($this->logger());
        } else if ($this->_options['transport'] == 'http_proto') {
            $this->_transport = new Transports\TransportHTTPPROTO($this->logger());
        } else {
            $this->_transport = new Transports\TransportHTTPJSON($this->logger());
        }

        // Note: the GUID is not generated until the library is initialized
        // as it depends on the access token
        $this->_startTime = $this->_util->nowMicros();
        $this->_reportStartTime = $this->_startTime;
        $this->_lastFlushMicros = $this->_startTime;

        // PHP is (in many real-world contexts) single-threaded and
        // does not have an event loop like Node.js.  Flush on exit.
        $tracer = $this;
        register_shutdown_function(function() use ($tracer) {
            $tracer->flush();
        });
    }

    public function __destruct() {
        $this->flush();
    }

    public function options($options) {

        $this->_options = array_merge($this->_options, $options);

        // Deferred group name / access token initialization is supported (i.e.
        // it is possible to create logs/spans before setting this info).
        if (!empty($options['access_token']) && !empty($options['component_name'])) {
            $this->_initDataIfNeeded($options['component_name'], $options['access_token']);
        }

        if (isset($options['min_reporting_period_secs'])) {
            $this->_minFlushPeriodMicros = $options['min_reporting_period_secs'] * 1e6;
        }
        if (isset($options['max_reporting_period_secs'])) {
            $this->_maxFlushPeriodMicros = $options['max_reporting_period_secs'] * 1e6;
        }

        $this->_debug = ($this->_options['verbose'] > 0);

        // Coerce invalid options into stable values
        if (!($this->_options['max_log_records'] > 0)) {
            $this->_options['max_log_records'] = 1;
            $this->_debugRecordError('Invalid value for max_log_records');
        }
        if (!($this->_options['max_span_records'] > 0)) {
            $this->_options['max_span_records'] = 1;
            $this->_debugRecordError('Invalid value for max_span_records');
        }
    }

    private function _initDataIfNeeded($componentName, $accessToken) {

        // Pre-conditions
        if (!is_string($accessToken)) {
            throw new \Exception('access_token must be a string');
        }
        if (!is_string($componentName)) {
            throw new \Exception('componentName must be a string');
        }
        if (!(strlen($accessToken) > 0)) {
            throw new \Exception('access_token must be non-zero in length');
        }
        if (!(strlen($componentName) > 0)) {
            throw new \Exception('componentName must be non-zero in length');
        }

        // Potentially redundant initialization info: only complain if
        // it is inconsistent.
        if ($this->_auth != NULL || $this->_runtime != NULL) {
            if ($this->_auth->getAccessToken() !== $accessToken) {
                throw new \Exception('access_token cannot be changed after it is set');
            }
            if ($this->_runtime->getGroupName() !== $componentName) {
                throw new \Exception('component name cannot be changed after it is set');
            }
            return;
        }

        // Generate the GUID on initialization as the GUID should be
        // stable for a particular access token / component name combo.
        $this->_guid = $this->_generateStableUUID($accessToken, $componentName);
        $this->_auth = new Auth($accessToken);

        $attrs = [];
        foreach ($this->_options['attributes'] as $key => $value) {
            $attrs[] = new KeyValue(strval($key), strval($value));
        }
        $this->_runtime = new Runtime(strval($this->_guid), intval($this->_startTime), strval($componentName), $attrs);
    }

    public function guid() {
        return $this->_guid;
    }

    public function disable() {
        $this->_discard();
        $this->_enabled = false;
    }

    /**
     * Internal use only.
     *
     * Discard all currently buffered data.  Useful for unit testing.
     */
    public function _discard() {
        $this->_spanRecords = [];
    }

    public function startSpan($operationName, $fields = NULL) {
        if (!$this->_enabled) {
            return new NoOpSpan;
        }

        $span = new ClientSpan($this, $this->_options['max_payload_depth']);
        $span->setOperationName($operationName);
        $span->setStartMicros($this->_util->nowMicros());

        if ($fields != NULL) {
            if (isset($fields['parent'])) {
                $span->setParent($fields['parent']);
            }
            if (isset($fields['tags'])) {
                $span->setTags($fields['tags']);
            }
            if (isset($fields['startTime'])) {
                $span->setStartMicros($fields['startTime'] * 1000);
            }
        }
        return $span;
    }

    /**
     * Copies the span data into the given carrier object.
     */
    public function inject(Span $span, $format, &$carrier) {
        switch ($format) {
        case LIGHTSTEP_FORMAT_TEXT_MAP:
            $this->injectToArray($span, $carrier);
            break;

        case LIGHTSTEP_FORMAT_BINARY:
            throw new \Exception('FORMAT_BINARY not yet implemented');
            break;

        default:
            $this->_debugRecordError('Unknown inject format');
            break;
        }
    }

    protected function injectToArray(Span $span, &$carrier) {
        $carrier[CARRIER_TRACER_STATE_PREFIX . 'spanid'] = $span->guid();
        $traceGUID = $span->traceGUID();
        if ($traceGUID) {
            $carrier[CARRIER_TRACER_STATE_PREFIX . 'traceid'] = $traceGUID;
        }
        $carrier[CARRIER_TRACER_STATE_PREFIX . 'sampled'] = 'true';

        foreach ($span->getBaggage() as $key => $value) {
            $carrier[CARRIER_BAGGAGE_PREFIX . $key] = $value;
        }
    }

    /**
     * Creates a new span data from the given carrier object.
     */
    public function join($operationName, $format, $carrier) {
        $span = new ClientSpan($this, $this->_options['max_payload_depth']);
        $span->setOperationName($operationName);
        $span->setStartMicros($this->_util->nowMicros());

        switch ($format) {
        case LIGHTSTEP_FORMAT_TEXT_MAP:
            $this->joinFromArray($span, $carrier);
            break;

        case LIGHTSTEP_FORMAT_BINARY:
            throw new \Exception('FORMAT_BINARY not yet implemented');
            break;

        default:
            $this->_debugRecordError('Unknown inject format');
            break;
        }
        return $span;
    }

    protected function joinFromArray(Span $span, $carrier) {
        foreach ($carrier as $rawKey => $value) {
            $key = strtolower($rawKey);
            if ($this->_startsWith($key, CARRIER_TRACER_STATE_PREFIX)) {
                $shortKey = substr($key, strlen(CARRIER_TRACER_STATE_PREFIX));
                switch ($shortKey) {
                case 'traceid':
                    $span->setTraceGUID($value);
                    break;
                case 'spanid':
                    $span->setParentGUID($value);
                    break;
                }
            } else if ($this->_startsWith($key, CARRIER_BAGGAGE_PREFIX)) {
                $shortKey = substr($key, strlen(CARRIER_BAGGAGE_PREFIX));
                $span->setBaggageItem($shortKey, $value);
            } else {
                // By convention, LightStep join() ignores unrecognized key-value
                // pairs.
            }
        }
    }

    protected function _startsWith($haystack, $needle) {
         return (str_starts_with($haystack, $needle));
    }

    // PHP does not have an event loop or timer threads. Instead manually check as
    // new data comes in by calling this method.
    protected function flushIfNeeded() {
        if (!$this->_enabled) {
            return;
        }

        $now = $this->_util->nowMicros();
        $delta = $now - $this->_lastFlushMicros;

        // Set a bound on maximum flush frequency
        if ($delta < $this->_minFlushPeriodMicros) {
            return;
        }

        // Set a bound of minimum flush frequency
        if ($delta > $this->_maxFlushPeriodMicros) {
            $this->flush();
            return;
        }

        // Look for a trigger that a flush is warranted
        if (count($this->_spanRecords) >= $this->_options["max_span_records"]) {
            $this->flush();
            return;
        }
    }

    public function flush() {
        if (!$this->_enabled) {
            return;
        }

        $now = $this->_util->nowMicros();

        // The runtime configuration has not yet been set: allow logs and spans
        // to be buffered in this case, but flushes won't yet be possible.
        if ($this->_runtime == NULL) {
            return;
        }

        if (count($this->_spanRecords) == 0) {
            return;
        }

        // For unit testing
        if ($this->_options['debug_disable_flush']) {
            return;
        }

        $this->_transport->ensureConnection($this->_options);

        // Ensure the  span GUIDs are set correctly. This is covers a real
        // case: the runtime GUID cannot be generated until the access token
        // and group name are set (so that is the same GUID between script
        // invocations), but the library allows spans to be buffered
        // prior to setting those values.  Any such 'early buffered' spans need
        // to have the GUID set; for simplicity, the code resets them all.
        foreach ($this->_spanRecords as $span) {
            $span->setRuntimeGUID($this->_guid);
        }

        $reportRequest = new ReportRequest($this->_runtime, intval($this->_reportStartTime), intval($now), $this->_spanRecords, $this->_counters);

        $this->_lastFlushMicros = $now;

        $resp = NULL;
        try {
            // It *is* valid for the transport to return a null response in the
            // case of a low-overhead "fire and forget" report
            $resp = $this->_transport->flushReport($this->_auth, $reportRequest);
        } catch (\Exception $e) {
            // Exceptions *are* expected as connections can be broken, etc. when
            // reporting. Prevent reporting exceptions from interfering with the
            // client code.
            $this->_debugRecordError($e);
        }

        // ALWAYS reset the buffers and update the counters as the RPC response
        // is, by design, not waited for and not reliable.
        $this->_reportStartTime = $now;
        $this->_spanRecords = [];
        foreach ($this->_counters as &$value) {
            $value = 0;
        }

        // Process server response commands
        if (!is_null($resp) && is_array($resp->commands)) {
            foreach ($resp->commands as $cmd) {
                if ($cmd->disable) {
                    $this->disable();
                }
            }
        }
    }

    /**
     * Internal use only.
     *
     * Generates a stable unique value for the runtime.
     */
    public function _generateStableUUID($token, $group) {
        $pid = getmypid();
        $hostinfo = php_uname('a');

        // It would be better to use GMP, but this adds a client dependency
        // http://www.sitepoint.com/create-unique-64bit-integer-string/
        // gmp_strval(gmp_init(substr(md5($str), 0, 16), 16), 10);
        // CRC32 lacks the cryptographic strength of GMP.
        //
        // It'd also be good to include process start time in the mix if that
        // can be determined reliably in a platform independent manner.
        return sprintf("%08x%08x",
            crc32(sprintf("%d%s", $pid, $group)),
            crc32(sprintf("%s%d", $pid, $token, $hostinfo)));
    }

    /**
     * Internal use only.
     *
     * Generates a random ID (not a RFC-4122 UUID).
     */
    public function _generateUUIDString() {
        return $this->_util->_generateUUIDString();
    }

    /**
     * Internal use only.
     */
    public function _finishSpan(ClientSpan $span) {
        if (!$this->_enabled) {
            return;
        }

        $span->setEndMicros($this->_util->nowMicros());
        $success = Util::pushIfSpaceAllows(
            $this->_spanRecords,
            $span,
            $this->_options["max_span_records"]);
        if (!$success) {
            if(!isset($this->_counters['dropped_spans'])) {
                $this->_counters['dropped_spans'] = 0;
            }
            $this->_counters['dropped_spans']++;
        }

        $this->flushIfNeeded();
    }

    protected function _debugRecordError($e) {
        if ($this->_debug) {
            $this->logger()->debug($e);
        }
    }

    protected function logger()
    {

        if (! $this->logger) {
            $this->setLogger(new SystemLogger);
        }

        return $this->logger;
    }
}
