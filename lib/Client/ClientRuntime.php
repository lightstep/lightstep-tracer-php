<?php
namespace LightStepBase\Client;

require_once(dirname(__FILE__) . "/../api.php");
require_once(dirname(__FILE__) . "/ClientSpan.php");
require_once(dirname(__FILE__) . "/NoOpSpan.php");
require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/Transports/TransportThrift.php");
require_once(dirname(__FILE__) . "/Transports/TransportUDP.php");
require_once(dirname(__FILE__) . "/Version.php");

/**
 * Main implementation of the Runtime interface
 */
class ClientRuntime implements \LightStepBase\Runtime {

    protected $_util = null;
    protected $_options = array();
    protected $_enabled = true;
    protected $_debug = false;

    protected $_guid = "";
    protected $_startTime = 0;
    protected $_thriftAuth = null;
    protected $_thriftRuntime = null;
    protected $_transport = null;

    protected $_reportStartTime = 0;
    protected $_logRecords = array();
    protected $_spanRecords = array();
    protected $_counters = array(
        'dropped_logs' => 0,
        'dropped_counters' => 0,
    );

    protected $_lastFlushMicros = 0;
    protected $_minFlushPeriodMicros = 0;
    protected $_maxFlushPeriodMicros = 0;

    public function __construct($options = array()) {
        $this->_util = new Util();

        $defaults = array(
            'service_host'              => 'api.traceguide.io',
            'service_port'              => 9998,
            'secure'                    => false,

            'transport'                 => 'thrift',
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
            // runtime checks. Not intended to run in production as it may add
            // logging "noise" to the calling code.
            'debug'                 => false,

            // Flag intended solely to unit testing convenience
            'debug_disable_flush'   => false,
        );

        // Modify some of the interdependent defaults based on what the user-specified
        if (isset($options['secure'])) {
            $defaults['service_port'] = $option['secure'] ? 9997 : 9998;
        }
        // UDP has significantly lower size contraints
        if (isset($options['transport']) && $options['transport'] == 'udp') {
            $defaults['service_port'] = 9996;
            $defaults['max_log_records'] = 16;
            $defaults['max_span_records'] = 16;
        }

        // Set the options, merged with the defaults
        $this->options(array_merge($defaults, $options));

        if ($this->_options['transport'] == 'udp') {
            $this->_transport = new Transports\TransportUDP();
        } else {
            $this->_transport = new Transports\TransportThrift();
        }

        // Note: the GUID is not generated until the library is initialized
        // as it depends on the access token
        $this->_startTime = $this->_util->nowMicros();
        $this->_reportStartTime = $this->_startTime;
        $this->_lastFlushMicros = $this->_startTime;

        // PHP is (in many real-world contexts) single-threaded and
        // does not have an event loop like Node.js.  Flush on exit.
        $runtime = $this;
        register_shutdown_function(function() use ($runtime) {
            $runtime->flush();
        });
    }

    public function __destruct() {
        $this->flush();
    }

