<?php
namespace LightStepBase\Client;

use Lightstep\Collector\Reporter;
use Lightstep\Collector\KeyValue;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");


/**
 * Class Runtime encapsulates the data required to form a Runtime RPC object.
 * @package LightStepBase\Client
 */
class Runtime
{
    protected $_guid = "";
    protected $_start_micros = 0;
    protected $_group_name = "";
    protected $_attrs = null;

    /**
     * Runtime constructor.
     * @param string $guid Unique identifier of the tracer.
     * @param int $start_micros Start time of the tracer.
     * @param string $group_name Name for the component.
     * @param array $attrs Additional attributes, like platform, version.
     */
    public function __construct($guid, $start_micros, $group_name, $attrs) {
        $this->_guid = $guid;
        $this->_start_micros = $start_micros;
        $this->_group_name = $group_name;
        $this->_attrs = $attrs;
    }

    public function getGroupName() {
        return $this->_group_name;
    }

    /**
     * @return \CroutonThrift\Runtime A Thrift representation of this object.
     */
    public function toThrift()
    {
        $thriftAttrs = [];
        foreach ($this->_attrs as $attr) {
            $thriftAttrs[] = $attr->toThrift();
        }
        return new \CroutonThrift\Runtime([
            'guid' => $this->_guid,
            'start_micros' => $this->_start_micros,
            'group_name' => $this->_group_name,
            'attrs' => $thriftAttrs,
        ]);
    }

    /**
     * @return Reporter A Proto representation of this object.
     */
    public function toProto() {
        $tags = [];
        foreach ($this->_attrs as $attr) {
            $tag = $attr->toProto();
            $tags[] = $tag;
        }
        $tags[] = new KeyValue([
            'key' => 'lightstep.component_name',
            'string_value' => $this->_group_name,
        ]);

        return new Reporter([
            'tags' => $tags,
            'reporter_id' => Util::hexdec($this->_guid),
        ]);
    }
}