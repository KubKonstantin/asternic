<?php

function no_queue_user_group($username) {
	$username = trim((string)$username);
	$separator = strpos($username, '_');
	$group = $separator === false ? '' : substr($username, 0, $separator);

	return preg_match('/^[A-Za-z0-9-]+$/', $group) ? $group : '';
}

function no_queue_recording_api($path, $payload, $timeout) {
	$ch = curl_init('http://10.137.2.178:5000/' . $path);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode($payload),
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => $timeout
	]);
	$response = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($errno !== 0) {
		return ['success' => false, 'error' => 'CURL error #' . $errno . ': ' . ($error ?: 'unknown curl error')];
	}
	if ($http_code !== 200) {
		return ['success' => false, 'error' => 'Recording API HTTP error: ' . $http_code];
	}

	$result = json_decode($response, true);
	if (!is_array($result)) {
		return ['success' => false, 'error' => 'Recording API returned invalid JSON'];
	}

	return ['success' => true, 'result' => $result];
}

function no_queue_find_recording($group, $call) {
	$callid = explode('.', (string)$call['uniqueid'])[0];
	$agent = (string)$call['cnum'];
	$short_agent = substr($agent, strlen($group) + 1);
	$remote_number = $call['direction'] === 'inbound' ? (string)$call['src'] : (string)$call['dst'];
	$digits = preg_replace('/\D+/', '', $remote_number);
	$agents = array_values(array_unique(array_filter([$short_agent, $agent])));
	$numbers = array_values(array_unique(array_filter([$remote_number, $digits])));

	foreach ($agents as $agent_variant) {
		foreach ($numbers as $number_variant) {
			$prefix = '25_' . $group . '|' . $agent_variant . '_' . $number_variant . '_' . $callid;
			$api = no_queue_recording_api('list-files', ['X-Client' => $group, 'prefix' => $prefix], 10);
			if (!$api['success']) {
				return $api;
			}
			if (($api['result']['status'] ?? '') === 'success' && !empty($api['result']['files'][0])) {
				return ['success' => true, 'file_info' => $api['result']['files'][0]];
			}
		}
	}

	return ['success' => false, 'error' => 'Запись не найдена'];
}

function no_queue_load_call($connection, $uniqueid, $group, $direction) {
	$external_number_pattern = '^([+]7|7|8)[0-9]{10}$';
	$direction_condition = $direction === 'inbound'
		? "src REGEXP '$external_number_pattern' AND dst NOT REGEXP '$external_number_pattern'"
		: "dst REGEXP '$external_number_pattern'";
	$sql = 'SELECT uniqueid, src, dst, cnum, disposition FROM cdr'
		. ' WHERE uniqueid = ? AND LEFT(cnum, ?) = ? AND ' . $direction_condition
		. ' AND NOT EXISTS (SELECT 1 FROM queue_log q WHERE q.callid = cdr.uniqueid) LIMIT 1';
	$stmt = $connection->prepare($sql);
	$prefix = $group . '_';
	$prefix_length = strlen($prefix);
	$stmt->bind_param('sis', $uniqueid, $prefix_length, $prefix);
	$stmt->execute();
	$stmt->bind_result($call_uniqueid, $src, $dst, $cnum, $disposition);
	$call = $stmt->fetch() ? [
		'uniqueid' => $call_uniqueid,
		'src' => $src,
		'dst' => $dst,
		'cnum' => $cnum,
		'disposition' => $disposition,
		'direction' => $direction
	] : null;
	$stmt->close();
	return $call;
}

function no_queue_json_response($payload, $status = 200) {
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

function no_queue_handle_recording_action($connection, $group, $direction) {
	$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
	if ($action === '') {
		return;
	}

	if ($action === 'check_recording') {
		$uniqueid = trim(isset($_GET['uniqueid']) ? (string)$_GET['uniqueid'] : '');
		$call = $uniqueid === '' ? null : no_queue_load_call($connection, $uniqueid, $group, $direction);
		if (!$call || $call['disposition'] !== 'ANSWERED') {
			no_queue_json_response(['success' => false, 'error' => 'Вызов не найден или недоступен'], 404);
		}

		$recording = no_queue_find_recording($group, $call);
		if (!$recording['success']) {
			no_queue_json_response($recording, 404);
		}

		$token = bin2hex(random_bytes(16));
		$_SESSION['NO_QUEUE_RECORDINGS'][$token] = [
			'filename' => basename((string)$recording['file_info']['original_filename']),
			'group' => $group,
			'created_at' => time()
		];
		$_SESSION['NO_QUEUE_RECORDINGS'] = array_slice($_SESSION['NO_QUEUE_RECORDINGS'], -20, null, true);
		no_queue_json_response(['success' => true, 'recording_token' => $token]);
	}

	if ($action === 'decrypt_play') {
		$token = isset($_GET['recording_token']) ? (string)$_GET['recording_token'] : '';
		$recording = $_SESSION['NO_QUEUE_RECORDINGS'][$token] ?? null;
		if (!$recording || $recording['group'] !== $group || time() - $recording['created_at'] > 900) {
			no_queue_json_response(['success' => false, 'error' => 'Токен записи недействителен'], 403);
		}

		$api = no_queue_recording_api('decrypt', [
			'record_file' => $recording['filename'],
			'X-Client' => $group
		], 30);
		if (!$api['success'] || ($api['result']['status'] ?? '') !== 'success') {
			no_queue_json_response(['success' => false, 'error' => $api['error'] ?? 'Ошибка декодирования'], 502);
		}

		no_queue_json_response([
			'success' => true,
			'audio_url' => 'find_audio_file.php?original_filename=' . rawurlencode($recording['filename'])
		]);
	}

	no_queue_json_response(['success' => false, 'error' => 'Неизвестное действие'], 400);
}
