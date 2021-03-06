<?php namespace Obie\Validation;

class FormValidator implements IValidator {
	protected $messages = [];
	protected $fields_fail = [];
	protected $fields_success = [];
	protected $fields;
	public $stop_on_first_error;

	public function __construct(array $fields, bool $stop_on_first_error = true) {
		foreach ($fields as $name => $opt) {
			if (!is_array($opt) ||
				((!array_key_exists('validator', $opt) || !is_callable([$opt['validator'], 'validate'])) &&
				(!array_key_exists('regex', $opt) || !is_string($opt['regex'])) &&
				(!array_key_exists('type', $opt) || !is_int($opt['type'])) &&
				(!array_key_exists('optional', $opt) || !is_bool($opt['optional']))
			)) {
				throw new \Exception('Fields must be an array of "name" => ["message" => ?mixed, "validator" => ?IValidator, "regex" => ?string, "type" => ?int, "optional" => ?bool]');
			}
		}
		$this->fields = $fields;
		$this->stop_on_first_error = $stop_on_first_error;
	}

	public function validate($input) : bool {
		if (!is_array($input)) return false;

		$retval = true;
		$this->messages = [];
		$this->fields_fail = [];
		$this->fields_success = [];

		foreach ($this->fields as $name => $opt) {
			if (array_key_exists('validator', $opt)) {
				$v = $opt['validator'];
			} elseif (array_key_exists('regex', $opt)) {
				$v = new RegexValidator($opt['regex']);
			} elseif (array_key_exists('type', $opt)) {
				$v = new SimpleValidator($opt['type']);
			} else {
				$v = null;
			}
			$field_required = !array_key_exists('optional', $opt) || !$opt['optional'];
			$field_exists   = array_key_exists($name, $input) && $input[$name] !== null;
			if ($field_required && !$field_exists ||
				$field_exists && $v !== null && !$v->validate($input[$name])) {
				$retval = false;
				if (array_key_exists('message', $opt) && !empty($opt['message'])) {
					$this->messages[] = $opt['message'];
				} elseif ($v !== null && is_callable([$v, 'getMessages'])) {
					$this->messages = array_merge($this->messages, $v->getMessages());
				} elseif ($v !== null && is_callable([$v, 'getMessage'])) {
					$this->messages[] = $v->getMessage();
				}
				$this->fields_fail[] = $name;
				if ($this->stop_on_first_error) return $retval;
			} else {
				$this->fields_success[] = $name;
			}
		}

		return $retval;
	}

	public function getMessages() {
		return $this->messages;
	}

	public function getFailedFields() {
		return $this->fields_fail;
	}

	public function getSuccessfulFields() {
		return $this->fields_success;
	}
}
