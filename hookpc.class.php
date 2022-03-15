<?php
if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}


class plugin_yunling_redirect_resource
{
    function common()
    {
        global $_G;
        $config = $_G['cache']['plugin']['yunling_redirect_resource'];
        if ($config['switch'])
        {
            require_once libfile('function/func','plugin/yunling_redirect_resource');
            $expect_zip_url = explode("\n",trim($config['expect_zip_url']));
            $expect_redirect_url = explode("\n",trim($config['expect_redirect_url']));
            $regexps = trim($config['regexp']);
            if (!empty($regexps))
                $regexps = explode("\n",$regexps);


            yunling_redirect_resource_cache();

            if (!in_array($_SERVER['REQUEST_URI'],$expect_redirect_url))
            {

                $_G['setting']['output']['preg']['search']['yunling_redirect_resource_img'] = '/<img.*?src=["|\']([^"\']*?)["|\']/i';
                $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_img'] = 'yunling_replace($matches)';

                $_G['setting']['output']['preg']['search']['yunling_redirect_resource_link'] = '/<link.*?href=["|\']([^"]*?)["|\'][^>]*>/i';
                $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_link'] = 'yunling_replace($matches)';

                $_G['setting']['output']['preg']['search']['yunling_redirect_resource_script'] = '/<script.*?src=["|\']([^"]*?)["|\'][^>]*><\/script>/i';
                $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_script'] = 'yunling_replace($matches)';

                $_G['setting']['output']['preg']['search']['yunling_redirect_resource_error_img'] = '/<img.*?onerror=["|\']this\.onerror=null;this\.src=["|\']([^\'"]*?)["|\']{2}/i';
                $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_error_img'] = 'yunling_replace($matches)';

                $_G['setting']['output']['preg']['search']['yunling_redirect_resource_background_img'] = '/background(?:-image)?[ ]*:[#\w ]*url\(["|\']?([^\'"]*?)["|\']?\)/i';
                $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_background_img'] = 'yunling_replace($matches)';

                $_G['setting']['output']['preg']['search']['yunling_redirect_resource_css_import'] = '/@import url\(["|\']?([^"\']*?)["|\']?\)/i';
                $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_css_import'] = 'yunling_replace($matches)';

                if (is_array($regexps))
                {
                    if (count($regexps) >= 1)
                    {
                        foreach ($regexps as $k => $regexp) {
                            if (empty($regexp))continue;
                            $explode = explode('>=<',$regexp);
                            if (empty($explode[0]) || empty($explode[1]))continue;
                            $_G['setting']['output']['preg']['search']['yunling_redirect_resource_'.$k] = $explode[0];
                            $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_'.$k] = $explode[1];
                        }
                    }
                }

            }

            if (!in_array($_SERVER['REQUEST_URI'],$expect_zip_url)  && !$config['open_force_zip_one_line'])
            {
                $_G['setting']['output']['preg']['search']['yunling_redirect_resource_zip'] = '/([\s\S]+)/i';
                $_G['setting']['output']['preg']['replace']['yunling_redirect_resource_zip'] = 'yunling_redirect_resource_zip($matches)';
            }

            if ($config['open_force_zip'])
            {
                $_G['yunling_redirect_resource']['is_zip'] = false;
            }

        }

    }

    function avatar($param)
    {
        global $_G;
        if (!$_G['cache']['plugin']['yunling_redirect_resource']['avatar_switch'])return;
        require_once libfile('function/func','plugin/yunling_redirect_resource');
        $param = $param['param'];
        $uid = array_key_exists(0,$param)?$param[0]:false;
        $size = array_key_exists(1,$param)?$param[1]:'middle';
        $size = empty($size)?'small':$size;
        $returnsrc = array_key_exists(2,$param)?$param[2]:false;
        $real = array_key_exists(3,$param)?$param[3]:false;
        $static = array_key_exists(4,$param)?$param[4]:false;
        $ucenterurl = array_key_exists(5,$param)?$param[5]:false;

        if (empty($ucenterurl))$ucenterurl = $_G['setting']['ucenterurl'];
        $uid = sprintf("%09d", $uid);
        $dir1 = substr($uid, 0, 3);
        $dir2 = substr($uid, 3, 2);
        $dir3 = substr($uid, 5, 2);
        $file = $ucenterurl.'/data/avatar/'.$dir1.'/'.$dir2.'/'.$dir3.'/'.substr($uid, -2).($real ? '_real' : '').'_avatar_'.$size.'.jpg';
        $path = parse_url($file)['path'];
        $img = '<img src="%s" alt="' . $_G['setting']['sitename'] . '" title="' . $_G['setting']['sitename'] . '" width="%d" height="%d" />';

        $width = 48;
        $height = 48;
        switch ($size)
        {
            case 'middle':
                $width = 120;
                $height = 120;
                break;

            case 'large':
                $width = 200;
                $height = 200;
                break;
        }

        if (!is_file(DISCUZ_ROOT.$path))
        {
            $file = $ucenterurl.'/images/noavatar_'.$size.'.gif';
        }

        $file = yunling_replace([$file,$file]);
        $return = sprintf($img,$file,$width,$height);

        $_G['hookavatar'] = $returnsrc ? $file : $return;
    }
}

