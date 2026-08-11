<?php
require_once "config.php";
require_once "casdoor_auth.php";

check_auth();

if (!isset($cdr_direction) || !in_array($cdr_direction, ['inbound', 'outbound'], true)) {
	http_response_code(400);
	die('Неизвестное направление вызовов');
}

function cdr_user_group() {
	$username = get_authenticated_username();
	$separator = strpos($username, '_');
	$group = $separator === false ? '' : substr($username, 0, $separator);

	if ($group === '' || !preg_match('/^[A-Za-z0-9-]+$/', $group)) {
		http_response_code(403);
		die('Не удалось определить группу из логина');
	}

	return $group;
}

function cdr_recording_api($path, $payload, $timeout) {
	$ch = curl_init('http://10.137.2.178:5000/' . $path);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	$response = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($errno) {
		return ['success' => false, 'error' => 'CURL error #' . $errno . ': ' . ($error ?: 'unknown curl error')];
	}
	if ($http_code !== 200) {
		return ['success' => false, 'error' => 'HTTP error: ' . $http_code];
	}

	$result = json_decode($response, true);
	if (!is_array($result)) {
		return ['success' => false, 'error' => 'Invalid JSON response from API'];
	}
	return ['success' => true, 'result' => $result];
}

function cdr_find_recording($group, $uniqueid, $agent, $remote_number) {
	$callid = explode('.', $uniqueid)[0];
	$short_agent = strpos($agent, $group . '_') === 0 ? substr($agent, strlen($group) + 1) : $agent;
	$digits = preg_replace('/\D+/', '', $remote_number);
	$agents = array_values(array_unique(array_filter([$short_agent, $agent])));
	$numbers = array_values(array_unique(array_filter([$remote_number, $digits])));
	$last_error = 'Запись не найдена';

	foreach ($agents as $agent_variant) {
		foreach ($numbers as $number_variant) {
			$prefix = '25_' . $group . '|' . $agent_variant . '_' . $number_variant . '_' . $callid;
			$api = cdr_recording_api('list-files', ['X-Client' => $group, 'prefix' => $prefix], 10);
			if (!$api['success']) {
				$last_error = $api['error'];
				continue;
			}
			if (($api['result']['status'] ?? '') === 'success' && !empty($api['result']['files'])) {
				return ['success' => true, 'file_info' => $api['result']['files'][0]];
			}
		}
	}

	return ['success' => false, 'error' => $last_error];
}

$user_group = cdr_user_group();

if (isset($_GET['action'])) {
	header('Content-Type: application/json; charset=utf-8');
	if ($_GET['action'] === 'check_recording') {
		if (empty($_GET['uniqueid']) || empty($_GET['agent']) || empty($_GET['number'])) {
			echo json_encode(['success' => false, 'error' => 'Missing parameters']);
		} elseif (strpos((string)$_GET['agent'], $user_group . '_') !== 0) {
			http_response_code(403);
			echo json_encode(['success' => false, 'error' => 'Доступ к записи запрещен']);
		} else {
			echo json_encode(cdr_find_recording($user_group, (string)$_GET['uniqueid'], (string)$_GET['agent'], (string)$_GET['number']));
		}
		exit;
	}
	if ($_GET['action'] === 'decrypt_play') {
		if (empty($_GET['original_filename'])) {
			echo json_encode(['success' => false, 'error' => 'Missing parameters']);
		} else {
			$api = cdr_recording_api('decrypt', ['record_file' => basename((string)$_GET['original_filename']), 'X-Client' => $user_group], 30);
			$success = $api['success'] && (($api['result']['status'] ?? '') === 'success');
			echo json_encode($success ? ['success' => true] : ['success' => false, 'error' => $api['error'] ?? 'Decryption failed']);
		}
		exit;
	}
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Unknown action']);
	exit;
}

require_once "sesvars.php";

function cdr_filter_value($name) {
	return trim(isset($_GET[$name]) ? (string)$_GET[$name] : '');
}

function cdr_sql_like($connection, $column, $value) {
	if ($value === '') {
		return '';
	}
	return " AND " . $column . " LIKE '%" . $connection->real_escape_string($value) . "%'";
}

$filters = [
	'src' => cdr_filter_value('src'),
	'dst' => cdr_filter_value('dst'),
	'did' => cdr_filter_value('did'),
	'cnum' => cdr_filter_value('cnum'),
	'disposition' => cdr_filter_value('disposition')
];

