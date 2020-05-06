<?php

declare(strict_types=1);

namespace LightStepBase\Client;

use ArrayIterator;

class ClientSpanContext implements \OpenTracing\SpanContext {
    /**
     * @var string
     */
    private $traceId;

    /**
     * @var string
     */
    private $spanId;

    /**
     * @var bool
     */
    private $isSampled;

    /**
     * @var array
     */
    private $baggageItems;

    public function __construct(string $traceId, string $spanId, bool $isSampled = true, array $baggageItems = [])
    {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->isSampled = $isSampled;
        $this->baggageItems = $baggageItems;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function isSampled(): bool
    {
        return $this->isSampled;
    }

    public function getBaggage(): array
    {
        return $this->baggageItems;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->baggageItems);
    }

    /**
     * {@inheritdoc}
     */
    public function getBaggageItem($key): ?string
    {
        return array_key_exists($key, $this->baggageItems) ? strval($this->baggageItems[$key]) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function withBaggageItem($key, $value): ?SpanContext
    {
        return new self($this->traceId, $this->spanId, $this->isSampled, array_merge($this->bagaggeItems, [$key => $value]));
    }

}