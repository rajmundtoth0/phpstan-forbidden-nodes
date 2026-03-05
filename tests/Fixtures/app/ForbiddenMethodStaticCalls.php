<?php

$immutable = new DateTimeImmutable();
$mutable = new DateTime();

$immutable->format(DATE_ATOM);
$mutable->format(DATE_ATOM);
$immutable->getTimestamp();

DateTimeImmutable::createFromFormat('U', '1');
DateTime::createFromFormat('U', '1');
DateTimeImmutable::createFromMutable(new DateTime());
