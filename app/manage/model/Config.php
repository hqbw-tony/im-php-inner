<?php
/**
 * raingad IM [ThinkPHP6]
 * @author xiekunyu <raingad@foxmail.com>
 */
namespace app\manage\model;

use app\BaseModel;
use think\facade\Cache;
class Config extends BaseModel
{
    protected $json = ['value'];
    protected $jsonAssoc = true;
    protected static $clientLangList=['zh-cn','en-us','ja','ko'];
    protected static $friendAddModeList=[1,2,3];

    public static function normalizeClientDefaultLang($language)
    {
        $language=strtolower(str_replace('_','-',trim((string)$language)));
        $map=[
            'zh'=>'zh-cn',
            'zh-hans'=>'zh-cn',
            'zh-cn'=>'zh-cn',
            'cn'=>'zh-cn',
            'en'=>'en-us',
            'en-us'=>'en-us',
            'ja'=>'ja',
            'jp'=>'ja',
            'ko'=>'ko',
            'kr'=>'ko',
        ];
        $language=$map[$language] ?? $language;
        return in_array($language,self::$clientLangList,true) ? $language : '';
    }

    /**
     * 规范化加好友通过方式：1 全部确认，2 指定发起人直通，3 全部直通。
     */
    public static function normalizeFriendAddMode($mode)
    {
        $mode=(int)$mode;
        return in_array($mode,self::$friendAddModeList,true) ? $mode : 1;
    }

    /**
     * 聊天配置缺少好友通过方式时，按兼容旧系统的“全部确认”处理。
     */
    public static function normalizeChatInfo($value)
    {
        if(!is_array($value)){
            $value=[];
        }
        $value['friendAddMode']=self::normalizeFriendAddMode($value['friendAddMode'] ?? 1);
        return $value;
    }

    // 获取系统配置信息
    public static function getSystemInfo($update=false){
        $name='systemInfo';
        // $auth=request()->header('Authorization');
        $nameFields=['sysInfo','fileUpload','chatInfo','compass'];
        // 如果是登录状态才会返回chatINfo
        // if($auth){
        //     $name='all'.$name;
        //     $nameFields[]="chatInfo";
        // }
        if(Cache::has($name) && !$update){
            $systemInfo=Cache::get($name);
        }else{
            $systemInfo=[];
            $conf=Config::where([['name','in',$nameFields]])->select()->toArray();
            foreach($conf as $v){
                $value=[];
                if($v['name']=='fileUpload'){
                    $value['size'] = $v['value']['size'];
                    $value['preview'] = $v['value']['preview'];
                    $value['fileExt'] = $v['value']['fileExt'];
                }elseif($v['name']=='chatInfo'){
                    $value=self::normalizeChatInfo($v['value']);
                }else{
                    $value=$v['value'];
                }
                $systemInfo[$v['name']]=$value;
            }
            if(isset($systemInfo['sysInfo'])){
                $systemInfo['sysInfo']['clientDefaultLang']=self::normalizeClientDefaultLang($systemInfo['sysInfo']['clientDefaultLang'] ?? '') ?: 'zh-cn';
            }
            Cache::set($name,$systemInfo,7*86400);
        }
        if(isset($systemInfo['sysInfo'])){
            $systemInfo['sysInfo']['clientDefaultLang']=self::normalizeClientDefaultLang($systemInfo['sysInfo']['clientDefaultLang'] ?? '') ?: 'zh-cn';
        }
        return $systemInfo;
    }
}
