<?php

<?php
/**
 * Description mobile client interface
 *
 * @author     YiDe
 * @datetime   2015/6/29 0029 下午 2:50
 * @copyright  Beijing CmsTop Technology Co.,Ltd.
 */

use Core\Api;
use Yaf\Dispatcher;
use Yaf\Application;
use Model\Member\MobileModel;
use General\Util\Sendmail;

final class API_MobileController extends Api
{

    /**
     * 验证码过期时间
     */
    const SMS_LIFETIME = 120;
    const SMS_INTERFACE = 'http://sms-api.luosimao.com/v1/send.json';

    /**
     * 初始化
     */
    public function init ()
    {
        parent::init();
        Dispatcher::getInstance()->disableView();
    }

    /**
     * 移动端请求发送验证码
     */
    public function codeAction ()
    {
        $send['state'] = false;

        $params = $this->filterParams($this->_params);
        $mobile = $params['mobile'];
        do {
            $appkey = $this->_getAppkey();
            $code = $this->_sendSms($appkey, $mobile);
            if ($code) {

                $model = new MobileModel();

                // is user registed
                if ($model->checkUserRegisted($mobile)) {
                    $send['error'] = 'The user is regited';
                    break;
                }

                // save to regist table
                $saveToRegist = $model->saveToRegist(array(
                    'siteid'    => $params['siteid'],
                    'ip'        => $params['ip'],
                    'code'      => $code,
                    'mobile'    => $mobile,
                    'lifetime'  => self::SMS_LIFETIME
                ));

                if ($saveToRegist) {
                    $send['state']  = true;
                    $send['data']   = array(
                        'message' => "success",
                        'lifetime'  => self::SMS_LIFETIME
                    );
                } else {
                    $send['error'] = 'Server error';
                }
                break;
            }
            $send['error'] = 'Verification code transmission failure';
        } while(0);

        $this->sendOutput($send);
    }

    /**
     * 用户注册
     */
    public function registAction ()
    {
        $send['state'] = false;
        $params = $this->filterParams($this->_params);
        $mobile = $params['mobile'];

        $model = new MobileModel();
        // 查看注册信息
        do {
            $registRow = $model->getRegistInfo($mobile);
            if (!$registRow) {
                $send['error'] = 'Invalid user account';
                break;
            }

            if ($registRow['code'] !== $params['code']) {
                $send['error'] = 'Verification code is not correct';
                break;
            }

            if (strtotime($registRow['regist_time']) < time()) {
                $send['error'] = "Verification code timeout";
                break;
            }

            $distruct = md5(uniqid() . mt_rand() . time());
            $salt = mb_substr($distruct, 0, 10);

            $nowTime = date('Y-m-d H:i:s');
            $loginIp = ip2long($params['ip']);
            $nickname = substr_replace($params['mobile'], '***', 3, 5);
            $data = array(
                'nickname'  => $nickname,
                'salt'      => $salt,
                'password'  => sha1($params['password'] . $salt . 'cmstop'),
                'mobile'    => $params['mobile'],
                'lasttime_login_time'   => $nowTime,
                'thistime_login_time'   => $nowTime,
                'lasttime_login_ip'     => $loginIp,
                'thistime_login_ip'     => $loginIp,
            );

            $memberID = $model->saveToMember($data);
            if (!$memberID) {
                $send['error'] = 'Failed to save user information';
                break;
            }

            $detailId = $model->saveToMemberDetail(array(
                'memberid'          => $memberID,
                'regist_siteid'     => $params['siteid'],
                'regist_ip'         => ip2long($params['ip']),
                'regist_time'       => $nowTime,
                'regist_device'     => $params['type']
            ));

            if (!$detailId) {
                $send['error'] = 'Failed to save user information';
            }

            if ($model->saveToMemberSite(array(
                'memberid'  => $memberID,
                'siteid'    => $params['siteid'],
                'access_time'   => $nowTime
            ))) {
                // delete member_regist
                $model->deleteRegist($mobile);

                // set login status
                $model->saveToLoginState(array(
                    'memberid'  => $memberID,
                    'siteid'    => $params['siteid'],
                    'device_id' => $params['device_id'],
                    'state'     => 1
                ));

                $send['state']  = true;
                $send['data']   = array(
                    'message'   => 'success',
                    'memberid'  => intval($memberID),
                    'nickname'  => $nickname,
                    'state'     => 1
                );
            } else {
                $send['error']  = 'Failed to regist user';
            }

        } while(0);
        $this->sendOutput($send);
    }

