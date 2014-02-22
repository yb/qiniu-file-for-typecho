<?php
/**
 * 将 Typecho 的附件上传至七牛云存储中。该插件仅为满足个人需求而制作，如有考虑不周的地方，可自行修改。<a href="https://github.com/abelyao/Typecho-QiniuFile" target="_blank">源代码参考</a> &amp; <a href="https://portal.qiniu.com/signup?code=3li4q4loavdxu" target="_blank">注册七牛</a>
 * 
 * @package Qiniu File
 * @author abelyao
 * @version 1.1.0
 * @link http://www.abelyao.com/
 * @date 2014-02-22
 */

class QiniuFile_Plugin implements Typecho_Plugin_Interface
{
    // 激活插件
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('QiniuFile_Plugin', 'uploadHandle');
        Typecho_Plugin::factory('Widget_Upload')->modifyHandle = array('QiniuFile_Plugin', 'modifyHandle');
        Typecho_Plugin::factory('Widget_Upload')->deleteHandle = array('QiniuFile_Plugin', 'deleteHandle');
        Typecho_Plugin::factory('Widget_Upload')->attachmentHandle = array('QiniuFile_Plugin', 'attachmentHandle');
        return _t('插件已经激活，需先配置七牛的信息！');
    }

    
    // 禁用插件
    public static function deactivate()
    {
        return _t('插件已被禁用');
    }

    
    // 插件配置面板
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $bucket = new Typecho_Widget_Helper_Form_Element_Text('bucket', null, null, _t('空间名称：'));
        $form->addInput($bucket->addRule('required', _t('“空间名称”不能为空！')));

        $accesskey = new Typecho_Widget_Helper_Form_Element_Text('accesskey', null, null, _t('AccessKey：'));
        $form->addInput($accesskey->addRule('required', _t('AccessKey 不能为空！')));

        $sercetkey = new Typecho_Widget_Helper_Form_Element_Text('sercetkey', null, null, _t('SecretKey：'));
        $form->addInput($sercetkey->addRule('required', _t('SecretKey 不能为空！')));

        $domain = new Typecho_Widget_Helper_Form_Element_Text('domain', null, 'http://', _t('绑定域名：'), _t('以 http:// 开头，结尾不要加 / ！'));
        $form->addInput($domain->addRule('required', _t('请填写空间绑定的域名！'))->addRule('url', _t('您输入的域名格式错误！')));
    }


    // 个人用户配置面板
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }


    // 获得插件配置信息
    public static function getConfig()
    {
        return Typecho_Widget::widget('Widget_Options')->plugin('QiniuFile');
    }


    // 初始化七牛SDK
    public static function initSDK($accesskey, $sercetkey)
    {
        // 调用 SDK 设置密钥
        require_once 'sdk/io.php';
        require_once 'sdk/rs.php';
        Qiniu_SetKeys($accesskey, $sercetkey);
    }


    // 删除文件
    public static function deleteFile($filepath)
    {
        // 获取插件配置
        $option = self::getConfig();

        // 初始化 SDK
        self::initSDK($option->accesskey, $option->sercetkey);

        // 删除
        $client = new Qiniu_MacHttpClient(null);
        return Qiniu_RS_Delete($client, $option->bucket, $filepath);
    }


    // 上传文件
    public static function uploadFile($file, $content = null)
    {
        // 获取上传文件
        if (empty($file['name'])) return false;

        // 校验扩展名
        $part = explode('.', $file['name']);
        $ext = (($length = count($part)) > 1) ? strtolower($part[$length-1]) : '';
        if (!Widget_Upload::checkFileType($ext)) return false;

        // 获取插件配置
        $option = self::getConfig();
        $date = new Typecho_Date(Typecho_Widget::widget('Widget_Options')->gmtTime);

        // 保存位置
        $savename = $date->year . '/' . $date->month . '/' . sprintf('%u', crc32(uniqid())) . '.' . $ext;
        if (isset($content))
        {
            $savename = $content['attachment']->path;
            self::deleteFile($savename);
        }

        // 上传文件
        $filename = $file['tmp_name'];
        if (!isset($filename)) return false;

        // 初始化 SDK
        self::initSDK($option->accesskey, $option->sercetkey);

        // 上传凭证
        $policy = new Qiniu_RS_PutPolicy($option->bucket);
        $token = $policy->Token(null);
        $extra = new Qiniu_PutExtra();
        $extra->Crc32 = 1;

        // 上传
        list($result, $error) = Qiniu_PutFile($token, $savename, $filename, $extra);
        if ($error == null)
        {
            return array
            (
                'name'  =>  $file['name'],
                'path'  =>  $savename,
                'size'  =>  $file['size'],
                'type'  =>  $ext,
                'mime'  =>  Typecho_Common::mimeContentType($savename)
            );
        }
        else return false;
    }


    // 上传文件处理函数
    public static function uploadHandle($file)
    {
        return self::uploadFile($file);
    }


    // 修改文件处理函数
    public static function modifyHandle($content, $file)
    {
        return self::uploadFile($file, $content);
    }


    // 删除文件
    public static function deleteHandle(array $content)
    {
        self::deleteFile($content['attachment']->path);
    }


    // 获取实际文件绝对访问路径
    public static function attachmentHandle(array $content)
    {
        $option = self::getConfig();
        return Typecho_Common::url($content['attachment']->path, $option->domain);
    }
}