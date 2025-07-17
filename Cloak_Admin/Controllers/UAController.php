<?php
namespace Cloak\Admin\Controllers;

require_once dirname(__DIR__, 2) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\Auth;
use Cloak\Core\Logger;
use Cloak\Core\BlacklistChecker;

/**
 * UA黑名单管理控制器
 * 处理User-Agent黑名单的增删改查操作
 */
class UAController {
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
        
        // 获取UA列表
        $uas = $this->getUAList();
        
        // 获取API配置
        $apiConfig = $this->config->getAPIConfig();
        
        // 渲染页面
        $this->render('ua_management', [
            'uas' => $uas,
            'api_config' => $apiConfig,
            'message' => $message,
            'messageType' => $messageType,
            'stats' => $this->getUAStats($uas)
        ]);
    }
    
    /**
     * 处理POST请求
     */
    private function handlePostRequest() {
        // 文件上传
        if (isset($_POST['upload_ua'])) {
            return $this->handleFileUpload();
        }
        
        // API自动更新
        if (isset($_POST['auto_update_ua'])) {
            return $this->handleAPIUpdate();
        }
        
        // 手动添加UA
        if (isset($_POST['add_ua'])) {
            return $this->handleManualAdd();
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
     * 处理文件上传
     */
    private function handleFileUpload() {
        $uploadType = $_POST['upload_type'] ?? 'merge';
        
        if (!isset($_FILES['ua_file']) || $_FILES['ua_file']['error'] !== UPLOAD_ERR_OK) {
            return ['message' => '❌ 上传失败，请重试', 'type' => 'error'];
        }
        
        $file = $_FILES['ua_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'txt') {
            return ['message' => '❌ 只允许上传txt文件', 'type' => 'error'];
        }
        
        $content = file_get_contents($file['tmp_name']);
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $lines = array_unique($lines);
        
        if (empty($lines)) {
            return ['message' => '❌ 上传的文件内容为空', 'type' => 'error'];
        }
        
        $uaFile = $this->config->getUABlacklistPath();
        
        if ($uploadType === 'cover') {
            file_put_contents($uaFile, implode("\n", $lines));
            $this->logger->logInfo("UA黑名单覆盖上传", ['count' => count($lines)]);
            return ['message' => "✅ 上传覆盖成功，UA黑名单共 " . count($lines) . " 条", 'type' => 'success'];
        } else {
            $existing = file_exists($uaFile) ? file($uaFile, FILE_IGNORE_NEW_LINES) : [];
            $merged = array_unique(array_merge($existing, $lines));
            sort($merged);
            file_put_contents($uaFile, implode("\n", $merged));
            $this->logger->logInfo("UA黑名单合并上传", ['new' => count($lines), 'total' => count($merged)]);
            return ['message' => "✅ 上传合并成功，UA黑名单共 " . count($merged) . " 条", 'type' => 'success'];
        }
    }
    
    /**
     * 处理API自动更新
     */
    private function handleAPIUpdate() {
        $apiUrl = trim($_POST['api_url'] ?? '');
        $apiParams = trim($_POST['api_params'] ?? '');
        
        if (empty($apiUrl)) {
            return ['message' => '❌ API地址不能为空', 'type' => 'error'];
        }
        
        // 保存API配置
        $apiConfig = [
            'api_url' => $apiUrl,
            'api_params' => $apiParams,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        $this->config->saveAPIConfig($apiConfig);
        
        // 执行API请求
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiParams);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $response !== false && strlen($response) > 10) {
            $newLines = array_filter(array_map('trim', explode("\n", $response)));
            $newLines = array_unique($newLines);
            
            if (!empty($newLines)) {
                $uaFile = $this->config->getUABlacklistPath();
                $existing = file_exists($uaFile) ? file($uaFile, FILE_IGNORE_NEW_LINES) : [];
                $merged = array_unique(array_merge($existing, $newLines));
                sort($merged);
                file_put_contents($uaFile, implode("\n", $merged));
                
                // 提取IP地址
                $ipCount = $this->extractAndSaveIPs($newLines);
                
                $this->logger->logInfo("UA黑名单API更新", [
                    'new_count' => count($newLines),
                    'total_count' => count($merged),
                    'ip_count' => $ipCount
                ]);
                
                $msg = "✅ 自动更新成功！从API获取到 " . count($newLines) . " 条新UA，合并后黑名单共 " . count($merged) . " 条";
                if ($ipCount > 0) {
                    $msg .= "，同时提取并保存了 " . $ipCount . " 个IP地址";
                }
                
                return ['message' => $msg, 'type' => 'success'];
            } else {
                return ['message' => '⚠️ API返回结果为空，未更新任何UA', 'type' => 'error'];
            }
        } else {
            $this->logger->logError("UA黑名单API更新失败", [
                'http_code' => $httpCode,
                'curl_error' => $curlErr
            ]);
            return ['message' => "❌ API请求失败，HTTP状态码: $httpCode" . ($curlErr ? "，错误: $curlErr" : ""), 'type' => 'error'];
        }
    }
    
    /**
     * 处理手动添加UA
     */
    private function handleManualAdd() {
        $newUA = trim($_POST['new_ua'] ?? '');
        
        if (empty($newUA)) {
            return ['message' => '❌ UA不能为空', 'type' => 'error'];
        }
        
        $uaFile = $this->config->getUABlacklistPath();
        $existing = file_exists($uaFile) ? file($uaFile, FILE_IGNORE_NEW_LINES) : [];
        
        if (in_array($newUA, $existing)) {
            return ['message' => '⚠️ 该UA已存在于黑名单中', 'type' => 'error'];
        }
        
        $existing[] = $newUA;
        sort($existing);
        file_put_contents($uaFile, implode("\n", $existing));
        
        $this->logger->logInfo("手动添加UA到黑名单", ['ua' => substr($newUA, 0, 100)]);
        
        return ['message' => '✅ UA添加成功', 'type' => 'success'];
    }
    
    /**
     * 处理删除操作
     */
    private function handleDelete() {
        $delUA = urldecode($_GET['ua'] ?? '');
        
        if (empty($delUA)) {
            return ['message' => '❌ 删除参数错误', 'type' => 'error'];
        }
        
        $uaFile = $this->config->getUABlacklistPath();
        $list = file_exists($uaFile) ? file($uaFile, FILE_IGNORE_NEW_LINES) : [];
        $list = array_filter($list, fn($x) => $x !== $delUA);
        file_put_contents($uaFile, implode("\n", $list));
        
        $this->logger->logInfo("从黑名单删除UA", ['ua' => substr($delUA, 0, 100)]);
        
        return ['message' => '✅ UA删除成功', 'type' => 'success'];
    }
    
    /**
     * 处理清空所有
     */
    private function handleClearAll() {
        $uaFile = $this->config->getUABlacklistPath();
        file_put_contents($uaFile, '');
        
        $this->logger->logInfo("清空所有UA黑名单");
        
        return ['message' => '✅ 已清空所有UA黑名单', 'type' => 'success'];
    }
    
    /**
     * 获取UA列表
     */
    private function getUAList() {
        $uaFile = $this->config->getUABlacklistPath();
        return file_exists($uaFile) ? file($uaFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    }
    
    /**
     * 获取UA统计信息
     */
    private function getUAStats($uas) {
        $stats = [
            'total' => count($uas),
            'with_ip' => 0,
            'pure_ua' => 0
        ];
        
        foreach ($uas as $ua) {
            if (preg_match('/\[ip:([^\]]+)\]$/', $ua)) {
                $stats['with_ip']++;
            } else {
                $stats['pure_ua']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * 从UA列表中提取IP地址并保存
     */
    private function extractAndSaveIPs($uaLines) {
        $ips = [];
        
        foreach ($uaLines as $line) {
            if (preg_match('/\[ip:([^\]]+)\]$/', $line, $matches)) {
                $ip = trim($matches[1]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }
        
        if (!empty($ips)) {
            $ipFile = $this->config->getIPBlacklistPath();
            $existing = file_exists($ipFile) ? file($ipFile, FILE_IGNORE_NEW_LINES) : [];
            $merged = array_unique(array_merge($existing, $ips));
            sort($merged);
            file_put_contents($ipFile, implode("\n", $merged));
            
            return count($ips);
        }
        
        return 0;
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