    /**
     * 重新发送验证码
     */
    public function resendsmsAction ()
    {
        $send['state'] = false;

        $params = $this->filterParams($this->_params);
        $mobile = $params['mobile'];

        do {
            $appkey = $this->_getAppkey();
            $model = new MobileModel();

            if (!$model->getResetpwInfo($mobile)) {
                $send['error'] = 'Invalid account';
                break;
            }

            $code = $this->_sendSms($appkey, $mobile);
            if ($code) {
                // change code
                if ($model->changeResetpwCode($mobile, $code)) {
                    $send['state'] = true;
                    $send['data'] = array(
                        'message'   => 'success',
                        'lifetime'  => self::SMS_LIFETIME,
                    );
                    break;
                }
            }

            $send['error'] = 'Failed to send message';
        }while(0);

        $this->sendOutput($send);
    }

    /**
     * 快速登录接口
     */
    public function quickloginAction ()
    {
        $send['state'] = false;
        $params = $this->filterParams($this->_params);

        do {

            // check this device is logined on the other app
            $model = new MobileModel();

            $loginInfo = $model->isLogined($params['device_id'], $params['siteid']);

            if (!$loginInfo) {
                $send['error'] = 'User is not logined';
                break;
            }

            $memberInfo = $model->getMemberInfo($loginInfo['memberid']);

            if (!$memberInfo) {
                $send['error'] = 'User data error';
                break;
            }

            if ($memberInfo['state'] != 1) {
                $send['error'] = 'User is limited to log in';
                break;
            }

            $thumb = $this->_thumbFormat($memberInfo['thumb']);

            $send['state'] = true;
            $send['data']  = array(
                'memberid'  => $memberInfo['id'],
                'nickname'  => $memberInfo['nickname'],
                'thumb'     => $thumb,
            );

        } while(0);
        $this->sendOutput($send);
    }

    /**
     * 云账号登录
     */
    public function cloudloginAction () {
        $send['state'] = false;
        $params = $this->filterParams($this->_params);

        do {
            // check this device is logined on the other app
            $model = new MobileModel();

            $memberInfo = $model->getMemberInfoByAccount($params['account_type'], $params['account']);
            if (!$memberInfo) {
                $send['error'] = 'User not exists';
                break;
            }

            if ($memberInfo['password'] != sha1($params['password'] . $memberInfo['salt'] . 'cmstop')) {
                $send['error'] = 'Wrong password';
                break;
            }

            if ($memberInfo['state'] != 1) {
                $send['error'] = 'User is limited to log in';
                break;
            }

            // last_login_ip/time
            $newData = array(
                'lasttime_login_time'   => $memberInfo['thistime_login_time'],
                'lasttime_login_ip'     => $memberInfo['thistime_login_ip'],
                'thistime_login_time'   => date('Y-m-d H:i:s'),
                'thistime_login_ip'     => ip2long($params['ip']),
            );
            $model->modifyMemberInfo($memberInfo['id'], $newData);

            // check member_site
            $model->saveToMemberSite(array(
                'siteid'    => $params['siteid'],
                'memberid'  => $memberInfo['id'],
            ));

            // check member_login_state
            $model->saveToLoginState(array(
                'memberid'  => $memberInfo['id'],
                'device_id' => $params['device_id'],
                'siteid'    => $params['siteid'],
                'state'     => 1,
                'login_time'=> date("Y-m-d H:i:s")
            ));

            $send['state'] = true;
            $send['data']  = array(
                'memberid'  => $memberInfo['id'],
                'nickname'  => $memberInfo['nickname'],
                'thumb'     => $this->_thumbFormat($memberInfo['thumb']),
            );

        } while(0);
        $this->sendOutput($send);
    }

