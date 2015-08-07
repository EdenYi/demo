<?php
/**
 * Description mobile interface model
 *
 * @author     YiDe
 * @datetime   2015/6/29 0029 下午 2:50
 * @copyright  Beijing CmsTop Technology Co.,Ltd.
 */

namespace Model\Member;

use Core\Factory;
use Core\Loader;
use General\Db\Sql\Select;
use Yaf\Exception;

class MobileModel
{
    /**
     * 保存用户注册信息到 member_regist
     * @param $params
     */
    public function saveToRegist ($params)
    {
        $mobile = $params['mobile'];
        if (empty($mobile)) {
            return false;
        }

        $data = array(
            'registid'      => $mobile,
            'code'          => $params['code'],
            'type'          => 'mobile',
            'device'        => 'mobile',
            'regist_time'   => date('Y-m-d H:i:s', time() + $params['lifetime']),
            'regist_ip'     => ip2long($params['ip']),
            'siteid'        => $params['siteid'],
            'remark'        => '移动端注册'
        );

        $registRow = Factory::table('member_regist')->get(array('registid' => $mobile));
        if ($registRow) {
            return $registRow->mergeData($data)->save();
        }

        return Factory::table('member_regist')->create($data)->save();
    }

    /**
     * 根据用户账号查找用户注册信息
     * @param $mobile
     * @return array|\General\Db\Row\AbstractRow|null
     */
    public function getRegistInfo($mobile)
    {
        return Factory::table('member_regist')->get(array('registid' => $mobile), true);
    }

    /**
     * 保存到member表
     * @return int id
     */
    public function saveToMember ($data)
    {
        // save to member
        $memberRow = Factory::table('member')->create($data);
        $memberRow->save();
        return $memberRow->get('id');
    }

    /**
     * 保存到member_detail表，返回id
     */
    public function saveToMemberDetail ($data)
    {
        $detailRow = Factory::table('member_detail')->create($data);
        $detailRow->save();
        return $detailRow->get('id');
    }

    /**
     * 保存到member_site表，返回id
     * @param $data
     * @return bool|mixed
     */
    public function saveToMemberSite ($data)
    {
        $row = Factory::table('member_site')->get(array('siteid' => $data['siteid'], 'memberid' => $data['memberid']));
        if ($row) {
            return TRUE;
        }
        $siteRow = Factory::table('member_site')->create($data);
        $siteRow->save();
        return $siteRow->get('id');
    }

    /**
     * 删除注册信息
     */
    public function deleteRegist ($mobile)
    {
        $registRow = Factory::table('member_regist')->get(array('registid' => $mobile));
        if ($registRow) {
            return $registRow->delete();
        }
        return false;
    }

    /**
     * 修改验证码
     */
    public function changeRegistCode ($mobile, $code, $lifetime)
    {
        $registRow = Factory::table('member_regist')->get(array('registid' => $mobile));
        return $registRow->mergeData(array(
            'code'          => $code,
            'regist_time'   => date('Y-m-d H:i:s', time() + $lifetime),
        ))->save();
    }

    /**
     * 保存用户登录状态
     */
    public function saveToLoginState ($data)
    {
        $loginRow = Factory::table('member_login_state')->get(array(
            'siteid'    => $data['siteid'],
            'memberid'  => $data['memberid'],
            'device_id' => $data['device_id'],
        ));

        if ($loginRow) {
            return true;
        }

        $create = Factory::table('member_login_state')->create($data);
        $create->save();
        return $create->get('id');
    }

    /**
     * 用户是否被注册
     */
    public function checkUserRegisted ($mobile)
    {
        // is in member
        if (Factory::table('member')->get(array('mobile' => $mobile))) {
            return true;
        }
        return false;
    }

    /**
     * 检查终端上有没有登录用户
     * @param $device_id
     */
    public function isLogined ($device_id, $siteid)
    {
        $row = Factory::table('member_login_state')->select(function (Select $select) use($device_id, $siteid) {
            $select->where(array(
                'device_id' => $device_id,
                'siteid'    => $siteid,
            ));
            $select->order('login_time DESC');
        });

        if (!$row) {
            return false;
        }

        return $row[0];
    }

    /**
     * 获取用户登录信息
     */
    public function getMemberInfo ($memberid)
    {
        return Factory::table('member')->get(array('id' => $memberid), true);
    }

