<?php
/*
Copyright 2007, 2008 Nicolás Gudiño

This file is part of Asternic Call Center Stats.

Asternic Call Center Stats is free software: you can redistribute it
and/or modify it under the terms of the GNU General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

Asternic Call Center Stats is distributed in the hope that it will be
useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Asternic Call Center Stats.  If not, see
<http://www.gnu.org/licenses/>.
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ob_start();
$language = 'ru';
require_once "lang/$language.php";
require 'tfpdf.php';
session_start();

class PDF extends tFPDF {
	function __construct() {
		parent::__construct('L', 'mm', 'A3');
	}

	function Footer() {
		global $lang;
		global $language;
		//Go to 1.5 cm from bottom
		$this->SetY(-15);
		//Select Arial italic 8
		$this->SetFont('ArialMT', '', 8);
		//Print centered page number
		$this->Cell(0, 10, $lang["$language"]['page'] . ' ' . $this->PageNo(), 0, 0, 'C');
		//$this->Cell(0, 10, ' c. ' . $this->PageNo(), 0, 0, 'C');
	}

	function Cover($cover) {
		$this->SetFont('ArialMT', '', 15);
		$this->MultiCell(250, 9, $cover);
		$this->Ln();
	}

	function Header() {
		global $title;
		$this->SetFont('ArialMT', '', 15);
		$this->Cell(0, 10, $title, 0, 1, 'C');
		$this->Ln(2);
	}

	function FitCell($width, $height, $text, $border = 0, $align = 'L', $fill = false, $maximum_size = 10, $minimum_size = 6) {
		$original_family = $this->FontFamily;
		$original_style = $this->FontStyle;
		$original_size = $this->FontSizePt;
		$font_size = min($original_size, $maximum_size);
		$available_width = max(1, $width - 2);
		$display_text = (string)$text;

		$this->SetFont($original_family, $original_style, $font_size);
		while ($font_size > $minimum_size && $this->GetStringWidth($display_text) > $available_width) {
			$font_size -= 0.5;
			$this->SetFont($original_family, $original_style, $font_size);
		}

		if ($this->GetStringWidth($display_text) > $available_width) {
			$suffix = '...';
			while ($display_text !== '' && $this->GetStringWidth($display_text . $suffix) > $available_width) {
				$display_text = substr($display_text, 0, -1);
			}
			$display_text .= $suffix;
		}

		$this->Cell($width, $height, $display_text, $border, 0, $align, $fill);
		$this->SetFont($original_family, $original_style, $original_size);
	}

	function ScaleWidths($widths) {
		$total_width = array_sum($widths);
		if ($total_width <= 0) {
			return $widths;
		}
		$available_width = $this->w - $this->lMargin - $this->rMargin;
		$scale = $available_width / $total_width;
		foreach ($widths as $index => $width) {
			$widths[$index] = $width * $scale;
		}
		return $widths;
	}

	function TableHeader($header, $w) {
		$this->SetFillColor(54, 84, 115);
		$this->SetTextColor(255);
		$this->SetDrawColor(185, 197, 209);
		$this->SetLineWidth(.2);
		$this->SetFont('', '', 10);

		for ($i = 0; $i < count($header); $i++) {
			$this->FitCell($w[$i], 10, $header[$i], 'LTRB', 'C', true, 10, 7);
		}

		$this->Ln();
	}

//Colored table
	function FancyTable($header, $data, $w) {

		//$this->TableHeader($header, $w);

		//Color and font restoration
		$this->SetFillColor(237, 243, 249);
		$this->SetTextColor(0);
		$this->SetDrawColor(205, 214, 223);
		$this->SetFont('', '', 9);
		//Data
		$fill = 0;
		foreach ($data as $row) {
			if ($this->GetY() + 7 > $this->PageBreakTrigger) {
				$this->Cell(array_sum($w), 0, '', 'T');
				$this->AddPage();
				$this->TableHeader($header, $w);
				$this->SetFillColor(237, 243, 249);
				$this->SetTextColor(0);
				$this->SetDrawColor(205, 214, 223);
				$this->SetFont('', '', 9);
			}
			$contador = 0;
			foreach ($row as $valor) {
				$this->FitCell($w[$contador], 7, $valor, 'LR', 'C', $fill, 9, 6);
				$contador++;
			}
			$this->Ln();
			$fill = !$fill;
		}
		$this->Cell(array_sum($w), 0, '', 'T');
	}
}

function export_filename($title, $extension) {
	$filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title);
	$filename = trim($filename, '_');
	if ($filename === '') {
		$filename = 'report';
	}
	return $filename . '.' . $extension;
}

function export_csv($header, $data, $title) {
	header("Content-Type: text/csv; charset=UTF-8");
	header("Content-Disposition: attachment; filename=\"" . export_filename($title, 'csv') . "\"");
	echo "\xEF\xBB\xBF";
	$output = fopen('php://output', 'w');
	fputcsv($output, $header, ';', '"', '\\');
	foreach ($data as $row) {
		fputcsv($output, $row, ';', '"', '\\');
	}
	fclose($output);
}

$payload = null;
$export_token = isset($_POST['export_token']) ? (string)$_POST['export_token'] : '';
if ($export_token !== '' && isset($_SESSION['EXPORT_PAYLOADS'][$export_token])) {
	$payload = $_SESSION['EXPORT_PAYLOADS'][$export_token];
}

// Совместимость с уже открытыми страницами, созданными до перехода на токены.
if (!is_array($payload) && isset($_POST['payload'])) {
	$encoded_payload = str_replace(' ', '+', (string)$_POST['payload']);
	$decoded_payload = base64_decode($encoded_payload, true);
	$payload = $decoded_payload !== false ? json_decode($decoded_payload, true) : null;
}
if (!is_array($payload)) {
	http_response_code(400);
	die('Некорректные данные выгрузки. Обновите страницу отчета и повторите попытку.');
}

$headercsv = isset($payload['header_csv']) && is_array($payload['header_csv']) ? $payload['header_csv'] : array();
$header = isset($payload['header_pdf']) && is_array($payload['header_pdf']) ? $payload['header_pdf'] : array();
$data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : array();
$width = isset($payload['width']) && is_array($payload['width']) ? $payload['width'] : array();
$title = isset($payload['title']) ? (string)$payload['title'] : 'report';
$download_title = $title;
$cover = isset($payload['cover']) ? (string)$payload['cover'] : '';

function pdf_text($value) {
	$converted = iconv('UTF-8', 'windows-1251//TRANSLIT', (string)$value);
	return $converted === false ? (string)$value : $converted;
}

$header = array_map('pdf_text', $header);
$title = pdf_text($title);
$cover = pdf_text($cover);
foreach ($data as $row_index => $row) {
	$data[$row_index] = array_map('pdf_text', $row);
}

if (isset($_POST['format']) && $_POST['format'] === 'pdf') {
	if (ob_get_length()) {
		ob_clean();
	}
	$pdf = new PDF();
	$pdf->AddFont('ArialMT','','arialuni.php');
	// $pdf->AddFont('ArialMT','B','arial.php');
	$pdf->SetFont('ArialMT','',12);
	// $pdf->SetFont('ArialMT','B',12); 
	$pdf->SetAutoPageBreak(true, 15);
	$pdf->SetLeftMargin(8);
	$pdf->SetRightMargin(8);
	$width = $pdf->ScaleWidths($width);
	$pdf->AddPage();
	$pdf->TableHeader($header, $width);
	$pdf->FancyTable($header, $data, $width);
	if ($cover != "") {
		$pdf->AddPage();
		$pdf->Cover($cover);
	}
	$filename = export_filename($download_title, 'pdf');
	$pdf->Output($filename,"D");
	//$pdf->Output('F', '/var/www/html/queue-stats/pdf/export.pdf', true);
} else {
	if (ob_get_length()) {
		ob_clean();
	}
	export_csv($headercsv, $data, $download_title);
}
?>
