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