    /**
     * 通过账号获取用户信息
     * @param $type
     * @param $account
     */
    public function getMemberInfoByAccount ($type, $account)
    {
        if ($type == 'email') {
            return Factory::table('member')->get(array('email' => $account), true);
        } else {
            return Factory::table('member')->get(array('mobile' => $account), true);
        }
    }

    /**
     * 用户登录功能修改登录信息
     * @param $memberid
     * @param $data
     */
    public function modifyMemberInfo ($memberid, $data)
    {
        $memberRow = Factory::table('member')->get(array('id' => $memberid));
        if ($memberRow) {
            return $memberRow->mergeData($data)->save();
        }
        return false;
    }

    /**
     * check is exists in resetpw table
     * @param $params
     * @param $code
     * @return bool
     */
    public function checkResetPw ($params, $code)
    {
        $resetRow = Factory::table('member_resetpw')->get(array('registid' => $params['account'], 'type' => $params['account_type']));
        if (!$resetRow) {
            return false;
        }

        $resetRow->mergeData(array(
            'apply_time'    => date('Y-m-d H:i:s'),
            'code'          => $code
        ));

        return $resetRow->save();
    }

    /**
     * 获取用户url
     * @param $siteid
     * @return bool|void
     */
    public function getSiteRoute ($siteid)
    {
        $siteRows = Factory::table('site_route')->select(array('siteid' => $siteid));
        if (!$siteRows) {
            return;
        }

        foreach ($siteRows as $row) {
            if ($row['type'] == 'define' && $row['usage'] == 'pc') {
                return $row['domain'];
            }
            if ($row['type'] == 'default' && $row['usage'] == 'pc') {
                return $row['domain'];
            }
        }

        return false;
    }

    /**
     * user find password
     * @param $params
     * @param $code
     */
    public function saveToMemberResetpw ($params, $code, $url)
    {
        $getSiteName = Factory::table('site')->get(array('id' => $params['siteid']), true);
        return Factory::table('member_resetpw')->create(array(
            'registid'  => $params['account'],
            'type'      => $params['account_type'],
            'code'      => $code,
            'siteid'    => $params['siteid'],
            'redirect_uri'  => 'http://' . rtrim($url, '/') . '/index/login/callback',
            'sitename'  => $getSiteName['name']
        ))->save();
    }

    /**
     * 检查验证码
     * @param $mobile
     * @return array|\General\Db\Row\AbstractRow|null
     */
    public function getResetpwInfo ($mobile)
    {
        return Factory::table('member_resetpw')->get(array('registid' => $mobile, 'type'=>'mobile'), true);
    }

    /**
     * 重新修改密码 code
     * @param $mobile
     * @param $code
     * @return mixed
     */
    public function changeResetpwCode ($mobile, $code)
    {
        return Factory::table('member_resetpw')->get(array('registid' => $mobile))->mergeData(array(
            'code'  => $code
        ))->save();
    }

    /**
     * set new password
     * @param $mobile
     * @param $password
     */
    public function newPassword ($mobile, $password, $salt)
    {
        $memberRow = Factory::table('member')->get(array('mobile' => $mobile));
        if (!$memberRow) {
            return false;
        }
        $memberRow->mergeData(array('password' => $password, 'salt' => $salt));
        if ($memberRow->save()) {
            return Factory::table('member_resetpw')->get(array('registid' => $mobile))->delete();
        }
        return false;
    }

    /**
     * check bind
     * @param $params
     */
    public function checkBind ($params)
    {
        $openid = $params['openid'];
        $platform = $params['platform'];

        $social = Factory::table('member_social')->get(array('platform' => $platform, 'openid' => $openid));
        if ($social) {
            $socialData = $social->toArray();
            if ($params['access_token'] != $socialData['access_token']) {
                $social->mergeData($params)->save();
            }

            if (!empty($socialData['memberid'])) {
                return $socialData['memberid'];
            }
            return false;
        }

        Factory::table('member_social')->create(array_merge($params, array('device' => 'mobile')))->save();
        return false;
    }