$start_sql = $connection->real_escape_string($start);
$end_sql = $connection->real_escape_string($end);
$external_number_pattern = '^([+]7|7|8)[0-9]{10}$';
$direction_condition = $cdr_direction === 'inbound'
	? "cdr.src REGEXP '$external_number_pattern' AND cdr.dst NOT REGEXP '$external_number_pattern'"
	: "cdr.dst REGEXP '$external_number_pattern'";

$sql = "SELECT cdr.calldate, cdr.uniqueid, cdr.src, cdr.dst, cdr.did, cdr.cnum,
		cdr.duration, cdr.billsec, cdr.disposition, cdr.recordingfile
	FROM cdr
	WHERE cdr.calldate >= '$start_sql'
		AND cdr.calldate <= '$end_sql'
		AND LEFT(cdr.cnum, " . (strlen($user_group) + 1) . ") = '" . $connection->real_escape_string($user_group . '_') . "'
		AND $direction_condition
		AND NOT EXISTS (
			SELECT 1 FROM queue_log q WHERE q.callid = cdr.uniqueid
		)";

$sql .= cdr_sql_like($connection, 'cdr.src', $filters['src']);
$sql .= cdr_sql_like($connection, 'cdr.dst', $filters['dst']);
$sql .= cdr_sql_like($connection, 'cdr.did', $filters['did']);
$sql .= cdr_sql_like($connection, 'cdr.cnum', $filters['cnum']);
if ($filters['disposition'] !== '') {
	$sql .= " AND cdr.disposition = '" . $connection->real_escape_string($filters['disposition']) . "'";
}
$sql .= ' ORDER BY cdr.calldate DESC';

$res = $connection->query($sql);
if (!$res) {
	die('Ошибка SQL запроса: ' . htmlspecialchars($connection->error));
}

$calls = [];
while ($row = $res->fetch_assoc()) {
	$calls[] = $row;
}
$res->free();
$connection->close();

