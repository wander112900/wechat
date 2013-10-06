wechat
======

PHP版微信公共平台消息主动推送10月6日最新实现


使用方法：

$arr = array(
	'account' => '公众平台帐号',
	'password' => '密码'
);
$w = new Weixin($arr);

$w->getAllUserInfo();//获取用户信息

$w->sendMessage('群发内容'); //群发给所有用户

$w->sendMessage('群发内容',$userId); //群发给特定用户

