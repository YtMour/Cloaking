<?php
namespace Cloak\Monitor;

require_once dirname(__DIR__) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\Logger;
use Cloak\Core\BlacklistChecker;

/**
 * 日志分析器
 * 负责日志的读取、分析、过滤和统计
 */
class LogAnalyzer {
    private $config;
    private $logger;
    private $blacklistChecker;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->logger = new Logger();
        $this->blacklistChecker = new BlacklistChecker();
    }
    
    /**
     * 获取日志数据（分页）
     */
    public function getLogs($page = 1, $perPage = 50, $filters = []) {
        $logs = $this->readAndFilterLogs($filters);
        
        // 分页处理
        $totalLogs = count($logs);
        $totalPages = ceil($totalLogs / $perPage);
        $offset = ($page - 1) * $perPage;
        $pagedLogs = array_slice($logs, $offset, $perPage);
        
        // 为每条日志添加索引和黑名单状态
        foreach ($pagedLogs as $index => &$log) {
            $log['index'] = $offset + $index;
            $log['ip_in_blacklist'] = $this->blacklistChecker->checkIP($log['ip']);
            $log['ua_in_blacklist'] = $this->blacklistChecker->checkUA($log['ua']);
            $log['is_blocked'] = $this->isLogBlocked($log['action']);
        }
        
        return [
            'logs' => $pagedLogs,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_logs' => $totalLogs,
                'per_page' => $perPage,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => max(1, $page - 1),
                'next_page' => min($totalPages, $page + 1)
            ]
        ];
    }
    
    /**
     * 获取所有日志（用于导出）
     */
    public function getAllLogs($filters = []) {
        return $this->readAndFilterLogs($filters);
    }
    
    /**
     * 读取和过滤日志
     */
    private function readAndFilterLogs($filters = []) {
        $logFile = $this->config->getLogPath();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        
        foreach (array_reverse($lines) as $line) {
            $parsed = $this->parseLogLine($line);
            if ($parsed && $this->matchesFilters($parsed, $filters)) {
                $logs[] = $parsed;
            }
        }
        
        return $logs;
    }
    
    /**
     * 解析日志行
     */
    private function parseLogLine($line) {
        $parts = explode(' | ', $line, 5);
        
        if (count($parts) >= 4) {
            return [
                'time' => $parts[0] ?? '',
                'ip' => $parts[1] ?? '',
                'ua' => $parts[2] ?? '',
                'action' => $parts[3] ?? '',
                'referer' => $parts[4] ?? '-'
            ];
        }
        
        return null;
    }
    
    /**
     * 检查日志是否匹配过滤条件
     */
    private function matchesFilters($log, $filters) {
        // IP过滤
        if (!empty($filters['ip']) && stripos($log['ip'], $filters['ip']) === false) {
            return false;
        }
        
        // UA过滤
        if (!empty($filters['ua']) && stripos($log['ua'], $filters['ua']) === false) {
            return false;
        }
        
        // 动作过滤
        if (!empty($filters['action']) && stripos($log['action'], $filters['action']) === false) {
            return false;
        }
        
        // 来源过滤
        if (!empty($filters['referer']) && stripos($log['referer'], $filters['referer']) === false) {
            return false;
        }
        
        // 日期过滤
        if (!empty($filters['date_from'])) {
            $logDate = substr($log['time'], 0, 10); // YYYY-MM-DD
            if ($logDate < $filters['date_from']) {
                return false;
            }
        }
        
        if (!empty($filters['date_to'])) {
            $logDate = substr($log['time'], 0, 10); // YYYY-MM-DD
            if ($logDate > $filters['date_to']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 检查日志是否为被拦截
     */
    private function isLogBlocked($action) {
        $blockedKeywords = ['假页面', 'BLOCKED', '机器人检测', '测试模式'];
        
        foreach ($blockedKeywords as $keyword) {
            if (strpos($action, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 获取统计信息
     */
    public function getStats($days = 7) {
        $stats = $this->logger->getLogStats($days);
        
        // 添加额外的统计信息
        $stats['blacklist_stats'] = $this->blacklistChecker->getStats();
        $stats['system_info'] = $this->getSystemInfo();
        
        return $stats;
    }
    
    /**
     * 获取系统信息
     */
    private function getSystemInfo() {
        $logFile = $this->config->getLogPath();
        $uaFile = $this->config->getUABlacklistPath();
        $ipFile = $this->config->getIPBlacklistPath();
        
        return [
            'log_file_size' => file_exists($logFile) ? filesize($logFile) : 0,
            'ua_blacklist_size' => file_exists($uaFile) ? filesize($uaFile) : 0,
            'ip_blacklist_size' => file_exists($ipFile) ? filesize($ipFile) : 0,
            'log_file_lines' => file_exists($logFile) ? count(file($logFile)) : 0,
            'ua_blacklist_lines' => file_exists($uaFile) ? count(file($uaFile)) : 0,
            'ip_blacklist_lines' => file_exists($ipFile) ? count(file($ipFile)) : 0,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'disk_free_space' => disk_free_space('.'),
            'disk_total_space' => disk_total_space('.')
        ];
    }
    
    /**
     * 搜索日志
     */
    public function searchLogs($keyword, $limit = 100) {
        return $this->logger->searchLogs($keyword, $limit);
    }
    
    /**
     * 获取热门IP统计
     */
    public function getTopIPs($limit = 10, $days = 7) {
        $logs = $this->readAndFilterLogs([
            'date_from' => date('Y-m-d', strtotime("-$days days"))
        ]);
        
        $ipCounts = [];
        foreach ($logs as $log) {
            $ip = $log['ip'];
            $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
        }
        
        arsort($ipCounts);
        return array_slice($ipCounts, 0, $limit, true);
    }
    
    /**
     * 获取热门User-Agent统计
     */
    public function getTopUserAgents($limit = 10, $days = 7) {
        $logs = $this->readAndFilterLogs([
            'date_from' => date('Y-m-d', strtotime("-$days days"))
        ]);
        
        $uaCounts = [];
        foreach ($logs as $log) {
            $ua = substr($log['ua'], 0, 100); // 截取前100个字符
            $uaCounts[$ua] = ($uaCounts[$ua] ?? 0) + 1;
        }
        
        arsort($uaCounts);
        return array_slice($uaCounts, 0, $limit, true);
    }
    
    /**
     * 获取每日访问统计
     */
    public function getDailyStats($days = 30) {
        $logs = $this->readAndFilterLogs([
            'date_from' => date('Y-m-d', strtotime("-$days days"))
        ]);
        
        $dailyStats = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dailyStats[$date] = [
                'total' => 0,
                'blocked' => 0,
                'passed' => 0
            ];
        }
        
        foreach ($logs as $log) {
            $date = substr($log['time'], 0, 10);
            if (isset($dailyStats[$date])) {
                $dailyStats[$date]['total']++;
                if ($this->isLogBlocked($log['action'])) {
                    $dailyStats[$date]['blocked']++;
                } else {
                    $dailyStats[$date]['passed']++;
                }
            }
        }
        
        return $dailyStats;
    }
    
    /**
     * 获取实时统计（最近1小时）
     */
    public function getRealtimeStats() {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $logs = $this->readAndFilterLogs();
        
        $realtimeStats = [
            'total' => 0,
            'blocked' => 0,
            'passed' => 0,
            'unique_ips' => [],
            'recent_logs' => []
        ];
        
        foreach ($logs as $log) {
            if ($log['time'] >= $oneHourAgo) {
                $realtimeStats['total']++;
                $realtimeStats['unique_ips'][$log['ip']] = true;
                
                if ($this->isLogBlocked($log['action'])) {
                    $realtimeStats['blocked']++;
                } else {
                    $realtimeStats['passed']++;
                }
                
                if (count($realtimeStats['recent_logs']) < 10) {
                    $realtimeStats['recent_logs'][] = $log;
                }
            }
        }
        
        $realtimeStats['unique_ips'] = count($realtimeStats['unique_ips']);
        
        return $realtimeStats;
    }
    
    /**
     * 清理旧日志
     */
    public function cleanOldLogs($days = 30) {
        return $this->logger->cleanOldLogs($days);
    }
    
    /**
     * 获取日志文件信息
     */
    public function getLogFileInfo() {
        $logFile = $this->config->getLogPath();
        
        if (!file_exists($logFile)) {
            return [
                'exists' => false,
                'size' => 0,
                'lines' => 0,
                'last_modified' => null
            ];
        }
        
        return [
            'exists' => true,
            'size' => filesize($logFile),
            'lines' => count(file($logFile)),
            'last_modified' => filemtime($logFile),
            'readable' => is_readable($logFile),
            'writable' => is_writable($logFile)
        ];
    }
}
?>
