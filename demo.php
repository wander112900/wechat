<?php
    require './weixin.php';
	$arr = array(
		'account' => '',
		'password' => ''
	);
	$w = new Weixin($arr);
	var_dump($w->getAllUserInfo());//获取所有用户信息
	$a = $w->sendMessage('群发内123123123容');
	var_dump($a);
?>