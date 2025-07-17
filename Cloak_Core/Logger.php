<?php
namespace Cloak\Core;

/**
 * 日志记录模块
 * 负责记录访问日志和系统日志
 */
class Logger {
    private $config;
    
    public function __construct() {
        $this->config = Config::getInstance();
    }
    
    /**
     * 记录访问日志
     */
    public function log($ip, $userAgent, $action, $referer = '-') {
        $timestamp = date('Y-m-d H:i:s');
        $userAgent = $this->sanitizeUserAgent($userAgent);
        $referer = $this->sanitizeReferer($referer);
        
        $logEntry = sprintf(
            "%s | %s | %s | %s | %s\n",
            $timestamp,
            $ip,
            $userAgent,
            $action,
            $referer
        );
        
        return $this->writeToLogFile($logEntry);
    }
    
    /**
     * 记录系统日志
     */
    public function logSystem($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        
        $logEntry = sprintf(
            "%s | %s | %s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextStr
        );
        
        return $this->writeToSystemLogFile($logEntry);
    }
    
    /**
     * 记录错误日志
     */
    public function logError($message, $context = []) {
        return $this->logSystem('ERROR', $message, $context);
    }
    
    /**
     * 记录警告日志
     */
    public function logWarning($message, $context = []) {
        return $this->logSystem('WARNING', $message, $context);
    }
    
    /**
     * 记录信息日志
     */
    public function logInfo($message, $context = []) {
        return $this->logSystem('INFO', $message, $context);
    }
    
    /**
     * 记录调试日志
     */
    public function logDebug($message, $context = []) {
        if ($this->config->get('debug', false)) {
            return $this->logSystem('DEBUG', $message, $context);
        }
        return true;
    }
    
    /**
     * 写入访问日志文件
     */
    private function writeToLogFile($logEntry) {
        $logFile = $this->config->getLogPath();
        $this->config->ensureDataDirectory();
        
        // 检查日志文件大小，如果太大则轮转
        if (file_exists($logFile) && filesize($logFile) > $this->config->get('log_max_size', 10485760)) {
            $this->rotateLogFile($logFile);
        }
        
        return file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * 写入系统日志文件
     */
    private function writeToSystemLogFile($logEntry) {
        $logFile = $this->config->getDataPath('logs/system.log');
        $this->config->ensureDataDirectory();
        
        // 检查日志文件大小，如果太大则轮转
        if (file_exists($logFile) && filesize($logFile) > $this->config->get('log_max_size', 10485760)) {
            $this->rotateLogFile($logFile);
        }
        
        return file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * 轮转日志文件
     */
    private function rotateLogFile($logFile) {
        $backupFile = $this->config->getDataPath('logs/' . basename($logFile, '.log') . '_' . date('Y-m-d_H-i-s') . '.log');
        
        if (file_exists($logFile)) {
            rename($logFile, $backupFile);
            
            // 压缩旧日志文件（如果支持gzip）
            if (function_exists('gzencode')) {
                $content = file_get_contents($backupFile);
                $compressed = gzencode($content, 9);
                file_put_contents($backupFile . '.gz', $compressed);
                unlink($backupFile);
            }
        }
    }
    
    /**
     * 清理User-Agent字符串
     */
    private function sanitizeUserAgent($userAgent) {
        // 移除可能的恶意字符
        $userAgent = str_replace(["\n", "\r", "\t", "|"], " ", $userAgent);
        // 限制长度
        return substr(trim($userAgent), 0, 500);
    }
    
    /**
     * 清理Referer字符串
     */
    private function sanitizeReferer($referer) {
        if (empty($referer)) {
            return '-';
        }
        
        // 移除可能的恶意字符
        $referer = str_replace(["\n", "\r", "\t", "|"], " ", $referer);
        // 限制长度
        return substr(trim($referer), 0, 200);
    }
    
    /**
     * 读取日志文件
     */
    public function readLogs($limit = 100, $offset = 0) {
        $logFile = $this->config->getLogPath();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $lines = array_reverse($lines); // 最新的在前面
        
        return array_slice($lines, $offset, $limit);
    }
    
    /**
     * 搜索日志
     */
    public function searchLogs($keyword, $limit = 100) {
        $logFile = $this->config->getLogPath();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $results = [];
        
        foreach (array_reverse($lines) as $line) {
            if (stripos($line, $keyword) !== false) {
                $results[] = $line;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 解析日志行
     */
    public function parseLogLine($line) {
        $parts = explode(' | ', $line, 5);
        
        if (count($parts) >= 4) {
            return [
                'timestamp' => $parts[0] ?? '',
                'ip' => $parts[1] ?? '',
                'user_agent' => $parts[2] ?? '',
                'action' => $parts[3] ?? '',
                'referer' => $parts[4] ?? '-'
            ];
        }
        
        return null;
    }
    
    /**
     * 获取日志统计信息
     */
    public function getLogStats($days = 7) {
        $logFile = $this->config->getLogPath();
        
        if (!file_exists($logFile)) {
            return [
                'total_requests' => 0,
                'bot_requests' => 0,
                'real_requests' => 0,
                'unique_ips' => 0,
                'top_ips' => [],
                'top_user_agents' => []
            ];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $cutoffDate = date('Y-m-d', strtotime("-$days days"));
        
        $stats = [
            'total_requests' => 0,
            'bot_requests' => 0,
            'real_requests' => 0,
            'unique_ips' => [],
            'ip_counts' => [],
            'ua_counts' => []
        ];
        
        foreach ($lines as $line) {
            $parsed = $this->parseLogLine($line);
            if (!$parsed || $parsed['timestamp'] < $cutoffDate) {
                continue;
            }
            
            $stats['total_requests']++;
            
            if (strpos($parsed['action'], '机器人') !== false || strpos($parsed['action'], '假页面') !== false) {
                $stats['bot_requests']++;
            } else {
                $stats['real_requests']++;
            }
            
            // 统计IP
            $ip = $parsed['ip'];
            $stats['unique_ips'][$ip] = true;
            $stats['ip_counts'][$ip] = ($stats['ip_counts'][$ip] ?? 0) + 1;
            
            // 统计User-Agent
            $ua = substr($parsed['user_agent'], 0, 50); // 截取前50个字符
            $stats['ua_counts'][$ua] = ($stats['ua_counts'][$ua] ?? 0) + 1;
        }
        
        // 排序并获取Top 10
        arsort($stats['ip_counts']);
        arsort($stats['ua_counts']);
        
        return [
            'total_requests' => $stats['total_requests'],
            'bot_requests' => $stats['bot_requests'],
            'real_requests' => $stats['real_requests'],
            'unique_ips' => count($stats['unique_ips']),
            'top_ips' => array_slice($stats['ip_counts'], 0, 10, true),
            'top_user_agents' => array_slice($stats['ua_counts'], 0, 10, true)
        ];
    }
    
    /**
     * 清理旧日志
     */
    public function cleanOldLogs($days = 30) {
        $logsDir = $this->config->getDataPath('logs/');
        $cutoffTime = time() - ($days * 24 * 3600);
        $cleaned = 0;
        
        if (is_dir($logsDir)) {
            $files = glob($logsDir . '*.log*');
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
}
?>
