<?php
/*
Copyright 2019, https://asterisk-pbx.ru

This file is part of Asterisk Call Center Stats.
Asterisk Call Center Stats is free software: you can redistribute it
and/or modify it under the terms of the GNU General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

Asterisk Call Center Stats is distributed in the hope that it will be
useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Asterisk Call Center Stats.  If not, see
<http://www.gnu.org/licenses/>.
 */
require_once "config.php";
include "sesvars.php";
?>
<?php
//query mixed from queuelog and cdr (queuelog table must be in cdr databases)
$sql = "select queue_log.time, queue_log.callid, queue_log.queuename, queue_log.agent,  queue_log.event, queue_log.data1 as wait, queue_log.data2 as dur, cdr.did, cdr.src, cdr.recordingfile, cdr.disposition from queue_log, cdr where queue_log.time >= '$start' AND queue_log.time <= '$end' AND queue_log.callid = cdr.uniqueid and queue_log.event in ('COMPLETECALLER', 'COMPLETEAGENT') and queue_log.agent in ($agent) and queue_log.queuename in ($queue) and cdr.disposition = 'ANSWERED' order by queue_log.time";

$res = $connection->query($sql);

$out = array();
while ($row = $res->fetch_assoc()) {
    $out[] = $row;
}

$header_pdf = array("Дата", "CallerId", "DID", "Очередь", "Агент", "Ожид.", "Разг.");
$width_pdf = array(40, 32, 25, 25, 64, 25, 25);
$title_pdf = "Принятые вызовы";
$data_pdf = array();
foreach ($out as $k => $r) {
    $time = strtotime($r['time']);
    $time = date('Y-m-d H:i:s', $time);
    $dur = seconds2minutes($r['dur']);
    $wait = seconds2minutes($r['wait']);
    $linea_pdf = array($time, $r['src'], $r['did'], $r['queuename'], $r['agent'], $wait, $dur);
    $data_pdf[] = $linea_pdf;
}

// Кодируем в JSON для JavaScript
$out_json = json_encode($out, JSON_UNESCAPED_UNICODE);

if (json_last_error() !== JSON_ERROR_NONE) {
    $out_json = '[]';
}

$connection->close();

// Функция для проверки наличия записи через API
function checkRecording($uniqueid_without_dot, $queue, $cnum, $dst) {
    if (empty($queue) || empty($uniqueid_without_dot) || empty($cnum) || empty($dst)) {
        return array('success' => false, 'error' => 'Missing parameters');
    }
    
    // Извлекаем номер из cnum (убираем префикс очереди)
    $cnum_num = str_replace($queue . '_', '', $cnum);
    
    // Формируем префикс для поиска файла
    // Для входящих звонков может быть другой формат, уточнить при необходимости
    $prefix = "25_" . $queue . "|" . $cnum_num . "_" . $dst . "_" . $uniqueid_without_dot;
    
    $api_url = "http://10.137.2.178:5000/list-files";
    
    $data = array(
        "X-Client" => $queue,
        "prefix" => $prefix
    );
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return array('success' => false, 'error' => 'CURL error: ' . curl_error($ch));
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $result = json_decode($response, true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] == 'success') {
                if (!empty($result['files'])) {
                    return array(
                        'success' => true,
                        'file_info' => $result['files'][0],
                        'queue' => $queue
                    );
                } else {
                    return array('success' => false, 'error' => 'Запись не найдена');
                }
            } else {
                return array('success' => false, 'error' => 'API вернул ошибку');
            }
        } else {
            return array('success' => false, 'error' => 'Invalid JSON response from API');
        }
    } elseif ($http_code == 0) {
        return array('success' => false, 'error' => 'API недоступен');
    } else {
        return array('success' => false, 'error' => 'HTTP error: ' . $http_code);
    }
}

