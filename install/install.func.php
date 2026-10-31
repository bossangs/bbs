<?php

function get_env(&$env, &$write) {
    $env = array();
    $env['os']['name'] = lang('os');
    $env['os']['must'] = FALSE;
    $env['os']['current'] = PHP_OS;
    $env['os']['need'] = 'Linux、Windows/IIS 或兼容 PHP 主机';
    $env['os']['status'] = 1;

    $env['php_version']['name'] = lang('php_version');
    $env['php_version']['must'] = TRUE;
    $env['php_version']['current'] = PHP_VERSION;
    $env['php_version']['need'] = '7.0 - 8.4';
    $env['php_version']['status'] = version_compare(PHP_VERSION, '7.0', '>=') && version_compare(PHP_VERSION, '8.5', '<');

    $required_extensions = array('pdo_mysql'=>'PDO MySQL', 'mbstring'=>'mbstring', 'json'=>'JSON', 'zlib'=>'zlib');
    foreach($required_extensions as $extension=>$label) {
        $env[$extension]['name'] = $label;
        $env[$extension]['must'] = TRUE;
        $env[$extension]['current'] = extension_loaded($extension) ? '已启用' : '未启用';
        $env[$extension]['need'] = $extension;
        $env[$extension]['status'] = extension_loaded($extension) ? 1 : 0;
    }
    $optional_extensions = array('openssl'=>'OpenSSL (建议)', 'curl'=>'cURL (建议)', 'zip'=>'Zip (插件包功能)', 'fileinfo'=>'Fileinfo (上传检测)');
    foreach($optional_extensions as $extension=>$label) {
        $env['optional_'.$extension]['name'] = $label;
        $env['optional_'.$extension]['must'] = FALSE;
        $env['optional_'.$extension]['current'] = extension_loaded($extension) ? '已启用' : '未启用';
        $env['optional_'.$extension]['need'] = $extension;
        $env['optional_'.$extension]['status'] = extension_loaded($extension) ? 1 : 2;
    }

    $writedir = array('../conf/', '../log/', '../tmp/', '../upload/', '../plugin/');
    $write = array();
    foreach($writedir as $dir) $write[$dir] = xn_is_writable('./'.$dir);
}

function install_parse_mysql_host($value) {
    $host = trim((string)$value);
    $port = 3306;
    if(preg_match('/^\[([^\]]+)\](?::([0-9]{1,5}))?$/', $host, $match)) {
        $host = $match[1];
        if(isset($match[2]) && $match[2] !== '') $port = intval($match[2]);
    } elseif(preg_match('/^([^:]+):([0-9]{1,5})$/', $host, $match)) {
        $host = $match[1];
        $port = intval($match[2]);
    }
    if(strpos($host, ':') !== FALSE) {
        if(!preg_match('/^[0-9a-fA-F:]+$/', $host)) return FALSE;
    } elseif(!preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
        return FALSE;
    }
    if($host === '' || $port < 1 || $port > 65535) return FALSE;
    return array($host, $port);
}

function install_sql_split($sql) {
    $statements = array();
    $buffer = '';
    $quote = '';
    $escaped = FALSE;
    $line_comment = FALSE;
    $block_comment = FALSE;
    $length = strlen($sql);
    for($i=0; $i<$length; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $length) ? $sql[$i + 1] : '';
        if($line_comment) {
            if($char === "\n") { $line_comment = FALSE; $buffer .= "\n"; }
            continue;
        }
        if($block_comment) {
            if($char === '*' && $next === '/') { $block_comment = FALSE; $i++; $buffer .= ' '; }
            continue;
        }
        if($quote !== '') {
            $buffer .= $char;
            if($escaped) { $escaped = FALSE; continue; }
            if($char === '\\' && $quote !== '`') { $escaped = TRUE; continue; }
            if($char === $quote) {
                if($next === $quote) { $buffer .= $next; $i++; continue; }
                $quote = '';
            }
            continue;
        }
        if($char === '#' || ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))) {
            $line_comment = TRUE;
            if($char === '-') $i++;
            continue;
        }
        if($char === '/' && $next === '*') { $block_comment = TRUE; $i++; continue; }
        if($char === "'" || $char === '"' || $char === '`') { $quote = $char; $buffer .= $char; continue; }
        if($char === ';') {
            $statement = trim($buffer);
            if($statement !== '') $statements[] = $statement;
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $statement = trim($buffer);
    if($statement !== '') $statements[] = $statement;
    return $statements;
}

function install_sql_file($sqlfile) {
    global $errno, $errstr;
    $sql = @file_get_contents($sqlfile);
    if($sql === FALSE) message(-1, '无法读取安装 SQL：'.$sqlfile);
    foreach(install_sql_split($sql) as $statement) {
        if(db_exec($statement) === FALSE) message(-1, 'sql: '.$statement.', errno: '.$errno.', errstr: '.$errstr);
    }
}

?>
