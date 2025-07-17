<?php
namespace Cloak\Tools;

require_once dirname(__DIR__) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\BlacklistChecker;
use Cloak\Core\IPDetector;
use Cloak\Core\Logger;

/**
 * User-Agent 测试工具
 * 允许测试不同的UA是否会被黑名单拦截
 */
class UATest {
    private $config;
    private $blacklistChecker;
    private $logger;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->blacklistChecker = new BlacklistChecker();
        $this->logger = new Logger();
    }
    
    /**
     * 获取黑名单中的UA列表
     */
    public function getBlacklistUAs() {
        $uas = [];
        $debug = [];
        
        $filename = $this->config->getUABlacklistPath();
        
        if (!file_exists($filename)) {
            $debug[] = "文件 $filename 不存在";
            return ['uas' => $uas, 'debug' => $debug];
        }
        
        if (!is_readable($filename)) {
            $debug[] = "文件 $filename 不可读";
            return ['uas' => $uas, 'debug' => $debug];
        }
        
        $content = file_get_contents($filename);
        if ($content === false) {
            $debug[] = "无法读取文件内容";
            return ['uas' => $uas, 'debug' => $debug];
        }
        
        $lines = explode("\n", $content);
        $debug[] = "读取到 " . count($lines) . " 行";
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // 检查是否包含 [ip:xxx] 格式
            if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line, $matches)) {
                $uaPart = trim($matches[1]);
                if (!empty($uaPart)) {
                    $uas[] = $uaPart;
                    $debug[] = "第" . ($lineNum + 1) . "行: 提取混合格式 UA: " . substr($uaPart, 0, 50) . "...";
                }
            } else {
                // 纯 UA 行
                $uas[] = $line;
                $debug[] = "第" . ($lineNum + 1) . "行: 纯 UA: " . substr($line, 0, 50) . "...";
            }
        }
        
        $uas = array_unique($uas);
        $debug[] = "去重后共 " . count($uas) . " 个唯一 UA";
        
        return ['uas' => $uas, 'debug' => $debug];
    }
    
    /**
     * 测试UA是否被拦截
     */
    public function testUA($userAgent, $testIP = null) {
        $ipDetector = new IPDetector();
        $realIP = $testIP ?: $ipDetector->getRealIP();
        
        $result = [
            'user_agent' => $userAgent,
            'test_ip' => $realIP,
            'is_blocked' => false,
            'block_reason' => '',
            'ua_match' => false,
            'ip_match' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 检查UA
        $result['ua_match'] = $this->blacklistChecker->checkUA($userAgent);
        
        // 检查IP
        $result['ip_match'] = $this->blacklistChecker->checkIP($realIP);
        
        // 综合判断
        $result['is_blocked'] = $result['ua_match'] || $result['ip_match'];
        
        if ($result['is_blocked']) {
            $reasons = [];
            if ($result['ua_match']) {
                $reasons[] = 'User-Agent在黑名单中';
            }
            if ($result['ip_match']) {
                $reasons[] = 'IP地址在黑名单中';
            }
            $result['block_reason'] = implode(', ', $reasons);
        } else {
            $result['block_reason'] = '未被拦截';
        }
        
        // 记录测试日志
        $this->logger->logInfo("UA测试", [
            'user_agent' => substr($userAgent, 0, 100),
            'test_ip' => $realIP,
            'result' => $result['is_blocked'] ? 'blocked' : 'passed',
            'reason' => $result['block_reason']
        ]);
        
        return $result;
    }
    
    /**
     * 批量测试UA
     */
    public function batchTestUAs($userAgents, $testIP = null) {
        $results = [];
        
        foreach ($userAgents as $ua) {
            $results[] = $this->testUA($ua, $testIP);
        }
        
        return $results;
    }
    
    /**
     * 获取常见的测试UA
     */
    public function getCommonTestUAs() {
        return [
            'browsers' => [
                'Chrome Windows' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Firefox Windows' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
                'Safari macOS' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
                'Edge Windows' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'Chrome Android' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'Safari iPhone' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1'
            ],
            'bots' => [
                'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
                'Facebookbot' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
                'Twitterbot' => 'Twitterbot/1.0',
                'LinkedInBot' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
                'WhatsApp' => 'WhatsApp/2.23.24.76 A',
                'Telegram' => 'TelegramBot (like TwitterBot)',
                'AhrefsBot' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'
            ],
            'tools' => [
                'curl' => 'curl/7.68.0',
                'wget' => 'Wget/1.20.3 (linux-gnu)',
                'Python requests' => 'python-requests/2.28.1',
                'Postman' => 'PostmanRuntime/7.29.2',
                'Apache HttpClient' => 'Apache-HttpClient/4.5.13 (Java/11.0.16)',
                'Go HTTP client' => 'Go-http-client/1.1'
            ]
        ];
    }
    
    /**
     * 模拟访问测试
     */
    public function simulateVisit($userAgent, $testIP = null, $referer = null) {
        $ipDetector = new IPDetector();
        $realIP = $testIP ?: $ipDetector->getRealIP();
        $referer = $referer ?: '-';
        
        // 执行黑名单检查
        $isBot = $this->blacklistChecker->isBot($userAgent, $realIP);
        
        $result = [
            'user_agent' => $userAgent,
            'ip' => $realIP,
            'referer' => $referer,
            'is_blocked' => $isBot,
            'action' => $isBot ? '显示假页面 (机器人检测)' : '正常跳转',
            'landing_url' => $isBot ? null : $this->config->getLandingURL(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 记录到日志（模拟真实访问）
        $this->logger->log($realIP, $userAgent, $result['action'], $referer);
        
        return $result;
    }
    
    /**
     * 生成测试报告
     */
    public function generateTestReport($testResults) {
        $report = [
            'total_tests' => count($testResults),
            'blocked_count' => 0,
            'passed_count' => 0,
            'ua_blocks' => 0,
            'ip_blocks' => 0,
            'both_blocks' => 0,
            'block_rate' => 0,
            'test_summary' => []
        ];
        
        foreach ($testResults as $result) {
            if ($result['is_blocked']) {
                $report['blocked_count']++;
                
                if (isset($result['ua_match']) && isset($result['ip_match'])) {
                    if ($result['ua_match'] && $result['ip_match']) {
                        $report['both_blocks']++;
                    } elseif ($result['ua_match']) {
                        $report['ua_blocks']++;
                    } elseif ($result['ip_match']) {
                        $report['ip_blocks']++;
                    }
                }
            } else {
                $report['passed_count']++;
            }
        }
        
        if ($report['total_tests'] > 0) {
            $report['block_rate'] = round(($report['blocked_count'] / $report['total_tests']) * 100, 2);
        }
        
        $report['test_summary'] = [
            'effectiveness' => $report['block_rate'] > 80 ? 'high' : ($report['block_rate'] > 50 ? 'medium' : 'low'),
            'recommendations' => $this->generateRecommendations($report)
        ];
        
        return $report;
    }
    
    /**
     * 生成测试建议
     */
    private function generateRecommendations($report) {
        $recommendations = [];
        
        if ($report['block_rate'] < 50) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => '拦截率较低，建议检查黑名单配置'
            ];
        }
        
        if ($report['passed_count'] > 0 && $report['blocked_count'] === 0) {
            $recommendations[] = [
                'type' => 'info',
                'message' => '所有测试都通过了，黑名单可能需要更新'
            ];
        }
        
        if ($report['ua_blocks'] > $report['ip_blocks']) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'UA黑名单比IP黑名单更有效'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * 导出测试结果
     */
    public function exportTestResults($testResults, $format = 'json') {
        $timestamp = date('Y-m-d_H-i-s');
        
        if ($format === 'json') {
            $filename = "ua_test_results_{$timestamp}.json";
            $content = json_encode($testResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif ($format === 'csv') {
            $filename = "ua_test_results_{$timestamp}.csv";
            $content = $this->convertToCSV($testResults);
        } else {
            throw new \InvalidArgumentException('Unsupported format');
        }
        
        return [
            'filename' => $filename,
            'content' => $content,
            'size' => strlen($content)
        ];
    }
    
    /**
     * 转换为CSV格式
     */
    private function convertToCSV($testResults) {
        $csv = "时间,User-Agent,测试IP,是否拦截,拦截原因\n";
        
        foreach ($testResults as $result) {
            $csv .= sprintf(
                "%s,\"%s\",%s,%s,\"%s\"\n",
                $result['timestamp'],
                str_replace('"', '""', $result['user_agent']),
                $result['test_ip'],
                $result['is_blocked'] ? '是' : '否',
                str_replace('"', '""', $result['block_reason'])
            );
        }
        
        return $csv;
    }
}
?>
