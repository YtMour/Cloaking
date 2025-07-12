<?php
// ----------- 配置区域 -----------
$real_url = trim(file_get_contents("real_landing_url.txt"));  // 跳转地址
$fake_page = "fake_page.html";                                // 假页面内容
$log_file = "log.txt";                                        // 可选：记录日志

// 解析 UA 黑名单文件（包含 UA 和 IP 的混合格式）
$bad_keywords = [];
$bad_ips_from_ua_file = [];

if (file_exists("ua_blacklist.txt")) {
    $lines = file("ua_blacklist.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // 检查是否包含 [ip:xxx] 格式
        if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line, $matches)) {
            // 提取 UA 部分和 IP 部分
            $ua_part = trim($matches[1]);
            $ip_part = trim($matches[2]);

            if (!empty($ua_part)) {
                $bad_keywords[] = strtolower($ua_part);
            }
            if (!empty($ip_part)) {
                $bad_ips_from_ua_file[] = $ip_part;
            }
        } else {
            // 纯 UA 行，直接添加
            $bad_keywords[] = strtolower($line);
        }
    }
}

// IP 黑名单（从独立文件读取）
$bad_ips = file_exists("ip_blacklist.txt")
    ? file("ip_blacklist.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];

// 合并来自 UA 文件的 IP 和独立 IP 文件的 IP
$bad_ips = array_merge($bad_ips, $bad_ips_from_ua_file);

// 云服务器IP前缀（保留作为备用检测）
$cloud_ip_prefix = ['34.', '35.', '66.249.', '104.28.', '54.']; // AWS/GCP/IP识别

// 调试模式（在所有变量定义后立即检查）
if (isset($_GET['debug'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h3>🔍 调试信息</h3>";
    echo "<p><strong>IP黑名单数量:</strong> " . count($bad_ips) . "</p>";
    echo "<p><strong>前10个IP黑名单:</strong></p>";
    echo "<pre>" . htmlspecialchars(implode("\n", array_slice($bad_ips, 0, 10))) . "</pre>";
    echo "<p><strong>GET参数:</strong></p>";
    echo "<pre>" . htmlspecialchars(print_r($_GET, true)) . "</pre>";
    echo "<p><strong>HTTP头信息:</strong></p>";
    echo "<pre>";
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0 || in_array($key, ['REMOTE_ADDR'])) {
            echo htmlspecialchars("$key: $value") . "\n";
        }
    }
    echo "</pre>";
    exit;
}
// --------------------------------

// 调试模式 - 显示IP检测信息
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== IP检测调试信息 ===\n\n";

    echo "HTTP头信息:\n";
    echo "CF-Connecting-IP: " . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '未设置') . "\n";
    echo "X-Forwarded-For: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '未设置') . "\n";
    echo "X-Real-IP: " . ($_SERVER['HTTP_X_REAL_IP'] ?? '未设置') . "\n";
    echo "Client-IP: " . ($_SERVER['HTTP_CLIENT_IP'] ?? '未设置') . "\n";
    echo "Remote-Addr: " . ($_SERVER['REMOTE_ADDR'] ?? '未设置') . "\n\n";

    echo "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? '未设置') . "\n\n";

    $detected_ip = getRealIP();
    echo "检测到的IP: $detected_ip\n\n";

    // 检查IP黑名单
    if (file_exists('ip_blacklist.txt')) {
        $ip_blacklist = file('ip_blacklist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        echo "IP黑名单内容 (" . count($ip_blacklist) . " 条):\n";
        foreach ($ip_blacklist as $blocked_ip) {
            echo "- " . trim($blocked_ip) . "\n";
        }
        echo "\nIP是否在黑名单: " . (in_array($detected_ip, array_map('trim', $ip_blacklist)) ? '是' : '否') . "\n\n";
    } else {
        echo "IP黑名单文件不存在\n\n";
    }

    // 检查UA黑名单
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ua_blacklist = file('ua_blacklist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "UA黑名单检查:\n";
    $is_bot = false;
    foreach ($ua_blacklist as $blocked_ua) {
        $blocked_ua = trim($blocked_ua);
        if (empty($blocked_ua)) continue;

        // 提取UA部分（忽略IP部分）
        if (strpos($blocked_ua, '[ip:') !== false) {
            $blocked_ua = trim(substr($blocked_ua, 0, strpos($blocked_ua, '[ip:')));
        }

        if (stripos($user_agent, $blocked_ua) !== false) {
            echo "- 匹配到: $blocked_ua\n";
            $is_bot = true;
        }
    }
    echo "是否为机器人: " . ($is_bot ? '是' : '否') . "\n\n";

    echo "最终判断: " . ($is_bot ? '显示假页面' : '重定向到真实页面') . "\n";
    exit;
}

// 获取访客真实IP - 适配Cloudflare CDN环境
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

$ip = getRealIP();
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$referer = $_SERVER['HTTP_REFERER'] ?? '-';



// 写入日志函数
function writeLog($log_file, $ip, $ua, $action, $referer) {
    $log = date('Y-m-d H:i:s') . " | $ip | $ua | $action | $referer\n";
    file_put_contents($log_file, $log, FILE_APPEND);
}

// 是否可疑 UA 或黑名单 IP
function isBot($ua, $ip, $bad_keywords, $bad_ips, $cloud_ip_prefix) {
    // 检查 UA 黑名单
    foreach ($bad_keywords as $word) {
        if (strpos($ua, $word) !== false) return true;
    }

    // 检查 IP 黑名单（精确匹配）
    foreach ($bad_ips as $bad_ip) {
        if (trim($bad_ip) === $ip) return true;
    }

    // 检查云服务器IP前缀（备用检测）
    foreach ($cloud_ip_prefix as $prefix) {
        if (strpos($ip, $prefix) === 0) return true;
    }

    return false;
}

// 判断访问者类型
if (isBot($ua, $ip, $bad_keywords, $bad_ips, $cloud_ip_prefix)) {
    // 记录被阻挡的访问
    writeLog($log_file, $ip, $ua, '显示假页面 (机器人检测)', $referer);
    // 显示假页面
    include($fake_page);
    exit;
}

// 测试模式
if (isset($_GET['test']) && $_GET['test'] === 'fake') {
    writeLog($log_file, $ip, $ua, '显示假页面 (测试模式)', $referer);
    include($fake_page);
    exit;
}

// 真用户跳转
writeLog($log_file, $ip, $ua, '正常跳转', $referer);
header("Location: $real_url");
exit;
?>
