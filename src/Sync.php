<?php

declare(strict_types=1);

namespace Framework\Syncsso;

class Sync
{
    protected $access_token = '';
    protected $config=[];
    public function __construct($config=[])
    {
        $this->config = $config;
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));//生成url-encode的请求参数
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }


    public function refreshToken(){
        $data =[
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['oauth_client_id'],
            'client_secret' => $this->config['oauth_client_secret'],
            'scope' => $this->config['sync_scope']
        ];
        $result = $this->curl_https($this->config['server_sso_url'].$this->config['token_url'], $data, 'post',
            ['Content-Type' => 'application/x-www-form-urlencoded']);
        if($result){
            $response = json_decode($result, true);
            $this->access_token = $response['access_token'];
        }
    }
    public function syncOrg($org)
    {
        //todo  传参的机构或者获取所有的机构
        //todo  获取机构的基本信息  机构的人员 学生  班级 年级 部门
        //todo  获取accesstoken
        $orgData =[];

        if ($org) {
            //todo 获取机构详情
            $result = $this->curl_https($this->config['server_sso_url'].$this->config['organize_info_url'].'?organizeId='.$org, '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
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
            $result = $this->curl_https($this->config['server_sso_url'].$this->config['org'], '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
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
        $result = $this->curl_https($this->config['server_sso_url'].$this->config['userall_url'].'?orgCode='.$org.'&userType=teacher|staff|org','',
            'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $user = [];
        $result = json_decode($result,true);
        if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
            if ($result['Data'] ) {
                $listData = $result['Data'];
                foreach ($listData as $item) {
                    $user[] = [
                        'user_type' => isset($item['F_UserType'])?$item['F_UserType']:(isset($item['UserType'])?$item['UserType']:''),
                        'user_name' => isset($item['F_RealName'])?$item['F_RealName']:(isset($item['RealName'])?$item['RealName']:''),
                        'login_name' => isset($item['F_Account']) ?$item['F_Account']: (isset($item['Account'])?$item['Account']:''),
                        'nickname' => isset($item['F_RealName']) ?$item['F_RealName']: (isset($item['RealName'])?$item['RealName']:''),
                        'mobile' => isset($item['F_MobilePhone']) ?$item['F_MobilePhone']:(isset($item['MobilePhone'])?$item['MobilePhone']:''),
                        'photo' => isset($item['F_HeadIcon']) ?$item['F_HeadIcon']:(isset($item['HeadIcon'])?$item['HeadIcon']: ''),
                        'organizeid' => isset($item['F_OrganizeId'])?$item['F_OrganizeId']:(isset($item['OrganizeId'])?$item['OrganizeId']:''),
                        'sex' =>(isset($item['F_Gender'])? ($item['F_Gender'] == true ? 1 : ($item['F_Gender'] == false ? 2 : 3)):($item['Gender'] == true ? 1 : ($item['Gender'] == false ? 2 : 3))),
                        'uuid' => isset($item['F_Uid'])?$item['F_Uid']:(isset($item['Uid'])?$item['Uid']:''),
                        'idcard' => isset($item['F_IdCard']) ?$item['F_IdCard']:(isset($item['IdCard'])?$item['IdCard']:''),
                        'depart_id'=>$item['DepartmentId'],
                        'depart'=>isset($item['Departments'])?$item['Departments']:'',
                        'email'=>isset($item['Email'])?$item['Email']:'',
                        'is_admin'=>isset($item['IsAdministrator'])?$item['IsAdministrator']:'',
                        'job'=>isset($item['Job'])?$item['Job']:'',
                        'isaudit'=>isset($item['F_IsAudit'])?$item['F_IsAudit']:0,
                        'face_img'=>isset($item['F_FaceImg'])?$item['F_FaceImg']:'',
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
        $result = $this->curl_https($this->config['server_sso_url'].$this->config['depart_url'].'?OrganizeCode='.$org, '', 'get',
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
                        'managerid'=>isset($item['ManagerId'])?$item['ManagerId']:'',
                        'topmanagerid'=>isset($item['TopManagerId'])?$item['TopManagerId']:'',
                        'categoryid'=>isset($item['CategoryId'])?$item['CategoryId']:'',
                        'year'=>$item['Year'],
                        'gradecode'=>$item['GradeCode']
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
        $result = $this->curl_https($this->config['server_sso_url'].$this->config['class_url'].'?OrganizeCode='.$org, '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
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
        $result = $this->curl_https($this->config['server_sso_url'].$this->config['grade_url'].'?organizeId='.$org, '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
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
        $result = $this->curl_https($this->config['server_sso_url'].$this->config['user_url'].'?orgCode='.$org.'&userType=student', '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $student = [];
        $result = json_decode($result,true);
        if (isset($result['StatusCode']) && $result['StatusCode'] == 1) {
            if ($result['Data'] ) {
                $listData = $result['Data'];
                foreach ($listData as $item) {
                    $student[] = [
                        'real_name' => isset($item['F_RealName'])?$item['F_RealName']:(isset($item['RealName'])?$item['RealName']:''),
                        'mobile' => isset($item['F_MobilePhone']) ?$item['F_MobilePhone']:(isset($item['MobilePhone'])?$item['MobilePhone']:''),
                        'headicon' => isset($item['F_HeadIcon']) ?$item['F_HeadIcon']:(isset($item['HeadIcon'])?$item['HeadIcon']: ''),
                        'organizeid' => $org,
                        'sex' =>(isset($item['F_Gender'])? ($item['F_Gender'] == true ? 1 : ($item['F_Gender'] == false ? 2 : 3)):($item['Gender'] == true ? 1 : ($item['Gender'] == false ? 2 : 3))),
                        'uuid' => isset($item['F_Uid'])?$item['F_Uid']:(isset($item['Uid'])?$item['Uid']:''),
                        'student_code' => isset($item['F_StudentCode']) ?$item['F_StudentCode']:(isset($item['StudentCode'])?$item['StudentCode']:''),
                        'class_id' => isset($item['F_DepartmentId']) ?$item['F_DepartmentId']:(isset($item['DepartmentId'])?$item['DepartmentId']:0),
                    ];
                }
            }
        }

        return $student;
    }

    public function syncGroup($org){
        $postData = ['orgId' =>$org,  'departType' => 4];//用户组
        $url = $this->config['server_sso_url'].$this->config['group_url'];
        $result = $this->curl_https($url, $postData, 'post', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $groupData = [];
        $result = json_decode($result,true);
        if($result&&isset($result['Data'])&&$result['StatusCode']==1) {
            $res = $result['Data']['ListData'];
            $groupUserArray = [];
            $groupAllUser = [];
            foreach ($res as $group){
                if($group['type']=='publicgroup'){
                    $groupData[]=[
                        'groupid'=>trim($group['id'],'g_'),
                        'groupname'=>$group['text'],
                        'pid'=>$group['pid'],
                        'organizeid'=>$org,
                        'category'=>$group['type'],
                        'userCount'=>$group['userCount'],
                        'phone'=>$group['phone'],
                        'status'=>1,
                        'path'=>$group['path']
                    ];
                    //获取组内人员
                    if($group['userCount']>0){//调用接口获取人员
                        $data =['orgId' => $org, 'departId'=> $group['id'],'departType' => 4];
                        $groupUser =curl_http($url,$data,'post', ['Authorization:Bearer '.$this->access_token]);
                        $groupUserList = json_decode($groupUser, true);
                        if($groupUserList&&isset($groupUserList['Data'])&&$groupUserList['StatusCode']==1){//获取到用户
                            $groupUserArray = array_merge($groupUserArray,$groupUserList['Data']['ListData']);
                        }
                    }
                }

            }
        }
       return ['group'=>$groupData,'user'=>$groupUserArray];

    }


    public function syncTerm($org){

        $url = $this->config['server_sso_url'].$this->config['term_url'];
        $result = $this->curl_https($url, '', 'get', ['Authorization:Bearer ' . $this->access_token,'Content-Type:application/json']);
        $termResultInfo = json_decode($result, true);
        $addTearm =[];
        if ($termResultInfo && $termResultInfo['StatusCode'] == 1 && $termResultInfo['Data']) {
            $term = $termResultInfo['Data'];
            foreach ($term as $key => $value) {
                if ($value['IsCurrent']) {
                    $addTearm[] = [
                        'code' => $value['TermId'],
                        'status' => $value['IsCurrent'] ? 1 : 2,
                        'name' => $value['TermName'],
                        'start_time' => $value['StartTime'],
                        'end_time' => $value['EndTime'],
                        'organizeid'=>$org
                    ];
                }
            }
        }
        return $addTearm;
    }


}
