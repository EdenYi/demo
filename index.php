<?php


class Member 
{

    /**
     * 绑定邮件重新发送验证码
     */
    public function bindsmsAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {
            $model = new MobileModel();
            $m = $model->getBindAccount($params['memberid']);

            if (!$m) {
                $send['error'] = 'Data error';
                break;
            }

            if ($m['account'] != $params['account']) {
                $send['error'] = 'Mobile phone number does not match';
                break;
            }

            $appkey = $this->_getAppkey();
            $code = $this->_sendSms($appkey, $params['account']);
            if ($model->bindEmailOrMobile($params, $code)) {
                $send['state'] = true;
                $send['data']  = array(
                    'message'   => 'success',
                    'lifetime'  => self::SMS_LIFETIME
                );
            } else {
                $send['error'] = 'Data error';
            }
        } while (0);
        $this->sendOutput($send);
    }



    public function ()
    { 
	echo 'Hello';
    }

    public function ()
    {
	phpinfo();
    }
}
