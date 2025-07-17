<?php
namespace Cloak\Admin\Controllers;

require_once dirname(__DIR__, 2) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\Auth;
use Cloak\Core\Logger;
use Cloak\Core\BlacklistChecker;

/**
 * IP黑名单管理控制器
 * 处理IP地址黑名单的增删改查操作
 */
class IPController {
    private $config;
    private $auth;
    private $logger;
    private $blacklistChecker;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->auth = new Auth();
        $this->logger = new Logger();
        $this->blacklistChecker = new BlacklistChecker();
    }
    
    /**
     * 主页面
     */
    public function index() {
        // 认证检查
        $this->auth->requireLogin();
        
        $message = '';
        $messageType = 'success';
        
        // 处理POST请求
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handlePostRequest();
            $message = $result['message'];
            $messageType = $result['type'];
        }
        
        // 处理GET请求（删除操作）
        if (isset($_GET['action'])) {
            $result = $this->handleGetRequest();
            $message = $result['message'];
            $messageType = $result['type'];
        }
        
        // 获取IP列表
        $ipLists = $this->getIPLists();
        
        // 渲染页面
        $this->render('ip_management', [
            'ip_lists' => $ipLists,
            'message' => $message,
            'messageType' => $messageType,
            'stats' => $this->getIPStats($ipLists)
        ]);
    }
    
    /**
     * 处理POST请求
     */
    private function handlePostRequest() {
        // 手动添加IP
        if (isset($_POST['add_ip'])) {
            return $this->handleManualAdd();
        }
        
        // 从UA文件同步IP
        if (isset($_POST['sync_from_ua'])) {
            return $this->handleSyncFromUA();
        }
        
        // 文件上传
        if (isset($_POST['upload_ip'])) {
            return $this->handleFileUpload();
        }
        
        return ['message' => '', 'type' => 'success'];
    }
    
    /**
     * 处理GET请求
     */
    private function handleGetRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'delete':
                return $this->handleDelete();
            case 'clear_all':
                return $this->handleClearAll();
            default:
                return ['message' => '', 'type' => 'success'];
        }
    }
    
    /**
     * 获取IP列表
     */
    private function getIPLists() {
        // 从独立IP文件读取
        $ipFile = $this->config->getIPBlacklistPath();
        $ipsFromFile = file_exists($ipFile) ? 
            file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        
        // 从UA文件中提取IP
        $ipsFromUA = [];
        $uaFile = $this->config->getUABlacklistPath();
        if (file_exists($uaFile)) {
            $uaLines = file($uaFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($uaLines as $line) {
                $line = trim($line);
                if (preg_match('/\[ip:([^\]]+)\]$/', $line, $matches)) {
                    $ip = trim($matches[1]);
                    if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ipsFromUA[] = $ip;
                    }
                }
            }
        }
        
        return [
            'from_file' => array_unique($ipsFromFile),
            'from_ua' => array_unique($ipsFromUA),
            'all' => array_unique(array_merge($ipsFromFile, $ipsFromUA))
        ];
    }
    
    /**
     * 处理手动添加IP
     */
    private function handleManualAdd() {
        $newIP = trim($_POST['new_ip'] ?? '');
        
        if (empty($newIP)) {
            return ['message' => '❌ IP地址不能为空', 'type' => 'error'];
        }
        
        if (!filter_var($newIP, FILTER_VALIDATE_IP)) {
            return ['message' => '❌ 无效的IP地址格式', 'type' => 'error'];
        }
        
        $ipFile = $this->config->getIPBlacklistPath();
        $existing = file_exists($ipFile) ? file($ipFile, FILE_IGNORE_NEW_LINES) : [];
        
        if (in_array($newIP, $existing)) {
            return ['message' => '⚠️ 该IP已存在于黑名单中', 'type' => 'error'];
        }
        
        $existing[] = $newIP;
        sort($existing);
        file_put_contents($ipFile, implode("\n", $existing));
        
        $this->logger->logInfo("手动添加IP到黑名单", ['ip' => $newIP]);
        
        return ['message' => '✅ IP添加成功', 'type' => 'success'];
    }
    
    /**
     * 处理从UA文件同步IP
     */
    private function handleSyncFromUA() {
        $ipLists = $this->getIPLists();
        $ipsFromUA = $ipLists['from_ua'];
        
        if (empty($ipsFromUA)) {
            return ['message' => '⚠️ UA文件中没有找到IP地址', 'type' => 'error'];
        }
        
        $existing = $ipLists['from_file'];
        $merged = array_unique(array_merge($existing, $ipsFromUA));
        sort($merged);
        
        $ipFile = $this->config->getIPBlacklistPath();
        file_put_contents($ipFile, implode("\n", $merged));
        
        $this->logger->logInfo("从UA文件同步IP到黑名单", [
            'synced_count' => count($ipsFromUA),
            'total_count' => count($merged)
        ]);
        
        return ['message' => "✅ 已从UA文件同步 " . count($ipsFromUA) . " 个IP到独立文件，合并后共 " . count($merged) . " 条", 'type' => 'success'];
    }
    
    /**
     * 处理文件上传
     */
    private function handleFileUpload() {
        $uploadType = $_POST['upload_type'] ?? 'merge';
        
        if (!isset($_FILES['ip_file']) || $_FILES['ip_file']['error'] !== UPLOAD_ERR_OK) {
            return ['message' => '❌ 上传失败，请重试', 'type' => 'error'];
        }
        
        $file = $_FILES['ip_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'txt') {
            return ['message' => '❌ 只允许上传txt文件', 'type' => 'error'];
        }
        
        $content = file_get_contents($file['tmp_name']);
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        
        // 验证IP格式
        $validIPs = [];
        $invalidCount = 0;
        foreach ($lines as $line) {
            if (filter_var($line, FILTER_VALIDATE_IP)) {
                $validIPs[] = $line;
            } else {
                $invalidCount++;
            }
        }
        
        if (empty($validIPs)) {
            return ['message' => '❌ 上传的文件中没有有效的IP地址', 'type' => 'error'];
        }
        
        $validIPs = array_unique($validIPs);
        $ipFile = $this->config->getIPBlacklistPath();
        
        if ($uploadType === 'cover') {
            file_put_contents($ipFile, implode("\n", $validIPs));
            $this->logger->logInfo("IP黑名单覆盖上传", ['count' => count($validIPs)]);
            $msg = "✅ 上传覆盖成功，IP黑名单共 " . count($validIPs) . " 条";
        } else {
            $existing = file_exists($ipFile) ? file($ipFile, FILE_IGNORE_NEW_LINES) : [];
            $merged = array_unique(array_merge($existing, $validIPs));
            sort($merged);
            file_put_contents($ipFile, implode("\n", $merged));
            $this->logger->logInfo("IP黑名单合并上传", ['new' => count($validIPs), 'total' => count($merged)]);
            $msg = "✅ 上传合并成功，IP黑名单共 " . count($merged) . " 条";
        }
        
        if ($invalidCount > 0) {
            $msg .= "，跳过了 " . $invalidCount . " 个无效IP";
        }
        
        return ['message' => $msg, 'type' => 'success'];
    }
    
    /**
     * 处理删除操作
     */
    private function handleDelete() {
        $delIP = urldecode($_GET['ip'] ?? '');
        
        if (empty($delIP)) {
            return ['message' => '❌ 删除参数错误', 'type' => 'error'];
        }
        
        $ipFile = $this->config->getIPBlacklistPath();
        $list = file_exists($ipFile) ? file($ipFile, FILE_IGNORE_NEW_LINES) : [];
        $list = array_filter($list, fn($x) => trim($x) !== $delIP);
        file_put_contents($ipFile, implode("\n", $list));
        
        $this->logger->logInfo("从黑名单删除IP", ['ip' => $delIP]);
        
        return ['message' => '✅ IP删除成功', 'type' => 'success'];
    }
    
    /**
     * 处理清空所有
     */
    private function handleClearAll() {
        $ipFile = $this->config->getIPBlacklistPath();
        file_put_contents($ipFile, '');
        
        $this->logger->logInfo("清空所有IP黑名单");
        
        return ['message' => '✅ 已清空所有独立IP黑名单', 'type' => 'success'];
    }
    
    /**
     * 获取IP统计信息
     */
    private function getIPStats($ipLists) {
        $stats = [
            'total' => count($ipLists['all']),
            'from_file' => count($ipLists['from_file']),
            'from_ua' => count($ipLists['from_ua']),
            'ipv4_count' => 0,
            'ipv6_count' => 0
        ];
        
        foreach ($ipLists['all'] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $stats['ipv4_count']++;
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $stats['ipv6_count']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * 渲染视图
     */
    private function render($view, $data = []) {
        extract($data);
        
        // 包含头部模板
        include dirname(__DIR__) . '/Views/Templates/header.php';
        
        // 包含主视图
        include dirname(__DIR__) . "/Views/$view.php";
        
        // 包含底部模板
        include dirname(__DIR__) . '/Views/Templates/footer.php';
    }
}
?>
