# LightStep Instrumentation Library

## Install with Composer

```bash
composer require lightstep/tracer
```

## Instrumentation Example

```php
<?php

require __DIR__ . '/vendor/autoload.php';

LightStep::initialize('examples/trivial_process', '{{access_token_goes_here}}');
LightStep::infof("The current unix time = %d", time());

$span = LightStep::startSpan();
$span->setOperation("trivial/loop");
for ($i = 0; $i < 10; $i++) {
    $span->infof("Loop iteration %d", $i);
    echo "The current unix time is " . time() . "\n";
    sleep(1);
}
$span->finish();
```

## License

[The MIT License](LICENSE).
