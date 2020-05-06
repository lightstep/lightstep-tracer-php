<?php
namespace LightStepBase\Client;

use Lightstep\Collector\Reference;
use Lightstep\Collector\Reference\Relationship;
use Lightstep\Collector\Span;
use Lightstep\Collector\SpanContext;
use Google\Protobuf\Timestamp;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");

class ClientSpan implements \OpenTracing\Span {

    protected $_tracer = NULL;

    protected $_guid = "";
    protected $_traceGUID = "";
    protected $_operation = "";
    protected $_tags = [];
    protected $_baggage = [];
    protected $_startMicros = 0;
    protected $_endMicros = 0;
    protected $_errorFlag = false;
    protected $_runtimeGUID = "";

    protected $_joinIds = [];
    protected $_logRecords = [];

    protected $maxPayloadDepth = 0;

    public function __construct($tracer, $maxPayloadDepth) {
        $this->_tracer = $tracer;
        $this->_traceGUID = $tracer->_generateUUIDString();
        $this->_guid = $tracer->_generateUUIDString();
        $this->$maxPayloadDepth = $maxPayloadDepth;
    }

    public function __destruct() {
        // Use $_endMicros as a indicator this span has not been finished
        if ($this->_endMicros == 0) {
            $this->warnf("finish() never closed on span (operaton='%s')", $this->_operation, $this->_joinIds);
            $this->finish();
        }
    }

    public function tracer() {
        return $this->_tracer;
    }

    public function guid() {
        return $this->_guid;
    }

    public function setRuntimeGUID($guid) {
        $this->_runtimeGUID = $guid;
    }

    public function traceGUID() {
        return $this->_traceGUID;
    }

    public function setTraceGUID($guid) {
        $this->_traceGUID = $guid;
        return $this;
    }

    public function setStartMicros($start) {
        $this->_startMicros = $start;
        return $this;
    }

    public function setEndMicros($start) {
        $this->_endMicros = $start;
        return $this;
    }

    public function getOperationName() {
        return $this->_operation;
    }

    public function getContext() {
        return new ClientSpanContext(
            $this->traceGUID(),
            $this->guid(),
            true,
            $this->getBaggage()
        );
    }

    public function finish($finishTime = NULL) {
        $this->_tracer->_finishSpan($this, $finishTime);
    }

    public function overwriteOperationName($name) {
        $this->_operation = $name;
        return $this;
    }

    public function addTraceJoinId($key, $value) {
        $this->_joinIds[$key] = $value;
        return $this;
    }

    public function setTags($fields) {
        foreach ($fields as $key => $value) {
            $this->setTag($key, $value);
        }
        return $this;
    }

    public function setTag($key, $value) {
        $this->_tags[$key] = $value;
        return $this;
    }

    public function addBaggageItem($key, $value) {
        $this->_baggage[$key] = $value;
        return $this;
    }

    public function getBaggageItem($key) {
        return $this->_baggage[$key];
    }

    public function getBaggage() {
        return $this->_baggage;
    }

    public function setParent($span) {
        // Inherit any join IDs from the parent that have not been explicitly
        // set on the child
        foreach ($span->_joinIds as $key => $value) {
            if (!array_key_exists($key, $this->_joinIds)) {
                $this->_joinIds[$key] = $value;
            }
        }

        $this->_traceGUID = $span->_traceGUID;
        $this->setTag("parent_span_guid", $span->guid());
        return $this;
    }

    public function setParentGUID($guid) {
        $this->setTag("parent_span_guid", $guid);
        return $this;
    }

    public function getParentGUID() {
        if (array_key_exists('parent_span_guid', $this->_tags)) {
            return $this->_tags['parent_span_guid'];
        }
        return NULL;
    }

    public function logEvent($event, $payload = NULL) {
        $this->log([
            'event' => strval($event),
            'payload' => $payload,
        ]);
    }

    public function log(array $fields = [], $timestamp = NULL) {
        $record = [
            'span_guid' => strval($this->_guid),
        ];
        $payload = NULL;

        if (!empty($fields['event'])) {
            $record['event'] = strval($fields['event']);
        }

        if ($timestamp) {
            $record['timestamp_micros'] = intval(1000 * $timestamp);
        } else if (!empty($fields['timestamp'])) {
            $record['timestamp_micros'] = intval(1000 * $fields['timestamp']);
        }
        // no need to verify value of fields['payload'] as it will be checked by _rawLogRecord
        $this->_rawLogRecord($record, $fields['payload']);
    }

    public function infof($fmt) {
        $this->_log('I', false, $fmt, func_get_args());
        return $this;
    }

    public function warnf($fmt) {
        $this->_log('W', false, $fmt, func_get_args());
        return $this;
    }

