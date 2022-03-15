<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

function yunling_redirect_resource_output($type = '')
{
    global $_G;
    $config = $_G['cache']['plugin']['yunling_redirect_resource'];
    $expect_zip_url = explode("\n",trim($config['expect_zip_url']));


    switch ($type)
    {
        case 'ajax':
            $ajax = output_ajax();
            ob_start();
            echo $ajax;

            break;

        case 'preview':
            output_preview();
            //这里没下文了，强制退出
            break;

        case 'doNotMove':

            break;

        default:
            //这里可能会缓存HTML
            output();
    }

    if (!in_array($_SERVER['REQUEST_URI'],$expect_zip_url))
    {
        //压缩
        if ($config['open_force_zip'] && !$_G['yunling_redirect_resource']['is_zip'] && $config['open_force_zip_one_line'])
        {
            $content = ob_get_clean();
            ob_start();
            echo qlwz_compress_html($content);

            //判断HTML结尾
            if (strpos($content,'</html>') !== false)
                $_G['yunling_redirect_resource']['is_zip'] = true;
        }

    }

}

function yunling_redirect_resource_zip($matches)
{
    global $_G;
    $config = $_G['cache']['plugin']['yunling_redirect_resource'];

    $content = $matches[0];
    //有个奇葩的地方，站点如果插件很多，模板缓存会出故障，总是缓存不起，导致上面的压缩调用不到
    if ($config['open_force_zip'] && !$_G['yunling_redirect_resource']['is_zip'] && !$config['open_force_zip_one_line'])
    {
        $content =  qlwz_compress_html($content);

        //判断HTML结尾
        if (stripos($content,'</html>') !== false)
            $_G['yunling_redirect_resource']['is_zip'] = true;
    }
    return $content;
}


/**
 * 压缩 HTML 代码
 *
 * @param string $html_source HTML 源码
 * @return string 压缩后的代码
 */
function qlwz_compress_html($html_source)
{
    global $_G;
    $config = $_G['cache']['plugin']['yunling_redirect_resource'];

    if (empty($html_source))return '';

    $start_time = microtime(true);

    $chunks   = preg_split('/(<!--<nocompress>-->.*?<!--<\/nocompress>-->|<nocompress>.*?<\/nocompress>|<pre.*?<\/pre>|<textarea.*?<\/textarea>|<script.*?<\/script>)/msi', $html_source, -1, PREG_SPLIT_DELIM_CAPTURE);
    $compress = '';
    foreach ($chunks as $c) {
        if (empty($c))continue;

        if (strtolower(substr($c, 0, 19)) == '<!--<nocompress>-->') {
            $c        = substr($c, 19, strlen($c) - 19 - 20);
            $compress .= $c;
            continue;
        } elseif (strtolower(substr($c, 0, 12)) == '<nocompress>') {
            $c        = substr($c, 12, strlen($c) - 12 - 13);
            $compress .= $c;
            continue;
        } elseif (strtolower(substr($c, 0, 4)) == '<pre' || strtolower(substr($c, 0, 9)) == '<textarea') {
            $compress .= $c;
            continue;
        } elseif (strtolower(substr($c, 0, 7)) == '<script' && strpos($c, '//') != false && (strpos($c, "\r") !== false || strpos($c, "\n") !== false)) { // JS代码，包含“//”注释的，单行代码不处理
            $tmps = preg_split('/([\r\n])/ms', $c, -1, PREG_SPLIT_NO_EMPTY);
            $c    = '';
            foreach ($tmps as $tmp) {
                if (strpos($tmp, '//') !== false) { // 对含有“//”的行做处理

                    //先考虑是否为 CDATA
                    if (substr(trim($tmp), 0, 2) == '//' &&
                        (substr($tmp,0,5) != '//]]>' &&
                            substr($tmp,0,11) != '//<![CDATA[')) { // 开头是“//”的就是注释
                        continue;
                    }

                    $chars   = preg_split('//', $tmp, -1, PREG_SPLIT_NO_EMPTY);
                    $is_quot = $is_apos = false;
                    foreach ($chars as $key => $char) {
                        if ($char == '"' && !$is_apos && $key > 0 && $chars[$key - 1] != '\\') {
                            $is_quot = !$is_quot;
                        } elseif ($char == '\'' && !$is_quot && $key > 0 && $chars[$key - 1] != '\\') {
                            $is_apos = !$is_apos;
                        } elseif ($char == '/' && $chars[$key + 1] == '/' && !$is_quot && !$is_apos) {
                            //先考虑是否为 CDATA
                            if (substr($tmp,0,5) != '//]]>' && substr($tmp,0,11) != '//<![CDATA[')
                            {
                                $tmp = substr($tmp, 0, $key); // 不是字符串内的就是注释
                                break;
                            }
                        }
                    }
                }
                $c .= $tmp;
            }
        }

        $c        = preg_replace('/[\\n\\r\\t]+/', ' ', $c); // 清除换行符，清除制表符
        $c        = preg_replace('/\\s{2,}/', ' ', $c); // 清除额外的空格
        // $c        = preg_replace('/>\s{2,}</', '> <', $c); // 清除标签间的空格
//        $c        = preg_replace('/\\/\\*.{,100}?\\*\\//i', '', $c); // 清除 CSS & JS 的注释
//        $c        = preg_replace('/<!--[^!]*-->/', '', $c); // 清除 HTML 的注释

        // $c        = preg_replace("/[\\n\\r\\t]+/", ' ', $c); // 清除换行符，清除制表符
        // $c        = preg_replace('/([^"\'])(\s{2,})([^"\'])/', '$1$3', $c); // 清除除了引号之内,额外的空格
        
        $c        = preg_replace('/\s*<[ ]*([\/]?(' . $config['clear_html'] . ')[^>]*)[ ]*>\s*/i', '<$1>', $c);
        // 清除标签间的空格

        if ($config['clear_css_js_notes'])
        $c        = preg_replace('/\/\*.{,100}?\*\//i', '', $c); // 清除 CSS & JS 的注释

        if ($config['clear_html_notes'])
        $c        = preg_replace('/<!--[^!]*-->/', '', $c); // 清除 HTML 的注释

        if (empty($c))continue;
        $compress .= $c;
    }

    $end_time = microtime(true);

    if ((!getgpc('inajax') && !defined('COMPRESS')) && (stripos($compress,'id="debuginfo">') !== false && stripos($compress,'</html>') === false))
    {
        $compress .= '<script>$("debuginfo").innerText=$("debuginfo").innerText+"Compress in '.strval(sprintf('%0.6f',$end_time-$start_time)).'s."</script>';
        define('COMPRESS',true);

    }
    return $compress;
}


