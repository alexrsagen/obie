<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

class VirtualIdModel extends BaseModel {
	const RESERVED_IDS = [
		// base36 variants
		1243314,     // 0xms
		155821,      // (a)dmin
		14423,       // (a)lex
		548,         // (a)pi
		15752344092, // history
		19137037,    // login
		22574,       // rpc
		30241021,    // sagen
		39204058847, // sandbox
		1102125615,  // signup
		892315,      // test
		1235049123,  // upload

		// base62 variants
		12583812,      // 0xms
		391244467,     // admin
		6340665,       // alex
		102520,        // api
		1906217330580, // history
		556382707,     // login
		167862,        // rpc
		656480219,     // sagen
		2523613114261, // sandbox
		40820019337,   // signup
		10842853,      // test
		42756913585,   // upload
	];

	use VirtualIDTrait;
}
