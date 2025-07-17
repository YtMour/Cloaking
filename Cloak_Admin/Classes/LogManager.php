<?php
/**
 * 日志管理模块
 * 处理访问日志的读取、统计、过滤、分页等功能
 */

class LogManager {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * 读取日志数据，支持分页和过滤
     * @param int $page 页码（从1开始）
     * @param int $per_page 每页显示数量
     * @param array $filters 过滤条件
     * @return array 包含日志数据和分页信息
     */
    public function getLogData($page = 1, $per_page = 50, $filters = []) {
        $log_file = $this->config['log_file'] ?? '../access.log';
        
        if (!file_exists($log_file)) {
            return [
                'logs' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => 0
            ];
        }

        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];

        // 解析日志行
        foreach (array_reverse($lines) as $line) {
            $log = $this->parseLogLine($line);
            if ($log && $this->matchesFilters($log, $filters)) {
                $logs[] = $log;
            }
        }

        // 分页处理
        $total = count($logs);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $logs = array_slice($logs, $offset, $per_page);

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages
        ];
    }
    
    /**
     * 解析单行日志
     */
    private function parseLogLine($line) {
        // 处理新格式：时间 | IP | UA | 动作 | 来源
        $parts = explode(' | ', $line);
        if (count($parts) >= 4) {
            return [
                'time' => $parts[0] ?? '',
                'ip' => $parts[1] ?? '',
                'ua' => $parts[2] ?? '',
                'action' => $parts[3] ?? '',
                'referer' => $parts[4] ?? '-'
            ];
        }
        
        // 处理旧格式：时间 | IP: xxx | UA: xxx | Referer: xxx
        if (preg_match('/^(.+?) \| IP: (.+?) \| UA: (.+?) \| Referer: (.+)$/', $line, $matches)) {
            return [
                'time' => $matches[1],
                'ip' => $matches[2],
                'ua' => $matches[3],
                'action' => '未知动作 (旧格式)',
                'referer' => $matches[4]
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
        if (!empty($filters['action'])) {
            if ($filters['action'] === 'blocked') {
                if (stripos($log['action'], '假页面') === false && 
                    stripos($log['action'], 'BLOCKED') === false &&
                    stripos($log['action'], '机器人检测') === false &&
                    stripos($log['action'], '测试模式') === false) {
                    return false;
                }
            } elseif ($filters['action'] === 'passed') {
                if (stripos($log['action'], '假页面') !== false || 
                    stripos($log['action'], 'BLOCKED') !== false ||
                    stripos($log['action'], '机器人检测') !== false ||
                    stripos($log['action'], '测试模式') !== false) {
                    return false;
                }
            }
        }
        
        // 日期过滤
        if (!empty($filters['date'])) {
            if (stripos($log['time'], $filters['date']) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 获取日志统计信息
     */
    public function getLogStats() {
        $log_file = $this->config['log_file'] ?? '../access.log';

        if (!file_exists($log_file)) {
            return [
                'total' => 0,
                'blocked' => 0,
                'redirected' => 0,
                'today' => 0,
                'unique_ips' => 0
            ];
        }

        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines);
        $blocked = 0;
        $redirected = 0;
        $today = 0;
        $today_date = date('Y-m-d');
        $unique_ips = [];

        foreach ($lines as $line) {
            $log = $this->parseLogLine($line);
            if ($log) {
                // 统计拦截和通过
                if (stripos($log['action'], '假页面') !== false ||
                    stripos($log['action'], 'BLOCKED') !== false ||
                    stripos($log['action'], '机器人检测') !== false ||
                    stripos($log['action'], '测试模式') !== false) {
                    $blocked++;
                } else {
                    $redirected++;
                }

                // 统计今日访问
                if (stripos($log['time'], $today_date) !== false) {
                    $today++;
                }

                // 统计独立IP
                if (!empty($log['ip']) && !in_array($log['ip'], $unique_ips)) {
                    $unique_ips[] = $log['ip'];
                }
            }
        }

        return [
            'total' => $total,
            'blocked' => $blocked,
            'redirected' => $redirected,
            'today' => $today,
            'unique_ips' => count($unique_ips)
        ];
    }
    
    /**
     * 清空日志文件
     */
    public function clearLog() {
        $log_file = $this->config['log_file'] ?? '../access.log';

        if (file_put_contents($log_file, '') !== false) {
            return ['success' => true, 'message' => '✅ 访问日志已清空'];
        } else {
            return ['success' => false, 'message' => '❌ 清空日志失败'];
        }
    }

    /**
     * 获取系统信息
     */
    public function getSystemInfo() {
        $info = [];

        // 文件状态
        $files = [
            'UA 黑名单' => $this->config['ua_file'] ?? 'Cloak_Data/ua_blacklist.txt',
            'IP 黑名单' => $this->config['ip_file'] ?? 'Cloak_Data/ip_blacklist.txt',
            '跳转地址' => $this->config['landing_file'] ?? 'Cloak_Data/real_landing_url.txt',
            '访问日志' => $this->config['log_file'] ?? 'Cloak_Data/log.txt',
            'API 配置' => $this->config['api_config_file'] ?? 'Cloak_Data/api_config.json'
        ];

        foreach ($files as $name => $file) {
            $info['files'][$name] = [
                'exists' => file_exists($file),
                'size' => file_exists($file) ? filesize($file) : 0,
                'modified' => file_exists($file) ? date('Y-m-d H:i:s', filemtime($file)) : '-'
            ];
        }

        return $info;
    }
}
