<?php
namespace Cloak\Admin\Controllers;

require_once dirname(__DIR__, 2) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\Auth;
use Cloak\Core\Logger;
use Cloak\Tools\BlacklistChecker;
use Cloak\Tools\UATest;
use Cloak\Tools\IPTest;

/**
 * 工具管理控制器
 * 提供各种系统工具和测试功能
 */
class ToolsController {
    private $config;
    private $auth;
    private $logger;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->auth = new Auth();
        $this->logger = new Logger();
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
        
        // 获取系统信息
        $systemInfo = $this->getSystemInfo();
        
        // 渲染页面
        $this->render('tools', [
            'system_info' => $systemInfo,
            'message' => $message,
            'messageType' => $messageType
        ]);
    }
    
    /**
     * 黑名单检查工具
     */
    public function blacklistCheck() {
        $this->auth->requireLogin();
        
        $checker = new BlacklistChecker();
        $report = $checker->generateReport();
        
        $this->render('blacklist_check', [
            'report' => $report
        ]);
    }
    
    /**
     * UA测试工具
     */
    public function uaTest() {
        $this->auth->requireLogin();
        
        $message = '';
        $messageType = 'success';
        $testResults = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $uaTest = new UATest();
            
            if (isset($_POST['test_ua'])) {
                $userAgent = trim($_POST['user_agent'] ?? '');
                $testIP = trim($_POST['test_ip'] ?? '');
                
                if (!empty($userAgent)) {
                    $testResults = [$uaTest->testUA($userAgent, $testIP ?: null)];
                    $message = '✅ UA测试完成';
                } else {
                    $message = '❌ 请输入User-Agent';
                    $messageType = 'error';
                }
            } elseif (isset($_POST['batch_test'])) {
                $testType = $_POST['test_type'] ?? 'common';
                
                if ($testType === 'common') {
                    $commonUAs = $uaTest->getCommonTestUAs();
                    $allUAs = array_merge(
                        array_values($commonUAs['browsers']),
                        array_values($commonUAs['bots']),
                        array_values($commonUAs['tools'])
                    );
                    $testResults = $uaTest->batchTestUAs($allUAs);
                } elseif ($testType === 'blacklist') {
                    $blacklistData = $uaTest->getBlacklistUAs();
                    $sampleUAs = array_slice($blacklistData['uas'], 0, 20); // 测试前20个
                    $testResults = $uaTest->batchTestUAs($sampleUAs);
                }
                
                $message = '✅ 批量测试完成，共测试 ' . count($testResults) . ' 个UA';
            }
        }
        
        $this->render('ua_test', [
            'test_results' => $testResults,
            'message' => $message,
            'messageType' => $messageType
        ]);
    }
    
    /**
     * IP测试工具
     */
    public function ipTest() {
        $this->auth->requireLogin();
        
        $message = '';
        $messageType = 'success';
        $testResults = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ipTest = new IPTest();
            
            if (isset($_POST['test_ip'])) {
                $ip = trim($_POST['ip_address'] ?? '');
                $userAgent = trim($_POST['user_agent'] ?? '');
                
                if (!empty($ip)) {
                    $testResults = [$ipTest->testIP($ip, $userAgent ?: null)];
                    $message = '✅ IP测试完成';
                } else {
                    $message = '❌ 请输入IP地址';
                    $messageType = 'error';
                }
            } elseif (isset($_POST['batch_test_ip'])) {
                $testType = $_POST['test_type'] ?? 'common';
                
                if ($testType === 'common') {
                    $commonIPs = $ipTest->getCommonTestIPs();
                    $allIPs = array_merge(
                        array_values($commonIPs['public_dns']),
                        array_values($commonIPs['cloud_providers']),
                        array_values($commonIPs['bot_ips'])
                    );
                    $testResults = $ipTest->batchTestIPs($allIPs);
                } elseif ($testType === 'blacklist') {
                    $blacklistData = $ipTest->getBlacklistIPs();
                    $sampleIPs = array_slice($blacklistData['ips'], 0, 20); // 测试前20个
                    $testResults = $ipTest->batchTestIPs($sampleIPs);
                }
                
                $message = '✅ 批量测试完成，共测试 ' . count($testResults) . ' 个IP';
            }
        }
        
        $this->render('ip_test', [
            'test_results' => $testResults,
            'message' => $message,
            'messageType' => $messageType
        ]);
    }
    
    /**
     * 处理POST请求
     */
    private function handlePostRequest() {
        // 清理日志
        if (isset($_POST['clear_logs'])) {
            return $this->handleClearLogs();
        }
        
        // 备份数据
        if (isset($_POST['backup_data'])) {
            return $this->handleBackupData();
        }
        
        // 清理黑名单
        if (isset($_POST['clean_blacklist'])) {
            return $this->handleCleanBlacklist();
        }
        
        return ['message' => '', 'type' => 'success'];
    }
    
    /**
     * 处理清理日志
     */
    private function handleClearLogs() {
        $logFile = $this->config->getLogPath();
        
        if (file_exists($logFile)) {
            // 备份当前日志
            $backupFile = $this->config->getDataPath() . 'backup/log_backup_' . date('Y-m-d_H-i-s') . '.txt';
            copy($logFile, $backupFile);
            
            // 清空日志文件
            file_put_contents($logFile, '');
            
            $this->logger->logInfo("清理系统日志", ['backup_file' => basename($backupFile)]);
            
            return ['message' => '✅ 日志已清理，备份文件：' . basename($backupFile), 'type' => 'success'];
        }
        
        return ['message' => '⚠️ 日志文件不存在', 'type' => 'error'];
    }
    
    /**
     * 处理备份数据
     */
    private function handleBackupData() {
        $timestamp = date('Y-m-d_H-i-s');
        $backupDir = $this->config->getDataPath() . 'backup/';
        
        $files = [
            'ua_blacklist.txt' => $this->config->getUABlacklistPath(),
            'ip_blacklist.txt' => $this->config->getIPBlacklistPath(),
            'log.txt' => $this->config->getLogPath(),
            'real_landing_url.txt' => $this->config->getLandingURLPath(),
            'api_config.json' => $this->config->getAPIConfigPath()
        ];
        
        $backedUp = 0;
        foreach ($files as $name => $path) {
            if (file_exists($path)) {
                $backupFile = $backupDir . $timestamp . '_' . $name;
                if (copy($path, $backupFile)) {
                    $backedUp++;
                }
            }
        }
        
        $this->logger->logInfo("数据备份", ['files_count' => $backedUp, 'timestamp' => $timestamp]);
        
        return ['message' => "✅ 已备份 $backedUp 个文件到 backup/$timestamp", 'type' => 'success'];
    }
    
    /**
     * 处理清理黑名单
     */
    private function handleCleanBlacklist() {
        $type = $_POST['clean_type'] ?? 'ua';
        $options = [
            'remove_empty' => isset($_POST['remove_empty']),
            'remove_duplicates' => isset($_POST['remove_duplicates'])
        ];
        
        $checker = new BlacklistChecker();
        $result = $checker->cleanBlacklist($type, $options);
        
        if ($result['success']) {
            $this->logger->logInfo("清理黑名单", [
                'type' => $type,
                'removed_count' => $result['removed_count'],
                'options' => $options
            ]);
            
            return ['message' => $result['message'], 'type' => 'success'];
        } else {
            return ['message' => $result['message'], 'type' => 'error'];
        }
    }
    
    /**
     * 获取系统信息
     */
    private function getSystemInfo() {
        $info = [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'disk_free' => disk_free_space('.'),
            'disk_total' => disk_total_space('.'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'current_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ];
        
        // 文件大小信息
        $files = [
            'ua_blacklist' => $this->config->getUABlacklistPath(),
            'ip_blacklist' => $this->config->getIPBlacklistPath(),
            'log_file' => $this->config->getLogPath(),
            'api_config' => $this->config->getAPIConfigPath()
        ];
        
        foreach ($files as $key => $path) {
            $info[$key . '_size'] = file_exists($path) ? filesize($path) : 0;
            $info[$key . '_lines'] = file_exists($path) ? count(file($path)) : 0;
        }
        
        return $info;
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
