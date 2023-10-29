<?php namespace Obie;

class Log {
	const DEFAULT_LOG_NAME = 'app';

	public static string $default_log_name = self::DEFAULT_LOG_NAME;
	public static array $global_context = [];
	protected static array $loggers = [];

	public static function logger($name): Logger {
		if (!array_key_exists($name, static::$loggers)) {
			static::$loggers[$name] = new Logger($name);
		}
		return static::$loggers[$name];
	}

	/** @see Logger::setFormat() */
	public static function setFormat(?string $format = null): void {
		static::logger(static::$default_log_name)->setFormat($format);
	}
	/** @see Logger::getDefaultContext() */
	public static function getDefaultContext(): array {
		return static::logger(static::$default_log_name)->getDefaultContext();
	}
	/** @see Logger::setDefaultContext() */
	public static function setDefaultContext(array $context = []): void {
		static::logger(static::$default_log_name)->setDefaultContext($context);
	}
	/** @see Logger::log() */
	public static function log($level, $message, array $context = []): void {
		static::logger(static::$default_log_name)->log($level, $message, $context);
	}
	/** @see Logger::debug() */
	public static function debug($message, array $context = []): void {
		static::logger(static::$default_log_name)->debug($message, $context);
	}
	/** @see Logger::info() */
	public static function info($message, array $context = []): void {
		static::logger(static::$default_log_name)->info($message, $context);
	}
	/** @see Logger::notice() */
	public static function notice($message, array $context = []): void {
		static::logger(static::$default_log_name)->notice($message, $context);
	}
	/** @see Logger::warning() */
	public static function warning($message, array $context = []): void {
		static::logger(static::$default_log_name)->warning($message, $context);
	}
	/** @see Logger::error() */
	public static function error($message, array $context = []): void {
		static::logger(static::$default_log_name)->error($message, $context);
	}
	/** @see Logger::critical() */
	public static function critical($message, array $context = []): void {
		static::logger(static::$default_log_name)->critical($message, $context);
	}
	/** @see Logger::alert() */
	public static function alert($message, array $context = []): void {
		static::logger(static::$default_log_name)->alert($message, $context);
	}
	/** @see Logger::emergency() */
	public static function emergency($message, array $context = []): void {
		static::logger(static::$default_log_name)->emergency($message, $context);
	}
	/** @see Logger::getName() */
	public static function getName(): string {
		return static::logger(static::$default_log_name)->getName();
	}
	/** @see Logger::withName() */
	public static function withName(string $name): Logger {
		return static::logger(static::$default_log_name)->withName($name);
	}
	/** @see Logger::pushHandler() */
	public static function pushHandler(\Monolog\Handler\HandlerInterface $handler): Logger {
		return static::logger(static::$default_log_name)->pushHandler($handler);
	}
	/** @see Logger::popHandler() */
	public static function popHandler(): \Monolog\Handler\HandlerInterface {
		return static::logger(static::$default_log_name)->popHandler();
	}
	/** @see Logger::setHandlers() */
	public static function setHandlers(array $handlers): Logger {
		return static::logger(static::$default_log_name)->setHandlers($handlers);
	}
	/** @see Logger::getHandlers() */
	public static function getHandlers(): array {
		return static::logger(static::$default_log_name)->getHandlers();
	}
	/** @see Logger::pushProcessor() */
	public static function pushProcessor(callable $callback): Logger {
		return static::logger(static::$default_log_name)->pushProcessor($callback);
	}
	/** @see Logger::popProcessor() */
	public static function popProcessor(): callable {
		return static::logger(static::$default_log_name)->popProcessor();
	}
	/** @see Logger::getProcessors() */
	public static function getProcessors(): array {
		return static::logger(static::$default_log_name)->getProcessors();
	}
	/** @see Logger::useMicrosecondTimestamps() */
	public static function useMicrosecondTimestamps(bool $micro): Logger {
		return static::logger(static::$default_log_name)->useMicrosecondTimestamps($micro);
	}
	/** @see Logger::useLoggingLoopDetection() */
	public static function useLoggingLoopDetection(bool $detectCycles): Logger {
		return static::logger(static::$default_log_name)->useLoggingLoopDetection($detectCycles);
	}
	/** @see Logger::addRecord() */
	public static function addRecord(int $level, string $message, array $context = [], ?\DateTimeImmutable $datetime = null): bool {
		return static::logger(static::$default_log_name)->addRecord($level, $message, $context, $datetime);
	}
	/** @see Logger::close() */
	public static function close(): void {
		static::logger(static::$default_log_name)->close();
	}
	/** @see Logger::reset() */
	public static function reset(): void {
		static::logger(static::$default_log_name)->reset();
	}
	/** @see Logger::isHandling() */
	public static function isHandling(int $level): bool {
		return static::logger(static::$default_log_name)->isHandling($level);
	}
	/** @see Logger::setExceptionHandler() */
	public static function setExceptionHandler(?callable $callback): Logger {
		return static::logger(static::$default_log_name)->setExceptionHandler($callback);
	}
	/** @see Logger::getExceptionHandler() */
	public static function getExceptionHandler(): ?callable {
		return static::logger(static::$default_log_name)->getExceptionHandler();
	}
	/** @see Logger::setTimezone() */
	public static function setTimezone(\DateTimeZone $tz): Logger {
		return static::logger(static::$default_log_name)->setTimezone($tz);
	}
	/** @see Logger::getTimezone() */
	public static function getTimezone(): \DateTimeZone {
		return static::logger(static::$default_log_name)->getTimezone();
	}
}
