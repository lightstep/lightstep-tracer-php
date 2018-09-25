<?php
namespace LightStepBase\Client;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");

class Runtime
{
    protected $_guid = "";
    protected $_start_micros = 0;
    protected $_group_name = "";
    protected $_attrs = null;

    public function __construct($guid, $start_micros, $group_name, $attrs) {
        $this->_guid = $guid;
        $this->_start_micros = $start_micros;
        $this->_group_name = $group_name;
        $this->_attrs = $attrs;
    }

    public function getGroupName() {
        return $this->_group_name;
    }

    public function toThrift()
    {
        $thriftAttrs = [];
        foreach ($this->_attrs as $attr) {
            array_push($thriftAttrs, new \CroutonThrift\KeyValue($attr->GetKey(), $attr->GetValue()));
        }
        return new \CroutonThrift\Runtime([
            'guid' => $this->_guid,
            'start_micros' => $this->_start_micros,
            'group_name' => $this->_group_name,
            'attrs' => $thriftAttrs,
        ]);
    }
}