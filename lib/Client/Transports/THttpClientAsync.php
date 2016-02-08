<?php
/*
 * This code in this file is forked from:
 * apache/thrift/lib/php/lib/Thrift/Transport/THttpClient.php
 * which is licensed under http://www.apache.org/licenses/LICENSE-2.0
 */
namespace LightStepBase\Client\Transports;

use Thrift\Transport\TTransport;
use Thrift\Exception\TTransportException;
use Thrift\Factory\TStringFuncFactory;

/**
 * A custom THTTPClient that does an asynchronous HTTP POST in order to not
 * block the user thread.  The response from the request is NOT available when
 * using this TTransport.
 */
class THttpClientAsync extends TTransport {

    /**
     * Timeout for opening the persistent socket.
     */
    const DEFAULT_CONNECT_TIMEOUT_SECS = 0.5;

    /**
     * Stream timeout
     */
    const DEFAULT_STREAM_TIMEOUT_MICROS = 1e5;

    /**
     * On a socket open failure, number of times to retry regardless of the
     * error.
     */
    const MAX_SOCKET_OPEN_RETRIES = 1;

    /**
     * Max bytes per single write to the socket.
     */
    const MAX_BYTES_PER_WRITE = 8192;

    /**
     * The host to connect to
     *
     * @var string
     */
    protected $host_;

    /**
     * The port to connect on
     *
     * @var int
     */
    protected $port_;

    /**
     * The URI to request
     *
     * @var string
     */
    protected $uri_;

    /**
     * The scheme to use for the request, i.e. http, https
     *
     * @var string
     */
    protected $scheme_;

    /**
    * Buffer for the HTTP request data
    *
    * @var string
    */
    protected $buf_;

    /**
    * Read timeout
    *
    * @var float
    */
    protected $timeout_;

    /**
    * http headers
    *
    * @var array
    */
    protected $headers_;


    /**
     * Persistent socket
     * @var resource
     */
    protected $socket_;

    /**
     * Enable additional logging and runtime checks.
     * @var boolean
     */
    protected $debug_;


    /**
     * Buffer containing the RPC response before it is read back by read()
     * calls.  THttpClientAsync populates this with a fixed empty response on
     * every flush() to avoid the overhead of the actual readback.
     */
    protected $respBuffer_;

    /**
     * Hard-coded binary representation of an empty response from the server.
     * Using this as a proxy for the actual response allows the rest of the
     * Thrift of the pipeline to continue on its way without waiting for the
     * actual socket data.
     */
    protected $emptyResponse_;

    /**
     * Make a new HTTP client.
     *
     * @param string $host
     * @param int    $port
     * @param string $uri
     */
    public function __construct($host, $port=80, $uri='', $secure = TRUE, $debug = FALSE) {
        if ((TStringFuncFactory::create()->strlen($uri) > 0) && ($uri{0} != '/')) {
          $uri = '/'.$uri;
        }
        $this->scheme_ = ($secure ? 'ssl' : 'tcp');
        $this->host_ = $host;
        $this->port_ = $port;
        $this->uri_ = $uri;
        $this->buf_ = '';
        $this->respBuffer_ = '';
        $this->timeout_ = null;
        $this->headers_ = array();
        $this->socket_ = null;
        $this->debug_ = $debug;

        // Hard-coded empty thrift ReportResponse in the binary protocol.
        // Ideally this would be generated dynamically based on the active RPC.
        $this->emptyResponse_ = "\x80\x01\x00\x02\x00\x00\x00\x06\x52\x65\x70\x6f\x72\x74\x00\x00\x00\x00\x0c\x00\x00\x0c\x00\x02\x0a\x00\x01\x00\x05\x23\xbf\x9d\x4b\x96\x51\x0a\x00\x02\x00\x05\x23\xbf\x9d\x4b\x96\x96\x00\x00\x00\x00";
    }

    public function __destruct() {
        $this->_closeSocket();
    }

    /**
    * Set read timeout
    *
    * @param float $timeout
    */
    public function setTimeoutSecs($timeout) {
        $this->timeout_ = $timeout;
    }

    /**
     * Whether this transport is open.
     *
     * @return boolean true if open
     */
    public function isOpen() {
        return true;
    }

    /**
     * Open the transport for reading/writing
     *
     * @throws TTransportException if cannot open
     */
    public function open() {}

    /**
     * Close the transport.
     */
    public function close() {
        $this->_closeSocket();
    }

    /**
    * Read some data into the array.
    *
    * @param int    $len How much to read
    * @return string The data that has been read
    * @throws TTransportException if cannot read any more data
    */
    public function read($len) {
        // Do a non-blocking read to clear any buffering, even though
        // the code doesn't use the results
        if (is_resource($this->socket_)) {
            @fread($this->socket_, max($len, 8192));
        }

        // Return data from the hard-coded response buffer
        $part = substr($this->respBuffer_, 0, $len);
        $this->respBuffer_ = substr($this->respBuffer_, $len);
        return $part;
    }

    /**
    * Writes some data into the pending buffer
    *
    * @param string $buf  The data to write
    * @throws TTransportException if writing fails
    */
    public function write($buf) {
        $this->buf_ .= $buf;
    }

