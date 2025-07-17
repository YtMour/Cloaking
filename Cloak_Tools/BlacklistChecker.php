<?php
namespace Cloak\Tools;

require_once dirname(__DIR__) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\Utils;

/**
 * 黑名单检查工具
 * 检查黑名单文件的完整性和格式
 */
class BlacklistChecker {
    private $config;
    
    public function __construct() {
        $this->config = Config::getInstance();
    }
    
    /**
     * 分析UA黑名单文件
     */
    public function analyzeUABlacklist() {
        $filename = $this->config->getUABlacklistPath();
        
        if (!file_exists($filename)) {
            return [
                'error' => "文件 $filename 不存在",
                'stats' => [],
                'entries' => []
            ];
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $totalLines = count($lines);
        
        $stats = [
            'total_lines' => $totalLines,
            'mixed_format' => 0,  // UA + IP 格式
            'pure_ua' => 0,       // 纯 UA 格式
            'empty_lines' => 0,   // 空行
            'invalid_lines' => 0, // 无效行
            'duplicate_ua' => 0,  // 重复的 UA
            'duplicate_ip' => 0,  // 重复的 IP
            'extracted_ua' => 0,  // 提取的 UA 数量
            'extracted_ip' => 0,  // 提取的 IP 数量
            'ua_with_multiple_ips' => 0  // 同一UA对应多个IP
        ];
        
        $entries = [];
        $uaSeen = [];
        $ipSeen = [];
        $lineNumber = 0;
        
        foreach ($lines as $line) {
            $lineNumber++;
            $originalLine = $line;
            $line = trim($line);
            
            $entry = [
                'line_number' => $lineNumber,
                'original' => $originalLine,
                'type' => '',
                'ua_part' => '',
                'ip_part' => '',
                'status' => 'valid',
                'issues' => []
            ];
            
            // 检查空行
            if (empty($line)) {
                $stats['empty_lines']++;
                $entry['type'] = 'empty';
                $entry['status'] = 'warning';
                $entry['issues'][] = '空行';
                $entries[] = $entry;
                continue;
            }
            
            // 检查是否为混合格式 (UA [ip:xxx.xxx.xxx.xxx])
            if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line, $matches)) {
                $entry['type'] = 'mixed';
                $entry['ua_part'] = trim($matches[1]);
                $entry['ip_part'] = trim($matches[2]);
                $stats['mixed_format']++;
                
                // 验证IP格式
                if (!filter_var($entry['ip_part'], FILTER_VALIDATE_IP)) {
                    $entry['status'] = 'invalid';
                    $entry['issues'][] = '无效的IP地址格式';
                    $stats['invalid_lines']++;
                }
                
                // 检查UA重复
                $uaLower = strtolower($entry['ua_part']);
                if (isset($uaSeen[$uaLower])) {
                    $entry['issues'][] = "UA重复 (首次出现在第{$uaSeen[$uaLower]}行)";
                    $stats['duplicate_ua']++;
                    if ($entry['status'] === 'valid') {
                        $entry['status'] = 'warning';
                    }
                } else {
                    $uaSeen[$uaLower] = $lineNumber;
                    $stats['extracted_ua']++;
                }
                
                // 检查IP重复
                if (isset($ipSeen[$entry['ip_part']])) {
                    $entry['issues'][] = "IP重复 (首次出现在第{$ipSeen[$entry['ip_part']]}行)";
                    $stats['duplicate_ip']++;
                    if ($entry['status'] === 'valid') {
                        $entry['status'] = 'warning';
                    }
                } else {
                    $ipSeen[$entry['ip_part']] = $lineNumber;
                    $stats['extracted_ip']++;
                }
                
            } else {
                // 纯UA格式
                $entry['type'] = 'pure_ua';
                $entry['ua_part'] = $line;
                $stats['pure_ua']++;
                
                // 检查UA重复
                $uaLower = strtolower($entry['ua_part']);
                if (isset($uaSeen[$uaLower])) {
                    $entry['issues'][] = "UA重复 (首次出现在第{$uaSeen[$uaLower]}行)";
                    $stats['duplicate_ua']++;
                    if ($entry['status'] === 'valid') {
                        $entry['status'] = 'warning';
                    }
                } else {
                    $uaSeen[$uaLower] = $lineNumber;
                    $stats['extracted_ua']++;
                }
            }
            
            // 检查UA长度
            if (strlen($entry['ua_part']) > 500) {
                $entry['issues'][] = 'UA过长 (>500字符)';
                if ($entry['status'] === 'valid') {
                    $entry['status'] = 'warning';
                }
            }
            
            // 检查UA是否为空
            if (empty($entry['ua_part'])) {
                $entry['issues'][] = 'UA为空';
                $entry['status'] = 'invalid';
                $stats['invalid_lines']++;
            }
            
            $entries[] = $entry;
        }
        
