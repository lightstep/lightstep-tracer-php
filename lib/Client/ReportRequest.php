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
    protected $_runtime = null;
    protected $_reportStartTime = 0;
    protected $_now = 0;
    protected $_spanRecords = null;
    protected $_counters = null;

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
            $count = new MetricsSample();
            $count->setName(strval($key));
            $count->setIntValue(intval($value));
            $counts[] = $count;
        }
        $internalMetrics = new InternalMetrics();
        $internalMetrics->setCounts($counts);

        $spans = [];
        foreach ($this->_spanRecords as $sr) {
            $spans[] = $sr->toProto();
        }
        $report = new ProtoReportRequest();
        $report->setAuth($auth->toProto());
        $report->setInternalMetrics($internalMetrics);
        $report->setReporter($this->_runtime->toProto());
        $report->setSpans($spans);

        return $report;
    }
}