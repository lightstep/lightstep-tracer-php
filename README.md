# lightstep-tracer-php [Deprecated]

> ❗ **This instrumentation is no longer recommended**. Please review [documentation on setting up and configuring the OpenTelemetry PHP API and SDK ](https://github.com/open-telemetry/opentelemetry-php) for more information on using OpenTelemetry with PHP.

In August 2023, [Lightstep became ServiceNow
Cloud Observability](https://docs.lightstep.com/docs/banner-faq). To ease the
transition, all code artifacts will continue to use the Lightstep name. You
don't need to do anything to keep using this repository.

[![Latest Stable Version](https://poser.pugx.org/lightstep/tracer/v/stable)](https://packagist.org/packages/lightstep/tracer)
[![Circle CI](https://circleci.com/gh/lightstep/lightstep-tracer-php.svg?style=shield)](https://circleci.com/gh/lightstep/lightstep-tracer-php)
[![MIT license](http://img.shields.io/badge/license-MIT-blue.svg)](http://opensource.org/licenses/MIT)

The LightStep distributed tracing library for PHP.

## Installation

```bash
composer require lightstep/tracer
```

The `lightstep/tracer` package is [available here on packagist.org](https://packagist.org/packages/lightstep/tracer).

## Getting started

```php
<?php

require __DIR__ . '/vendor/autoload.php';

LightStep::initGlobalTracer('examples/trivial_process', '{your_access_token}');

$span = LightStep::startSpan("trivial/loop");
for ($i = 0; $i < 10; $i++) {
    $span->logEvent("loop_iteration", $i);
    echo "The current unix time is " . time() . "\n";
    usleep(1e5);
    $child = LightStep::startSpan("child_span", array(parent => $span));
    usleep(2e5);
    $child->logEvent("hello world");
    $child->finish();
    usleep(1e5);
}
$span->finish();
```

See `lib/api.php` for detailed API documentation.

### Setting collector endpoint and port

You can override the default endpoint and port that spans are sent to by setting `collector_host` and `collector_port` options when initalizing the tracer.

For example when using the global initializer:

```php
LightStep::initGlobalTracer('examples/trivial_process', '{your_access_token}', [
    'collector_host' => '<FDQN or IP>',
    'collector_port' => '<port>'
]);
```

By default the the tracer sends trace data securely to the public LightStep satellites at `collector.lightstep.com` over port `443` using TLS.

## Developer Setup

```bash
brew install composer
make install
make test
```