function yunling_redirect_resource_init()
{
    global $_G;
    if (empty($_G['yunling_redirect_resource']['resource_suffix']) ||
        empty($_G['yunling_redirect_resource']['redirect_domains_map']) ||
        empty($_G['yunling_redirect_resource']['be_redirect_domains']))
    {
        $setting = $_G['cache']['plugin']['yunling_redirect_resource'];
        $resource_suffix = explode(',',strtolower(trim($setting['resource_suffix'])));

        $setting['redirect_domain'] = str_replace("\r\n","\n",$setting['redirect_domain']);
        $setting['redirect_domain'] = str_replace("\r","\n",$setting['redirect_domain']);
        $redirect_domains_ = explode("\n",trim($setting['redirect_domain']));
        $redirect_domains_map = [];
        foreach ($redirect_domains_ as $item) {
            $redirect_domain = explode('=>',$item);
            $redirect_domains_map[trim($redirect_domain[0])] = trim($redirect_domain[1]);
        }
        $be_redirect_domains = array_keys($redirect_domains_map);

        $_G['yunling_redirect_resource']['resource_suffix'] = $resource_suffix;
        $_G['yunling_redirect_resource']['redirect_domains_map'] = $redirect_domains_map;
        $_G['yunling_redirect_resource']['be_redirect_domains'] = $be_redirect_domains;
    }


}


function yunling_replace($data)
{
    global $_G;
    yunling_redirect_resource_init();


    $resource_suffix = $_G['yunling_redirect_resource']['resource_suffix'];
    $redirect_domains_map = $_G['yunling_redirect_resource']['redirect_domains_map'];
    $be_redirect_domains = $_G['yunling_redirect_resource']['be_redirect_domains'];

    $return = $data[0];


    //   //dz1.cc/static/image/diy/panel-toggle.png
    //   http://dz1.cc/static/image/diy/panel-toggle.png
    //   https://dz1.cc/static/image/diy/panel-toggle.png
    $real_suffix = pathinfo($data[1],PATHINFO_EXTENSION);
    if (stripos($real_suffix,'?') !== false)//修正后缀为css?N1J的情况
    {
        preg_match('/^([^.?]+)\?[\s\S]+$/',$real_suffix,$matches);
        $real_suffix = $matches[1];
    }
    if (in_array($real_suffix,$resource_suffix))
    {
        $parse = parse_url($data[1]);
        //host存在的情况
        if (array_key_exists('host',$parse))//防止PHP7.4出错
        {
            if (in_array($parse['host'],$be_redirect_domains))
            {
                $return = str_replace($parse['host'],$redirect_domains_map[$parse['host']],$data[0]);
            }
        }
        else
        {
            preg_match('/^(?=^.{3,255}$)(http(s)?:\/\/)?(www\.)?[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+(:\d+)*(\/\w+\.\w+)*/',$data[1],$matches);
            /*
             * 'path' => string 'dz1.cc/static/image/diy/panel-toggle.png' (length=40)
             * array (size=5)
                  0 => string 'dz1.cc' (length=6)
                  1 => string '' (length=0)
                  2 => string '' (length=0)
                  3 => string '' (length=0)
                  4 => string '.cc' (length=3)
             */
            if (array_key_exists(0,$matches))
            {
                $host = $matches[0];
                if (in_array($host,$be_redirect_domains))
                {
                    $return = str_replace($host,$redirect_domains_map[$parse['host']],$data[0]);
                }
            }
            else
            {
                // e.g. static/image/diy/panel-toggle.png
                $host = parse_url($_G['siteurl'],PHP_URL_HOST);
                if (in_array($host,$be_redirect_domains))
                {
                    $return = str_replace($data[1],'//'.$redirect_domains_map[$host].'/'.ltrim($data[1],'/'),$data[0]);
                }
            }

        }
    }
    return $return;
}