    public function resetpwAction ()
    {
        $send['state']  = false;
        $sendMsg        = false;
        $params         = $this->filterParams($this->_params);

        do {

            // if the member exists
            $model = new MobileModel();
            if (!$model->getMemberInfoByAccount($params['account_type'], $params['account'])) {
                $send['error'] = 'User does not exist';
                break;
            }

            if ($params['account_type'] == 'email') {

                // 重新组合邮件内容
                $time = time();
                $code = md5(uniqid() . mt_rand() . $time);

                // 发送邮件
                $suffix = '?shenqing=' . $time . md5('meitiyun') . 'code=' . $code;
                $validateUrl = rtrim(APP_FRONTEND_URL, '/') . '/login/index/validpwemail' . $suffix;

                $mail = new Sendmail();
                $mailContent = $mail->userResetPw($params['account'], $validateUrl);
                $sendMsg = $mail->mail('CmsTop媒体云—用户密码找回', 'CmsTop媒体云', '找回密码', $mailContent, $params['account']);

                if (!$sendMsg) {
                    $send['error'] = 'Email sending failure';
                    break;
                }

            } else if ($params['account_type'] == 'mobile') {

                $appkey = $this->_getAppkey();
                $code = $this->_sendSms($appkey, $params['account']);

                if (!$code) {
                    $send['error'] = 'Verification code transmission failure';
                    break;
                }
            }

            // check if it exists in member_resetpw
            if (!$model->checkResetPw($params, $code)) {
                // get site domain, or it can't get back
                $url = $model->getSiteRoute($params['siteid']);
                $model->saveToMemberResetpw($params, $code, $url);
            }

            $send['state'] = true;
            $send['data'] =  array(
                'message'   => 'success',
                'type'      => $params['account_type'],
            );

            if ($params['account_type'] == 'mobile') {
                $send['data']['lifetime'] = self::SMS_LIFETIME;
            }
        } while (0);
        $this->sendOutput($send);
    }

