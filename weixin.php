<?php
/*
使用方法：
 $arr = array(
	'account' => '公众平台帐号',
	'password' => '密码'
);
$w = new Weixin($arr);
$w->getAllUserInfo();//获取用户信息
$w->sendMessage('群发内容'); //群发给所有用户
$w->sendMessage('群发内容',$userId); //群发给特定用户
*/
class Weixin {
	public $userFakeid;//所有粉丝的fakeid
	private $_account;//用户名
	private $_password;//密码
	private $url;//请求的网址
	private $send_data;//提交的数据
	private $getHeader = 0;//是否显示Header信息
	private $token;//公共帐号TOKEN
	private $host = 'mp.weixin.qq.com';//主机
	private $origin = 'https://mp.weixin.qq.com';
	private $referer;//引用地址
	private $cookie;
	private $pageSize = 100000;//每页用户数（用于读取所有用户）
	private $userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:23.0) Gecko/20100101 Firefox/23.0';
	
	
	public function __construct($options){
		$this->_account = isset($options['account'])?$options['account']:'';
		$this->_password = isset($options['password'])?$options['password']:'';
		$this->login();
	}
	
	//登录
	private function login(){
		$url = 'https://mp.weixin.qq.com/cgi-bin/login?lang=zh_CN';
		$this->send_data = array(
            'username' => $this->_account,
            'pwd' => md5($this->_password),
            'f' => 'json'
        );
		$this->referer = "https://mp.weixin.qq.com/";
		$this->getHeader = 1;
		$result = explode("\n",$this->curlPost($url));
		foreach ($result as $key => $value) {
			$value = trim($value);
			if(preg_match('/"ErrCode": (.*)/i', $value,$match)){//获取token
				switch ($match[1]) {
					case -1:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"系统错误")));
					case -2:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"帐号或密码错误")));
					case -3:
						die(urldecode(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>urlencode("密码错误")))));
					case -4:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"不存在该帐户")));
					case -5:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"访问受限")));
					case -6:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"需要输入验证码")));
					case -7:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"此帐号已绑定私人微信号，不可用于公众平台登录")));
					case -8:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"邮箱已存在")));
					case -32:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"验证码输入错误")));
					case -200:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"因频繁提交虚假资料，该帐号被拒绝登录")));
					case -94:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"请使用邮箱登陆")));
					case 10:
						die(json_encode(array('status'=>1,'errCode'=>$match[1],'msg'=>"该公众会议号已经过期，无法再登录使用")));
					case 0:
					    $this->userFakeid = $this->getUserFakeid();
						break;
				}
			}
			if(preg_match('/^set-cookie:[\s]+([^=]+)=([^;]+)/i', $value,$match)){//获取cookie
				$this->cookie .=$match[1].'='.$match[2].'; ';
			}
			if(preg_match('/"ErrMsg"/i', $value,$match)){//获取token
		    	$this->token = rtrim(substr($value,strrpos($value,'=')+1),'",');
			}
		}
	}
	
    //单发消息
	private function send($fakeid,$content){
		$url = 'https://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response&lang=zh_CN';
		$this->send_data = array(
				'type' => 1,
				'content' => $content,
				'error' => 'false',
				'tofakeid' => $fakeid,
				'token' => $this->token,
				'ajax' => 1,
			);
		$this->referer = 'https://mp.weixin.qq.com/cgi-bin/singlemsgpage?token='.$this->token.'&fromfakeid='.$fakeid.'&msgid=&source=&count=20&t=wxm-singlechat&lang=zh_CN';
		return $this->curlPost($url);
	}
	
	//群发消息
    public function sendMessage($content='',$userId='') {
		if(is_array($userId) && !empty($userId)){
			foreach($userId as $v){
				$json = json_decode($this->send($v,$content));
				if($json->ret!=0){
					$errUser[] = $v;
				}
			}
		}else{
			foreach($this->userFakeid as $v){
				$json = json_decode($this->send($v['fakeid'],$content));
				if($json->ret!=0){
					$errUser[] = $v['fakeid'];
				}
			}
		}
		
		//共发送用户数
		$count = count($this->userFakeid);
		//发送失败用户数
		$errCount = count($errUser);
		//发送成功用户数
		$succeCount = $count-$errCount;
		
		$data = array(
			'status'=>0,
			'count'=>$count,
			'succeCount'=>$succeCount,
			'errCount'=>$errCount,
			'errUser'=>$errUser 
		);
		
		return json_encode($data);
    }
	//获取所有用户信息
	public function getAllUserInfo(){
		foreach($this->userFakeid as $v){
			$info[] = $this->getUserInfo($v['groupid'],$v['fakeid']);
		}
		
		return $info;
	}
	
	
	
	//获取用户信息
	public function getUserInfo($groupId,$fakeId){
		$url = "https://mp.weixin.qq.com/cgi-bin/getcontactinfo?t=ajax-getcontactinfo&lang=zh_CN&fakeid={$fakeId}";
		$this->getHeader = 0;
		$this->referer = 'https://mp.weixin.qq.com/cgi-bin/contactmanagepage?token='.$this->token.'&t=wxm-friend&lang=zh_CN&pagesize='.$this->pageSize.'&pageidx=0&type=0&groupid='.$groupId;
		$this->send_data = array(
			'token'=>$this->token,
			'ajax'=>1
		);
        $message_opt = $this->curlPost($url);
        return $message_opt;
	}
	
	//获取所有用户fakeid
	private function getUserFakeid(){
		ini_set('max_execution_time',600);
		$pageSize = 1000000;
		$this->referer = "https://mp.weixin.qq.com/cgi-bin/home?t=home/index&lang=zh_CN&token={$_SESSION['token']}";
		$url = "https://mp.weixin.qq.com/cgi-bin/contactmanage?t=user/index&pagesize={$pageSize}&pageidx=0&type=0&groupid=0&token={$this->token}&lang=zh_CN";
		$user = $this->vget($url);
		$preg = "/\"id\":(\d+),\"name\"/";
		preg_match_all($preg,$user,$b);
		$i = 0;
		foreach($b[1] as $v){
			$url = 'https://mp.weixin.qq.com/cgi-bin/contactmanage?t=user/index&pagesize='.$pageSize.'&pageidx=0&type=0&groupid='.$v.'&token='.$this->token.'&lang=zh_CN';
			$user = $this->vget($url);
			$preg = "/\"id\":(\d+),\"nick_name\"/";
			preg_match_all($preg,$user,$a);
			foreach($a[1] as $vv){
				$arr[$i]['fakeid'] = $vv;
				$arr[$i]['groupid'] = $v;
				$i++;
			}
		}
		return $arr;
	}

    /**
     * curl模拟登录的post方法
     * @param $url request地址
     * @param $header 模拟headre头信息
     * @return json
     */
    private function curlPost($url) {
		$header = array(
            'Accept:*/*',
            'Accept-Charset:GBK,utf-8;q=0.7,*;q=0.3',
            'Accept-Encoding:gzip,deflate,sdch',
            'Accept-Language:zh-CN,zh;q=0.8',
            'Connection:keep-alive',
            'Host:'.$this->host,
            'Origin:'.$this->origin,
            'Referer:'.$this->referer,
            'X-Requested-With:XMLHttpRequest'
        );
        $curl = curl_init(); //启动一个curl会话
        curl_setopt($curl, CURLOPT_URL, $url); //要访问的地址
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header); //设置HTTP头字段的数组
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); //从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $this->useragent); //模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); //使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); //自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); //发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->send_data); //Post提交的数据包
        curl_setopt($curl, CURLOPT_COOKIE, $this->cookie); //读取储存的Cookie信息
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); //设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, $this->getHeader); //显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //获取的信息以文件流的形式返回
        $result = curl_exec($curl); //执行一个curl会话
        curl_close($curl); //关闭curl
        return $result;
    }
	
	private function vget($url){ // 模拟获取内容函数
		$header = array(
				'Accept:*/*',
				'Accept-Encoding:gzip,deflate',
				'Accept-Language:zh-CN,zh;q=0.8',
				'Connection:keep-alive',
				'Host:mp.weixin.qq.com',
				'Referer:'.$this->referer,
				'X-Requested-With:XMLHttpRequest'
		);
		
		$useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:23.0) Gecko/20100101 Firefox/23.0';
		$curl = curl_init(); // 启动一个CURL会话
		curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header); //设置HTTP头字段的数组
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
		curl_setopt($curl, CURLOPT_USERAGENT, $useragent); // 模拟用户使用的浏览器
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
		curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
		curl_setopt($curl, CURLOPT_HTTPGET, 1); // 发送一个常规的GET请求
		curl_setopt($curl, CURLOPT_COOKIE, $this->cookie); // 读取上面所储存的Cookie信息
		curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
		curl_setopt($curl, CURLOPT_HEADER, $this->getHeader); // 显示返回的Header区域内容
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
		$tmpInfo = curl_exec($curl); // 执行操作
		if (curl_errno($curl)) {
			// echo 'Errno'.curl_error($curl);
		}
		curl_close($curl); // 关闭CURL会话
		return $tmpInfo; // 返回数据
	}

}

?>
