<?php namespace Obie;
use Obie\Encoding\Json;
use Obie\Encoding\Url;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\InvalidArgumentException;

/**
 * @phpstan-import-type Level from \Monolog\Logger
 */
class Logger extends \Monolog\Logger {
	public static $logs_dir = '';
	protected $default_context = [];

	function __construct(string $name, ?string $format = null, array $handlers = [], array $processors = [], ?\DateTimeZone $timezone = null) {
		parent::__construct($name, $handlers, $processors, $timezone);
		$this->setFormat($format);
	}

	protected static function stringifyMessage($message): string {
		if (is_string($message) || is_object($message) && in_array(\Stringable::class, class_implements($message))) {
			return $message;
		} else {
			return Json::encode($message);
		}
	}

	protected function createLineFormatter(?string $format = null): LineFormatter {
		return new LineFormatter($format, null, true, true);
	}

	protected function getLogPath(): string {
		$path = static::$logs_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, Url::removeDotSegments(str_replace('\\', '/', $this->name))) . '.log';
		if ($path !== false && strncmp($path, static::$logs_dir, strlen(static::$logs_dir)) !== 0) {
			throw new \Exception('Log path directory escape');
		}
		if (realpath(dirname($path)) !== realpath(static::$logs_dir)) {
			throw new \Exception('Log path directory escape');
		}
		return $path;
	}

	/**
	 * @return Level
	 * @throws InvalidArgumentException
	 */
	protected function getLogLevel(): int {
		$config = App::$app::getConfig();
		if (!$config) return \Monolog\Logger::DEBUG;
		return static::toMonologLevel($config->get('log_level'));
	}

	protected function populateContext(array $context): array {
		return array_merge(Log::$global_context, $this->default_context, $context);
	}

	public function setFormat(?string $format = null): void {
		$handlers = $this->getHandlers();
		foreach ($handlers as $handler) {
			if ($handler instanceof StreamHandler) {
				$handler->setFormatter($this->createLineFormatter($format));
				return;
			}
		}
		$handler = new StreamHandler($this->getLogPath(), $this->getLogLevel());
		$handler->setFormatter($this->createLineFormatter($format));
		$this->pushHandler($handler);
	}

	public function getDefaultContext(): array {
		return $this->default_context;
	}

	public function setDefaultContext(array $context = []): void {
		$this->default_context = $context;
	}

	/** @see \Monolog\Handler\AbstractHandler::log() */
	public function log($level, $message, array $context = []): void {
		parent::log($level, static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::debug() */
	public function debug($message, array $context = []): void {
		parent::debug(static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::info() */
	public function info($message, array $context = []): void {
		parent::info(static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::notice() */
	public function notice($message, array $context = []): void {
		parent::notice(static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::warning() */
	public function warning($message, array $context = []): void {
		parent::warning(static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::error() */
	public function error($message, array $context = []): void {
		parent::error(static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::critical() */
	public function critical($message, array $context = []): void {
		parent::critical(static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::alert() */
	public function alert($message, array $context = []): void {
		parent::alert(static::stringifyMessage($message), $this->populateContext($context));
	}

	/** @see \Monolog\Handler\AbstractHandler::emergency() */
	public function emergency($message, array $context = []): void {
		parent::emergency(static::stringifyMessage($message), $this->populateContext($context));
	}
}