    /**
    * Opens and sends the actual request over the HTTP connection
    *
    * @throws TTransportException if a writing error occurs
    */
    public function flush() {
        $headerHost = $this->host_.($this->port_ != 80 ? ':'.$this->port_ : '');

        $headers = array();
        $defaultHeaders = array('Host' => $headerHost,
                                'Accept' => 'application/x-thrift',
                                'User-Agent' => 'PHP/THttpClient',
                                'Content-Type' => 'application/x-thrift',
                                'Content-Length' => TStringFuncFactory::create()->strlen($this->buf_),
                            );
        foreach (array_merge($defaultHeaders, $this->headers_) as $key => $value) {
            $headers[] = "$key: $value";
        }

        $body = "POST " . $this->uri_ . " HTTP/1.1\r\n";
        foreach ($headers as $val) {
            $body .= "$val\r\n";
        }
        $body .= "\r\n";
        $body .= $this->buf_;

        // Set the hard-coded response.
        $this->respBuffer_ = $this->emptyResponse_;

        if ($this->_ensureSocketCreated()) {
            $this->_writeStream($body);
        } else if ($this->debug_) {
            error_log("Failed to create socket");
        }

        $this->buf_ = '';
    }

    public function addHeaders($headers) {
        $this->headers_ = array_merge($this->headers_, $headers);
    }

    /**
     * Helper to write the given buffer to the persistent socket.
     */
    protected function _writeStream($buffer) {
        $fd = $this->socket_;
        $total = TStringFuncFactory::create()->strlen($buffer);

        // Early out on the trivial case
        if ($total <= 0) {
            return;
        }

        $failed = FALSE;
        $sent = 0;

        while (!$failed && $sent < $total) {

            if (!is_resource($fd)) {
                $failed = TRUE;
            }

            try {
                // Supress any error messages as it is considered part of normal
                // operation for the write to fail on a broken pipe or timeout
                $written = @fwrite($fd, $buffer, self::MAX_BYTES_PER_WRITE);

                if ($written > 0){
                    $sent += $written;
                    $buffer = substr($buffer, $written);

                } else if ($written === FALSE) {
                    if ($this->debug_) {
                        error_log("Write failed.");
                    }
                    $failed = TRUE;

                } else if ($written === 0) {
                    if ($this->debug_) {
                        error_log("Zero bytes written to socket. sent=$sent total=$total");
                    }
                    $failed = TRUE;
                } else {
                    if ($this->debug_) {
                        error_log("Unexpected fwrite return value '$written'");
                    }
                    $failed = TRUE;
                }

                if ($this->debug_) {
                    error_log("Written = $written bytes. Sent $sent of $total.");
                }

            } catch (Exception $e) {
                if ($this->debug_) {
                    error_log($e);
                }
                $failed = TRUE;
            }
        }

        if ($failed) {
            $this->_closeSocket();
        }
    }

    /**
     * Intended for debugging purposes only. The response is ignored in normal
     * production circumstances as not to block the calling process.
     */
    protected function _readStream() {
        if (!$this->debug_) {
            error_log('Intended as a debug-only function');
            return;
        }

        if (!is_resource($this->socket_)) {
            error_log("Invalid socket handle");
            return;
        }

        $buffer = "";
        $start = microtime(TRUE);
        while (!feof($this->socket_)) {
            $meta = stream_get_meta_data($this->socket_);
            error_log("socket status (". sprintf("%.2f", microtime(TRUE) - $start) . "s): ". json_encode($meta));

            $read = fread($this->socket_, 8192);
            if (strlen($read) == 0) {
                usleep(1e5);
            }
            $buffer .= $read;
        }
        $end = microtime(TRUE);
        error_log("Read took: " . ($end - $start) . " seconds.");

        $meta = stream_get_meta_data($this->socket_);
        $headers = array();
        foreach (explode("\n", $buffer) as $line) {
            $pair = explode(":", $line);
            if (count($pair) == 2) {
                $key = $pair[0];
                $value = $pair[1];
                $headers[$key] = $value;
            }
        }
        error_log("Response headers: " . json_encode($headers));
    }

    /**
     * Create the persistent socket connection if necessary.  Otherwise, do
     * nothing.
     */
    protected function _ensureSocketCreated() {
        // Already ready!
        if (is_resource($this->socket_)) {
            return TRUE;
        }

        $sockaddr = $this->scheme_ . '://' . $this->host_;
        $port = $this->port_;

        // Ignore the Thrift specified timeout ($this->timeout_) and use the
        // socket-specific timeout set in this file.
        $timeout = self::DEFAULT_CONNECT_TIMEOUT_SECS;

        for ($retry = 0; $retry < self::MAX_SOCKET_OPEN_RETRIES; $retry++) {
            try {
                // Suppress connection error logs
                $fd = @pfsockopen($sockaddr, $port, $errno, $errstr, $timeout);
                if ($errno == 0 && is_resource($fd)) {
                    // Connection okay - break out of the retry loop
                    stream_set_blocking($fd, 0);
                    stream_set_timeout($fd, 0, self::DEFAULT_STREAM_TIMEOUT_MICROS);
                    $this->socket_ = $fd;
                    break;
                }
            } catch (Exception $e) {
                // Ignore the exception and retry
            }
        }

        return is_resource($this->socket_);
    }

    /**
     * Close the persistent connection. Note this does not necessarily close the
     * socket itself, as it is persisted, but the handle this process has to it.
     */
    protected function _closeSocket() {
        if ($this->debug_) {
            error_log("Closing socket.");
        }

        $fd = $this->socket_;
        if (is_resource($fd)) {
            $this->socket_ = null;
            @fclose($fd);
        }
    }
}