        return [
            'error' => null,
            'stats' => $stats,
            'entries' => $entries
        ];
    }
    
    /**
     * 分析IP黑名单文件
     */
    public function analyzeIPBlacklist() {
        $filename = $this->config->getIPBlacklistPath();
        
        if (!file_exists($filename)) {
            return [
                'error' => "文件 $filename 不存在",
                'stats' => [],
                'entries' => []
            ];
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $totalLines = count($lines);
        
        $stats = [
            'total_lines' => $totalLines,
            'valid_ips' => 0,
            'invalid_ips' => 0,
            'duplicate_ips' => 0,
            'empty_lines' => 0,
            'ipv4_count' => 0,
            'ipv6_count' => 0
        ];
        
        $entries = [];
        $ipSeen = [];
        $lineNumber = 0;
        
        foreach ($lines as $line) {
            $lineNumber++;
            $originalLine = $line;
            $line = trim($line);
            
            $entry = [
                'line_number' => $lineNumber,
                'original' => $originalLine,
                'ip' => $line,
                'type' => '',
                'status' => 'valid',
                'issues' => []
            ];
            
            // 检查空行
            if (empty($line)) {
                $stats['empty_lines']++;
                $entry['type'] = 'empty';
                $entry['status'] = 'warning';
                $entry['issues'][] = '空行';
                $entries[] = $entry;
                continue;
            }
            
            // 验证IP格式
            if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $entry['type'] = 'ipv4';
                $stats['ipv4_count']++;
                $stats['valid_ips']++;
            } elseif (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $entry['type'] = 'ipv6';
                $stats['ipv6_count']++;
                $stats['valid_ips']++;
            } else {
                $entry['type'] = 'invalid';
                $entry['status'] = 'invalid';
                $entry['issues'][] = '无效的IP地址格式';
                $stats['invalid_ips']++;
            }
            
            // 检查重复
            if (isset($ipSeen[$line])) {
                $entry['issues'][] = "IP重复 (首次出现在第{$ipSeen[$line]}行)";
                $stats['duplicate_ips']++;
                if ($entry['status'] === 'valid') {
                    $entry['status'] = 'warning';
                }
            } else {
                $ipSeen[$line] = $lineNumber;
            }
            
            $entries[] = $entry;
        }
        
        return [
            'error' => null,
            'stats' => $stats,
            'entries' => $entries
        ];
    }
    
    /**
     * 生成检查报告
     */
    public function generateReport() {
        $uaAnalysis = $this->analyzeUABlacklist();
        $ipAnalysis = $this->analyzeIPBlacklist();
        
        return [
            'ua_blacklist' => $uaAnalysis,
            'ip_blacklist' => $ipAnalysis,
            'summary' => $this->generateSummary($uaAnalysis, $ipAnalysis),
            'recommendations' => $this->generateRecommendations($uaAnalysis, $ipAnalysis)
        ];
    }
    
    /**
     * 生成摘要
     */
    private function generateSummary($uaAnalysis, $ipAnalysis) {
        $summary = [
            'total_issues' => 0,
            'critical_issues' => 0,
            'warnings' => 0,
            'health_score' => 100
        ];
        
        // 统计UA黑名单问题
        if (!$uaAnalysis['error']) {
            foreach ($uaAnalysis['entries'] as $entry) {
                if ($entry['status'] === 'invalid') {
                    $summary['critical_issues']++;
                } elseif ($entry['status'] === 'warning') {
                    $summary['warnings']++;
                }
            }
        }
        
        // 统计IP黑名单问题
        if (!$ipAnalysis['error']) {
            foreach ($ipAnalysis['entries'] as $entry) {
                if ($entry['status'] === 'invalid') {
                    $summary['critical_issues']++;
                } elseif ($entry['status'] === 'warning') {
                    $summary['warnings']++;
                }
            }
        }
        
        $summary['total_issues'] = $summary['critical_issues'] + $summary['warnings'];
        
        // 计算健康分数
        if ($summary['total_issues'] > 0) {
            $totalEntries = count($uaAnalysis['entries'] ?? []) + count($ipAnalysis['entries'] ?? []);
            if ($totalEntries > 0) {
                $errorRate = $summary['total_issues'] / $totalEntries;
                $summary['health_score'] = max(0, 100 - ($errorRate * 100));
            }
        }
        
        return $summary;
    }
    
    /**
     * 生成建议
     */
    private function generateRecommendations($uaAnalysis, $ipAnalysis) {
        $recommendations = [];
        
        // UA黑名单建议
        if (!$uaAnalysis['error'] && $uaAnalysis['stats']) {
            if ($uaAnalysis['stats']['duplicate_ua'] > 0) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'UA重复项',
                    'message' => "发现 {$uaAnalysis['stats']['duplicate_ua']} 个重复的User-Agent，建议清理以提高效率"
                ];
            }
            
            if ($uaAnalysis['stats']['invalid_lines'] > 0) {
                $recommendations[] = [
                    'type' => 'error',
                    'title' => '无效条目',
                    'message' => "发现 {$uaAnalysis['stats']['invalid_lines']} 个无效条目，需要修复或删除"
                ];
            }
            
            if ($uaAnalysis['stats']['empty_lines'] > 5) {
                $recommendations[] = [
                    'type' => 'info',
                    'title' => '空行清理',
                    'message' => "发现 {$uaAnalysis['stats']['empty_lines']} 个空行，建议清理以减少文件大小"
                ];
            }
        }
        
        // IP黑名单建议
        if (!$ipAnalysis['error'] && $ipAnalysis['stats']) {
            if ($ipAnalysis['stats']['duplicate_ips'] > 0) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'IP重复项',
                    'message' => "发现 {$ipAnalysis['stats']['duplicate_ips']} 个重复的IP地址，建议清理"
                ];
            }
            
            if ($ipAnalysis['stats']['invalid_ips'] > 0) {
                $recommendations[] = [
                    'type' => 'error',
                    'title' => '无效IP',
                    'message' => "发现 {$ipAnalysis['stats']['invalid_ips']} 个无效的IP地址，需要修复或删除"
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * 清理黑名单文件
     */
    public function cleanBlacklist($type = 'ua', $options = []) {
        $filename = $type === 'ua' ? $this->config->getUABlacklistPath() : $this->config->getIPBlacklistPath();
        
        if (!file_exists($filename)) {
            return ['success' => false, 'message' => '文件不存在'];
        }
        
        // 备份原文件
        $backupFile = $filename . '.backup.' . date('Y-m-d_H-i-s');
        if (!copy($filename, $backupFile)) {
            return ['success' => false, 'message' => '无法创建备份文件'];
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        $cleanedLines = [];
        $removedCount = 0;
        
        $seen = [];
        
        foreach ($lines as $line) {
            $originalLine = $line;
            $line = trim($line);
            
            // 移除空行
            if (empty($line) && isset($options['remove_empty']) && $options['remove_empty']) {
                $removedCount++;
                continue;
            }
            
            // 移除重复项
            if (isset($options['remove_duplicates']) && $options['remove_duplicates']) {
                $key = strtolower($line);
                if (isset($seen[$key])) {
                    $removedCount++;
                    continue;
                }
                $seen[$key] = true;
            }
            
            $cleanedLines[] = $originalLine;
        }
        
        // 写入清理后的内容
        if (file_put_contents($filename, implode("\n", $cleanedLines)) !== false) {
            return [
                'success' => true,
                'message' => "清理完成，移除了 $removedCount 个项目",
                'backup_file' => $backupFile,
                'removed_count' => $removedCount
            ];
        } else {
            return ['success' => false, 'message' => '写入文件失败'];
        }
    }
}
?>
