<?php
namespace LightStepBase\Client;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");

class NoOpSpan implements \LightStepBase\Span {
    public function guid() { return ""; }
    public function traceGUID() { return ""; }
    public function setTraceGUID($traceGUID) {}

    public function setOperationName($name) {}
    public function addTraceJoinId($key, $value) {}

    public function setEndUserId($id) {}

    public function tracer() { return LightStep::getInstance(); }
    public function setTag($key, $value) {}
    public function setBaggageItem($key, $value) {}
    public function getBaggageItem($key) {}
    public function getBaggage() { return []; }

    public function logEvent($event, $payload = NULL) {}
    public function log($fields) {}

    public function setParent($span) {}
    public function setParentGUID($parentGUID) {}


    public function finish() {}

    public function infof($fmt) {}
    public function warnf($fmt) {}
    public function errorf($fmt) {}
    public function fatalf($fmt) {}
}
