<?php

namespace LightStepBase;

// TODO: these constants should be replaced with OpenTracing constants as soon
// as a OpenTracing PHP library exists.
define("LIGHTSTEP_FORMAT_TEXT_MAP", "LIGHTSTEP_FORMAT_TEXT_MAP");
define("LIGHTSTEP_FORMAT_BINARY", "LIGHTSTEP_FORMAT_BINARY");

/**
 *@internal
 *
 * The trace join ID key used for identifying the end user.
 */
define("LIGHTSTEP_JOIN_KEY_END_USER_ID", "end_user_id");

/**
 * Interface for the instrumentation library.
 *
 * This interface is most commonly accessed via LightStep::getInstance()
 * singleton.
 */
interface Tracer {

    // ---------------------------------------------------------------------- //
    // OpenTracing API
    // ---------------------------------------------------------------------- //

    /**
     * Creates a span object to record the start and finish of an application
     * operation.  The span object can then be used to record further data
     * about this operation, such as which user it is being done on behalf
     * of and log records with arbitrary payload data.
     *
     * @param string $operationName the logical name to use for the operation
     *                              this span is tracking
     * @param array $fields optional array of key-value pairs. Valid pairs are:
     *        'parent' Span the span to use as this span's parent
     *        'tags' array string-string pairs to set as tags on this span
     *        'startTime' float Unix time (in milliseconds) representing the
     *        					start time of this span. Useful for retroactively
     *        					created spans.
     * @return Span
     */
    public function startSpan($operationName, $fields);

    /**
     * Copies the span data into the given carrier object.
     *
     * See http://opentracing.io/spec/#inject-and-join.
     *
     * @param  Span $span the span object that will populate $carrier
     * @param  string $format the OpenTracing constant for the format of $carrier
     * @param  mixed $carrier the carrier object; the type depends on the $format
     */
    public function inject(Span $span, $format, &$carrier);

    /**
     * Creates a new span data from the given carrier object.
     *
     * See http://opentracing.io/spec/#inject-and-join.
     *
     * @param  string $operationName operation name to use for the newly created
     *                               span
     * @param  string $format the OpenTracing constant for the format of the
     *                        carrier object
     * @param  mixed $carrier carrier object; the type depends on $format
     * @return Span the newly created Span
     */
    public function join($operationName, $format, $carrier);

    // ---------------------------------------------------------------------- //
    // LightStep Extentsions
    // ---------------------------------------------------------------------- //

    /**
     * Manually causes any buffered log and span records to be flushed to the
     * server. In most cases, explicit calls to flush() are not required as the
     * logs and spans are sent incrementally over time and at process exit.
     */
    public function flush();

    /**
     * Returns the generated unique identifier for the runtime.
     *
     * Note: the value is only valid *after* the runtime has been initialized.
     * If called before initialization, this method will return zero.
     *
     * @return int runtime GUID or zero if called before initialization
     */
    public function guid();

    /**
     * Disables all functionality of the runtime.  All methods are effectively
     * no-ops when in disabled mode.
     */
    public function disable();

    /**
     * Currently for internal use only.
     *
     * @param array $options
     */
    public function options($options);
}

/**
 * Interface for the handle to an active span.
 */
interface Span {

    // ---------------------------------------------------------------------- //
    // OpenTracing API
    // ---------------------------------------------------------------------- //

    /**
     * Sets the name of the operation that the span represents.
     *
     * @param string $name name of the operation
     */
    public function setOperationName($name);

    /**
     * Finishes the active span. This should always be called at the
     * end of the logical operation that the span represents.
     */
    public function finish();

    /**
     * Returns the instance of the Tracer that created the Span.
     *
     * @return Tracer the instance of the Tracer that created this Span.
     */
    public function tracer();

    /**
     * Sets a tag on the span.  Tags belong to a span instance itself and are
     * not transferred to child or across process boundaries.
     *
     * @param string key the key of the tag
     * @param string value the value of the tag
     */
    public function setTag($key, $value);

    /**
     * Sets a baggage item on the span.  Baggage is transferred to children and
     * across process boundaries; use sparingly.
     *
     * @param string key the key of the baggage item
     * @param string value the value of the baggage item
     */
    public function setBaggageItem($key, $value);

    /**
     * Gets a baggage item on the span.
     *
     * @param string key the key of the baggage item
     */
    public function getBaggageItem($key);

    /**
     * Logs a stably named event along with an optional payload and associates
     * it with the span.
     *
     * @param string event the name used to identify the event
     * @param mixed payload any data to be associated with the event
     */
    public function logEvent($event, $payload = NULL);

    /**
     * Logs a stably named event along with an optional payload and associates
     * it with the span.
     *
     * @param array fields a set of key-value pairs for specifying an event.
     *        'event' string, required the stable name of the event
     *        'payload' mixed, optional any data to associate with the event
     *        'timestamp' float, optional Unix time (in milliseconds)
     *        		representing the event time.
     */
    public function log($fields);

    // ---------------------------------------------------------------------- //
    // LightStep Extentsions
    // ---------------------------------------------------------------------- //

    /**
     * Returns the unique identifier for the span instance.
     *
     * @return string
     */
    public function guid();

    /**
     * Returns the unique identifier for the trace of which this span is a part.
     *
     * @return string
     */
    public function traceGUID();

    /**
     * Sets the GUID of the containing trace onto this span.
     *
     * @param string $traceGUID
     */
    public function setTraceGUID($traceGUID);

    /**
     * Explicitly associates this span as a child operation of the
     * given parent operation. This provides the instrumentation with
     * additional information to construct the trace.
     *
     * @param Span $span the parent span of this span
     */
    public function setParent($span);

    /**
     * Explicitly associates this span as a child operation of the
     * span identified by the given GUID. This provides the
     * instrumentation with additional information to construct the
     * trace.
     * @param string $parentGUID
     */
    public function setParentGUID($parentGUID);

    /**
     * Fetches all baggage for this span.
     *
     * @return array
     */
    public function getBaggage();

    /**
     * Creates a printf-style log statement that will be associated with
     * this particular operation instance.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function infof($fmt);

    /**
     * Creates a printf-style warning log statement that will be associated with
     * this particular operation instance.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function warnf($fmt);

    /**
     * Creates a printf-style error log statement that will be associated with
     * this particular operation instance.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function errorf($fmt);

    /**
     * Creates a printf-style fatal log statement that will be associated with
     * this particular operation instance.
     *
     * If the runtime is enabled, the implementation *will* call die() after
     * creating the log. If the runtime is disabled, the log record will
     * not be created and the die() call will not be made.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function fatalf($fmt);

    /**
     * Provides a mechanism to prevent fatalf from calling die() after
     * creating a log.
     *
     * @param bool $dieOnFatal
     */
    public function setDieOnFatal($dieOnFatal);

    // ---------------------------------------------------------------------- //
    // Deprecated
    // ---------------------------------------------------------------------- //

    /**
     * Sets a string uniquely identifying the user on behalf the
     * span operation is being run. This may be an identifier such
     * as unique username or any other application-specific identifier
     * (as long as it is used consistently for this user).
     *
     *
     *
     * @param string $id a unique identifier of the current user
     */
    public function setEndUserId($id);

    /**
     * Sets a trace join ID key-value pair.
     *
     * @param string $key the trace key
     * @param string $value the value to associate with the given key.
     */
    public function addTraceJoinId($key, $value);

}
