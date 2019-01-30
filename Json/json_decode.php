<?php
namespace FreePBX\modules\Backup\Json;

function json_decode($json, $assoc = false, $depth = 512, $options = 0) {
	$data = \json_decode($json, $assoc, $depth, $options);
	if (JSON_ERROR_NONE !== json_last_error()) {
		throw new \JsonException(json_last_error_msg(),json_last_error());
	}
	return $data;
}