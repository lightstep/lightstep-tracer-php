# LightStep Instrumentation Library

[![Circle CI](https://circleci.com/gh/lightstep/lightstep-tracer-php.svg?style=shield)](https://circleci.com/gh/lightstep/lightstep-tracer-php)

## Install with Composer

```bash
composer require lightstep/tracer
```

The `lightstep/tracer` package is [available here on packagist.org](https://packagist.org/packages/lightstep/tracer).

## Instrumentation Example

```php
<?php

require __DIR__ . '/vendor/autoload.php';

LightStep::initGlobalTracer('examples/trivial_process', '{your_access_token}');

$span = LightStep::startSpan("trivial/loop");
for ($i = 0; $i < 10; $i++) {
    $span->logEvent("loop_iteration", $i);
    echo "The current unix time is " . time() . "\n";
    sleep(1);
}
$span->finish();
```

See `lib/api.php` for detailed API documentation.

## License

[The MIT License](LICENSE).
