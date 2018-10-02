<?php

namespace LightStepBase\Client;


/**
 * Class ReportRequest encapsulates all of the information required to make an RPC call to the LightStep satellite.
 * @package LightStepBase\Client
 */
class ReportRequest
{
    protected $_runtime = null;
    protected $_reportStartTime = 0;
    protected $_now = 0;
    protected $_logRecords = null;
    protected $_spanRecords = null;
    protected $_counters = null;

    /**
     * ReportRequest constructor.
     * @param Runtime $runtime
     * @param int $reportStartTime
     * @param int $now
     * @param array $logRecords
     * @param array $spanRecords
     * @param array $counters
     */
    public function __construct($runtime, $reportStartTime, $now, $logRecords, $spanRecords, $counters) {
        $this->_runtime = $runtime;
        $this->_reportStartTime = $reportStartTime;
        $this->_now = $now;
        $this->_logRecords = $logRecords;
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

        // Convert the logs to thrift form
        $thriftLogs = [];
        foreach ($this->_logRecords as $lr) {
            $thriftLogs[] = $lr->toThrift();
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
            'log_records'     => $thriftLogs,
            'span_records'    => $thriftSpans,
            'counters'        => $thriftCounters,
        ]);
    }
}