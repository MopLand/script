<?php
/********************************
Simple PHP File Manager
Copyright (c) VeryIDE

Liscense: MIT
********************************/

$password = 'bangbang';
$limitdir = FALSE;

session_start();

if( empty( $_COOKIE['_sfm_auth'] ) ) {
	// sha1, and random bytes to thwart timing attacks.  Not meant as secure hashing.
	$salt = md5(rand());
	if( isset( $_POST['p'] ) && sha1($salt.$_POST['p']) === sha1($salt.$password) ) {
		setcookie("_sfm_auth", $salt);
		header('Location: ?');
	}
	echo '<html><body><form action="" method=post><input type="password" name="p" placeholder="password" /></form></body></html>'; 
	exit;
}

///////////////////////
  
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// must be in UTF-8 or `basename` doesn't work
setlocale(LC_ALL,'en_US.UTF-8');

$tmp = realpath($_REQUEST['file']);
if($tmp === false)
	err(404,'File or Directory Not Found');
if($limitdir && substr($tmp, 0,strlen(__DIR__)) !== __DIR__)
	err(403,"Forbidden");

if(!$_COOKIE['_sfm_xsrf'])
	setcookie('_sfm_xsrf',bin2hex(md5(time())));
if($_POST) {
	if($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf'])
		err(403,"XSRF Failure");
}

$file = $_REQUEST['file'] ?: '.';
if($_GET['do'] == 'list') {
	if (is_dir($file)) {
		$directory = realpath($file);
		$result = array();
		$files = array_diff(scandir($directory), array('.','..'));
	    foreach($files as $entry) if($entry !== basename(__FILE__)) {
    		$i = str_replace( '//', '/', str_replace( '\\', '/', $directory . '/' . $entry ) );
		$n = correct_encoding( $i );
	    	$stat = stat($i);
	        $result[] = array(
	        	'mtime' => $stat['mtime'],
	        	'size' => $stat['size'],
	        	'name' => $n,
	        	'path' => preg_replace('@^\./@', '', $n),
	        	'is_dir' => is_dir($i),
	        	'is_deleteable' => (!is_dir($i) && is_writable($directory)) || 
	        					   (is_dir($i) && is_writable($directory) && is_recursively_deleteable($i)),
	        	'is_readable' => is_readable($i),
	        	'is_writable' => is_writable($i),
	        	'is_executable' => is_executable($i),
	        );
	    }
	} else {
		err(412,"Not a Directory");
	}
	echo json_encode(array('success' => true, 'parent' => str_replace( '\\', '/', dirname($directory) ), 'is_writable' => is_writable($file), 'results' =>$result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
} elseif ($_GET['do'] == 'reader') {
	$extra = strtolower( pathinfo($file, PATHINFO_EXTENSION) );
	//var_dump( $extra );
	if( in_array( $extra, array('png','gif','jpg','jpeg','bmp','ico') ) ){
		header('Content-Type: image/'.$extra);
		echo file_get_contents($file);
	}else{
		header('Content-Type: text/html');
		echo '<script src="//cdn.staticfile.org/highlight.js/8.8.0/highlight.min.js"></script>';
		echo '<link href="//cdn.staticfile.org/highlight.js/8.8.0/styles/default.min.css" rel="stylesheet">';
		echo '<script>hljs.initHighlightingOnLoad();</script>';
		echo '<pre><code>'.htmlspecialchars( correct_encoding( file_get_contents($file) ) ).'</code></pre>';	
	}
	exit;
} elseif ($_POST['do'] == 'delete') {
	echo json_encode(array('success' => true, 'results' => rmrf($file) ) );
	exit;
} elseif ($_POST['do'] == 'mkdir') {
	chdir($file);
	@mkdir($_POST['name']);
	echo json_encode(array('success' => true, 'results' => $file));
	exit;
} elseif ($_POST['do'] == 'upload') {
	var_dump($_POST);
	var_dump($_FILES);
	var_dump($_FILES['file_data']['tmp_name']);
	var_dump(move_uploaded_file($_FILES['file_data']['tmp_name'], $file.'/'.$_FILES['file_data']['name']));
	exit;
} elseif ($_GET['do'] == 'download') {
	$filename = basename($file);
	//header('Content-Type: ' . mime_content_type($file));
	header('Content-Type: application/octet-stream');
	header('Content-Length: '. filesize($file));
	header(sprintf('Content-Disposition: attachment; filename=%s',
		strpos('MSIE',$_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\"" ));
	ob_flush();
	readfile($file);
	exit;
}

function correct_encoding($text) {
    $current_encoding = mb_detect_encoding($text, array('ASCII','UTF-8','GB2312','GBK','BIG5'));
    if( $current_encoding != 'UTF-8' ){
		$text = iconv($current_encoding, 'UTF-8', $text);
    }
    return $text;
}

function rmrf($dir) {
	if(is_dir($dir)) {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file)
			rmrf("$dir/$file");
		rmdir($dir);
	} else {
		unlink($dir);
	}
}
function is_recursively_deleteable($d) {
	$stack = array($d);
	while($dir = array_pop($stack)) {
		if(!is_readable($dir) || !is_writable($dir)) 
			return false;
		$files = array_diff(scandir($dir), array('.','..'));
		foreach($files as $file) if(is_dir($file)) {
			$stack[] = "$dir/$file";
		}
	}
	return true;
}

function err($code,$msg) {
	echo json_encode(array('error' => array('code'=>intval($code), 'msg' => $msg)));
	exit;
}

function asBytes($ini_v) {
	$ini_v = trim($ini_v);
	$s = array('g'=> 1<<30, 'm' => 1<<20, 'k' => 1<<10);
	return intval($ini_v) * ($s[strtolower(substr($ini_v,-1))] ?: 1);
}
$MAX_UPLOAD_SIZE = min(asBytes(ini_get('post_max_size')), asBytes(ini_get('upload_max_filesize')));
?>
<!DOCTYPE html>
<html><head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>XFile</title>
<style>
body {font-family: "lucida grande","Segoe UI",Arial, sans-serif; font-size: 14px; padding:1em;margin:0; background: -webkit-gradient(linear, left top, left 120, from(#DAF5FD), to(#FFF)) no-repeat; }
th {font-weight: normal; color: #1F75CC; background-color: #F0F9FF; padding:.5em 1em .5em .2em; 
	text-align: left;cursor:pointer;user-select: none;}
th .indicator {margin-left: 6px }
thead {border-top: 1px solid #82CFFA; border-bottom: 1px solid #96C4EA; }
#mkdir {display:inline-block;float:right;padding-top:10px;}
	#mkdir *{ padding:5px; }
label { display:block; font-size:11px; color:#555;}
#file_drop_target {width:500px; padding:12px 0; border: 4px dashed #ccc;font-size:12px;color:#ccc;
	text-align: center;float:right;margin-right:20px;}
#file_drop_target.drag_over {border: 4px dashed #96C4EA; color: #96C4EA;}
#upload_progress {padding: 4px 0;}
#upload_progress .error {color:#a00;}
#upload_progress > div { padding:3px 0;}
.no_write #mkdir, .no_write #file_drop_target {display: none}
.progress_track {display:inline-block;width:200px;height:10px;border:1px solid #333;margin: 0 4px 0 10px;}
.progress {background-color: #82CFFA;height:10px; }
header{ overflow:hidden; }
	header h2{ float:left; font-style: oblique; font-size:40px; text-shadow: 0 1px 1px #111; color: #FFF; font-family: Courier,monospace; line-height:50px; margin:0; }
footer {font-size:11px; color:#bbbbc5; padding:4em 0 0; text-align:right;}
footer a, footer a:visited {color:#bbbbc5;}
#breadcrumb { font-size:15px; line-height:30px; color:#aaa; clear:both; }
#folder_actions {width: 50%;float:right;}

#dialog{ background:#FFF; overflow:hidden; max-width:1000px; max-height:700px; padding:40px 0 0 0; border:#C7C7C7 solid 1px; border-radius:5px; position: fixed; margin:auto; left:0; right:0; top:0; bottom:0; z-index: 1000; box-sizing: border-box; }
	#dialog dt{ cursor:default; line-height:39px; font-weight:bold; color:#666; display:block; font-size:14px; border-bottom:#C7C7C7 solid 1px; position:relative; background: -webkit-gradient(linear, left top, left 50, from(#F1EBCC), to(#FFF)) no-repeat; margin-top:-40px;}
		#dialog dt strong{ display:block; text-align:center; }
		#dialog dt span{ position: absolute; right: 0; width:40px; text-align:center; display:block; overflow:hidden; cursor:pointer; margin:1px;}			
		
		#dialog dt .remove{ font-family:"Lucida Console", Monaco, monospace; font-size:26px; right:0; }
			#dialog dt .remove:hover{ color:red; }		
		
		#dialog dt .saving{ font-family:"Lucida Console", Monaco, monospace; font-size:26px; width:60px; display:none; }
			#dialog dt .saving button{     background-color: #DCA34D;     border: none;     padding: 5px; color: #FFF; }		
	
	#dialog dd{ background:#fff; padding:5px; height:100%; width:100%; padding:0; margin:0; box-sizing: border-box; }
		#dialog dd iframe{ width:100%; height:100%; overflow:auto; border:none; }
		
	#dialog[draggable=true] dt{ -moz-user-select:none; -khtml-user-drag: element; cursor: move }

a, a:visited { color:#00c; text-decoration: none}
a:hover {text-decoration: underline}
.sort_hide{ display:none;}
table {border-collapse: collapse;width:100%;}
thead {max-width: 1024px}
tbody tr:nth-child(odd){ background:#f9f9f9;}
td { padding:.2em 1em .2em .2em; border-bottom:1px solid #def;height:30px; font-size:12px;white-space: nowrap;}
td.first {font-size:14px;white-space: normal;}
td.empty { color:#777; font-style: italic; text-align: center;padding:3em 0;}
.is_dir .size {color:transparent;font-size:0;}
.is_dir .size:before {content: "--"; font-size:14px;color:#333;}
.is_dir .download{visibility: hidden}
a.delete {display:inline-block;
	background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAFFJREFUOI1jYEAFpxkYGP7jwScZCID/DAwM5jjkLKDyBA0gSZ6RSI3YACO6ALGGoKhjIqAIncYAuAwgGowaMCwMQAZkpUQWJPZpdEkc4DgyBwDUVxeagilzrQAAAABJRU5ErkJggg==) no-repeat scroll 0 center;
	color:#d00;	margin-left: 15px;font-size:11px;padding:4px 0 4px 22px;
}
.name {
	background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAxklEQVRIS2NkoDFgRDd/4okfqv+YmLgJ2cv8n+H/OzPWyw2MjP/wqcWwYMKJH0cYmBitCVnAxcrI8O3PvxkfTNiz8VlCtgX6kkwMH38wMDz48BevJRRZoCTEzHD+2V+8llBsASgo8VlCFQtgltz/8K+r0JS9HDn+yLaAnZmBgZUZof0/AwPDl5//DhaaczhQxQJsqez///+jFuDPfqNBRKh4YhgNotEggoQAsTXa4C2LiK30sfqA+d+XImOOO3iLa4JJhUQFAGCaxRkn20dsAAAAAElFTkSuQmCC) no-repeat scroll 0px center;
	padding:15px 0 10px 40px;
}
.updir .name{
	background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAMAAAC6V+0/AAAAgVBMVEUAAAAAf38/P78/X78zTLI/Vb86TrA/T689T7hBULY+U7Y9ULNAUrU+UbU+UbM+ULQ+UbQ+ULQ+UbU+ULU+UbQ/UbU+UbM/UbU+UbQ+UbU+T7U/UbQ+UbQ/T7M+UbQ9UbU/UbU9T7U+UbM9UbM9T7U/T7M9T7U9UbU9T7M9T7U/UbW/jtJuAAAAKnRSTlMAAgQICgwNEB0jMTY7RVFSan+Hj5OqrK2vsLS5vL3Az9bX2Nzg5ufr9/zc0zIpAAAAcElEQVQYGX3BBRbCQBBEwcbdfVg82L//AUmQJQw8qvRPo6cvtd2lJae4hFNdn8akNhXl9bmbF/TWufIwVFSdhnAGQghN5RkgzwB5BsgzQJ4B8hZASc4IGMhpkzruMytFE14SRWXjKVFOd7Y9ZNb67QZmrxAIbMvMlAAAAABJRU5ErkJggg==) no-repeat scroll 0px center;
	padding:15px 0 10px 40px;
}
.is_dir .name {
	background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAABQElEQVRIS+2VvS9DURjGn+fcdNLUTYT4GMTSIMZqV0M18THZDVYjCWtXEjYzGn+AlElNpia3OzoYaiBEcyWYOK9wGJq0uadt7p1659/z/s7z5t57iJAfhjwf0QlEQBxhBkBfQysHgjt4zEN30va3geShZAJnFCw2G6I1CqqGtU4kRlBABhplxGKAUk0PKsop0o0fBrb4lDqXnq/+OSM4RhZjoyUkEoF5G0B/vNecufvxH9YIzgfWMTR4YBO2Zl7qq1x4OjGCi+EtuO6OddgG9P1t5h53/wQjG3D792xy1oz/usncw74ReFNZiJSswzYgOc/Z68ueoPWyeisKfJGiXVFlOgOty4GnagvQaaarnvkOBBQvWSTUclszWsBCOWXqdoWENNxoUklO4ovxriSOvDFVvWn4XXc1MCAc3Z0cVotvGM+KGR56VXoAAAAASUVORK5CYII=) no-repeat scroll 0px center;
	padding:15px 0 10px 40px;
}
.download {
	background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAGlJREFUOI1jYKAQMOKR+0+MWiZKXTBqAI0MmMWAGYUMULFZxBjKzsDAcByqARkfg8oRBaQYGBieI2l+DhXDCWAKkYEVAwPDLyi2IqQemwEMDAwM6QwMDGnEWIjLAFwArp4FiwRJgOJ0AAApLx4H+OZ62AAAAABJRU5ErkJggg==) no-repeat scroll 0px 5px;
	padding:4px 0 4px 22px;
}
</style>
<script src="http://libs.baidu.com/jquery/1.9.0/jquery.js"></script>
<script>
(function($){
	$.fn.tablesorter = function() {
		var $table = this;
		this.find('th').click(function() {
			var idx = $(this).index();
			var direction = $(this).hasClass('sort_asc');
			$table.tablesortby(idx,direction);
		});
		return this;
	};
	$.fn.tablesortby = function(idx,direction) {
		var $rows = this.find('tbody tr');
		function elementToVal(a) {
			var $a_elem = $(a).find('td:nth-child('+(idx+1)+')');
			var a_val = $a_elem.attr('data-sort') || $a_elem.text();
			return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
		}
		$rows.sort(function(a,b){
			var a_val = elementToVal(a), b_val = elementToVal(b);
			return (a_val > b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
		})
		this.find('th').removeClass('sort_asc sort_desc');
		$(this).find('thead th:nth-child('+(idx+1)+')').addClass(direction ? 'sort_desc' : 'sort_asc');
		for(var i =0;i<$rows.length;i++)
			this.append($rows[i]);
		this.settablesortmarkers();
		return this;
	}
	$.fn.retablesort = function() {
		var $e = this.find('thead th.sort_asc, thead th.sort_desc');
		if($e.length)
			this.tablesortby($e.index(), $e.hasClass('sort_desc') );
		
		return this;
	}
	$.fn.settablesortmarkers = function() {
		this.find('thead th span.indicator').remove();
		this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>');
		this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>');
		return this;
	}
})(jQuery);
$(function(){
	var XSRF = (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)')||0)[2];
	var MAX_UPLOAD_SIZE = <?php echo $MAX_UPLOAD_SIZE ?>;
	var $tbody = $('#list');
	$(window).bind('hashchange',list).trigger('hashchange');
	$('#table').tablesorter();
	
	$(document.body).on('click','.delete',function(data) {
		confirm('Sure?') && $.post("",{'do':'delete',file:$(this).attr('data-file'),xsrf:XSRF},function(response){
			list();
		},'json');
		return false;
	});
	
	$(document.body).on('click','.name',function(data) {
		var href = $(this).attr('href');
		if( href.substr(0,1) != '#' ){
			readerDialog(href);
			return false;
		}
	});
	
	$(document).bind("click",function(e){
		var target  = $(e.target);
		if(target.closest("#dialog").length == 0){
			$("#dialog").remove();
		}
	});

	$('#mkdir').submit(function(e) {
		var hashval = window.location.hash.substr(1),
			$dir = $(this).find('[name=name]');
		e.preventDefault();
		$dir.val().length && $.post('?',{'do':'mkdir',name:$dir.val(),xsrf:XSRF,file:hashval},function(data){
			list();
		},'json');
		$dir.val('');
		return false;
	});

	// file upload stuff
	$('#file_drop_target').bind('dragover',function(){
		$(this).addClass('drag_over');
		return false;
	}).bind('dragend',function(){
		$(this).removeClass('drag_over');
		return false;
	}).bind('drop',function(e){
		e.preventDefault();
		var files = e.originalEvent.dataTransfer.files;
		$.each(files,function(k,file) {
			uploadFile(file);
		});
		$(this).removeClass('drag_over');
	});
	$('input[type=file]').change(function(e) {
		e.preventDefault();
		$.each(this.files,function(k,file) {
			uploadFile(file);
		});
	});

	function uploadFile(file) {
		var folder = window.location.hash.substr(1);

		if(file.size > MAX_UPLOAD_SIZE) {
			var $error_row = renderFileSizeErrorRow(file,folder);
			$('#upload_progress').append($error_row);
			window.setTimeout(function(){$error_row.fadeOut();},5000);
			return false;
		}
		
		var $row = renderFileUploadRow(file,folder);
		$('#upload_progress').append($row);
		var fd = new FormData();
		fd.append('file_data',file);
		fd.append('file',folder);
		fd.append('xsrf',XSRF);
		fd.append('do','upload');
		var xhr = new XMLHttpRequest();
		xhr.open('POST', '?');
		xhr.onload = function() {
			$row.remove();
    		list();
  		};
		xhr.upload.onprogress = function(e){
			if(e.lengthComputable) {
				$row.find('.progress').css('width',(e.loaded/e.total*100 | 0)+'%' );
			}
		};
	    xhr.send(fd);
	}
	function renderFileUploadRow(file,folder) {
		return $row = $('<div/>')
			.append( $('<span class="fileuploadname" />').text( (folder ? folder+'/':'')+file.name))
			.append( $('<div class="progress_track"><div class="progress"></div></div>')  )
			.append( $('<span class="size" />').text(formatFileSize(file.size)) )
	};
	function renderFileSizeErrorRow(file,folder) {
		return $row = $('<div class="error" />')
			.append( $('<span class="fileuploadname" />').text( 'Error: ' + (folder ? folder+'/':'')+file.name))
			.append( $('<span/>').html(' file size - <b>' + formatFileSize(file.size) + '</b>'
				+' exceeds max upload size of <b>' + formatFileSize(MAX_UPLOAD_SIZE) + '</b>')  );
	}

	function list() {
		var hashval = window.location.hash.substr(1);
		$.get('?',{'do':'list','file':hashval},function(data) {
			$tbody.empty();
			$('#breadcrumb').empty().html(renderBreadcrumbs(hashval));
			if(data.success) {
			
				if( hashval.slice(-1) == '/' && (hashval.match(/\//g) || []).length == 1 ){
					$('#head .name').removeAttr('href').text( data.parent );
				}else{
					$('#head .name').attr('href', '#' + data.parent ).text( data.parent );
				}
			
				$.each(data.results,function(k,v){
					$tbody.append(renderFileRow(v));
				});
				!data.results.length && $tbody.append('<tr><td class="empty" colspan=5>This folder is empty</td</td>')
				data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
			} else {
				console.warn(data.error.msg);
			}
			$('#table').retablesort();
		},'json');
	}
	function renderFileRow(data) {
		var $link = $('<a class="name" />')
			.attr('href', data.is_dir ? '#' + data.path : '?do=reader&file='+data.path)
			.text(data.name);
		var $dl_link = $('<a/>').attr('href','?do=download&file='+encodeURIComponent(data.path))
			.addClass('download').text('download');
		var $delete_link = $('<a href="#" />').attr('data-file',data.path).addClass('delete').text('delete');
		var perms = [];
		if(data.is_readable) perms.push('read');
		if(data.is_writable) perms.push('write');
		if(data.is_executable) perms.push('exec');
		
		var $html = $('<tr />')
			.addClass(data.is_dir ? 'is_dir' : '')
			.append( $('<td class="first" />').append($link) )
			.append( $('<td/>').attr('data-sort',data.is_dir ? -1 : data.size)
				.html($('<span class="size" />').text(formatFileSize(data.size))) ) 
			.append( $('<td/>').attr('data-sort',data.mtime).text(formatTimestamp(data.mtime)) )
			.append( $('<td/>').text(perms.join('+')) )
			.append( $('<td/>').append($dl_link).append( data.is_deleteable ? $delete_link : '') )
		return $html;
	}
	function renderBreadcrumbs(path) {
		var base = "",
			$html = $('<div/>').append( $('<a href=#>#</a></div>') );
		$.each(path.split('/'),function(k,v){
			if(v) {
				var href = ( ( base + v ).indexOf(':')>-1 ? '' : '/' ) + base + v;
				$html.append( $('<span/>').text(' ▸ ') )
					.append( $('<a/>').attr('href','#'+ href ).text(v) );
				base += v + '/';
			}
		});
		return $html;
	}
	function formatTimestamp(unix_timestamp) {
		var m = ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'];
		var d = new Date(unix_timestamp*1000);
		return [m[d.getMonth()],' ',d.getDate(),', ',d.getFullYear()," ",
			(d.getHours() % 12 || 12),":",(d.getMinutes() < 10 ? '0' : '')+d.getMinutes(),
			" ",d.getHours() >= 12 ? '下午' : '上午'].join('');
	}
	function formatFileSize(bytes) {
		var s = ['bytes', 'KB','MB','GB','TB','PB','EB'];
		for(var pos = 0;bytes >= 1000; pos++,bytes /= 1024);
		var d = Math.round(bytes*10);
		return pos ? [parseInt(d/10),".",d%10," ",s[pos]].join('') : bytes + ' bytes';
	}
	
	function readerDialog(href){
		
		$("#dialog").remove();
		
		var html = $('<dl id="dialog" draggable="true" />')
			.append( $('<dt />')
				.append( $('<span class="remove" />').html('&times').click(function(){ $( html ).remove(); }) )
				.append( $('<span class="saving" />').html('<button>保存</button>').click(function(){ $( html ).remove(); }) )
				.append( $('<strong class="title" />').text( href.split('file=')[1] ) ) )
			.append( $('<dd><iframe src="'+ href +'" /></dd>') );
			
		//console.log( html );
		
		$(document.body).append( html );
			
	}
})

</script>
</head>
<body>

<header>
	<h2>XFile</h2>
	<form action="?" method="post" id="mkdir" />
		<input id=dirname type=text name=name value="" placeholder="文件夹名称……" />
		<input type="submit" value="创建" />
	</form>
	<div id="file_drop_target">
		拖动文件到这里上传
		<b>or</b>
		<input type="file" multiple />
	</div>
	<div id="breadcrumb">&nbsp;</div>
</header>

<div id="upload_progress"></div>
<table id="table">
<thead>
	<tr>
		<th>名称</th>
		<th>大小</th>
		<th>最后修改</th>
		<th>权限</th>
		<th width="160">操作</th>
	</tr>
</thead>
<thead id="head">
	<tr class="updir">
		<td><a class="name" href="#../">../</a></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
	</tr>
</thead>
<tbody id="list"></tbody>
</table>
<footer>By <a href="https://github.com/jcampbell1" target="_blank">jcampbell1</a> & <a href="http://veryide.net/" target="_blank">veryide</a><footer>
</body></html>