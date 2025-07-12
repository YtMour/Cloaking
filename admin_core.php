<?php
/**
 * 管理后台核心功能模块
 * 包含共用的认证、配置和工具函数
 */

session_start();

// ====== 配置项 ======
$config = [
    'password' => '123456', // 登录密码（请修改为强密码）
    'landing_file' => 'real_landing_url.txt',
    'ua_file' => 'ua_blacklist.txt',
    'ip_file' => 'ip_blacklist.txt',
    'log_file' => 'log.txt',
    'api_config_file' => 'api_config.json'
];

// 认证检查
function checkAuth($password) {
    if (isset($_POST['password'])) {
        if ($_POST['password'] === $password) {
            $_SESSION['auth'] = true;
            return true;
        } else {
            return ['error' => '密码错误'];
        }
    }
    
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    return isset($_SESSION['auth']);
}

// 显示登录页面
function showLoginPage($error = '') {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
    <meta charset="UTF-8" />
    <title>登录后台</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: auto; padding: 40px 20px; background: #f9f9f9; }
        h2 { color: #333; text-align: center; }
        form { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 0 10px #ddd; }
        input[type="password"] { width: 100%; padding: 10px; margin-top: 10px; box-sizing: border-box; }
        button { margin-top: 15px; width: 100%; padding: 10px; cursor: pointer; }
        p.error { color: #c00; text-align: center; }
    </style>
    </head>
    <body>
    <h2>🔐 管理后台登录</h2>
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="post">
        <label>请输入密码：</label>
        <input type="password" name="password" required autofocus />
        <button type="submit">登录</button>
    </form>
    </body>
    </html>
    <?php
    exit;
}

// 获取通用样式
function getCommonStyles() {
    return '
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            position: relative;
            background: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .logout-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220,53,69,0.3);
        }

        .nav-menu {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .nav-btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .nav-btn.active {
            background: #28a745;
        }

        h2 {
            color: #2c3e50;
            margin: 0 60px 0 0;
            font-size: 28px;
            font-weight: 600;
        }

        .card {
            background: rgba(255,255,255,0.95);
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .msg {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left-color: #28a745;
        }

        .error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left-color: #dc3545;
        }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .header h2 { font-size: 20px; margin-right: 80px; }
            .nav-menu { gap: 10px; }
            .nav-btn { padding: 8px 16px; font-size: 13px; }
        }
    </style>';
}

// 获取导航菜单
function getNavMenu($current_page = '') {
    $menu_items = [
        'admin.php' => '🏠 主页',
        'admin_ua.php' => '🛡 UA管理',
        'admin_ip.php' => '🚫 IP查看',
        'admin_monitor.php' => '📊 监控'
    ];
    
    $html = '<div class="nav-menu">';
    foreach ($menu_items as $page => $title) {
        $active = ($current_page === $page) ? ' active' : '';
        $html .= '<a href="' . $page . '" class="nav-btn' . $active . '">' . $title . '</a>';
    }
    $html .= '</div>';

    return $html;
}

// 读取黑名单统计
function getBlacklistStats($config) {
    $stats = [
        'ua_total' => 0,
        'ip_total' => 0,
        'ip_from_ua' => 0,
        'ip_from_file' => 0
    ];
    
    // 统计 UA
    if (file_exists($config['ua_file'])) {
        $uas = file($config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['ua_total'] = count($uas);
    }
    
    // 统计 IP
    $ips_from_file = file_exists($config['ip_file']) ? 
        file($config['ip_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $stats['ip_from_file'] = count($ips_from_file);
    
    // 从 UA 文件提取 IP
    $ips_from_ua = [];
    if (file_exists($config['ua_file'])) {
        $ua_lines = file($config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($ua_lines as $line) {
            if (preg_match('/\[ip:([^\]]+)\]$/', trim($line), $matches)) {
                $ip = trim($matches[1]);
                if (!empty($ip)) {
                    $ips_from_ua[] = $ip;
                }
            }
        }
    }
    $stats['ip_from_ua'] = count($ips_from_ua);
    $stats['ip_total'] = count(array_unique(array_merge($ips_from_file, $ips_from_ua)));
    
    return $stats;
}

// 显示消息
function showMessage($message, $type = 'success') {
    if (!empty($message)) {
        echo '<div class="msg ' . ($type === 'error' ? 'error' : 'success') . '">';
        echo htmlspecialchars($message);
        echo '</div>';
    }
}

// 读取 API 配置
function getAPIConfig($config_file) {
    $default_config = [
        'api_url' => 'https://user-agents.net/download',
        'api_params' => 'crawler=true&limit=500&download=txt'
    ];

    if (file_exists($config_file)) {
        $saved_config = json_decode(file_get_contents($config_file), true);
        if ($saved_config && is_array($saved_config)) {
            return array_merge($default_config, $saved_config);
        }
    }

    return $default_config;
}

// 保存 API 配置
function saveAPIConfig($config_file, $api_url, $api_params) {
    $config = [
        'api_url' => trim($api_url),
        'api_params' => trim($api_params),
        'last_updated' => date('Y-m-d H:i:s')
    ];

    return file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

// 从 UA 文件提取 IP 并保存到独立文件
function extractAndSaveIPs($ua_file, $ip_file) {
    $extracted_ips = [];

    if (file_exists($ua_file)) {
        $ua_lines = file($ua_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($ua_lines as $line) {
            $line = trim($line);
            if (preg_match('/\[ip:([^\]]+)\]$/', $line, $matches)) {
                $ip = trim($matches[1]);
                if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $extracted_ips[] = $ip;
                }
            }
        }
    }

    if (!empty($extracted_ips)) {
        // 去重并排序
        $extracted_ips = array_unique($extracted_ips);
        sort($extracted_ips);

        // 保存到 IP 文件
        $result = file_put_contents($ip_file, implode("\n", $extracted_ips));
        return $result !== false ? count($extracted_ips) : false;
    }

    return 0;
}

// 获取真实访问者IP（与index.php保持一致）
function getRealVisitorIP() {
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

// 移除了Cloudflare检测函数，使用最直接的IP获取方式
?>
