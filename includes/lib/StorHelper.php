<?php

namespace lib;

class StorHelper
{
    private static function getConfig($storage){
        global $conf;
        switch($storage){
            case 'local':
                return $conf['filepath'];
                break;
            case 'sae':
            case 'ace':
                return $conf['storagename'];
                break;
            case 'oss':
                return ['accessKeyId' => $conf['oss_ak'], 'accessKeySecret' => $conf['oss_sk'], 'endpoint' => $conf['oss_endpoint'], 'bucket' => $conf['oss_bucket']];
                break;
            case 'qcloud':
                return ['secretId' => $conf['qcloud_id'], 'secretKey' => $conf['qcloud_key'], 'region' => $conf['qcloud_region'], 'bucket' => $conf['qcloud_bucket']];
                break;
            case 'obs':
                return ['accessKey' => $conf['obs_ak'], 'secretKey' => $conf['obs_sk'], 'endpoint' => $conf['obs_endpoint'], 'bucket' => $conf['obs_bucket']];
            case 'upyun':
                return ['operatorName' => $conf['upyun_user'], 'operatorPwd' => $conf['upyun_pwd'], 'serviceName' => $conf['upyun_name']];
            case 'qiniu':
                return ['accessKey' => $conf['qiniu_ak'], 'secretKey' => $conf['qiniu_sk'], 'bucket' => $conf['qiniu_bucket'], 'domain' => $conf['qiniu_domain']];
            case 'webdav':
                return [
                    'endpoint' => isset($conf['webdav_endpoint']) ? $conf['webdav_endpoint'] : '',
                    'username' => isset($conf['webdav_username']) ? $conf['webdav_username'] : '',
                    'password' => isset($conf['webdav_password']) ? $conf['webdav_password'] : '',
                    'root' => isset($conf['webdav_root']) ? $conf['webdav_root'] : '',
                    'publicUrl' => isset($conf['webdav_public_url']) ? $conf['webdav_public_url'] : '',
                ];
            default:
                break;
        }
    }

    public static function getModel($storage)
    {
        $class = "\\lib\\Storage\\".ucwords($storage);
        $config = self::getConfig($storage);
        if(class_exists($class)){
            $model = new $class($config);
            return $model;
        }
        return false;
    }

    //判断是否可以直接链接
    public static function is_cloud(){
        global $conf;
        $is_cloud = false;
        if(in_array($conf['storage'], ['oss','qcloud','obs','upyun','qiniu','webdav'])) $is_cloud = true;
        return $is_cloud;
    }

    public static function supports_direct_upload(){
        global $conf;
        return in_array($conf['storage'], ['oss','qcloud','obs','upyun','qiniu']);
    }

    public static function supports_direct_download(){
        global $conf;
        if($conf['storage'] === 'webdav') return !empty($conf['webdav_public_url']);
        return in_array($conf['storage'], ['oss','qcloud','obs','upyun','qiniu']);
    }

    //判断是否可以断点续传
    public static function is_range(){
        global $conf;
        $is_range = false;
        if(in_array($conf['storage'], ['local','oss','qcloud','obs','qiniu','webdav'])) $is_range = true;
        return $is_range;
    }
}
