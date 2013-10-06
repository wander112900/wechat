wechat
======
EMAIL:wander112900@gmail.com

PHP版微信公共平台消息主动推送10月6日最新实现

模拟登录微信公共平台，实现信息发送；

突破订阅号一天只能发送一条信息的限制。


使用方法：

$arr = array(
	'account' => '公众平台帐号',
	'password' => '密码'
);

$w = new Weixin($arr);

$w->getAllUserInfo();//获取用户信息

$w->sendMessage('群发内容'); //群发给所有用户

$w->sendMessage('群发内容',$userId); //群发给特定用户


本实例仅供参考，由此引发的法律风险，本人概不负责。谢谢。