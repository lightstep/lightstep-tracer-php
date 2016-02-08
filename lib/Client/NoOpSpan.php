<?php
namespace LightStepBase\Client;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");

class NoOpSpan implements \LightStepBase\ActiveSpan {
    public function guid() { return ""; }

    public function setOperation($name) {}
    public function addTraceJoinId($key, $value) {}

    public function setEndUserId($id) {}

    public function addAttribute($key, $value) {}

    public function setParent($span) {}

    public function finish() {}

    public function infof($fmt) {}
    public function warnf($fmt) {}
    public function errorf($fmt) {}
    public function fatalf($fmt) {}
}