    public function errorf($fmt) {
        $this->_errorFlag = true;
        $this->_log('E', true, $fmt, func_get_args());
        return $this;
    }

    public function fatalf($fmt) {
        $this->_errorFlag = true;
        $text = $this->_log('F', true, $fmt, func_get_args());
        die($text);
    }

    protected function _log($level, $errorFlag, $fmt, $allArgs) {
        // The $allArgs variable contains the $fmt string
        array_shift($allArgs);
        $text = vsprintf($fmt, $allArgs);

        $this->_rawLogRecord([
            'span_guid' => strval($this->_guid),
            'level' => $level,
            'error_flag' => $errorFlag,
            'message' => $text,
        ], $allArgs);
        return $text;
    }

    /**
     * Internal use only.
     */
    public function _rawLogRecord($fields, $payloadArray) {
        $fields['runtime_guid'] = strval($this->_guid);

        if (empty($fields['timestamp_micros'])) {
            $fields['timestamp_micros'] = intval(Util::nowMicros());
        }

        // TODO: data scrubbing and size limiting
        if (!empty($payloadArray)) {
            // $json == FALSE on failure
            //
            // Examples that will cause failure:
            // - "Resources" (e.g. file handles)
            // - Circular references
            // - Exceeding the max depth (i.e. it *does not* trim, it rejects)
            //
            $json = json_encode($payloadArray, 0, $this->maxPayloadDepth);
            if (is_string($json)) {
                $fields["payload_json"] = $json;
            }
        }

        $rec = new LogRecord($fields);
        $this->_logRecords[] = $rec;
    }

    public function toThrift() {
        // Coerce all the types to strings to ensure there are no encoding/decoding
        // issues
        $joinIds = [];
        foreach ($this->_joinIds as $key => $value) {
            $pair = new \CroutonThrift\TraceJoinId([
                "TraceKey" => strval($key),
                "Value"    => strval($value),
            ]);
            $joinIds[] = $pair;
        }

        $tags = [];
        foreach ($this->_tags as $key => $value) {
            $pair = new \CroutonThrift\KeyValue([
                "Key"      => strval($key),
                "Value"    => strval($value),
            ]);
            $tags[] = $pair;
        }

        // Convert the logs to thrift form
        $thriftLogs = [];
        foreach ($this->_logRecords as $lr) {
            $lr->runtime_guid = $this->_runtimeGUID;
            $thriftLogs[] = $lr->toThrift();
        }

        $rec = new \CroutonThrift\SpanRecord([
            "runtime_guid"    => strval($this->_runtimeGUID),
            "span_guid"       => strval($this->_guid),
            "trace_guid"      => strval($this->_traceGUID),
            "span_name"       => strval($this->_operation),
            "oldest_micros"   => intval($this->_startMicros),
            "youngest_micros" => intval($this->_endMicros),
            "join_ids"        => $joinIds,
            "error_flag"      => $this->_errorFlag,
            "attributes"      => $tags,
            "log_records"     => $thriftLogs,
        ]);
        return $rec;
    }

    /**
     * @return Span A Proto representation of this object.
     */
    public function toProto() {
        $spanContext = new SpanContext([
            'trace_id' => Util::hexdec($this->traceGUID()),
            'span_id' => Util::hexdec($this->guid()),
        ]);

        $ts = new Timestamp([
            'seconds' => floor($this->_startMicros / 1000000),
            'nanos' => ($this->_startMicros % 1000000) * 100,
        ]);

        $tags = [];
        foreach ($this->_tags as $key => $value) {
            if ($key == 'parent_span_guid') {
                continue;
            }
            $protoTag = new \Lightstep\Collector\KeyValue([
                'key' => $key,
                'string_value' => $value,
            ]);
            $tags[] = $protoTag;
        }

        $logs = [];
        foreach ($this->_logRecords as $log) {
            $logs[] = $log->toProto();
        }

        $references = [];
        if ($this->getParentGUID() != NULL) {
            $parentSpanContext = new SpanContext([
                'trace_id' => Util::hexdec($this->traceGUID()),
                'span_id' => Util::hexdec($this->getParentGUID())
            ]);

            $ref = new Reference([
                'span_context' => $parentSpanContext,
                'relationship' => Relationship::CHILD_OF
            ]);
            $references[] = $ref;
        }

        return new Span([
            'span_context' => $spanContext,
            'operation_name' => strval($this->_operation),
            'start_timestamp' => $ts,
            'duration_micros' => $this->_endMicros-$this->_startMicros,
            'tags' => $tags,
            'logs' => $logs,
            'references' => $references,
        ]);
    }

    /** Deprecated */
    public function setEndUserId($id) {
        $this->addTraceJoinId("LIGHTSTEP_JOIN_KEY_END_USER_ID", $id);
        return $this;
    }
}
