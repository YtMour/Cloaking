<?php
namespace Cloak\Tools;

require_once dirname(__DIR__) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\BlacklistChecker;
use Cloak\Core\IPDetector;
use Cloak\Core\Logger;

/**
 * IP测试工具
 * 允许测试不同的IP是否会被黑名单拦截
 */
class IPTest {
    private $config;
    private $blacklistChecker;
    private $ipDetector;
    private $logger;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->blacklistChecker = new BlacklistChecker();
        $this->ipDetector = new IPDetector();
        $this->logger = new Logger();
    }
    
    /**
     * 测试IP是否被拦截
     */
    public function testIP($ip, $userAgent = null) {
        $userAgent = $userAgent ?: 'Mozilla/5.0 (Test Client)';
        
        $result = [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'is_valid_ip' => false,
            'is_blocked' => false,
            'block_reason' => '',
            'ip_match' => false,
            'ua_match' => false,
            'ip_type' => '',
            'is_private' => false,
            'is_cloud_provider' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 验证IP格式
        $result['is_valid_ip'] = filter_var($ip, FILTER_VALIDATE_IP) !== false;
        
        if (!$result['is_valid_ip']) {
            $result['block_reason'] = '无效的IP地址格式';
            return $result;
        }
        
        // 判断IP类型
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $result['ip_type'] = 'IPv4';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $result['ip_type'] = 'IPv6';
        }
        
        // 检查是否为内网IP
        $result['is_private'] = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
        
        // 检查是否为云服务提供商IP
        $result['is_cloud_provider'] = $this->ipDetector->isCloudProvider($ip);
        
        // 检查IP黑名单
        $result['ip_match'] = $this->blacklistChecker->checkIP($ip);
        
        // 检查UA黑名单
        $result['ua_match'] = $this->blacklistChecker->checkUA($userAgent);
        
        // 综合判断
        $result['is_blocked'] = $result['ip_match'] || $result['ua_match'];
        
        if ($result['is_blocked']) {
            $reasons = [];
            if ($result['ip_match']) {
                $reasons[] = 'IP地址在黑名单中';
            }
            if ($result['ua_match']) {
                $reasons[] = 'User-Agent在黑名单中';
            }
            $result['block_reason'] = implode(', ', $reasons);
        } else {
            $result['block_reason'] = '未被拦截';
        }
        
        // 记录测试日志
        $this->logger->logInfo("IP测试", [
            'test_ip' => $ip,
            'user_agent' => substr($userAgent, 0, 100),
            'result' => $result['is_blocked'] ? 'blocked' : 'passed',
            'reason' => $result['block_reason']
        ]);
        
        return $result;
    }
    
    /**
     * 批量测试IP
     */
    public function batchTestIPs($ips, $userAgent = null) {
        $results = [];
        
        foreach ($ips as $ip) {
            $results[] = $this->testIP(trim($ip), $userAgent);
        }
        
        return $results;
    }
    
    /**
     * 获取黑名单中的IP列表
     */
    public function getBlacklistIPs() {
        $ips = [];
        $debug = [];
        
        $filename = $this->config->getIPBlacklistPath();
        
        if (!file_exists($filename)) {
            $debug[] = "文件 $filename 不存在";
            return ['ips' => $ips, 'debug' => $debug];
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $debug[] = "读取到 " . count($lines) . " 行";
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (filter_var($line, FILTER_VALIDATE_IP)) {
                $ips[] = $line;
                $debug[] = "第" . ($lineNum + 1) . "行: 有效IP: $line";
            } else {
                $debug[] = "第" . ($lineNum + 1) . "行: 无效IP: $line";
            }
        }
        
        $ips = array_unique($ips);
        $debug[] = "去重后共 " . count($ips) . " 个唯一IP";
        
        return ['ips' => $ips, 'debug' => $debug];
    }
    
    /**
     * 获取常见的测试IP
     */
    public function getCommonTestIPs() {
        return [
            'public_dns' => [
                'Google DNS 1' => '8.8.8.8',
                'Google DNS 2' => '8.8.4.4',
                'Cloudflare DNS 1' => '1.1.1.1',
                'Cloudflare DNS 2' => '1.0.0.1',
                'Quad9 DNS' => '9.9.9.9',
                'OpenDNS 1' => '208.67.222.222',
                'OpenDNS 2' => '208.67.220.220'
            ],
            'cloud_providers' => [
                'AWS US-East' => '3.80.0.1',
                'AWS US-West' => '13.57.0.1',
                'Google Cloud' => '35.184.0.1',
                'Azure East US' => '20.42.0.1',
                'Alibaba Cloud' => '47.88.0.1',
                'Tencent Cloud' => '49.51.0.1'
            ],
            'bot_ips' => [
                'Googlebot' => '66.249.66.1',
                'Bingbot' => '40.77.167.1',
                'Facebook' => '173.252.127.1',
                'Twitter' => '199.16.156.1',
                'LinkedIn' => '108.174.10.1'
            ],
            'private_ranges' => [
                'Local 192.168' => '192.168.1.1',
                'Local 10.x' => '10.0.0.1',
                'Local 172.16' => '172.16.0.1',
                'Localhost' => '127.0.0.1'
            ]
        ];
    }
    
    /**
     * 检查IP地理位置信息
     */
    public function getIPInfo($ip) {
        $info = [
            'ip' => $ip,
            'is_valid' => false,
            'type' => '',
            'is_private' => false,
            'is_cloud' => false,
            'location' => null
        ];
        
        // 验证IP
        $info['is_valid'] = filter_var($ip, FILTER_VALIDATE_IP) !== false;
        
        if (!$info['is_valid']) {
            return $info;
        }
        
        // IP类型
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $info['type'] = 'IPv4';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $info['type'] = 'IPv6';
        }
        
        // 是否为内网IP
        $info['is_private'] = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
        
        // 是否为云服务商IP
        $info['is_cloud'] = $this->ipDetector->isCloudProvider($ip);
        
        // 获取地理位置信息（可扩展）
        $info['location'] = $this->ipDetector->getIPLocation($ip);
        
        return $info;
    }
    
    /**
     * 生成IP范围
     */
    public function generateIPRange($startIP, $endIP, $maxCount = 100) {
        $ips = [];
        
        if (!filter_var($startIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || 
            !filter_var($endIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ips;
        }
        
        $start = ip2long($startIP);
        $end = ip2long($endIP);
        
        if ($start === false || $end === false || $start > $end) {
            return $ips;
        }
        
        $count = min($end - $start + 1, $maxCount);
        $step = max(1, floor(($end - $start) / $count));
        
        for ($i = 0; $i < $count; $i++) {
            $currentIP = long2ip($start + ($i * $step));
            if ($currentIP !== false) {
                $ips[] = $currentIP;
            }
        }
        
        return $ips;
    }
    
    /**
     * 模拟访问测试
     */
    public function simulateVisit($ip, $userAgent = null, $referer = null) {
        $userAgent = $userAgent ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $referer = $referer ?: '-';
        
        // 执行黑名单检查
        $isBot = $this->blacklistChecker->isBot($userAgent, $ip);
        
        $result = [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'is_blocked' => $isBot,
            'action' => $isBot ? '显示假页面 (机器人检测)' : '正常跳转',
            'landing_url' => $isBot ? null : $this->config->getLandingURL(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 记录到日志（模拟真实访问）
        $this->logger->log($ip, $userAgent, $result['action'], $referer);
        
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
            'invalid_ips' => 0,
            'private_ips' => 0,
            'cloud_ips' => 0,
            'ipv4_count' => 0,
            'ipv6_count' => 0,
            'block_rate' => 0
        ];
        
        foreach ($testResults as $result) {
            if (!$result['is_valid_ip']) {
                $report['invalid_ips']++;
                continue;
            }
            
            if ($result['ip_type'] === 'IPv4') {
                $report['ipv4_count']++;
            } elseif ($result['ip_type'] === 'IPv6') {
                $report['ipv6_count']++;
            }
            
            if (isset($result['is_private']) && $result['is_private']) {
                $report['private_ips']++;
            }
            
            if (isset($result['is_cloud_provider']) && $result['is_cloud_provider']) {
                $report['cloud_ips']++;
            }
            
            if ($result['is_blocked']) {
                $report['blocked_count']++;
            } else {
                $report['passed_count']++;
            }
        }
        
        $validTests = $report['total_tests'] - $report['invalid_ips'];
        if ($validTests > 0) {
            $report['block_rate'] = round(($report['blocked_count'] / $validTests) * 100, 2);
        }
        
        return $report;
    }
    
    /**
     * 导出测试结果
     */
    public function exportTestResults($testResults, $format = 'json') {
        $timestamp = date('Y-m-d_H-i-s');
        
        if ($format === 'json') {
            $filename = "ip_test_results_{$timestamp}.json";
            $content = json_encode($testResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif ($format === 'csv') {
            $filename = "ip_test_results_{$timestamp}.csv";
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
        $csv = "时间,IP地址,IP类型,是否有效,是否拦截,拦截原因,是否内网,是否云服务商\n";
        
        foreach ($testResults as $result) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,\"%s\",%s,%s\n",
                $result['timestamp'],
                $result['ip'],
                $result['ip_type'],
                $result['is_valid_ip'] ? '是' : '否',
                $result['is_blocked'] ? '是' : '否',
                str_replace('"', '""', $result['block_reason']),
                isset($result['is_private']) && $result['is_private'] ? '是' : '否',
                isset($result['is_cloud_provider']) && $result['is_cloud_provider'] ? '是' : '否'
            );
        }
        
        return $csv;
    }
}
?>
