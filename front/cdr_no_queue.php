<?php
require_once "config.php";
require_once "sesvars.php";
require_once "casdoor_auth.php";

check_auth();

if (!isset($cdr_direction) || !in_array($cdr_direction, ['inbound', 'outbound'], true)) {
	http_response_code(400);
	die('Неизвестное направление вызовов');
}

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
			<th>Длительность</th><th>Разговор</th><th>Статус</th><th>UniqueID</th>
		</tr></thead>
		<tbody>
		<?php if (!$calls): ?>
			<tr><td colspan="9">Вызовы не найдены</td></tr>
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
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
</div></div>
</body>
</html>
