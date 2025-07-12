<?php
/**
 * IP检测测试页面
 * 用于快速测试IP检测功能，无需登录
 */

// 引入IP检测函数
function getRealIP() {
    // 优先级顺序：
    // 1. CF-Connecting-IP (Cloudflare传递的真实访客IP)
    // 2. X-Forwarded-For (其他代理传递的IP)
    // 3. X-Real-IP (Nginx等代理设置的真实IP)
    // 4. Client-IP (部分代理使用)
    // 5. Remote-Addr (直连或最后的选择)
    
    // 如果配置了Cloudflare，CF-Connecting-IP应该是最可靠的
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    
    // 检查X-Forwarded-For（可能包含多个IP）
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach ($ips as $ip) {
            $ip = trim($ip);
            // 验证IP格式，排除内网IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                // 跳过IPv6（如果需要的话）
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    continue;
                }
                return $ip;
            }
        }
    }
    
    // 检查X-Real-IP（Nginx代理常用）
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    
    // 检查Client-IP
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = trim($_SERVER['HTTP_CLIENT_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    
    // 最后使用REMOTE_ADDR
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // 如果REMOTE_ADDR是有效的公网IP，直接返回
    if (filter_var($remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $remote_addr;
    }
    
    // 如果都不是有效的公网IP，返回REMOTE_ADDR（可能是内网IP或CDN IP）
    return $remote_addr;
}

$detected_ip = getRealIP();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>IP检测测试</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .result { 
            background: #e3f2fd; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0; 
            text-align: center; 
        }
        .ip { 
            font-size: 24px; 
            font-weight: bold; 
            color: #1976d2; 
            font-family: monospace; 
        }
        .headers { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 6px; 
            margin: 15px 0; 
        }
        .header-item { 
            margin: 8px 0; 
            padding: 8px; 
            background: white; 
            border-radius: 4px; 
        }
        .exists { color: #27ae60; }
        .missing { color: #95a5a6; }
        code { 
            background: #ecf0f1; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 12px; 
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 IP检测测试页面</h1>
    <p>这个页面用于测试IP检测功能，显示系统检测到的访客IP地址。</p>
    
    <div class="result">
        <h2>检测到的IP地址</h2>
        <div class="ip"><?php echo htmlspecialchars($detected_ip); ?></div>
        <p style="color: #666; margin-top: 10px;">
            这个IP地址会被记录到访问日志中，用于机器人检测
        </p>
    </div>
    
    <div class="headers">
        <h3>所有IP相关HTTP头信息</h3>
        
        <?php
        $headers = [
            'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP (Cloudflare)',
            'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For (代理链)',
            'HTTP_X_REAL_IP' => 'X-Real-IP (Nginx代理)',
            'HTTP_CLIENT_IP' => 'Client-IP (客户端)',
            'REMOTE_ADDR' => 'Remote-Addr (直连)'
        ];
        
        foreach ($headers as $key => $name) {
            $value = $_SERVER[$key] ?? null;
            $class = $value ? 'exists' : 'missing';
            $icon = $value ? '✅' : '➖';
            
            echo "<div class='header-item'>";
            echo "<strong class='$class'>$icon $name:</strong><br>";
            if ($value) {
                echo "<code>" . htmlspecialchars($value) . "</code>";
                if ($value === $detected_ip) {
                    echo " <span style='color: #e74c3c; font-weight: bold;'>← 当前使用</span>";
                }
            } else {
                echo "<span class='missing'>未设置</span>";
            }
            echo "</div>";
        }
        ?>
    </div>
    
    <div style="background: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107;">
        <strong>💡 使用说明：</strong><br>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>如果使用Cloudflare CDN，应该看到 CF-Connecting-IP 头</li>
            <li>如果使用其他代理，应该看到 X-Forwarded-For 或 X-Real-IP 头</li>
            <li>系统会按优先级选择最可靠的IP地址</li>
            <li>可以通过管理后台的"IP调试"页面查看更详细的检测过程</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="admin.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            返回管理后台
        </a>
    </div>
</div>

</body>
</html>
