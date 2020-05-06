<?php
namespace LightStepBase\Client;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");

class NoOpSpan implements \OpenTracing\Span {
    public function guid() { return ""; }
    public function setRuntimeGUID($guid) {}
    public function traceGUID() { return ""; }
    public function setTraceGUID($traceGUID) {}

    public function overwriteOperationName($name) {}
    public function addTraceJoinId($key, $value) {}

    public function setEndUserId($id) {}
    public function getContext() {}
    public function getOperationName() {}

    public function tracer() { return LightStep::getInstance(); }
    public function setTag($key, $value) {}
    public function addBaggageItem($key, $value) {}
    public function getBaggageItem($key) {}
    public function getBaggage() { return []; }

    public function logEvent($event, $payload = NULL) {}
    public function log(array $fields = [], $timestamp = NULL) {}

    public function setParent($span) {}
    public function setParentGUID($parentGUID) {}

    public function finish($finishTime = NULL) {}

    public function infof($fmt) {}
    public function warnf($fmt) {}
    public function errorf($fmt) {}
    public function fatalf($fmt) {}
}
