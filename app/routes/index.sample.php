<?php namespace MyZeroXApp;
use \ZeroX\Router;

Router::get('/', function() {
	$this->setContentType(Router::CONTENT_TYPE_TEXT);
	return 'hello world';
});
