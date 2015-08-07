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

   /**
    * 退出登录
    */
    public function loginoutAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        $model = new MobileModel();

        $patternArr = Application::app()->getConfig()->application->environment;
        $configArr = $patternArr->toArray();
        $pattern = $configArr['mode'];

        // 移动端已修改用户退出方案，这里不论私有云还是公有云，都只退出当前移动终端上的app
        if ($pattern == 'public') {
            $res = $model->loginOutPublicCloud($params);
        } else {
            $res = $model->loginOutPublicCloud($params);
        }

        if (!$res) {
            $send['error'] = 'Loginout failed';
        } else {
            $send['state'] = true;
            $send['data']['message'] = 'success';
        }
        $this->sendOutput($send);
    }
}
