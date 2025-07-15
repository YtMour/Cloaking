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
        if (!file_exists($this->config['log_file'])) {
            return [
                'logs' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => 0
            ];
        }

        $lines = file($this->config['log_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
                if (strpos($log['action'], '假页面') === false && 
                    strpos($log['action'], 'BLOCKED') === false &&
                    strpos($log['action'], '机器人检测') === false &&
                    strpos($log['action'], '测试模式') === false) {
                    return false;
                }
            } elseif ($filters['action'] === 'passed') {
                if (strpos($log['action'], '正常跳转') === false) {
                    return false;
                }
            }
        }
        
        // 时间过滤
        if (!empty($filters['date'])) {
            if (strpos($log['time'], $filters['date']) !== 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 获取日志统计信息
     */
    public function getLogStats() {
        if (!file_exists($this->config['log_file'])) {
            return [
                'total' => 0,
                'blocked' => 0,
                'redirected' => 0,
                'today' => 0,
                'unique_ips' => 0
            ];
        }
        
        $lines = file($this->config['log_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines);
        $blocked = 0;
        $redirected = 0;
        $today = 0;
        $ips = [];
        $today_date = date('Y-m-d');
        
        foreach ($lines as $line) {
            $log = $this->parseLogLine($line);
            if (!$log) continue;
            
            // 统计IP
            $ips[$log['ip']] = true;

            // 统计今日访问
            if (strpos($log['time'], $today_date) === 0) {
                $today++;
            }

            // 统计动作
            if (strpos($log['action'], '假页面') !== false || 
                strpos($log['action'], 'BLOCKED') !== false ||
                strpos($log['action'], '机器人检测') !== false ||
                strpos($log['action'], '测试模式') !== false) {
                $blocked++;
            } else {
                $redirected++;
            }
        }
        
        return [
            'total' => $total,
            'blocked' => $blocked,
            'redirected' => $redirected,
            'today' => $today,
            'unique_ips' => count($ips)
        ];
    }
    
    /**
     * 清空日志文件
     */
    public function clearLog() {
        return file_put_contents($this->config['log_file'], '') !== false;
    }
    
    /**
     * 获取最近的访问者IP列表（用于快速拉黑）
     */
    public function getRecentIPs($limit = 20) {
        $data = $this->getLogData(1, $limit);
        $ips = [];
        
        foreach ($data['logs'] as $log) {
            if (!in_array($log['ip'], $ips)) {
                $ips[] = $log['ip'];
            }
        }
        
        return array_slice($ips, 0, $limit);
    }
    
    /**
     * 获取最近的User Agent列表（用于快速拉黑）
     */
    public function getRecentUAs($limit = 20) {
        $data = $this->getLogData(1, $limit);
        $uas = [];
        
        foreach ($data['logs'] as $log) {
            if (!in_array($log['ua'], $uas)) {
                $uas[] = $log['ua'];
            }
        }
        
        return array_slice($uas, 0, $limit);
    }
    
    /**
     * 导出日志为CSV格式
     */
    public function exportToCSV($filters = []) {
        $data = $this->getLogData(1, 10000, $filters); // 导出最多10000条
        
        $csv = "时间,IP地址,User Agent,处理结果,访问来源\n";
        foreach ($data['logs'] as $log) {
            $csv .= sprintf('"%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $log['time']),
                str_replace('"', '""', $log['ip']),
                str_replace('"', '""', $log['ua']),
                str_replace('"', '""', $log['action']),
                str_replace('"', '""', $log['referer'])
            );
        }
        
        return $csv;
    }
}
