<?php
require_once 'WxPayApi.php';

/**
 * 回调基础类
 * @author stone
 */
class WxPayNotify
{
    /**
	 * 回调入口
     * @author stone
	 */
	final public function handle($attachParams){
		// 当返回false的时候，表示notify中调用notify_call_back回调失败获取签名校验失败，此时直接回复失败
		$result = WxPayApi::notify([$this, 'notify_call_back'], $attachParams);
		if (!empty($result) && is_array($result) && isset($result['code']) && $result['code'] == -1) {
			$this->reply_notify(['return_code' => 'FAIL', 'return_msg' => $result['message']]);
			return;
		}
		return $result;
	}

    /**
	 * 回复通知
	 * @param array $params ['return_code' => 'FAIL', 'return_msg' => $result['message']]
     * @author stone
	 */
	final private function reply_notify($params){
		WxpayApi::reply_notify(array_to_xml($params));
	}

    /**
	 * notify回调方法，该方法中需要赋值需要输出的参数,不可重写
	 * @param array $data
	 * @param array $attachParams
	 * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     * @author stone
	 */
	final public function notify_call_back($data, $attachParams){
        return $this->notify_process($data, $attachParams);
	}

    /**
	 * 回调方法入口，子类可重写该方法
	 * 注意：
	 * 1、微信回调超时时间为2s，建议用户使用异步处理流程，确认成功之后立刻回复微信服务器
	 * 2、微信服务器在调用失败或者接到回包为非确认包的时候，会发起重试，需确保你的回调是可以重入
	 * @param array $data 回调解释出的参数
	 * @param array $attachParams
	 * @return array
	 */
	public function notify_process($data, $attachParams){
		if (!array_key_exists('transaction_id', $data)) {
			return ['code' => -1, 'message' => '输入参数不正确'];
		}
		// 查询订单，判断订单真实性
		if (!$this->query_order($data['transaction_id'], $attachParams)) {
            return ['code' => -1, 'message' => '订单查询失败'];
		}

		return $data;
	}

    /**
     * 查询订单
     * @param array $transactionId
     * @param array $attachParams
     * @author stone
     */
	public function query_order($transactionId, $attachParams){
		$result = WxPayApi::order_query($transactionId, $attachParams);
		
		if (array_key_exists('return_code', $result) && array_key_exists('result_code', $result) && $result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
			return true;
		}
		return false;
	}
}