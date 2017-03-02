<?php
require_once 'CommonFunctions.php';

/**
 * 对微信支付接口访问的封装
 * @author stone
 */
class WxPayApi
{
    /**
     * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param   $params = [
     *              'body' => $params['body'],
     *              'attach' => $attachData,
     *              'out_trade_no' => $params['orderNo'],
     *              'total_fee' => $params['money'] * 100,
     *              'time_start' => date('YmdHis'),
     *              'time_expire' => date('YmdHis', time() + 600),
     *              'notify_url' => 'https://'.$_SERVER['HTTP_HOST'].'/wxpay/'.$params['notifyAction'],
     *              'trade_type' => 'APP',
     *          ]
     * @param int $timeOut
     * @throws WxPayException
     * @return 成功时返回，其他抛异常
     * @stone
     */
    public static function unified_order($params, $attachParams, $timeOut = 6){
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        // 检测必填参数
        if (!$params['out_trade_no']) {
            return ['code' => -1, 'message' => '缺少统一支付接口必填参数out_trade_no！'];
        }
        if (!$params['body']) {
            return ['code' => -1, 'message' => '缺少统一支付接口必填参数body！'];
        }
        if (!$params['total_fee']) {
            return ['code' => -1, 'message' => '缺少统一支付接口必填参数total_fee！'];
        }
        if (!$params['trade_type']) {
            return ['code' => -1, 'message' => '缺少统一支付接口必填参数trade_type！'];
        }
        if (!$params['notify_url']) {
            return ['code' => -1, 'message' => '缺少统一支付接口必填参数notify_url！'];
        }

        // 关联参数
        if ($params['trade_type'] == 'JSAPI' && !$params['openid']) {
            return ['code' => -1, 'message' => '统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！'];
        }
        if ($params['trade_type'] == 'NATIVE' && !$params['product_id']) {
            return ['code' => -1, 'message' => '统一支付接口中，缺少必填参数product_id！trade_type为NATIVE时，product_id为必填参数！'];
        }
        
        // 签名
        $params['sign'] = self::generate_sign($params, $attachParams);
        $xml = array_to_xml($params);
        
        $startTimeStamp = self::get_millisecond(); // 请求开始时间
        $response = self::post_xml_curl($xml, $url, false, $timeOut, $attachParams);
        $result = self::init($response, $attachParams);
        self::report_cost_time($url, $startTimeStamp, $result, $attachParams); // 上报请求花费时间
        
        return $result;
    }

    /**
     * 生成签名
     * @author stone
     */
    private static function generate_sign($params, $attachParams){
        //签名步骤一：按字典序排序参数
		ksort($params);
		$string = generate_url_params($params, 1, 1, 1, ['sign']);
		//签名步骤二：在string后加入key
		//签名步骤三：MD5加密
		//签名步骤四：所有字符转为大写
		return strtoupper(md5($string.'key='.$attachParams['key']));
    }

    /**
	 * 获取毫秒级别的时间戳
     * @author stone
	 */
	private static function get_millisecond(){
		// 获取毫秒的时间戳
		$time = explode(' ', microtime());
		$time = $time[1].($time[0] * 1000);
		return explode('.', $time)[0];
	}

