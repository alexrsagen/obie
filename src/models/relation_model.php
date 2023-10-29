<?php namespace Obie\Models;

abstract class RelationModel extends BaseModel {
	const TYPE_BELONGS_TO_ONE              = 1;
	const TYPE_BELONGS_TO_MANY             = 2;
	const TYPE_HAS_ONE                     = 4;
	const TYPE_HAS_MANY                    = 5;

	use RelationTrait;
}
