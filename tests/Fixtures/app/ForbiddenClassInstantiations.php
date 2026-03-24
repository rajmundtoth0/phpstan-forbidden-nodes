<?php

use RuntimeException as Boom;

new Boom('boom');
new LogicException('logic');
new \RuntimeException('fqn');
