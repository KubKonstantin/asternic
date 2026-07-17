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

function return_timestamp($date_string)
{
  list ($year,$month,$day,$hour,$min,$sec) = preg_split("/-|:| /",$date_string,6);
  $u_timestamp = mktime($hour,$min,$sec,$month,$day,$year);
  return $u_timestamp;
}

function swf_bar($values,$width,$height,$divid,$stack) {

	if($stack==1) {
		$chart = "barstack.swf";
	} else {
		$chart = "bar.swf";
	}
?>
<div id="<?php echo $divid?>">
<?php echo $values?>
</div>

<script type="text/javascript">
   var fo = new FlashObject("<?php echo $chart?>", "barchart", "<?php echo $width?>", "<?php echo $height?>", "7", "#336699");
   fo.addParam("wmode", "transparent");
//   fo.addParam("salign", "t");
	<?php
		$variables = split("&",$values);
		foreach ($variables as $deauna) {
			echo "//$deauna\n";
			$pedazos = split("=",$deauna);
			echo "fo.addVariable('".$pedazos[0]."','".$pedazos[1]."');\n";
		}
	?>
   fo.write("<?php echo $divid?>");
</script>

<?php
}

function tooltip($texto,$width) {
 echo " onmouseover=\"this.T_WIDTH=$width;this.T_PADDING=5;this.T_STICKY = false; return escape('$texto')\" ";
}


function print_exports($header_pdf,$data_pdf,$width_pdf,$title_pdf,$cover_pdf,$header_csv = null) {
		global $lang;
		global $language;
		if (!is_array($header_csv) || empty($header_csv)) {
			$header_csv = $header_pdf;
		}
		$payload = base64_encode(json_encode(array(
			'header_csv' => $header_csv,
			'header_pdf' => $header_pdf,
			'data' => $data_pdf,
			'width' => $width_pdf,
			'title' => $title_pdf,
			'cover' => $cover_pdf
		), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
		echo "<BR><form method='post' action='export.php'>\n";
		echo $lang["$language"]['export'];
		echo "<input type='hidden' name='payload' value='".htmlspecialchars($payload, ENT_QUOTES, 'UTF-8')."' />\n";
		echo "<button type='submit' name='format' value='pdf' style='border:0;background:transparent;cursor:pointer' ";
		tooltip($lang["$language"]['pdfhelp'],200);
		echo "><img src='images/pdf.gif' alt='PDF'></button>\n";
		echo "<button type='submit' name='format' value='excel' style='border:0;background:transparent;cursor:pointer' ";
		tooltip($lang["$language"]['csvhelp'],200);
		echo "><img src='images/excel.png' alt='Excel'></button>\n";
		echo "</form>";
}

function print_cdr_search_controls($table_id, $fields) {
	$control_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $table_id) . '-field-search';
	echo '<div id="' . $control_id . '" style="margin:12px 0;padding:10px;background:#f3f3f3">';
	echo '<label>Поле: <select class="cdr-search-field">';
	foreach ($fields as $column_index => $label) {
		echo '<option value="' . (int)$column_index . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
	}
	echo '</select></label> ';
	echo '<label>Значение: <input type="text" class="cdr-search-value"></label> ';
	echo '<button type="button" class="cdr-search-submit">Поиск</button> ';
	echo '<button type="button" class="cdr-search-reset">Сбросить</button>';
	echo '</div>';
	echo '<script>(function($){$(function(){';
	echo 'var root=$("#' . $control_id . '");';
	echo 'root.on("click",".cdr-search-submit",function(){var table=$("#' . $table_id . '").DataTable();table.search("").columns().search("");table.column(parseInt(root.find(".cdr-search-field").val(),10)).search(root.find(".cdr-search-value").val()).draw();});';
	echo 'root.on("click",".cdr-search-reset",function(){root.find(".cdr-search-value").val("");var table=$("#' . $table_id . '").DataTable();table.search("").columns().search("").draw();});';
	echo 'root.find(".cdr-search-value").on("keydown",function(event){if(event.keyCode===13){event.preventDefault();root.find(".cdr-search-submit").click();}});';
	echo '});})(jQuery);</script>';
}

function seconds2minutes($segundos) {
    $minutos = intval($segundos / 60);
    $segundos = $segundos % 60;
    if(strlen($segundos)==1) {
		$segundos = "0".$segundos;
	}
    return "$minutos:$segundos";
}
?>