    /**
     * get member info
     * @param $id
     */
    public function getMemberInfoById ($id, $params)
    {
        $row = Factory::table('member')->get(array('id' => $id), true);

        if (!$row) {
            return array();
        } else {

            if ($row['state'] != 1) {
                return array(
                    'state' => false,
                    'error' => 'Users are limited to log in'
                );
            }

            // modify this login info
            $up = array(
                'lasttime_login_time' => $row['thistime_login_time'],
                'lasttime_login_ip'   => $row['thistime_login_ip'],
                'thistime_login_time'   => date('Y-m-d H:i:s'),
                'thistime_login_ip'   => ip2long($params['ip']),
            );
            Factory::table('member')->get(array('id' => $id))->mergeData($up)->save();

            // modify member_login_state
            $this->saveToLoginState(array(
                'memberid'      => $id,
                'siteid'        => $params['siteid'],
                'device_id'     => $params['device_id'],
                'login_time'    => date('Y-m-d H:i:s')
            ));

            return array(
                'state' => true,
                'data'  => array(
                    'memberid'    => (int) $id,
                    'nickname'    => $row['nickname'],
                    'thumb'       => $row['thumb'],
                    'lasttime_login_time' => $row['lasttime_login_time'],
                    'lasttime_login_ip'   => long2ip($row['lasttime_login_ip'])
                )
            );
        }
    }

    /**
     * check openid is appered
     * @param $platform
     * @param $openid
     */
    public function checkOpenid ($platform, $openid)
    {
        return Factory::table('member_social')->get(array('openid' => $openid, 'platform' => $platform), true);
    }

    /**
     * 绑定
     * @param $memberid
     * @return mixed
     */
    public function bindSocial ($memberid, $params)
    {
        $table = Factory::table('member_social');
        $connection = $table->getAdapter()->getDriver()->getConnection();
        $connection->beginTransaction();

        try {

            $up = Factory::table('member_social')->get(array(
                'openid' => $params['openid'],
                'platform' => $params['platform'],
            ))->mergeData(array(
                'memberid' => $memberid
            ))->save();

            if (!$up) {
                return false;
            }

            // save to member_login_state
            $this->saveToLoginState(array(
                'memberid'      => $memberid,
                'siteid'        => $params['siteid'],
                'device_id'     => $params['device_id'],
                'login_time'    => date('Y-m-d H:i:s'),
            ));

            // save to member_site
            $this->saveToMemberSite(array('memberid' => $memberid, 'siteid' => $params['siteid']));

            $connection->commit();
            return true;

        } catch (Exception $e) {
            $connection->rollback();
        }
    }

    public function bindregistSocial ($socialInfo, $params)
    {

        $distruct = md5(uniqid() . mt_rand() . time());
        $salt = mb_substr($distruct, 0, 10);
        $password = sha1($params['password'] . $salt . 'cmstop');

        $nowtime = date('Y-m-d H:i:s');
        $ip      = ip2long($params['ip']);

        $table = Factory::table('member');
        $connection = $table->getAdapter()->getDriver()->getConnection();
        $connection->beginTransaction();

        try {

            // save to member
            $memberData = array(
                'email'     => '',
                'mobile'    => $params['mobile'],
                'nickname'  => $socialInfo['nickname'],
                'password'  => $password,
                'salt'      => $salt,
                'thumb'     => $socialInfo['figureurl'],
                'thistime_login_time' => $nowtime,
                'lasttime_login_time' => $nowtime,
                'thistime_login_ip'   => $ip,
                'lasttime_login_ip'   => $ip,
                'state'     => 1
            );
            $row = $table->create($memberData);
            $row->save();
            $memberid = $row->get('id');

            // save to member_detail
            Factory::table('member_detail')->create(array(
                'memberid'  => $memberid,
                'gender'    => $socialInfo['gender'],
                'address'   => $socialInfo['address'],
                'regist_siteid' => $params['siteid'],
                'regist_ip'     => ip2long($params['ip']),
                'regist_time'   => $nowtime,
                'regist_device' => $params['type']
            ))->save();

            // save to member_login_state
            $this->saveToLoginState(array(
                'memberid'      => $memberid,
                'siteid'        => $params['siteid'],
                'device_id'     => $params['device_id'],
                'login_time'    => $nowtime,
            ));

            // save to member_site
            $this->saveToMemberSite(array('memberid' => $memberid, 'siteid' => $params['siteid']));

            //  update member_social
            $up = Factory::table('member_social')->get(array(
                    'openid' => $params['openid'],
                    'platform' => $params['platform']
                ))->mergeData(array('memberid' => $memberid))->save();

            if ($up) {
                $connection->commit();
                return array_merge($memberData, array('id' => $memberid));
            }

        } catch (Exception $e) {
            $connection->rollback();
        }
        return null;
    }

