<?php
/**
 * Cloak 系统主入口文件
 * 负责流量检测、黑名单过滤和跳转逻辑
 * 
 * 功能：
 * 1. 获取访客真实IP地址（支持CDN环境）
 * 2. 检查IP和User-Agent是否在黑名单中
 * 3. 记录访问日志
 * 4. 根据检测结果显示假页面或跳转到真实地址
 */

// 引入核心模块
require_once 'Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\IPDetector;
use Cloak\Core\BlacklistChecker;
use Cloak\Core\Logger;

try {
    // 初始化配置
    $config = Config::getInstance();
    $config->ensureDataDirectory();
    
    // 获取访客真实IP
    $ipDetector = new IPDetector();
    $ip = $ipDetector->getRealIP();
    
    // 获取User-Agent和来源
    $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $referer = $_SERVER['HTTP_REFERER'] ?? '-';
    
    // 初始化日志记录器
    $logger = new Logger();
    
    // 黑名单检查
    $blacklistChecker = new BlacklistChecker();
    $isBot = $blacklistChecker->isBot($userAgent, $ip);
    
    if ($isBot) {
        // 机器人或恶意访问 - 显示假页面
        $logger->log($ip, $userAgent, '显示假页面 (机器人检测)', $referer);
        
        // 检查假页面是否存在
        if (file_exists('fake_page.html')) {
            include 'fake_page.html';
        } else {
            // 如果假页面不存在，显示默认页面
            showDefaultFakePage();
        }
        
        exit;
    }
    
    // 真实用户 - 跳转到目标地址
    $realUrl = $config->getLandingURL();
    
    // 验证跳转地址
    if (empty($realUrl) || $realUrl === 'https://www.example.com') {
        // 如果没有设置跳转地址或使用默认地址，显示设置提示
        $logger->log($ip, $userAgent, '跳转地址未设置', $referer);
        showSetupPage();
        exit;
    }
    
    // 记录正常跳转日志
    $logger->log($ip, $userAgent, '正常跳转', $referer);
    
    // 执行跳转
    header("Location: $realUrl", true, 302);
    exit;
    
} catch (Exception $e) {
    // 错误处理
    error_log("Cloak系统错误: " . $e->getMessage());
    
    // 显示通用错误页面
    http_response_code(500);
    echo "<!DOCTYPE html>
<html>
<head>
    <title>系统维护中</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 10px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class='error'>
        <h2>🔧 系统维护中</h2>
        <p>网站正在进行维护，请稍后再试。</p>
    </div>
</body>
</html>";
    exit;
}

/**
 * 显示默认假页面
 */
function showDefaultFakePage() {
    echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>网站建设中</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .icon { font-size: 4rem; margin-bottom: 20px; }
        h1 { color: #2c3e50; margin-bottom: 15px; font-size: 2rem; }
        p { color: #666; line-height: 1.6; margin-bottom: 20px; }
        .progress {
            background: #ecf0f1;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            width: 65%;
            border-radius: 4px;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .footer { margin-top: 30px; font-size: 0.9rem; color: #95a5a6; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='icon'>🚧</div>
        <h1>网站建设中</h1>
        <p>我们正在努力为您打造更好的体验，网站即将上线。</p>
        <div class='progress'>
            <div class='progress-bar'></div>
        </div>
        <p>预计完成进度：65%</p>
        <div class='footer'>
            <p>感谢您的耐心等待</p>
        </div>
    </div>
</body>
</html>";
}

/**
 * 显示设置页面
 */
function showSetupPage() {
    echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Cloak 系统设置</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 90%;
        }
        .icon { font-size: 4rem; margin-bottom: 20px; }
        h1 { color: #2c3e50; margin-bottom: 15px; font-size: 2rem; }
        p { color: #666; line-height: 1.6; margin-bottom: 20px; }
        .setup-steps {
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .step {
            margin-bottom: 15px;
            padding: 10px;
            border-left: 4px solid #667eea;
            background: white;
            border-radius: 4px;
        }
        .step strong { color: #2c3e50; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class='container'>
        <div class='icon'>⚙️</div>
        <h1>Cloak 系统设置</h1>
        <p>欢迎使用 Cloak 智能流量过滤系统！请完成以下设置步骤：</p>
        
        <div class='setup-steps'>
            <div class='step'>
                <strong>步骤 1：</strong> 访问管理后台设置跳转地址
            </div>
            <div class='step'>
                <strong>步骤 2：</strong> 配置黑名单规则
            </div>
            <div class='step'>
                <strong>步骤 3：</strong> 测试系统功能
            </div>
        </div>
        
        <a href='Cloak_admin.php' class='btn'>进入管理后台</a>
        
        <div style='margin-top: 30px; font-size: 0.9rem; color: #95a5a6;'>
            <p>首次登录密码：123456</p>
            <p>请及时修改默认密码</p>
        </div>
    </div>
</body>
</html>";
}
?>
