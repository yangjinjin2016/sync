<?php

declare(strict_types=1);

namespace Framework\Syncsso;

class Sync
{
    protected $access_token = '';
    public function __construct()
    {
        self::refreshToken();

    }
    //curl 获取数据
    public function curl_https($url, $data, $method, $header)
    {
        $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;

        $ch = curl_init();
        $headers = ['Accept-Charset: utf-8'];
        if ($ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $header));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible;MSIE 5.01;Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        if (strtoupper($method) == "POST") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }


    public function refreshToken(){
        $data =[
            'grant_type' => 'client_credentials',
            'client_id' => config('sso.oauth_client_id'),
            'client_secret' => config('sso.oauth_client_secret'),
            'scope' => config('sso.sync_scope')
        ];
        $result = $this->curl_https(config('sso.server_sso_url').config('sso.token_url'), $data, 'post',
            ['Content-Type' => 'application/x-www-form-urlencoded']);
        if($result){
            $response = json_decode($result, true);
            $this->access_token = $response['access_token'];
        }
    }

    /*
     *  'user_info_url' => 'getuserinfo', // 通过access_token 获取登录用户信息
        'depart_url' => 'GetDepartmentListByCode', // 通过organizedcode 获取部门
        'organize_info_url' => 'GetOrganizeInfo', // 通过组织id获取组织信息
        'user_url' => 'GetUserListByDepart', // 通过部门code获取用户列表
        'userall_url' => 'getAllUserList', // 通过组织code获取用户列表
        'user_info' => 'GetUserById',
         'org_list'=>'GetMyOrgList',//获取账号所在机构（+部门）以及所有的子机构
        'org'=>'GetOrgList'
        'class_url'=>'GetClassListByCode'
        'grade_url'=>'GetGradeListByOrgId'
     * */
    public function syncOrg($org)
    {
        //todo  传参的机构或者获取所有的机构
        //todo  获取机构的基本信息  机构的人员 学生  班级 年级 部门
        //todo  获取accesstoken
        $orgData =[];

        if ($org) {
            //todo 获取机构详情
            $result = $this->curl_https(config('sso.server_sso_url').config('sso.organize_info_url').'?organizeId='.$org, '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
            $organizeInfo = json_decode($result, true);
            $orgData[] = [
                'organizeid' => $organizeInfo['Data']['SingleData']['OrganizeId'],
                'parentid' => $organizeInfo['Data']['SingleData']['ParentId'],
                'organizename' => $organizeInfo['Data']['SingleData']['SchoolName'],
                'shortname' => $organizeInfo['Data']['SingleData']['Description'],
                'organizecode' => $organizeInfo['Data']['SingleData']['OrganizeCode'],
                'AreaSchoolId' => $organizeInfo['Data']['SingleData']['AreaSchoolId'],
                'CategoryId' => $organizeInfo['Data']['SingleData']['CategoryId'],
            ];
        } else {
            $result = $this->curl_https(config('sso.server_sso_url').config('sso.org'), '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
            //todo 所有的机构
            $result = json_decode($result,true);
            if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
                if ($result['Data'] && isset($result['Data']['ListData'])) {
                    $orgList = $result['Data']['ListData'];
                    foreach ($orgList as $item) {
                        //记录所有的机构
                        $orgData[] = [
                            'organizeid' => $item['OrganizeId'],
                            'parentid' => $item['ParentId'] ?? 0,
                            'organizename' => $item['SchoolName'] ?? '',
                            'shortname' => $item['ShortName'] ?? '',
                            'organizecode' => $item['OrganizeCode']??0,
                            'AreaSchoolId' => $item['AreaSchoolId']??0,
                            'CategoryId'=>$item['CategoryId']??0
                        ];
                    }


                }
            }
        }
        return $orgData;
    }

    /**
     * 用户
     * @param $org
     * @return array
     * @author: Yjj
     * @Date: user
     */
    public function syncUser($org)
    {
        $result = $this->curl_https(config('sso.server_sso_url').config('sso.userall_url').'?orgCode='.$org.'&userType=teacher|staff|org','',
            'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $user = [];
        $result = json_decode($result,true);
        if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
            if ($result['Data'] && isset($result['Data']['ListData'])) {
                $listData = $result['Data']['ListData'];
                foreach ($listData as $item) {
                    $user[] = [
                        'user_type' => $item['F_UserType'],
                        'user_name' => $item['F_RealName'],
                        'login_name' => $item['F_Account'] ?? $item['F_RealName'],
                        'nickname' => $item['F_RealName'] ?? $item['F_Account'],
                        'mobile' => $item['F_MobilePhone'] ?? '',
                        'photo' => $item['F_HeadIcon'] ?? '',
                        'organizeid' => $item['F_OrganizeId'],
                        'sex' => $item['F_Gender'] == true ? 1 : ($item['F_Gender'] == false ? 2 : 3),
                        'uuid' => $item['F_Uid'],
                        'idcard' => $item['F_IdCard'] ?: '',
                        'source' => 'sso'
                    ];
                }


            }
        }

        return $user;

    }

    /**
     * 部门
     * @param $org
     * @return array
     * @author: Yjj
     * @Date: user
     */
    public function syncDepart($org)
    {
        $result = $this->curl_https(config('sso.server_sso_url').config('sso.depart_url').'?OrganizeCode='.$org, '', 'get',
            ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);

        $depart = [];
        $result = json_decode($result,true);

        if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
            if ($result['Data'] ) {
                $listData = $result['Data'];
                foreach ($listData as $item) {
                    $parentId = $item['ParentId'];
                    if ($item['DepartmentFullPath']) {
                        $full = explode('/', $item['DepartmentFullPath']);
                        if (!isset($full[2])) {
                            $parentId = 0;
                        }
                    }
                    $depart[] = [
                        'organizeid' => $item['OrganizeId'],
                        'department_name' => $item['DepartmentName'],//部门名称
                        'department_id' => $item['DepartmentId'],//部门ID
                        'parent_id' => $parentId,// 父部门Id
                        'path' => $item['DepartmentFullPath'],
                        'sort' => isset($item['SortCode']) ? $item['SortCode'] : 0,
                    ];

                }


            }
        }

        return $depart;
    }

    /**
     * 班级
     * @param $org
     * @return array
     * @author: Yjj
     * @Date: user
     */

    public function syncClass($org)
    {
        $result = $this->curl_https(config('sso.server_sso_url').config('sso.class_url').'?OrganizeCode='.$org, '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $classs = [];
        $result = json_decode($result,true);
        if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
            if ($result['Data'] && isset($result['Data']['ListData'])) {
                $listData = $result['Data']['ListData'];
                foreach ($listData as $item) {
                    $classs[] = [
                        'code' => $item['DepartmentId'],
                        'name' => $item['DepartmentName'] ?? '',
                        'shortname' => $item['ShortName'] ?? '',
                        'organizeid' => $org,
                        'grade_code' => $item['GradeCode'],
                        'source' => 1,
                        'sort' => (int)$item['SortCode'],
                        'term_code' => $item['TermName'],
                    ];

                }

            }
        }

        return $classs;
    }

    /**
     * 年级
     * @param $org
     * @return array
     * @author: Yjj
     * @Date: user
     *
     */

    public function syncGrade($org)
    {
        $result = $this->curl_https(config('sso.server_sso_url').config('sso.grade_url').'?organizeId='.$org, '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $grade = [];
        $result = json_decode($result,true);
        if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
            if ($result['Data'] && isset($result['Data']['ListData'])) {
                $listData = $result['Data']['ListData'];
                foreach ($listData as $item) {
                    $grade[] = [
                        'code' => $item['Code'],
                        'name' => $item['Name'] ?? '',
                        'organizeid' => $org,
                        'source' => 1,
                    ];
                }


            }
        }

        return $grade;
    }

    /**
     * 同步学生
     * @param $org
     * @return array
     * @author: Yjj
     * @Date: user
     */

    public function syncStudent($org){
        $result = $this->curl_https(config('sso.server_sso_url').config('sso.userall_url').'?orgCode='.$org.'&userType=student', '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $student = [];
        $result = json_decode($result,true);
        if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
            if ($result['Data'] && isset($result['Data']['ListData'])) {
                $listData = $result['Data']['ListData'];
                foreach ($listData as $item) {
                    $student[] = [
                        'uuid' => $item['F_Uid'],
                        'real_name' => $item['F_RealName'] ?? '',
                        'headicon' => $item['F_HeadIcon'] ?? '',
                        'mobile' => $item['PhoneNumber'] ?? '',
                        'organizeid' => $org,
                        'student_code' => $item['F_StudentCode'],
                        'class_id' => $item['F_DepartmentId'] ?? 0,
                    ];
                }
            }
        }

        return $student;
    }


}