    /**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @param array $attachParams 附加参数
	 * @throws WxPayException
	 */
	private static function post_xml_curl($xml, $url, $useCert = false, $second = 30, $attachParams){
		$ch = curl_init();
		// 设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        // 如果有配置代理这里就设置代理
		if ($attachParams['curlProxyHost'] != '0.0.0.0' && $attachParams['curlProxyPort'] != 0) {
			curl_setopt($ch, CURLOPT_PROXY, $attachParams['curlProxyHost']);
			curl_setopt($ch, CURLOPT_PROXYPORT, $attachParams['curlProxyPort']);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 严格校验
		// 设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		// 要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if ($useCert == true) {
			// 设置证书
			// 使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLCERT, $attachParams['sslcertPath']);
			curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLKEY, $attachParams['sslkeyPath']);
		}
		// post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		// 运行curl
		$data = curl_exec($ch);
		// 返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
            return ['code' => -1, 'message' => 'curl出错，错误码:'.$error];
		}
	}

    /**
     * 将xml转为array并验签
     * @param string $xml
     * @param array $attachParams
     * @author stone
     */
	public static function init($xml, $attachParams){
		$result = xml_to_array($xml);
		if ($result['return_code'] != 'SUCCESS') {
			 return $result;
		}
		$result['checkSignRes'] = self::check_sign($result, $attachParams);
        return $result;
	}

    /**
	 * 微信支付检测签名
	 * @author stone
	 */
	public static function check_sign($result, $attachParams){
		if ($result['sign']) {
			$sign = self::generate_sign($result, $attachParams);
            if ($result['sign'] == $sign) {
                return ['code' => 0, 'message' => '签名正确！'];
            } else {
                return ['code' => -1, 'message' => '签名错误！'];
            }
		} else {
            return ['code' => -1, 'message' => '签名错误！'];
        }
	}

    /**
	 * 上报数据，上报的时候将屏蔽所有异常流程
	 * @param string $url
	 * @param int $startTimeStamp
	 * @param array $data
	 * @param array $attachParams
	 */
	private static function report_cost_time($url, $startTimeStamp, $data, $attachParams){
		// 如果不需要上报数据
		if ($attachParams['reportLevel'] == 0) {
			return;
		} 
		// 如果仅失败上报
		if ($attachParams['reportLevel'] == 1 && array_key_exists('return_code', $data) && $data['return_code'] == 'SUCCESS' && array_key_exists('result_code', $data) && $data['result_code'] == 'SUCCESS'){
			return;
		}
		 
		// 上报数据
        $reportData = [
            'interface_url' => $url,
            'execute_time_' => self::get_millisecond() - $startTimeStamp,
        ];
		// 返回状态码
		if (array_key_exists('return_code', $data)) {
            $reportData['return_code'] = $data['return_code'];
		}
		// 返回信息
		if (array_key_exists('return_msg', $data)) {
            $reportData['return_msg'] = $data['return_msg'];
		}
		// 业务结果
		if (array_key_exists('result_code', $data)) {
            $reportData['result_code'] = $data['result_code'];
		}
		// 错误代码
		if (array_key_exists('err_code', $data)) {
            $reportData['err_code'] = $data['err_code'];
		}
		// 错误代码描述
		if (array_key_exists('err_code_des', $data)) {
            $reportData['err_code_des'] = $data['err_code_des'];
		}
		// 商户订单号
		if (array_key_exists('out_trade_no', $data)) {
            $reportData['out_trade_no'] = $data['out_trade_no'];
		}
		// 设备号
		if (array_key_exists('device_info', $data)) {
            $reportData['device_info'] = $data['device_info'];
		}
		
		self::report($reportData, $attachParams);
	}

    /**
	 * 
	 * 测速上报，该方法内部封装在report中，使用时请注意异常流程
	 * WxPayReport中interface_url、return_code、result_code、user_ip、execute_time_必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param array $params
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function report($params, $attachParams, $timeOut = 1){
		$url = 'https://api.mch.weixin.qq.com/payitil/report';
		// 检测必填参数
		if (!$params['interface_url']) {
            return ['code' => -1, 'message' => '接口URL，缺少必填参数interface_url！'];
		} 
		if (!$params['return_code']) {
            return ['code' => -1, 'message' => '接口URL，缺少必填参数return_code！'];
		}
		if (!$params['result_code']) {
            return ['code' => -1, 'message' => '接口URL，缺少必填参数result_code！'];
		}
		/*if (!$params['user_ip']) {
            return ['code' => -1, 'message' => '接口URL，缺少必填参数user_ip！'];
		}*/
		if (!$params['execute_time_']) {
            return ['code' => -1, 'message' => '接口URL，缺少必填参数execute_time_！'];
		}

        $params['appid'] = $attachParams['appId']; // 公众账号ID
        $params['mch_id'] = $attachParams['mchId']; // 商户号
        $params['user_ip'] = $_SERVER['REMOTE_ADDR']; // 终端IP
        $params['time'] = date('YmdHis'); // 商户上报时间
        $params['nonce_str'] = $attachParams['nonceStr']; // 随机字符串
		
        $params['sign'] = self::generate_sign($params, $attachParams); // 签名
        $xml = array_to_xml($params);

		$response = self::post_xml_curl($xml, $url, false, $timeOut);
		return $response;
	}

    /**
 	 * 
 	 * 支付结果通用通知
 	 * @param function $callback
 	 * 直接回调函数使用方法: notify(you_function);
 	 * 回调类成员函数方法:notify(array($this, you_function));
 	 * $callback  原型为：function function_name($data){}
 	 */
	public static function notify($callback, $attachParams)
	{
		// 获取通知的数据
		$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
		// 如果返回成功则验证签名
		$result = self::init($xml, $attachParams);
        if (!empty($result) && is_array($result)) {
            if (isset($result['code']) && $result['code'] == -1) {
                return ['code' => -1, 'message' => $result['message']];
            }
            if (!empty($result['checkSignRes']) && is_array($result['checkSignRes']) && $result['checkSignRes']['code'] == -1) {
                return ['code' => -1, 'message' => $result['checkSignRes']['message']];
            }
        }
		
		return call_user_func($callback, $result, $attachParams);
	}

    /**
	 * 直接输出xml
	 * @param string $xml
     * @author stone
	 */
	public static function reply_notify($xml){
		echo $xml;
	}

    /**
	 * 
	 * 查询订单，$params中out_trade_no、transaction_id至少填一个
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param array $params
	 * @param array $attachParams
	 * @param int $timeOut
	 * @author stone
	 */
	public static function order_query($transactionId, $attachParams, $timeOut = 6){
		$url = 'https://api.mch.weixin.qq.com/pay/orderquery';
		// 检测必填参数
		if (!$transactionId) {
            return ['code' => -1, 'message' => '订单查询接口中缺少必要参数！'];
		}

        $queryParams = [
            'appid' => $attachParams['appId'],
            'mch_id' => $attachParams['mchId'],
            'transaction_id' => $transactionId,
            'nonce_str' => get_random_string(32), // 随机字符串
        ];

        // 签名
        $queryParams['sign'] = self::generate_sign($queryParams, $attachParams);
        $xml = array_to_xml($queryParams);
        
        $startTimeStamp = self::get_millisecond(); // 请求开始时间
        $response = self::post_xml_curl($xml, $url, false, $timeOut, $attachParams);
        $result = self::init($response, $attachParams);
        self::report_cost_time($url, $startTimeStamp, $result, $attachParams); // 上报请求花费时间
        
        return $result;
	}
}