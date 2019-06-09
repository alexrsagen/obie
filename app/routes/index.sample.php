<?php namespace MyZeroXApp;

Router::get('/', function() {
	$this->sendResponse('hello world', Router::CONTENT_TYPE_TEXT);
});