$is_inbound = $cdr_direction === 'inbound';
$page_title = $is_inbound ? 'Входящие CDR без очереди' : 'Исходящие CDR без очереди';
$header_pdf = ['Дата', 'Источник', 'Назначение', 'DID', 'Абонент', 'Длит.', 'Разг.', 'Статус'];
$width_pdf = [42, 30, 30, 30, 34, 24, 24, 32];
$data_pdf = [];
foreach ($calls as $call) {
	$data_pdf[] = [
		$call['calldate'],
		$call['src'],
		$call['dst'],
		$call['did'],
		$call['cnum'],
		seconds2minutes((int)$call['duration']),
		seconds2minutes((int)$call['billsec']),
		$call['disposition']
	];
}
$cover_pdf = $page_title . "\nПериод: " . $start . ' - ' . $end;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<title><?php echo htmlspecialchars($page_title); ?></title>
	<style type="text/css" media="screen">@import "css/basic.css";</style>
	<style type="text/css" media="screen">@import "css/tab.css";</style>
	<style type="text/css" media="screen">@import "css/table.css";</style>
	<style type="text/css" media="screen">@import "css/fixed-all.css";</style>
	<style>
		.cdr-search { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin: 15px 0; padding: 12px; background: #f3f3f3; }
		.cdr-search label { display: flex; flex-direction: column; gap: 4px; }
		.cdr-search input, .cdr-search select { min-width: 130px; padding: 5px; }
		.cdr-search-actions { display: flex; gap: 8px; padding-bottom: 1px; }
		.cdr-search-actions button, .cdr-search-actions a { padding: 6px 12px; }
		.cdr-table { width: 100%; border-collapse: collapse; }
		.cdr-table th, .cdr-table td { padding: 6px; border: 1px solid #ddd; }
		.recording-cell { min-width: 150px; }
		.recording-status, .recording-error { display: block; margin-top: 4px; font-size: 12px; }
		.recording-error { color: #b00020; }
	</style>
</head>
<body>
<?php include "menu.php"; ?>
<div id="main"><div id="contents">
	<h1><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($start . ' - ' . $end); ?></h1>
	<form class="cdr-search" method="get">
		<label>Источник<input type="text" name="src" value="<?php echo htmlspecialchars($filters['src']); ?>"></label>
		<label>Назначение<input type="text" name="dst" value="<?php echo htmlspecialchars($filters['dst']); ?>"></label>
		<label>DID<input type="text" name="did" value="<?php echo htmlspecialchars($filters['did']); ?>"></label>
		<label>Абонент<input type="text" name="cnum" value="<?php echo htmlspecialchars($filters['cnum']); ?>"></label>
		<label>Статус
			<select name="disposition">
				<option value="">Все</option>
				<?php foreach (['ANSWERED', 'NO ANSWER', 'BUSY', 'FAILED'] as $status): ?>
				<option value="<?php echo $status; ?>"<?php echo $filters['disposition'] === $status ? ' selected' : ''; ?>><?php echo $status; ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<div class="cdr-search-actions">
			<button type="submit">Поиск</button>
			<a href="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">Сбросить</a>
		</div>
	</form>

	<p>Найдено вызовов: <strong><?php echo count($calls); ?></strong></p>
	<?php print_exports($header_pdf, $data_pdf, $width_pdf, $page_title, $cover_pdf, $header_pdf); ?>

	<table class="cdr-table sortable">
		<thead><tr>
			<th>Дата</th><th>Источник</th><th>Назначение</th><th>DID</th><th>Абонент</th>
			<th>Длительность</th><th>Разговор</th><th>Статус</th><th>UniqueID</th><th>Запись</th>
		</tr></thead>
		<tbody>
		<?php if (!$calls): ?>
			<tr><td colspan="10">Вызовы не найдены</td></tr>
		<?php else: foreach ($calls as $call): ?>
			<tr>
				<td><?php echo htmlspecialchars($call['calldate']); ?></td>
				<td><?php echo htmlspecialchars($call['src']); ?></td>
				<td><?php echo htmlspecialchars($call['dst']); ?></td>
				<td><?php echo htmlspecialchars($call['did']); ?></td>
				<td><?php echo htmlspecialchars($call['cnum']); ?></td>
				<td><?php echo seconds2minutes((int)$call['duration']); ?></td>
				<td><?php echo seconds2minutes((int)$call['billsec']); ?></td>
				<td><?php echo htmlspecialchars($call['disposition']); ?></td>
				<td><?php echo htmlspecialchars($call['uniqueid']); ?></td>
				<td class="recording-cell">
				<?php if ($call['disposition'] === 'ANSWERED'): ?>
					<button type="button" class="check-recording"
						data-uniqueid="<?php echo htmlspecialchars($call['uniqueid']); ?>"
						data-agent="<?php echo htmlspecialchars($call['cnum']); ?>"
						data-number="<?php echo htmlspecialchars($is_inbound ? $call['src'] : $call['dst']); ?>">🔍 Проверить</button>
					<span class="recording-status"></span>
				<?php else: ?>—<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
</div></div>
<script src="js/1.10.2/jquery.min.js"></script>
<script>
$('.check-recording').on('click', function () {
	const button = this;
	const cell = button.closest('.recording-cell');
	const status = cell.querySelector('.recording-status');
	button.disabled = true;
	status.textContent = 'Проверка...';
	$.getJSON(window.location.pathname, {
		action: 'check_recording',
		uniqueid: button.dataset.uniqueid,
		agent: button.dataset.agent,
		number: button.dataset.number
	}).done(function (response) {
		if (!response.success) {
			button.disabled = false;
			status.className = 'recording-error';
			status.textContent = response.error || 'Запись не найдена';
			return;
		}
		button.textContent = '⏳ Декодирование...';
		$.getJSON(window.location.pathname, {
			action: 'decrypt_play',
			original_filename: response.file_info.original_filename
		}).done(function (decrypt) {
			if (!decrypt.success) {
				button.disabled = false;
				button.textContent = '▶ Повторить';
				status.className = 'recording-error';
				status.textContent = decrypt.error || 'Ошибка декодирования';
				return;
			}
			const audio = document.createElement('audio');
			audio.controls = true;
			audio.src = 'find_audio_file.php?original_filename=' + encodeURIComponent(response.file_info.original_filename);
			cell.replaceChildren(audio);
		}).fail(function () {
			button.disabled = false;
			status.textContent = 'Ошибка запроса декодирования';
		});
	}).fail(function (xhr) {
		button.disabled = false;
		status.className = 'recording-error';
		status.textContent = xhr.responseJSON?.error || 'Ошибка проверки записи';
	});
});
</script>
</body>
</html>
