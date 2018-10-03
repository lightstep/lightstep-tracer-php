# lightstep-tracer-php

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

## Developer Setup

```
brew install composer
make install
make test
```