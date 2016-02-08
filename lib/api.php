<?php

namespace LightStepBase;

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
interface Runtime {

    /**
     * Creates a span object to record the start and finish of an application
     * operation.  The span object can then be used to record further data
     * about this operation, such as which user it is being done on behalf
     * of and log records with arbitrary payload data.
     *
     * @return ActiveSpan
     */
    public function startSpan();

    /**
     * Creates a printf-style log statement that will be associated with
     * this particular operation instance.
     *
     * All arguments after the format string will automatically be captured
     * as part of the log payload.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function infof($fmt);

    /**
     * Creates a printf-style warning log statement that will be associated with
     * this particular operation instance.
     *
     * All arguments after the format string will automatically be captured
     * as part of the log payload.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function warnf($fmt);

    /**
     * Creates a printf-style error log statement that will be associated with
     * this particular operation instance.
     *
     * All arguments after the format string will automatically be captured
     * as part of the log payload.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function errorf($fmt);

    /**
     * Creates a printf-style fatal log statement that will be associated with
     * this particular operation instance.
     *
     * All arguments after the format string will automatically be captured
     * as part of the log payload.
     *
     * Note: a fatal log will exit the PHP process after the log record is
     * created.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function fatalf($fmt);

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
}

/**
 * Interface for the handle to an active span.
 */
interface ActiveSpan {
    /**
     * Sets the name of the operation that the span represents.
     *
     * @param string $name name of the operation
     */
    public function setOperation($name);

    /**
     * Sets a string uniquely identifying the user on behalf the
     * span operation is being run. This may be an identifier such
     * as unique username or any other application-specific identifier
     * (as long as it is used consistently for this user).
     *
     *
     *
     * @param string $id a unique identifier of the
     */
    public function setEndUserId($id);

    /**
     * Explicitly associates this span as a child operation of the
     * given parent operation. This provides the instrumentation with
     * additional information to construct the trace.
     *
     * @param ActiveSpan $span the parent span of this span
     */
    public function setParent($span);


    /**
     * Sets a trace join ID key-value pair.
     *
     * @param string $key the trace key
     * @param string $value the value to associate with the given key.
     */
    public function addTraceJoinId($key, $value);

    /**
     * Adds an attribute to the span.
     *
     * Note: this is currently intended for internal use only.
     */
    public function addAttribute($key, $value);


    /**
     * Finishes the active span. This should always be called at the
     * end of the logical operation that the span represents.
     */
    public function finish();

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
     * If the runtime is enabled, the implementation *will* call die() after
     * creating the log (if the runtime is disabled, the log record will
     * not be created and the die() call will not be made).
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function errorf($fmt);

    /**
     * Creates a printf-style fatal log statement that will be associated with
     * this particular operation instance.
     *
     * @param string $fmt a format string as accepted by sprintf
     */
    public function fatalf($fmt);

    /**
     * Returns the unique identifier for the span instance.
     *
     * @return string
     */
    public function guid();
}