    /**
     * 验证验证码
     */
    public function validsmsAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {

            $model = new MobileModel();

            $resetInfo = $model->getResetpwInfo($params['mobile']);
            if (!$resetInfo) {
                $send['error'] = 'Invalid access';
                break;
            }

            if ($resetInfo['code'] != $params['code']) {
                $send['error'] = 'Verification code error';
                break;
            }

            if ((strtotime($resetInfo['apply_time']) + self::SMS_LIFETIME) < time()) {
                $send['error'] = 'Verification code timeout';
                break;
            }

            $send['state'] = true;
            $send['data']  = array(
                'message'   => 'success',
            );

        } while (0);
        $this->sendOutput($send);
    }

    /**
     * set new password
     */
    public function newpwAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);


        $model = new MobileModel();

        $distruct = md5(uniqid() . mt_rand() . time());
        $salt = mb_substr($distruct, 0, 10);

        $password = sha1($params['password'] . $salt . 'cmstop');
        if ($model->newPassword($params['mobile'], $password, $salt)) {
            $send['state'] = true;
            $send['data']  = array(
                'message'   => 'success'
            );
        } else {
            $send['error'] = 'Failed reset password';
        }

        $this->sendOutput($send);
    }

    /**
     * 第三方登录
     */
    public function socialAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {
            $model = new MobileModel();

            // check bind
            if (!$memberid = $model->checkBind($params)) {
                // no bind
                $send['state'] = true;
                $send['data']['message'] = 'pendding';
                $send['data']['bind']   = false;
                $send['data']['info']   = new stdClass();
                break;
            } else {
                // get user info by memberid
                $getmemberInfo = $model->getMemberInfoById ($memberid, $params);
                if (empty($getmemberInfo)) {
                    $send['error'] = 'Data error';
                } else {
                    if (!$getmemberInfo['state']) {
                        $send['error'] = $getmemberInfo['error'];
                    } else {
                        $send['state'] = true;
                        $send['data']['message']  = "success";
                        $send['data']['bind']     = true;
                        $send['data']['info']     = $getmemberInfo['data'];

                        if (!empty($send['data']['info']['thumb'])) {
                            $send['data']['info']['thumb'] = $this->_thumbFormat($send['data']['info']['thumb']);
                        }
                    }
                }
            }

        } while(0);

        $this->sendOutput($send);
    }

    /**
     * 绑定并登录
     */
    public function bindloginAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {
            $model = new MobileModel();

            // check openid is appered
            $openidInfo = $model->checkOpenid($params['platform'], $params['openid']);
            if (!$openidInfo) {
                $send['error'] = 'No such openid in appered';
                break;
            }

            if (!empty($openidInfo['memberid'])) {
                $send['error'] = 'The openid has been bound';
                break;
            }

            // check member exists
            $getMember = $model->getMemberInfoByAccount($params['account_type'], $params['account']);
            if (!$getMember) {
                $send['error'] = 'Account error';
                break;
            }

            if ($getMember['password'] != sha1($params['password'] . $getMember['salt'] . 'cmstop')) {
                $send['error'] = 'Password error';
                break;
            }

            if ($getMember['state'] != 1) {
                $send['error'] = 'Users are limited to log in';
                break;
            }

            // bind
            if (!$model->bindSocial($getMember['id'], $params)) {
                $send['error'] = 'Bind failed';
                break;
            }

            // $saveData = $model->bindSocial($openidInfo, $params);
            $send['state'] = true;
            $send['data']['message'] = 'success';
            $send['data']['info']  = array(
                'message'  => 'success',
                'memberid' => (int) $getMember['id'],
                'nickname' => $getMember['nickname'],
                'thumb'    => $this->_thumbFormat($getMember['thumb']),
                'lasttime_login_time' => $getMember['lasttime_login_time'],
                'lasttime_login_ip'   => long2ip($getMember['lasttime_login_ip']),
            );

        } while(0);
        $this->sendOutput($send);
    }

    /**
     * 绑定并注册
     */
    public function bindregistAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {
            $model = new MobileModel();

            // check code
            $regist = $model->getRegistInfo($params['mobile']);
            if (!$regist) {
                $send['error'] = 'Data error';
                break;
            }

            if ((strtotime($regist['regist_time']) + self::SMS_LIFETIME) < time()) {
                $send['error'] = 'Verification code timeout';
                break;
            }

            if ($regist['code'] != $params['code']) {
                $send['error'] = 'Verification code does not match';
                break;
            }

            // check member exists
            if ($model->checkUserRegisted($params['mobile'])) {
                $send['error'] = 'User has been registered';
                break;
            }

            // check openid is appered
            $openidInfo = $model->checkOpenid($params['platform'], $params['openid']);
            if (!$openidInfo) {
                $send['error'] = 'No such openid in appered';
                break;
            }

            if (!empty($openidInfo['memberid'])) {
                $send['error'] = 'The openid has been bound';
                break;
            }

            $saveData = $model->bindregistSocial($openidInfo, $params);
            $send['state'] = true;
            $send['data']['message'] = 'success';
            $send['data']['info']  = array(
                'message'  => 'success',
                'memberid' => (int) $saveData['id'],
                'nickname' => $saveData['nickname'],
                'thumb'    => $this->_thumbFormat($saveData['thumb']),
                'lasttime_login_time' => $saveData['lasttime_login_time'],
                'lasttime_login_ip'   => long2ip($saveData['lasttime_login_ip']),
            );

        } while(0);
        $this->sendOutput($send);
    }

    /**
     * 快速绑定
     */
    public function bindquickAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {
            $model = new MobileModel();

            $openidInfo = $model->checkOpenid($params['platform'], $params['openid']);
            if (!$openidInfo) {
                $send['error'] = 'Invalid openid';
                break;
            }

            if (!empty($openidInfo['memberid'])) {
                $send['error'] = 'Account has been bound';
                break;
            }

            $memberInfo = $model->getMemberInfoById($params['memberid'], $params);
            if (!$memberInfo['state']) {
                $send['error'] = $memberInfo['error'];
            } else {

                if ($model->bindMember($params['memberid'], $params['openid'])) {
                    $send['state'] = true;
                    $send['data']['message'] = 'success';
                    $send['data']['info']  = $memberInfo['data'];
                } else {
                    $send['error'] = 'Bind failed';
                }
            }
        }while(0);
        $this->sendOutput($send);
    }

    /**
     * 获取用户数据
     */
    public function getmemberAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        $model = new MobileModel();
        $m = $model->getDetail($params['memberid']);
        if (!$m) {
            $send['error'] = 'User information query failed';
        } else {

            $m['thumb'] = $this->_thumbFormat($m['thumb']);

            $send['state'] = true;
            $send['data']['message'] = 'success';
            $send['data']['info']    = $m;
        }
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

    /**
     * 保存文章收藏
     */
    public function collectionAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        $memberid   = $params['memberid'];
        $siteid     = $params['siteid'];
        $contentid  = $params['contentid'];

        $model = new MobileModel();
        $c = $model->saveToCollection(compact('memberid', 'siteid', 'contentid'));
        if (isset($c['error'])) {
            $send['error'] = $c['error'];
        } else {
            if ($c) {
                $send['state'] = true;
                $send['data']['message'] = 'success';
            } else {
                $send['error'] = 'Failure of the article';
            }
        }

        $this->sendOutput($send);
    }

    /**
     * 绑定邮箱或手机啊
     */
    public function bindaccountAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);
        $type = $params['account_type'];

        do {

            $model = new MobileModel();
            // check account
            $checkRes = $model->checkAccount ($params);
            if ($checkRes) {
                $send['error'] = 'Account already exists';
                break;
            }

            if ($type == 'email') {
                // send mail
                $time = time();
                $code = md5(uniqid() . mt_rand() . $time);

                // 发送邮件
                $suffix = '?shenqing=' . $time . md5('meitiyun') . 'code=' . $code;
                $suf = rtrim(APP_FRONTEND_URL, '/') . '/login/index/validemail' . $suffix;

                $mail = new Sendmail();
                $mailContent = $mail->userBindEmail($params['account'], $suf);
                $sendMsg = $mail->mail('CmsTop媒体云—用户邮箱绑定', 'CmsTop媒体云', '绑定邮箱', $mailContent, $params['account']);

                if (!$sendMsg) {
                    // 邮件发送失败
                    $send['error'] = 'Verify email sending failed';
                    break;
                }
            } else {
                $appkey = $this->_getAppkey();
                $code = $this->_sendSms($appkey, $params['account']);
                if (!$code) {
                    // failed
                    $send['error'] = 'Verification code transmission failure';
                    break;
                }
            }

            // code send success
            if ($model->bindEmailOrMobile($params, $code)) {
                $send['state'] = true;
                $send['data'] = array(
                    'message'   => 'success',
                    'type'      => $type,
                    'lifetime'  => $type == 'email' ? 3*24*60*60 : self::SMS_LIFETIME
                );
            } else {
                $send['error'] = 'Data error';
            }
        } while(0);
        $this->sendOutput($send);
    }

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
     * 绑定手机号
     */
    public function bindmobileAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);
        do {

            // check time
            $model = new MobileModel();
            $b = $model->getBindAccount($params['memberid']);

            if (!$b) {
                $send['error'] = 'Data error';
                break;
            }

            if ($b['account'] != $params['account']) {
                $send['error'] = 'Account does not match';
                break;
            }

            if ($b['code'] != $params['code']) {
                $send['error'] = 'Verificatioin does not match';
                break;
            }

            if ((strtotime($b['apply_time']) + self::SMS_LIFETIME) < time()) {
                $send['error'] = 'Verification code timeout';
                break;
            }

            // modify member
            if ($model->modifyMemberInfo($params['memberid'], array('mobile' => $params['account']))) {
                $send['state'] = true;
                $send['data']  = array(
                    'message'   => 'success'
                );
            } else {
                $send['error'] = 'Modify failed';
            }

            // delete apply
            $model->deleteBindAccount($params['memberid']);


        } while (0);
        $this->sendOutput($send);
    }

    /**
     * 修改 nickname gender birthday
     */
    public function editbasicAction ()
    {
        $send['state']  = false;
        $genderArr = array('男', '女', '未知');
        $params         = $this->filterParams($this->_params);
        do {

            $model = new MobileModel();
            $m = $model->getMemberInfo($params['memberid']);
            if (!$m) {
                $send['error'] = 'No such user exists';
                break;
            }

            switch ($params['key']) {
                case "nickname" :
                    $data  = array(
                        'nickname'  => $params['value']
                    );
                    break;
                case "gender":
                    if (!in_array($params['value'], $genderArr)) {
                        $send['error'] = 'Illegal value';
                        break 2;
                    }
                    $data['gender'] = $params['value'];
                    break;
                case "birthday":
                    $birth = strtotime($params['value']);
                    $data = array(
                        'year'  => date('Y', $birth),
                        'month' => date('m', $birth),
                        'day'   => date('d', $birth)
                    );
                    break;
            }

            // save
            if ($model->saveMemberBasic($params['memberid'], $params['key'] , $data)) {
                $send['state']  = true;
                $send['data']   = array(
                    'message'   => 'success'
                );
            } else {
                $send['error'] = 'Change failed';
            }

        } while (0);
        $this->sendOutput($send);
    }

    /**
     * 编辑密码
     */
    public function editpwAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {

            $model = new MobileModel();
            $m = $model->getMemberInfo($params['memberid']);

            if (!$m) {
                $send['error'] = 'Data error';
                break;
            }

            if ($m['password'] != sha1($params['orgpass'] . $m['salt'] . 'cmstop')) {
                $send['error'] = 'The original password is incorrect';
                break;
            }

            $distruct   = md5(uniqid() . mt_rand() . time());
            $salt       = mb_substr($distruct, 0, 10);

            $password   = sha1($params['newpass'] . $salt . 'cmstop');

            if ($model->modifyPassword($params['memberid'], compact('password', 'salt'))) {
                $send['state'] = true;
                $send['data']  = array(
                    'message'   => 'success'
                );
            } else {
                $send['error'] = 'Change failed';
            }
        } while (0);
        $this->sendOutput($send);
    }

    /**
     * 修改用户头像
     */
    public function editthumbAction ()
    {
        $send['state']  = false;
        $params         = $this->filterParams($this->_params);

        do {

            $model = new MobileModel();
            $m = $model->getMemberInfo($params['memberid']);
            if (!$m) {
                $send['error'] = 'Data error';
                break;
            }

            if ($model->updateThumb($params)) {
                $send['state'] = true;
                $send['data']  = array(
                    'message'   => 'success',
                    'thumb'     => $this->_thumbFormat($params['thumb'])
                );
            } else {
                $send['error'] = 'User picture save failed';
            }

        } while (0);
        $this->sendOutput($send);
    }

    /**
     * 发送验证码
     * @param $appkey
     * @param $mobile
     * @return bool|int
     */
    public function _sendSms($appkey, $mobile) {

        $code = mt_rand(100000, 999999);

        $res = $this->_sendMessage($appkey, array(
            'mobile'    => $mobile,
            'message'   => '亲爱的用户，您的短信验证码为：'.$code.'【CmsTop媒体云】'
        ));

        $smsResult = json_decode($res, true);
        if ((isset($smsResult['error']) && $smsResult['error'] == 0)
            && (isset($smsResult['msg']) && $smsResult['msg'] == 'ok')) {
            return $code;
        }

        return false;
    }

    /**
     * sms interface
     * @param $appkey
     * @param $params
     * @return mixed
     */
    public function _sendMessage ($appkey, $params)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::SMS_INTERFACE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPAUTH , CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD  , 'api:key-' . $appkey);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        $res = curl_exec( $ch );
        curl_close( $ch );

        return $res;
    }

    /**
     * formate thumbnail
     */
    private function _thumbFormat ($thumb)
    {
        return (!empty($thumb) && !preg_match('/^(http|https)/', $thumb)) ? rtrim(RESOURCE_URL, '/') . $thumb : $thumb;
    }

    /**
     * 获取手机appkey
     * @return mixed
     */
    protected function _getAppkey ()
    {
        // get luosimao app config
        $luosimao = Application::app()->getConfig()->application->luosimao;
        $configArr = $luosimao->toArray();
        return $configArr['appkey'];
    }

}

