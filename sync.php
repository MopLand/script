<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>会员同步接口</title>
<style>
body{ padding:0; margin:0; }
p{ padding:15px; margin: 0 auto; width:750px; }
input, button, select, textarea, iframe{ padding:10px; border-radius:5px; border:#999 solid 1px; }
select{ width:680px; }
iframe{ width:720px; height:200px; border:#69A1E9 solid 1px; }
</style>
<script src="http://www.veryide.com/projects/mojs/lib/mo.js" type="text/javascript" charset="utf-8"></script>
<script>
var action = {
	'profile' : 'mobile=18672524888&fields=*',
	'credit' : 'mobile=18672524888&change=1&reason=test...',
	'money' : 'mobile=18672524888&change=1&reason=test...',
	'address' : 'mobile=18672524888',	
	'level' : '',
	'setting' : '',	
	'privilege' : '',	
	'activity' : '',
	'branch' : '',
	'member' : 'search={"username":"demo"}'	
};
Mo.reader(function(){

	Mo("select[name=action]").bind('change',function(){
	
		var val = Mo( this ).value();
		var pam = '<?php echo $_SERVER['REQUEST_METHOD'] != 'POST';?>';
		
		if( pam && val ){
			Mo( "textarea[name=param]" ).value( action[ val ] );
		}
		
	}).event('change');	
		
});
</script>
</head>

<body>

<?php

$gateway = 'http://c1.yaf.com/sync/';
$security_code = 'ajikpm7295swrcfz';

/**生成签名结果
 *$array要加密的数组
 *return 签名结果字符串
*/
function build_mysign($sort_array,$action,$security_code) {
    $prestr = create_linkstring($sort_array);     	//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
    $prestr = $action . $prestr . $security_code;				//把拼接后的字符串再与安全校验码直接连接起来
    $mysgin = md5($prestr);			    //把最终的字符串加密，获得签名结果
    return $mysgin;
}	

/********************************************************************************/

/**把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
	*$array 需要拼接的数组
	*return 拼接完成以后的字符串
*/
function create_linkstring($array) {
    $arg  = "";
    while (list ($key, $val) = each ($array)) {
        $arg.=$key."=".$val."&";
    }
    $arg = substr($arg,0,count($arg)-2);		     //去掉最后一个&字符
    return $arg;
}

/**把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
 **使用场景：GET方式请求时，对URL的中文进行编码
	*$array 需要拼接的数组
	*return 拼接完成以后的字符串
*/
function create_linkstring_urlencode($array) {
    $arg  = "";
    while (list ($key, $val) = each ($array)) {
		if ($key != "service" && $key != "_input_charset")
			$arg.=$key."=".urlencode($val)."&";
		else $arg.=$key."=".$val."&";
    }
    $arg = substr($arg,0,count($arg)-2);		     //去掉最后一个&字符
    return $arg;
}



/**构造请求URL（GET方式请求）
*return 请求url
 */
function create_url( $url, $action, $param ) {
	//$sort_array  = array();
	//$sort_array  = arg_sort($this->parameter);
	global $security_code;
	
	$sort_array  = $param;
	
	$arg         = create_linkstring_urlencode($sort_array);	//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
	
	$sign = build_mysign( $param, $action, $security_code );
	
	//把网关地址、已经拼接好的参数数组字符串、签名结果、签名类型，拼接成最终完整请求url
	$url .= $arg."&sign=" .$sign;
	
	return str_replace( '?&', '?', $url );
}

//////////////////////

if( isset( $_POST['action'] ) ){
	$gateway .= $_POST['action'] . '?';
	
	if( isset( $_POST['action'] ) ){
		$param = $_POST['param'];
		parse_str( $param, $args );
	}else{
		$args = array();
	}
	
	$gateway = create_url( $gateway, $_POST['action'], $args );
}


?>


<form action='' method='post'>

	<p>
		<select name="action">
			<option value="profile">profile	- 	获取或更新用户基本资料</option>
			<option value="credit">credit - 	获取或更新用户积分值</option>
			<option value="money">money - 		获取或更新用户余额值</option>
			<option value="address">address - 	获取用户地址本</option>
			<option value="level">level - 		获取会员卡级别</option>
			<option value="setting">setting - 	获取用户钱包和积分设置</option>
			<option value="privilege">privilege - 获取会员卡特权列表</option>
			<option value="activity">activity - 获取会员卡活动列表</option>
			<option value="member">member - 	获取会员列表</option>
			<option value="branch">branch - 	获取分店列表</option>
		</select>
		<button type='submit'>测试</button>
	</p>

	<p>
		<textarea name="param" rows="5" cols="100"><?php echo isset( $_POST['param'] ) ? $_POST['param'] : '';?></textarea>
	</p>
	
	<?php if( $_SERVER['REQUEST_METHOD'] === 'POST' ){ ?>

	<p>
		<strong>Token：</strong><?php echo $security_code;?>
		<br />
		<strong>Proxy：</strong><?php echo $gateway;?>
	</p>
	
	<p><iframe src="<?php echo $gateway;?>" frameborder="0"></iframe></p>
	
	<script>
	Mo("select[name=action]").value('<?php echo $_POST['action'];?>');
	</script>
	
	<?php }	?>
	
</form>
</body>
</html>