    public function options($options) {

        $this->_options = array_merge($this->_options, $options);

        // Deferred group name / access token initialization is supported (i.e.
        // it is possible to create logs/spans before setting this info).
        if (isset($options['access_token']) && isset($options['group_name'])) {
            $this->_initThriftDataIfNeeded($options['group_name'], $options['access_token']);
        }

        if (isset($options['min_reporting_period_secs'])) {
            $this->_minFlushPeriodMicros = $options['min_reporting_period_secs'] * 1e6;
        }
        if (isset($options['max_reporting_period_secs'])) {
            $this->_maxFlushPeriodMicros = $options['max_reporting_period_secs'] * 1e6;
        }

        $this->_debug = $this->_options['debug'];

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

    private function _initThriftDataIfNeeded($groupName, $accessToken) {

        // Pre-conditions
        if (!is_string($accessToken)) {
            throw new \Exception('access_token must be a string');
        }
        if (!is_string($groupName)) {
            throw new \Exception('group_name must be a string');
        }
        if (!(strlen($accessToken) > 0)) {
            throw new \Exception('access_token must be non-zero in length');
        }
        if (!(strlen($groupName) > 0)) {
            throw new \Exception('group_name must be non-zero in length');
        }

        // Potentially redundant initialization info: only complain if
        // it is inconsistent.
        if ($this->_thriftAuth != NULL || $this->_thriftRuntime != NULL) {
            if ($this->_thriftAuth->access_token !== $accessToken) {
                throw new \Exception('access_token cannot be changed after it is set');
            }
            if ($this->_thriftRuntime->group_name !== $groupName) {
                throw new \Exception('group_name cannot be changed after it is set');
            }
            return;
        }

        // Runtime attributes
        $runtimeAttrs = array(
            'cruntime_platform' => 'php',
            'cruntime_version'  => LIGHTSTEP_VERSION,
            'php_version' => phpversion(),
        );

        // Generate the GUID on thrift initialization as the GUID should be
        // stable for a particular access token / group name combo.
        $this->_guid = $this->_generateStableUUID($accessToken, $groupName);
        $this->_thriftAuth = new \CroutonThrift\Auth(array(
            'access_token' => strval($accessToken),
        ));

        $thriftAttrs = array();
        foreach ($runtimeAttrs as $key => $value) {
            array_push($thriftAttrs, new \CroutonThrift\KeyValue(array(
                'Key' => strval($key),
                'Value' => strval($value),
            )));
        }
        $this->_thriftRuntime = new \CroutonThrift\Runtime(array(
            'guid' => strval($this->_guid),
            'start_micros' => intval($this->_startTime),
            'group_name' => strval($groupName),
            'attrs' => $thriftAttrs,
        ));
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
        $this->_logRecords = array();
        $this->_spanRecords = array();
    }

    public function startSpan() {
        if (!$this->_enabled) {
            return new NoOpSpan;
        }

        $span = new ClientSpan($this);
        $span->setStartMicros($this->_util->nowMicros());
        return $span;
    }

    public function infof($fmt) {
        if (!$this->_enabled) {
            return;
        }
        $this->_log('I', $fmt, func_get_args());
    }

    public function warnf($fmt) {
        if (!$this->_enabled) {
            return;
        }
        $this->_log('W', $fmt, func_get_args());
    }

    public function errorf($fmt) {
        if (!$this->_enabled) {
            return;
        }
        $this->_log('E', $fmt, func_get_args());
    }

    public function fatalf($fmt) {
        if (!$this->_enabled) {
            return;
        }
        $text = $this->_log('F', $fmt, func_get_args());
        die($text);
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
        if (count($this->_logRecords) >= $this->_options["max_log_records"]) {
            $this->flush();
            return;
        }
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

        // The thrift configuration has not yet been set: allow logs and spans
        // to be buffered in this case, but flushes won't yet be possible.
        if ($this->_thriftRuntime == NULL) {
            return;
        }

        if (count($this->_logRecords) == 0 && count($this->_spanRecords) == 0) {
            return;
        }

        // For unit testing
        if ($this->_options['debug_disable_flush']) {
            return;
        }

        $this->_transport->ensureConnection($this->_options);

        // Ensure the log / span GUIDs are set correctly. This is covers a real
        // case: the runtime GUID cannot be generated until the access token
        // and group name are set (so that is the same GUID between script
        // invocations), but the library allows logs and spans to be buffered
        // prior to setting those values.  Any such 'early buffered' spans need
        // to have the GUID set; for simplicity, the code resets them all.
        foreach ($this->_logRecords as $log) {
            $log->runtime_guid = $this->_guid;
        }
        foreach ($this->_spanRecords as $span) {
            $span->runtime_guid = $this->_guid;
        }

        // Convert the counters to thrift form
        $thriftCounters = array();
        foreach ($this->_counters as $key => $value) {
            array_push($thriftCounters, new \CroutonThrift\NamedCounter(array(
                'Name' => strval($key),
                'Value' => intval($value),
            )));
        }
        $reportRequest = new \CroutonThrift\ReportRequest(array(
            'runtime'         => $this->_thriftRuntime,
            'oldest_micros'   => intval($this->_reportStartTime),
            'youngest_micros' => intval($now),
            'log_records'     => $this->_logRecords,
            'span_records'    => $this->_spanRecords,
            'counters'        => $thriftCounters,
        ));

        $this->_lastFlushMicros = $now;

        $resp = null;
        try {
            // It *is* valid for the transport to return a null response in the
            // case of a low-overhead "fire and forget" report
            $resp = $this->_transport->flushReport($this->_thriftAuth, $reportRequest);
        } catch (\Exception $e) {
            // Exceptions *are* expected as connections can be broken, etc. when
            // reporting. Prevent reporting exceptions from interfering with the
            // client code.
            $this->_debugRecordError($e);
        }

        // ALWAYS reset the buffers and update the counters as the RPC response
        // is, by design, not waited for and not reliable.
        $this->_reportStartTime = $now;
        $this->_logRecords = array();
        $this->_spanRecords = array();
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
     * Generates a random ID (not a *true* UUID).
     */
    public function _generateUUIDString() {
        return sprintf("%08x%08x%08x%08x",
            $this->_util->randInt32(),
            $this->_util->randInt32(),
            $this->_util->randInt32(),
            $this->_util->randInt32()
        );
    }

    /**
     * Internal use only.
     */
    public function _finishSpan(ClientSpan $span) {
        if (!$this->_enabled) {
            return;
        }

        $span->setEndMicros($this->_util->nowMicros());
        $full = $this->pushWithMax($this->_spanRecords, $span->toThrift(), $this->_options["max_span_records"]);
        if ($full) {
            $this->_counters['dropped_spans']++;
        }

        $this->flushIfNeeded();
    }

    /**
     * For internal use only.
     */
    public function _log($level, $fmt, $allArgs) {
        // The $allArgs variable contains the $fmt string
        array_shift($allArgs);
        $text = vsprintf($fmt, $allArgs);

        $this->_rawLogRecord(array(
            'level' => $level,
            'message' => $text,
        ), $allArgs);

        $this->flushIfNeeded();
        return $text;
    }

    /**
     * Internal use only.
     */
    public function _rawLogRecord($fields, $payloadArray) {
        if (!$this->_enabled) {
            return;
        }

        $fields = array_merge(array(
            'timestamp_micros' => intval($this->_util->nowMicros()),
            'runtime_guid' => strval($this->_guid),
        ), $fields);

        // TODO: data scrubbing and size limiting
        if (count($payloadArray) > 0) {
            // $json == FALSE on failure
            //
            // Examples that will cause failure:
            // - "Resources" (e.g. file handles)
            // - Circular references
            // - Exceeding the max depth (i.e. it *does not* trim, it rejects)
            //
            $json = json_encode($payloadArray, 0, $this->_options['max_payload_depth']);
            if (is_string($json)) {
                $fields["payload_json"] = $json;
            }
        }

        $rec = new \CroutonThrift\LogRecord($fields);
        $full = $this->pushWithMax($this->_logRecords, $rec, $this->_options['max_log_records']);
        if ($full) {
            $this->_counters['dropped_logs']++;
        }
    }

    protected function pushWithMax(&$arr, $item, $max) {
        if (!($max > 0)) {
            $max = 1;
        }

        array_push($arr, $item);

        // Simplistic random discard
        $count = count($arr);
        if ($count > $max) {
            $i = $this->_util->randIntRange(0, $max - 1);
            $arr[$i] = array_pop($arr);
            return true;
        } else {
            return false;
        }
    }

    protected function _debugRecordError($e) {
        if ($this->_debug) {
            error_log($e);
        }
    }
}
