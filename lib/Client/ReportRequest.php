<?php

namespace LightStepBase\Client;

use Lightstep\Collector\MetricsSample;
use Lightstep\Collector\InternalMetrics;
use Lightstep\Collector\ReportRequest as ProtoReportRequest;

/**
 * Class ReportRequest encapsulates all of the information required to make an RPC call to the LightStep satellite.
 * @package LightStepBase\Client
 */
class ReportRequest
{
    protected $_runtime = NULL;
    protected $_reportStartTime = 0;
    protected $_now = 0;
    protected $_spanRecords = NULL;
    protected $_counters = NULL;

    /**
     * ReportRequest constructor.
     * @param Runtime $runtime
     * @param int $reportStartTime
     * @param int $now
     * @param array $spanRecords
     * @param array $counters
     */
    public function __construct($runtime, $reportStartTime, $now, $spanRecords, $counters) {
        $this->_runtime = $runtime;
        $this->_reportStartTime = $reportStartTime;
        $this->_now = $now;
        $this->_spanRecords = $spanRecords;
        $this->_counters = $counters;
    }

    /**
     * @return \CroutonThrift\ReportRequest A Thrift representation of this object.
     */
    public function toThrift() {
        // Convert the counters to thrift form
        $thriftCounters = [];
        foreach ($this->_counters as $key => $value) {
            $thriftCounters[] = new \CroutonThrift\NamedCounter([
                'Name' => strval($key),
                'Value' => intval($value),
            ]);
        }

        // Convert the spans to thrift form
        $thriftSpans = [];
        foreach ($this->_spanRecords as $sr) {
            $thriftSpans[] = $sr->toThrift();
        }

        return new \CroutonThrift\ReportRequest([
            'runtime'         => $this->_runtime->toThrift(),
            'oldest_micros'   => $this->_reportStartTime,
            'youngest_micros' => $this->_now,
            'span_records'    => $thriftSpans,
            'counters'        => $thriftCounters,
        ]);
    }

    /**
     * @param Auth $auth
     * @return ProtoReportRequest A Proto representation of this object.
     */
    public function toProto($auth) {
        $counts = [];
        foreach ($this->_counters as $key => $value) {
            $counts[] = new MetricsSample([
                'name' => strval($key),
                'int_value' => intval($value),
            ]);
        }
        $internalMetrics = new InternalMetrics([
            'counts' => $counts
        ]);

        $spans = [];
        foreach ($this->_spanRecords as $sr) {
            $spans[] = $sr->toProto();
        }
        return new ProtoReportRequest([
            'auth' => $auth->toProto(),
            'internal_metrics' => $internalMetrics,
            'reporter' => $this->_runtime->toProto(),
            'spans' => $spans,
        ]);
    }
}