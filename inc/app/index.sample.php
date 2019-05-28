<?php

Router::get('/', function() {
	$this->sendResponse('hello world', Router::CONTENT_TYPE_TEXT);
});

switch (Router::execute()) {
	case RouterInstance::ENOT_FOUND:
		Router::setResponseCode(Router::HTTP_NOT_FOUND);
		Router::sendResponse(Router::HTTP_STATUSTEXT[Router::HTTP_NOT_FOUND], Router::CONTENT_TYPE_TEXT);
		break;

	case RouterInstance::EINVALID_METHOD:
		Router::setResponseCode(Router::HTTP_METHOD_NOT_ALLOWED);
		Router::sendResponse(Router::HTTP_STATUSTEXT[Router::HTTP_METHOD_NOT_ALLOWED], Router::CONTENT_TYPE_TEXT);
		break;
}