    /**
     * check is bind
     * @param $openid
     */
    public function bindMember ($memberid, $openid)
    {
        $mem = Factory::table('member_social')->get(array('openid' => $openid));
        if (!$mem) {
            return false;
        }
        return $mem->mergeData(array('memberid' => $memberid))->save();
    }

    /**
     * 获取用户数据
     */
    public function getDetail ($memberid)
    {
        $m = Factory::table('member')->get(array('id' => $memberid), true);
        if (!$m) {
            return false;
        }
        $md = Factory::table('member_detail')->get(array('memberid' => $memberid), true);
        return array(
            "memberid"  => (int) $memberid,
            "nickname"  => $m['nickname'],
            "truename"  => $md['truename'],
            "email"     => $m['email'],
            "mobile"    => $m['mobile'],
            "thumb"     => $m['thumb'],
            "gender"    => $md['gender'],
            "address"   => $md['address'],
            "year"      => $md['year'],
            "month"     => $md['month'],
            "day"       => $md['day'],
        );
    }

    /**
     * 退出公有云
     */
    public function loginOutPublicCloud ($params)
    {
        $row = Factory::table('member_login_state')->get(array(
            'device_id'     => $params['device_id'],
            'siteid'        => $params['siteid'],
            'memberid'      => $params['memberid'],
        ));
        if (!$row) {
            return true;
        }
        return $row->delete();
    }

    /**
     * 退出私有云
     * todo :: 移动端已修改退出方案
     */
    /*
    public function loginOutPrivateCloud ($params)
    {
        return Factory::sql()->update('member_login_state')->set(array(
            'state' => 0
        ))->where(array(
            'device_id' => $params['device_id'],
            'memberid'  => $params['memberid']
        ))->execute()->getAffectedRows();
    }
    */

    /**
     * 保存文章收藏
     */
    public function saveToCollection($data)
    {
        //  check repeat
        $cRow = Factory::table('member_collection')->get($data);
        if ($cRow) {
            return array(
                'state' => false,
                'error' => 'This article has been collected'
            );
        } else {
            return Factory::table('member_collection')->create(array_merge($data, array(
                'device'    => 'mobile'
            )))->save();
        }
    }

    /**
     * check account
     */
    public function checkAccount ($params)
    {
        if ($params['account_type'] == 'email') {
            return  Factory::table('member')->get(array('email' => $params['account']));
        } else {
            return Factory::table('member')->get(array('mobile' => $params['account']));
        }
    }

    /**
     * 绑定邮箱或手机号
     */
    public function bindEmailOrMobile ($params, $code)
    {
        // 有则修改，无则增加
        $res = Factory::table('member_bind_account')->get(array(
            'memberid'  => $params['memberid']
        ));

        $data = array(
            'memberid'  => $params['memberid'],
            'siteid'    => $params['siteid'],
            'account'   => $params['account'],
            'code'      => $code,
            'device'    => 'mobile',
            'apply_time' => date('Y-m-d H:i:s')
        );

        if ($res) {
            unset($data['memberid']);
            return $res->mergeData($data)->save();
        }

        return Factory::table('member_bind_account')->create($data)->save();
    }

    /**
     * 查询绑定号码记录
     * @param $mid
     * @return array|\General\Db\Row\AbstractRow|null
     */
    public function getBindAccount ($mid)
    {
        return Factory::table('member_bind_account')->get(array('memberid' => $mid), true);
    }

    /**
     * 删除绑定申请
     */
    public function deleteBindAccount ($mid)
    {
        return Factory::table('member_bind_account')->get(array('memberid' => $mid))->delete();
    }

    /**
     * 保存用户基本信息
     */
    public function saveMemberBasic ($memberid, $key, $data)
    {
        switch ($key) {
            case "nickname":
                return Factory::table('member')->get(array('id' => $memberid))->mergeData($data)->save();
                break;
            case "gender":
            case "birthday":
                return Factory::table('member_detail')->get(array('memberid' => $memberid))
                                ->mergeData($data)
                                ->save();
                break;
        }
    }

    /**
     * 修改密码
     */
    public function modifyPassword ($mid, $data)
    {
        return Factory::table('member')->get(array('id' => $mid))->mergeData($data)->save();
    }

    /**
     * 用户头像
     */
    public function updateThumb ($params)
    {
        return Factory::table('member')->get(array('id' => $params['memberid']))->mergeData(array(
            'thumb' => $params['thumb']
        ))->save();
    }

}