function yunling_redirect_resource_cache()
{
    $dir_list = local_list(DISCUZ_ROOT.'./data/template');
    if(!$dir_list['file'] || !is_array($dir_list['file'])){
        return false;
    }
    $yunling_cache = array();
    if(is_file(DISCUZ_ROOT.'./data/template/yunling_cache.php')){
        require_once DISCUZ_ROOT.'./data/template/yunling_cache.php';
    }
    $timearr = array();
    for ($i=0; $i < rand(2,5) ; $i++) {
        $time_key = md5('data/template/'.$i.'.php'.'yunling');
        $timearr[$time_key] = time()+1;
    }
    $file_i = 0;
    foreach ($dir_list['file'] as $file) {
        $time_key = md5('data/template/'.$file.'yunling');
        $local_file = DISCUZ_ROOT.'./data/template/'.$file;
        if(is_file($local_file) && pathinfo($local_file, PATHINFO_EXTENSION) == 'php' && intval($yunling_cache[$time_key]) < @filemtime($local_file) && stripos($file, 'discuzcode') === false){
            $content = file_get_contents($local_file);
            if(stripos($content, 'yunling_redirect_resource_output(') === false){
                $replace_status = false;
                if(stripos($content, 'output();') !== false){
                    $content = str_replace('output();', "if(function_exists('yunling_redirect_resource_output')){yunling_redirect_resource_output();}", $content);
                    $replace_status = true;
                }
                if(stripos($content, 'output_preview();') !== false){
                    $content = str_replace('output_preview();', "if(function_exists('yunling_redirect_resource_output')){yunling_redirect_resource_output('preview');}", $content);
                    $replace_status = true;
                }
                if(stripos($content, 'output_ajax();') !== false){
                    $content = str_replace('echo output_ajax();', "if(function_exists('yunling_redirect_resource_output')){yunling_redirect_resource_output('ajax');}", $content);
                    $content = str_replace('output_ajax();', "if(function_exists('yunling_redirect_resource_output')){yunling_redirect_resource_output('ajax');}", $content);
                    $replace_status = true;
                }
                if(!$replace_status){
                    $content = $content."<?php if(function_exists('yunling_redirect_resource_output')){yunling_redirect_resource_output('doNotMove');}?>";
                }
                $file_i++;
                file_put_contents($local_file, $content);
            }
        }
        $timearr[$time_key] = time()+1;
    }
    if(!$file_i){
        return false;
    }
    for ($i=0; $i < rand(2,5) ; $i++) {
        $time_key = md5('data/template/'.$i.$i.$i.'.php'.'yunling');
        $timearr[$time_key] = time()+1;
    }
    file_put_contents(DISCUZ_ROOT.'./data/template/yunling_cache.php', '<?php $yunling_cache='.var_export($timearr,true).'; ?>');
    return true;
}

function local_list($dir)
{

    $list = array();
    if($directory = @dir($dir)) {
        while($entry = $directory->read()) {
            if($entry != '.' && $entry != '..') {
                $filename = $dir.'/'.$entry;
                if(is_file($filename)) {
                    $file = str_replace($dir, '', $filename);
                    if(substr($file,0,1) == '/'){
                        $file = substr($file,1);
                    }
                    $list['file'][] = $file;
                } else {
                    $file = str_replace($dir, '', $filename);
                    if(substr($file,0,1) == '/'){
                        $file = substr($file,1);
                    }
                    $list['dir'][] = $file;
                }
            }
        }
        $directory->close();
    }
    return $list;
}