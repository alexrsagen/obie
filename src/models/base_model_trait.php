<?php namespace Obie\Models;

trait BaseModelTrait {
	protected static ?string $source             = null;
	protected static ?string $source_singular    = null;
	protected static array $columns              = [];
	protected static array $pk                   = [];
	protected static ?\PDO $db                   = null;
	protected static ?\PDO $ro_db                = null;
	protected static ?\PDOException $find_error  = null;
	protected static ?\PDOException $count_error = null;
	protected static ?string $last_query         = null;
}