// Функция для декодирования записи
function decryptRecording($original_filename, $queue) {
    if (empty($original_filename) || empty($queue)) {
        return array('success' => false, 'error' => 'Missing parameters');
    }
    
    $api_url = "http://10.137.2.178:5000/decrypt";
    
    $data = array(
        "record_file" => $original_filename,
        "X-Client" => $queue
    );
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return array('success' => false, 'error' => 'CURL error: ' . curl_error($ch));
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $result = json_decode($response, true);
        if ($result && isset($result['status']) && $result['status'] == 'success') {
            return array(
                'success' => true,
                'original_filename' => $original_filename
            );
        } else {
            return array('success' => false, 'error' => 'Decryption failed');
        }
    }
    
    return array('success' => false, 'error' => 'HTTP error: ' . $http_code);
}

// Обработка AJAX запросов
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'check_recording') {
        if (isset($_GET['callid']) && isset($_GET['queue']) && isset($_GET['agent']) && isset($_GET['src'])) {
            $callid_parts = explode('.', $_GET['callid']);
            $callid_without_dot = $callid_parts[0];
            $result = checkRecording($callid_without_dot, $_GET['queue'], $_GET['agent'], $_GET['src']);
            echo json_encode($result);
        } else {
            echo json_encode(array('success' => false, 'error' => 'Missing parameters'));
        }
        exit;
        
    } elseif ($_GET['action'] == 'decrypt_play') {
        if (isset($_GET['original_filename']) && isset($_GET['queue'])) {
            $result = decryptRecording($_GET['original_filename'], $_GET['queue']);
            
            if ($result['success']) {
                echo json_encode(array('success' => true));
            } else {
                echo json_encode(array('success' => false, 'error' => $result['error']));
            }
        } else {
            echo json_encode(array('success' => false, 'error' => 'Missing parameters'));
        }
        exit;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Asterisk Call Center Stats</title>
      <style type="text/css" media="screen">@import "css/basic.css";</style>
      <style type="text/css" media="screen">@import "css/tab.css";</style>
      <style type="text/css" media="screen">@import "css/table.css";</style>
      <style type="text/css" media="screen">@import "css/fixed-all.css";</style>
      <link href="css/jquery.dataTables.css" rel="stylesheet">
    <script src="js/1.10.2/jquery.min.js"></script>
    <script src="js/handlebars.js"></script>
    <script src="js/jquery.dataTables.cdr.js"></script>
    <script src="js/locale.js"></script>
    <style>
        .check-btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .play-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .action-btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        
        .action-btn.loading {
            background: #ff9800;
        }
        
        .audio-player {
            margin: 5px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        
        .no-recording {
            color: #999;
            font-style: italic;
        }
        
        .error-message {
            color: #f44336;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .recording-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .recording-status {
            min-height: 30px;
        }
        
        .recording-cell {
            position: relative;
        }
    </style>
    <script>
      let outs = <?php echo $out_json; ?>;
      
      if (!Array.isArray(outs)) {
          outs = [];
      }
      
 function outOverData(arr) {
     if (!arr || !arr.length) {
         return {};
     }
     
     let eve = {};
     let res = {};

     arr.forEach(v => {
         if (v.agent && v.event) {
             let key = v.agent + ',' + v.event;
             eve[key] = (eve[key] || 0) + 1;
         }
     });

     Object.keys(eve).forEach(v => {
         let parts = v.split(",");
         let agent = parts[0];
         let event = parts[1];
         let count = eve[v];
         
         if (!res[agent]) {
             res[agent] = { "agent": agent };
         }
         res[agent][event] = count;
     });

     return res;
 }

   var over_out = outOverData(outs);
   over_out = JSON.stringify(over_out);
   over_out = over_out.replace(/NO\sANSWER/g, "NO_ANSWER");
   over_out = JSON.parse(over_out);

// Функция для восстановления кнопок при загрузке страницы
function restoreButtonsFromCache() {
    $('.recording-cell').each(function() {
        const $cell = $(this);
        const callid = $cell.data('callid');
        
        // Проверяем localStorage
        const cachedData = localStorage.getItem(`record_${callid}`);
        if (cachedData) {
            const data = JSON.parse(cachedData);
            // Заменяем кнопку "Проверить" на "Воспроизвести"
            $cell.html(`
                <button class="play-btn" 
                        data-original-filename="${data.original_filename}"
                        data-queue="${data.queue}"
                        title="Воспроизвести запись">
                    ▶️ Воспроизвести
                </button>
                <div class="recording-status"></div>
            `);
        }
    });
}

$(function() {
    try {
        var theTemplateScript = $("#overs-template").html();
        var theTemplate = Handlebars.compile(theTemplateScript);
        var context = { over: over_out };
        var theCompiledHtml = theTemplate(context);
        $(".overs-placeholder").html(theCompiledHtml);
    } catch(e) {
        console.error('Ошибка при компиляции шаблона обзора:', e);
    }
});

$(function() {
    try {
        var theTemplateScript = $("#out-template").html();
        var theTemplate = Handlebars.compile(theTemplateScript);
        var context = { out: outs };
        var theCompiledHtml = theTemplate(context);
        $('.out-placeholder').html(theCompiledHtml);
        
        // Восстанавливаем кнопки из кэша
        setTimeout(restoreButtonsFromCache, 100);
    } catch(e) {
        console.error('Ошибка при компиляции шаблона детализации:', e);
    }
});

    $(document).ready(function() {
        try {
            if (navigator.language == 'ru') {
                $('#incTable').DataTable({
                    "language": dataTablesLocale['ru'],
                    "iDisplayLength": 100,
                    "order": [[0, "desc"]],
                    "drawCallback": function() {
                        // Восстанавливаем кнопки после каждой перерисовки таблицы
                        setTimeout(restoreButtonsFromCache, 100);
                    }
                });
            } else {
                $('#incTable').DataTable({
                    "iDisplayLength": 100,
                    "order": [[0, "desc"]],
                    "drawCallback": function() {
                        setTimeout(restoreButtonsFromCache, 100);
                    }
                });
            }
        } catch(e) {
            console.error('Ошибка при инициализации DataTable:', e);
        }
    });


Handlebars.registerHelper("prettyDate", function (timestamp) {
  //var a = Date.parse(timestamp);
  var a = new Date(timestamp * 1000);
  if (navigator.language == 'ru') {
     var months = ['Янв','Фев','Мар','Апр','Май','Июня','Июля','Авг','Сен','Окт','Ноя','Дек'];
   } else {
       var months = ['Jan','Feb','Mar','Apr','May', 'Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
   }
  var year = a.getFullYear();
  var month = months[a.getMonth()];
  var date = a.getDate();
  //var hour = a.getHours();
  var hour = (a.getHours() < 10 ? '0' : '') + a.getHours();
  //var min = a.getMinutes();
  var min = (a.getMinutes() < 10 ? '0' : '') + a.getMinutes();
  //var sec = a.getSeconds();
  var sec = (a.getSeconds() < 10 ? '0' : '') + a.getSeconds();
  if( a < 3600000 )
  var time = min + ':' + sec ;
  else
  var time = date + ' ' + month + ' ' + hour + ':' + min + ':' + sec ;

  return time;
});

Handlebars.registerHelper("formatSeconds", function (seconds) {
    if (!seconds) return '0 сек';
    
    var minutes = Math.floor(seconds / 60);
    var secs = seconds % 60;
    
    if (minutes > 0) {
        return minutes + ' мин ' + secs + ' сек';
    } else {
        return secs + ' сек';
    }
});

Handlebars.registerHelper("dataNorm", function (d) {
      if (d == undefined)
        return "0";
      else
        return d;
  });

function notU(d) {
    if (d == undefined || d == null)
        return 0;
    else
        return d;
}

Handlebars.registerHelper("dataPlus", function (a,b) {
    let d = notU(a) + notU(b);
    return d;
});

Handlebars.registerHelper("showRecordingButton", function (callid, queuename, agent, src) {
    return '<button class="check-btn" ' +
           'data-callid="' + callid + '" ' +
           'data-queuename="' + queuename + '" ' +
           'data-agent="' + agent + '" ' +
           'data-src="' + src + '" ' +
           'title="Проверить наличие записи">🔍 Проверить</button>' +
           '<div class="recording-status"></div>';
});

Handlebars.registerHelper("getStatus", function (s) {
      switch (s) {
  case 'COMPLETECALLER':
    status = '<span style="color: limegreen">Абонент</span>';
    break;
  case 'COMPLETEAGENT':
    status = '<span style="color: royalblue">Агент</span>';
    break;
  }
   return status;
  });

// Функция для проверки наличия записи
function checkRecording(callid, queue, agent, src) {
    const button = event.target;
    const $cell = $(button).closest('.recording-cell');
    const statusDiv = $cell.find('.recording-status');
    
    button.innerHTML = '⏳ Проверка...';
    button.classList.add('loading');
    button.disabled = true;
    statusDiv.html('<small class="recording-info">Проверка наличия записи...</small>');
    
    $.ajax({
        url: window.location.pathname,
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'check_recording',
            callid: callid,
            queue: queue,
            agent: agent,
            src: src
        },
        success: function(response) {
            console.log('API Response:', response);
            
            if (response.success) {
                // Сохраняем информацию в localStorage
                localStorage.setItem(`record_${callid}`, JSON.stringify({
                    original_filename: response.file_info.original_filename,
                    queue: queue,
                    timestamp: Date.now()
                }));
                
                // Заменяем кнопку "Проверить" на "Воспроизвести"
                $cell.html(`
                    <button class="play-btn" 
                            data-original-filename="${response.file_info.original_filename}"
                            data-queue="${queue}"
                            title="Воспроизвести запись">
                        ▶️ Воспроизвести
                    </button>
                    <div class="recording-status">
                        <small class="recording-info" style="color: green;">
                            ✓ Запись найдена (${response.file_info.size_mb ? response.file_info.size_mb.toFixed(2) + ' MB' : ''})
                        </small>
                    </div>
                `);
                
            } else {
                // Запись не найдена или ошибка
                button.innerHTML = '🔍 Проверить';
                button.classList.remove('loading');
                button.disabled = false;
                
                let errorMsg = response.error || 'Запись не найдена';
                statusDiv.html('<small class="error-message">' + errorMsg + '</small>');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error, xhr.responseText);
            
            button.innerHTML = '🔍 Проверить';
            button.classList.remove('loading');
            button.disabled = false;
            statusDiv.html('<small class="error-message">Ошибка проверки: ' + error + '</small>');
        }
    });
}

// Функция для воспроизведения записи
function playRecording(original_filename, queue) {
    const button = event.target;
    const $cell = $(button).closest('.recording-cell');
    const statusDiv = $cell.find('.recording-status');
    const callid = $cell.data('callid');
    
    button.innerHTML = '⏳ Декодирование...';
    button.classList.add('loading');
    button.disabled = true;
    statusDiv.html('<small class="recording-info">Декодирование...</small>');
    
    $.ajax({
        url: window.location.pathname,
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'decrypt_play',
            original_filename: original_filename,
            queue: queue
        },
        success: function(response) {
            console.log('Decrypt Response:', response);
            
            if (response.success) {
                // Создаем имя файла для воспроизведения
                // Ищем файл в буфере по оригинальному имени
                const audio_path = 'find_audio_file.php?original_filename=' + encodeURIComponent(original_filename);
                
                // Создаем элемент аудиоплеера
                const audioPlayer = document.createElement('div');
                audioPlayer.className = 'audio-player';
                audioPlayer.innerHTML = `
                    <audio controls style="width: 100%; max-width: 300px;">
                        <source src="${audio_path}" type="audio/mpeg">
                        Ваш браузер не поддерживает аудио элемент.
                    </audio>
                    <br>
                    <small style="color: green;">Запись готова к воспроизведению</small>
                `;
                
                // Заменяем кнопку аудиоплеером
                $cell.html(audioPlayer);
                
                // Пытаемся автоматически воспроизвести
                const audioElement = audioPlayer.querySelector('audio');
                audioElement.play().catch(function(e) {
                    console.log('Автовоспроизведение заблокировано, требуется клик пользователя');
                    audioPlayer.innerHTML += '<br><small>Нажмите play для воспроизведения</small>';
                });
                
                // Обработчик окончания воспроизведения
                audioElement.onended = function() {
                    audioPlayer.querySelector('small').textContent = 'Воспроизведение завершено';
                };
                
                audioElement.onerror = function(e) {
                    console.error('Audio playback error:', e);
                    audioPlayer.querySelector('small').textContent = 'Ошибка воспроизведения';
                    audioPlayer.querySelector('small').style.color = 'red';
                };
                
            } else {
                button.innerHTML = '▶️ Воспроизвести';
                button.classList.remove('loading');
                button.disabled = false;
                
                // Показываем ошибку
                statusDiv.html('<small class="error-message">Ошибка декодирования: ' + (response.error || 'Неизвестная ошибка') + '</small>');
                
                // Удаляем из кэша при ошибке
                localStorage.removeItem(`record_${callid}`);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error, xhr.responseText);
            
            button.innerHTML = '▶️ Воспроизвести';
            button.classList.remove('loading');
            button.disabled = false;
            
            statusDiv.html('<small class="error-message">Ошибка при обращении к серверу: ' + error + '</small>');
            
            // Удаляем из кэша при ошибке
            localStorage.removeItem(`record_${callid}`);
v        }
    });
}

// Обработчик кликов на кнопки проверки
$(document).on('click', '.check-btn', function(e) {
    e.preventDefault();
    const callid = $(this).data('callid');
    const queue = $(this).data('queuename');
    const agent = $(this).data('agent');
    const src = $(this).data('src');
    checkRecording(callid, queue, agent, src);
});

// Обработчик кликов на кнопку воспроизведения (делегирование, так как кнопки добавляются динамически)
$(document).on('click', '.play-btn', function(e) {
    e.preventDefault();
    if (!$(this).hasClass('loading')) {
        const original_filename = $(this).data('original-filename');
        const queue = $(this).data('queue');
        playRecording(original_filename, queue);
    }
});

    </script>

<script id="overs-template" type="text/x-handlebars-template">
    <h2>Обзор</h2>
        <div class="table">
            <table class="table centered">
                <thead>
                    <tr class="text-center">
                        <th class="text-left">Агент</th>
                        <th>COMPLETECALLER</th>
                        <th>COMPLETEAGENT</th>
                        <th>Всего</th>
                    </tr>
                </thead>
                <tbody>
                        {{#each over}}
                    <tr class="text-center">
                        <td class="text-left">{{@key}}</td>
                        <td>{{dataNorm this.COMPLETECALLER}}</td>
                        <td>{{dataNorm this.COMPLETEAGENT}}</td>
                        <td><b>{{dataPlus this.COMPLETECALLER this.COMPLETEAGENT}}</b></td>
                    </tr>
                        {{/each}}
                </tbody>
            </table>
        </div>
    </script>
<script id="out-template" type="text/x-handlebars-template">
    <div class="table table-list-search">
        <table id="incTable" class="table table-striped">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>CallerID</th>
                    <th>DID</th>
                    <th>Очередь</th>
                    <th>Агент</th>
                    <th>Ожид.</th>
                    <th>Разг.</th>
                    <th>Заверш.</th>
                    <th>Запись</th>
                </tr>
            </thead>
            <tbody>
                {{#each out}}
                <tr>
                    <td>{{prettyDate callid}}</td>
                    <td>{{src}}</td>
                    <td>{{did}}</td>
                    <td>{{queuename}}</td>
                    <td>{{agent}}</td>
                    <td>{{formatSeconds wait}}</td>
                    <td>{{formatSeconds dur}}</td>
                    <td>{{{getStatus event}}}</td>
                    <td class="recording-cell" data-callid="{{callid}}">
                        {{{showRecordingButton callid queuename agent src}}}
                    </td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
</script>
</head>
<body>
<?php include "menu.php";?>
<div id="main">
    <div id="contents">
      <h1>Принятые вызовы: <?php echo $start . " - " . $end ?></h1>
      <br/>
      <div class="overs-placeholder"></div>
      <br/>
      <h2>Детализация</h2>
      <br/>
<?php
print_exports($header_pdf, $data_pdf, $width_pdf, $title_pdf, $cover_pdf,$header_pdf);
?>
        <br/>
        <hr/>
      <div class="out-placeholder"></div>
    </div>
</div>
<div id='footer'><a href="https://helpdesk.docdoc.ru/">Нашли баг? Заведите заявку в HelpDesk раздел Инфраструктура - Телефония|</a>    |SH VoIP hub 2026</div>
<BR>
</body>
</html>
