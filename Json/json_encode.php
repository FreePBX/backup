<?php
namespace FreePBX\modules\Backup\Json;

function json_encode($json, $options = 0, $depth = 512) {
	$data = \json_encode($json, $options, $depth);
	if (JSON_ERROR_NONE !== json_last_error()) {
		throw new \JsonException(json_last_error_msg(),json_last_error());
	}
	return $data;
}