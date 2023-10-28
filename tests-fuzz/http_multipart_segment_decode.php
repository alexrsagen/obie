<?php
/** @var PhpFuzzer\Config $config */

require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'autoload.php';

$config->setTarget(function (string $input) {
    Obie\Http\Multipart\Segment::decode($input);
});

$config->setMaxLen(512);
